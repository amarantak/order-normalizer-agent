<?php

namespace App\Application\Order;

use App\Domain\Order\Order;
use LogicException;

// Resultado del caso de uso "normalizar un pedido": el pedido (si lo hay) más los datos
// del PROCESO (estado, si se intentó una corrección, qué quedó sin resolver).
// Vive en la capa de aplicación, no en el dominio: describe cómo terminó el flujo,
// no un concepto del negocio. Por eso correctionAttempted no está en Order.
//
// El constructor es privado: se crea solo con approved(), needsReview() o failed(),
// y cada uno exige lo que tiene sentido para su estado. Así es imposible armar
// un resultado incoherente, como "aprobado sin pedido" o "aprobado con violaciones".
final readonly class NormalizationResult
{
    /**
     * @param list<string> $violations reglas de OrderConsistencyValidator que quedaron sin resolver
     */
    private function __construct(
        public NormalizationStatus $status,
        public ?Order $order,
        public bool $correctionAttempted,  // se hizo una llamada de corrección (haya funcionado o no)
        public array $violations,
        public ?string $error,             // motivo cuando el flujo terminó por una excepción
    ) {
        // Las violaciones son reglas que fallan SOBRE un pedido: sin pedido no tienen sentido.
        if ($violations !== [] && $order === null) {
            throw new LogicException('Violations need the order they refer to.');
        }
    }

    // Pasó la validación, a la primera o después de corregir.
    public static function approved(Order $order, bool $correctionAttempted): self
    {
        return new self(NormalizationStatus::Approved, $order, $correctionAttempted, [], null);
    }

    /**
     * Problema de datos: una persona tiene que revisarlo.
     * - Sigue fallando tras la corrección: $order = el corregido, $violations = las que quedan.
     * - Los datos no tienen forma de pedido (InvalidOrderException): sin $order, con $error.
     *
     * @param list<string> $violations
     */
    public static function needsReview(
        ?Order $order,
        array $violations,
        bool $correctionAttempted,
        ?string $error = null,
    ): self {
        // Mandar a revisión sin decir por qué no le sirve a quien revisa.
        if ($violations === [] && $error === null) {
            throw new LogicException('A result that needs review must say why (violations or error).');
        }

        return new self(NormalizationStatus::NeedsReview, $order, $correctionAttempted, $violations, $error);
    }

    /**
     * Problema de integración: el normalizador no pudo responder (OrderNormalizationFailedException).
     * Si falló durante la corrección, se conserva el pedido anterior y sus violaciones.
     *
     * @param list<string> $violations
     */
    public static function failed(
        string $error,
        ?Order $order = null,
        array $violations = [],
        bool $correctionAttempted = false,
    ): self {
        return new self(NormalizationStatus::Failed, $order, $correctionAttempted, $violations, $error);
    }
}
