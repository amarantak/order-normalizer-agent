<?php

namespace App\Infrastructure\Anthropic;

use App\Domain\Order\OrderNormalizationFailedException;

// La llamada a Claude no produjo una respuesta usable: error HTTP o de conexión
// después de los reintentos, respuesta cortada (max_tokens), o JSON que no respeta
// el contrato (falta un campo, tipo incorrecto, fecha que no es ISO 8601...).
// Es un problema de integración, no del negocio: por eso no reutiliza InvalidOrderException.
// Extiende la excepción del dominio para que el orquestador la capture sin conocer a Claude
// (inversión de dependencias); esta clase solo agrega que el origen fue la API de Anthropic.
final class InvalidClaudeResponseException extends OrderNormalizationFailedException {}
