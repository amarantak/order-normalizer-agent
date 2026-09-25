<?php

namespace App\Domain\Order;

// Una línea del pedido (ej. "2 x Pizza Margherita").
// readonly: una vez creado no se puede modificar. final: no está pensada para heredarse.
final readonly class OrderItem
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPriceCents,  // precio unitario en centavos (12.50 € = 1250)
        public int $lineTotalCents,  // total de la línea tal como vino de Claude
    ) {
        // Reglas estructurales: si no se cumplen, el dato no tiene sentido como ítem.
        if (trim($name) === '') {
            throw new InvalidOrderException('Item name cannot be empty.');
        }
        if ($quantity <= 0) {
            throw new InvalidOrderException("Item \"{$name}\" must have a positive quantity.");
        }
        if ($unitPriceCents < 0 || $lineTotalCents < 0) {
            throw new InvalidOrderException("Item \"{$name}\" cannot have negative amounts.");
        }
        // Ojo: NO se chequea que lineTotal = quantity × unitPrice.
        // Esa inconsistencia la detecta el validador, para poder pedirle a Claude que la corrija.
    }

    // Cuánto debería valer la línea. El validador lo compara con $lineTotalCents.
    public function expectedLineTotalCents(): int
    {
        return $this->quantity * $this->unitPriceCents;
    }
}
