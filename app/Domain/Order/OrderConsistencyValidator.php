<?php

namespace App\Domain\Order;

// Verifica que un Order ya normalizado sea coherente (que las cuentas cierren).
// Clase concreta sin interfaz: son reglas de negocio determinísticas,
// con una sola implementación posible y sin dependencias externas.
final class OrderConsistencyValidator
{
    /**
     * Devuelve la lista de reglas que no se cumplen, en texto.
     * Lista vacía = el pedido es consistente.
     *
     * Revisa TODAS las reglas en vez de cortar en el primer error:
     * así el prompt de corrección recibe todos los problemas de una vez.
     *
     * @return list<string>
     */
    public function validate(Order $order): array
    {
        return [
            ...$this->checkLineTotals($order),
            ...$this->checkSubtotal($order),
            ...$this->checkTotal($order),
        ];
    }

    // Regla 1: cada ítem cumple line_total = quantity × unit_price.
    /** @return list<string> */
    private function checkLineTotals(Order $order): array
    {
        $errors = [];

        foreach ($order->items as $item) {
            $expected = $item->expectedLineTotalCents();

            if ($item->lineTotalCents !== $expected) {
                $errors[] = sprintf(
                    'Item "%s": line_total %s != quantity %d × unit_price %s (expected %s).',
                    $item->name,
                    $this->format($item->lineTotalCents),
                    $item->quantity,
                    $this->format($item->unitPriceCents),
                    $this->format($expected),
                );
            }
        }

        return $errors;
    }

    // Regla 2: subtotal = suma de los line_total de los ítems.
    /** @return list<string> */
    private function checkSubtotal(Order $order): array
    {
        $itemsTotal = $order->itemsTotalCents();

        if ($order->subtotalCents === $itemsTotal) {
            return [];
        }

        return [sprintf(
            'subtotal %s != sum of items line_total %s.',
            $this->format($order->subtotalCents),
            $this->format($itemsTotal),
        )];
    }

    // Regla 3: subtotal + extra_charges + tip = total.
    // Sin propina (tip null) cuenta como 0: no tener propina no es una inconsistencia.
    /** @return list<string> */
    private function checkTotal(Order $order): array
    {
        $tip = $order->tipCents ?? 0;
        $expected = $order->subtotalCents + $order->extraChargesTotalCents() + $tip;

        if ($order->totalCents === $expected) {
            return [];
        }

        return [sprintf(
            'total %s != subtotal %s + extra_charges %s + tip %s (expected %s).',
            $this->format($order->totalCents),
            $this->format($order->subtotalCents),
            $this->format($order->extraChargesTotalCents()),
            $this->format($tip),
            $this->format($expected),
        )];
    }

    // Centavos -> texto decimal ("1250" -> "12.50"), el mismo formato que ve Claude en el JSON.
    // Solo para mostrar en mensajes: las comparaciones de arriba siguen siendo con enteros.
    // Los montos nunca son negativos (lo garantizan los constructores), así que alcanza con esto.
    private function format(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
