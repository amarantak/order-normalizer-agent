<?php

namespace App\Domain\Order;

// Cargo que no es un ítem ni la propina (ej. "coperto", servicio, delivery).
final readonly class ExtraCharge
{
    public function __construct(
        public string $label,     // nombre original del cargo, tal como vino del POS
        public int $amountCents,  // monto en centavos
    ) {
        if (trim($label) === '') {
            throw new InvalidOrderException('Extra charge label cannot be empty.');
        }
        // Los descuentos (montos negativos) quedan fuera del alcance de V1.
        if ($amountCents < 0) {
            throw new InvalidOrderException("Extra charge \"{$label}\" cannot be negative.");
        }
    }
}
