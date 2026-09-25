<?php

namespace App\Infrastructure\Anthropic;

use RuntimeException;

// La respuesta de Claude no respeta el contrato acordado
// (falta un campo, tipo incorrecto, fecha que no es ISO 8601...).
// Es un problema de integración, no del negocio: por eso vive en infraestructura
// y no reutiliza InvalidOrderException (que es del dominio).
final class InvalidClaudeResponseException extends RuntimeException
{
}
