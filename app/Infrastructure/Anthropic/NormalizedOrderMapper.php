<?php

namespace App\Infrastructure\Anthropic;

use App\Domain\Order\ExtraCharge;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use DateTimeImmutable;

// Traduce entre el JSON normalizado de Claude (decimales, snake_case, fecha en texto)
// y los objetos del dominio (centavos, camelCase, DateTimeImmutable), en los dos sentidos:
// - map():     Claude -> dominio (después de normalizar o corregir)
// - toArray(): dominio -> Claude (para mostrarle su respuesta anterior en la corrección)
// Es el "borde" entre el contrato con Claude y el modelo de negocio: es el único lugar
// del sistema que sabe que Claude habla en decimales.
final class NormalizedOrderMapper
{
    // ISO 8601: fecha + hora; segundos, fracciones y zona horaria (Z o ±hh:mm) opcionales.
    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})?$/';

    /**
     * @param array<string, mixed> $data   JSON de Claude ya decodificado
     * @param string               $source POS de origen, lo decide nuestro código (no Claude)
     */
    public function map(array $data, string $source): Order
    {
        return new Order(
            orderId: $this->string($data, 'order_id'),
            source: $source,
            items: array_map(fn(mixed $item) => $this->mapItem($item), $this->list($data, 'items')),
            subtotalCents: $this->cents($data, 'subtotal'),
            extraCharges: array_map(
                fn(mixed $charge) => $this->mapExtraCharge($charge),
                $this->list($data, 'extra_charges'),
            ),
            tipCents: $this->nullableCents($data, 'tip'),  // null = sin propina
            totalCents: $this->cents($data, 'total'),
            currency: $this->string($data, 'currency'),
            timestamp: $this->timestamp($data),
            rawAnomalies: $this->list($data, 'raw_anomalies'),
        );
    }

    /**
     * Operación inversa de map(): Order -> array con la forma de NormalizedOrderSchema.
     * Cumple que map(toArray($order), $source) devuelve un Order igual al original.
     *
     * @return array<string, mixed>
     */
    public function toArray(Order $order): array
    {
        return [
            'order_id' => $order->orderId,
            // Sin "source": no está en el schema (lo decide nuestro código).
            'items' => array_map(fn(OrderItem $item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,                          // entero, como en el schema
                'unit_price' => $this->decimal($item->unitPriceCents),  // 1250 -> 12.5
                'line_total' => $this->decimal($item->lineTotalCents),
            ], $order->items),
            'subtotal' => $this->decimal($order->subtotalCents),
            'extra_charges' => array_map(fn(ExtraCharge $charge) => [
                'label' => $charge->label,
                'amount' => $this->decimal($charge->amountCents),
            ], $order->extraCharges),
            // null se mantiene null: "sin propina" no es lo mismo que "propina 0".
            'tip' => $order->tipCents === null ? null : $this->decimal($order->tipCents),
            'total' => $this->decimal($order->totalCents),
            'currency' => $order->currency,
            // DATE_ATOM = ISO 8601 con zona (2026-09-15T20:14:00+00:00), el formato que acepta map().
            // ?-> devuelve null si no hay fecha, sin tener que escribir el if.
            'timestamp' => $order->timestamp?->format(DATE_ATOM),
            'raw_anomalies' => $order->rawAnomalies,
        ];
    }

    private function mapItem(mixed $item): OrderItem
    {
        $item = $this->object($item, 'items');

        return new OrderItem(
            name: $this->string($item, 'name'),
            quantity: $this->int($item, 'quantity'),
            unitPriceCents: $this->cents($item, 'unit_price'),
            lineTotalCents: $this->cents($item, 'line_total'),
        );
    }

    private function mapExtraCharge(mixed $charge): ExtraCharge
    {
        $charge = $this->object($charge, 'extra_charges');

        return new ExtraCharge(
            label: $this->string($charge, 'label'),
            amountCents: $this->cents($charge, 'amount'),
        );
    }

    // ---- Lectura de campos: cada helper valida que el campo exista y tenga el tipo esperado ----

    private function string(array $data, string $key): string
    {
        $value = $this->required($data, $key);
        if (! is_string($value)) {
            throw new InvalidClaudeResponseException("Field \"{$key}\" must be a string.");
        }
        return $value;
    }

    private function int(array $data, string $key): int
    {
        $value = $this->required($data, $key);
        if (! is_int($value)) {
            throw new InvalidClaudeResponseException("Field \"{$key}\" must be an integer.");
        }
        return $value;
    }

    // Decimal -> centavos. Se redondea ANTES de convertir a int porque la multiplicación
    // en float no es exacta: 19.99 * 100 = 1998.9999999999998, y (int) daría 1998.
    private function cents(array $data, string $key): int
    {
        $value = $this->required($data, $key);
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidClaudeResponseException("Field \"{$key}\" must be a number.");
        }
        return (int) round($value * 100);
    }

    // Centavos -> decimal: la inversa de cents(), por eso está al lado.
    // Dividir un int por 100 da el float más cercano (1999 -> 19.99), y json_encode lo escribe
    // como 19.99; al volver con cents() se recupera el mismo int.
    // Devuelve int|float porque una división exacta da int en PHP (400 / 100 = 4).
    private function decimal(int $cents): int|float
    {
        return $cents / 100;
    }

    private function nullableCents(array $data, string $key): ?int
    {
        return $this->required($data, $key) === null ? null : $this->cents($data, $key);
    }

    // Una lista JSON ([...]), no un objeto ({...}).
    private function list(array $data, string $key): array
    {
        $value = $this->required($data, $key);
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidClaudeResponseException("Field \"{$key}\" must be a list.");
        }
        return $value;
    }

    // Un objeto JSON ({...}) dentro de una lista, ej. cada ítem.
    private function object(mixed $value, string $listKey): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidClaudeResponseException("Every entry in \"{$listKey}\" must be an object.");
        }
        return $value;
    }

    // null está permitido (el POS no mandó fecha); si viene, tiene que ser ISO 8601.
    // El formato de cada POS (ej. "15/09/2026 20:14") lo convierte Claude, no nosotros.
    private function timestamp(array $data): ?DateTimeImmutable
    {
        $value = $this->required($data, 'timestamp');
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || preg_match(self::ISO_8601, $value) !== 1) {
            throw new InvalidClaudeResponseException('Field "timestamp" must be ISO 8601 or null.');
        }
        return new DateTimeImmutable($value);
    }

    // array_key_exists (y no isset) para distinguir "campo ausente" de "campo en null".
    private function required(array $data, string $key): mixed
    {
        if (! array_key_exists($key, $data)) {
            throw new InvalidClaudeResponseException("Missing field \"{$key}\".");
        }
        return $data[$key];
    }
}
