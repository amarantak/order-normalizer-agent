<?php

namespace App\Domain\Order;

use RuntimeException;

// El normalizador no pudo producir un pedido: el servicio no respondió, la respuesta
// vino cortada o no respetó el contrato. Es un problema de integración, no de los datos:
// reintentar más tarde probablemente lo resuelva.
//
// Vive en el dominio porque es parte del contrato de OrderNormalizer: quien lo usa
// (el orquestador) la captura sin saber qué implementación hay detrás (Claude, un fake...).
// Cada implementación la extiende con su propio detalle (ej. InvalidClaudeResponseException).
// No es final justamente para poder extenderla.
class OrderNormalizationFailedException extends RuntimeException
{
}
