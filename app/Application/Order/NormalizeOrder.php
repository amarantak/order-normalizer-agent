<?php

namespace App\Application\Order;

use App\Domain\Order\InvalidOrderException;
use App\Domain\Order\OrderConsistencyValidator;
use App\Domain\Order\OrderNormalizationFailedException;
use App\Domain\Order\OrderNormalizer;

// Caso de uso "normalizar un pedido" (application service): el loop agéntico completo.
//   1. Analizar:  el normalizador convierte el crudo al schema.
//   2. Validar:   reglas determinísticas en código propio.
//   3. Corregir:  si falla, UNA sola corrección con las reglas que no se cumplieron.
//   4. Resultado: Approved, NeedsReview (datos) o Failed (integración).
//
// No contiene reglas de negocio (están en el dominio) ni detalles técnicos (están en
// infraestructura): solo decide el orden de los pasos y qué hacer con cada resultado.
// Depende de la interfaz OrderNormalizer, no de Claude: en los tests se usa un fake.
final class NormalizeOrder
{
    public function __construct(
        private readonly OrderNormalizer $normalizer,
        private readonly OrderConsistencyValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $rawOrder JSON crudo del POS, ya decodificado
     * @param string               $source   POS de origen (ej. "pos1")
     *
     * LogicException no se captura a propósito: es un error de programación
     * y tiene que verse, no convertirse en un estado del resultado.
     */
    public function handle(array $rawOrder, string $source): NormalizationResult
    {
        // ---- Paso 1: analizar ----
        try {
            $order = $this->normalizer->normalize($rawOrder, $source);
        } catch (InvalidOrderException $e) {
            // Los datos no tienen forma de pedido: no hay Order, una persona tiene que mirarlo.
            return NormalizationResult::needsReview(null, [], false, $e->getMessage());
        } catch (OrderNormalizationFailedException $e) {
            // Falla de integración: reintentar más tarde, sin molestar a nadie.
            return NormalizationResult::failed($e->getMessage());
        }

        // ---- Paso 2: validar ----
        $violations = $this->validator->validate($order);
        if ($violations === []) {
            return NormalizationResult::approved($order, correctionAttempted: false);
        }

        // ---- Paso 3: corregir (una sola vez; si no alcanza, revisión humana en vez de loopear) ----
        try {
            $corrected = $this->normalizer->correct($rawOrder, $order, $violations, $source);
        } catch (InvalidOrderException $e) {
            // La corrección devolvió algo sin forma de pedido: queda el anterior, con sus violaciones.
            return NormalizationResult::needsReview($order, $violations, true, $e->getMessage());
        } catch (OrderNormalizationFailedException $e) {
            // La API falló durante la corrección: se conserva el anterior para reintentar después.
            return NormalizationResult::failed($e->getMessage(), $order, $violations, true);
        }

        // ---- Paso 4: resultado ----
        $remaining = $this->validator->validate($corrected);

        return $remaining === []
            ? NormalizationResult::approved($corrected, correctionAttempted: true)
            : NormalizationResult::needsReview($corrected, $remaining, true);
    }
}
