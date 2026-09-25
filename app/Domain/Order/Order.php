<?php

namespace App\Domain\Order;

use DateTimeImmutable;

// Pedido normalizado. Es la "raíz" del agregado: OrderItem y ExtraCharge
// solo existen dentro de un Order, y Order garantiza que todo lo que contiene es válido.
final readonly class Order
{
    /**
     * @param list<OrderItem>   $items
     * @param list<ExtraCharge> $extraCharges
     * @param list<string>      $rawAnomalies
     */
    public function __construct(
        public string $orderId,
        public string $source,                  // de qué POS vino el pedido
        public array $items,
        public int $subtotalCents,
        public array $extraCharges,
        public ?int $tipCents,                  // null = el pedido no tiene propina
        public int $totalCents,
        public string $currency,                // código ISO de 3 letras (EUR, USD...)
        public ?DateTimeImmutable $timestamp,   // null = el POS no mandó fecha
        public array $rawAnomalies,             // notas de Claude sobre datos raros o corregidos
    ) {
        // Identificadores obligatorios.
        if (trim($orderId) === '' || trim($source) === '') {
            throw new InvalidOrderException('Order id and source cannot be empty.');
        }

        // Un pedido sin ítems no tiene sentido. array_is_list exige índices 0, 1, 2...
        if ($items === [] || ! array_is_list($items)) {
            throw new InvalidOrderException('Order must have a non-empty list of items.');
        }

        // PHP no tiene arrays tipados: chequeamos a mano el tipo de cada elemento.
        foreach ($items as $item) {
            if (! $item instanceof OrderItem) {
                throw new InvalidOrderException('Every item must be an OrderItem.');
            }
        }
        foreach ($extraCharges as $charge) {
            if (! $charge instanceof ExtraCharge) {
                throw new InvalidOrderException('Every extra charge must be an ExtraCharge.');
            }
        }
        foreach ($rawAnomalies as $anomaly) {
            if (! is_string($anomaly)) {
                throw new InvalidOrderException('Every anomaly must be a string.');
            }
        }

        // Ningún monto puede ser negativo.
        if ($subtotalCents < 0 || $totalCents < 0 || ($tipCents !== null && $tipCents < 0)) {
            throw new InvalidOrderException('Order amounts cannot be negative.');
        }

        // Moneda: exactamente 3 letras mayúsculas.
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidOrderException("Invalid currency code \"{$currency}\".");
        }

        // Igual que en OrderItem: acá NO se chequea que las sumas cierren.
        // Eso es trabajo del validador (paso 2 del loop).
    }

    // Suma de los totales de línea de todos los ítems.
    public function itemsTotalCents(): int
    {
        return array_sum(array_map(
            fn(OrderItem $item) => $item->lineTotalCents,
            $this->items,
        ));
    }

    // Suma de todos los cargos extra.
    public function extraChargesTotalCents(): int
    {
        return array_sum(array_map(
            fn(ExtraCharge $charge) => $charge->amountCents,
            $this->extraCharges,
        ));
    }
}
