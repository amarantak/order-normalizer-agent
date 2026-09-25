<?php

namespace App\Domain\Order;

use InvalidArgumentException;

// Error propio del dominio: se lanza cuando un dato no tiene forma de pedido válido.
// Permite distinguir "Claude devolvió algo inválido" de cualquier otro error del sistema.
final class InvalidOrderException extends InvalidArgumentException
{
}