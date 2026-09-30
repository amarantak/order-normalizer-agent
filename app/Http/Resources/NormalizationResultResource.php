<?php

namespace App\Http\Resources;

use App\Application\Order\NormalizationResult;
use App\Infrastructure\Anthropic\NormalizedOrderMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Convierte un NormalizationResult en el JSON que recibe el front (capa de presentación HTTP).
//
// El pedido se serializa con NormalizedOrderMapper::toArray(), que es el schema de Claude.
// Acoplamiento aceptado a propósito (evita duplicar un array hoy idéntico): si el schema
// de Claude cambia, cambia también lo que ve el front. Documentado en el doc de arquitectura.
final class NormalizationResultResource extends JsonResource
{
    // Sin el envoltorio {"data": ...} que Laravel agrega por defecto: el front lee los campos directo.
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var NormalizationResult $result */
        $result = $this->resource;

        return [
            'status' => $result->status->value,             // 'approved' | 'needs_review' | 'failed'
            'correction_attempted' => $result->correctionAttempted,
            // new y no inyección: el mapper es código puro, sin dependencias ni estado.
            'order' => $result->order === null
                ? null
                : (new NormalizedOrderMapper())->toArray($result->order),
            'violations' => $result->violations,            // reglas sin resolver (en inglés)
            'error' => $result->error,                      // motivo si terminó por una excepción
        ];
    }
}
