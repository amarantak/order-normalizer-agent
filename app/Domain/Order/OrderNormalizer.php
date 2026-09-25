<?php

namespace App\Domain\Order;

// Contrato: "algo que convierte un pedido crudo de cualquier POS en un Order normalizado".
// El dominio solo conoce esta interfaz; quién la implementa (Claude, un mapeo manual,
// un fake para tests) es un detalle de infraestructura.
interface OrderNormalizer
{
    /**
     * @param array<string, mixed> $rawOrder  JSON crudo del POS, ya decodificado a array
     * @param string               $source    identificador de la fuente (ej. "pos1")
     *
     * @throws InvalidOrderException si el resultado no tiene forma de pedido válido
     */
    public function normalize(array $rawOrder, string $source): Order;

    // El método de corrección (paso 3 del loop) se agrega cuando diseñemos
    // el prompt de corrección, para no adivinar qué datos necesita.
}
