<?php

namespace App\Http\Controllers;

use App\Application\Order\NormalizationStatus;
use App\Application\Order\NormalizeOrder;
use App\Http\Requests\NormalizeOrderRequest;
use App\Http\Resources\NormalizationResultResource;
use Illuminate\Http\JsonResponse;

// Endpoint Ajax de la pantalla: POST /orders/normalize.
// Capa HTTP: traduce entre HTTP y el caso de uso. Sin lógica de negocio y sin conocer
// a Claude ni al validador; todo eso lo resuelve NormalizeOrder (separación de capas).
// Controller de una sola acción (__invoke): el endpoint hace una sola cosa.
final class NormalizeOrderController extends Controller
{
    public function __invoke(NormalizeOrderRequest $request, NormalizeOrder $normalizeOrder): JsonResponse
    {
        // Laravel inyecta el orquestador ya armado (binding de OrderNormalizer en AppServiceProvider).
        $result = $normalizeOrder->handle($request->rawOrder(), $request->source());

        // El código HTTP es una decisión de la capa HTTP, por eso vive acá y no en el resultado:
        // approved y needs_review son resultados válidos del proceso (200);
        // failed significa que un servicio del que dependemos falló (502 Bad Gateway).
        $status = $result->status === NormalizationStatus::Failed ? 502 : 200;

        return (new NormalizationResultResource($result))
            ->response()
            ->setStatusCode($status);
    }
}
