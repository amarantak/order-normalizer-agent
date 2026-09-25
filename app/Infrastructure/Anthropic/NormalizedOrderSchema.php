<?php

namespace App\Infrastructure\Anthropic;

// JSON Schema que la API de Claude usa (structured outputs) para garantizar
// la forma de la respuesta. Es el contrato con Claude: el mismo que lee NormalizedOrderMapper.
// Vive en una sola clase porque lo van a usar la llamada de normalización y la de corrección.
final class NormalizedOrderSchema
{
    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => ['type' => 'string'],
                'items' => [
                    'type' => 'array',
                    'items' => self::object([
                        'name' => ['type' => 'string'],
                        'quantity' => ['type' => 'integer'],   // entero: OrderItem no acepta 2.5
                        'unit_price' => ['type' => 'number'],
                        'line_total' => ['type' => 'number'],
                    ]),
                ],
                'subtotal' => ['type' => 'number'],
                'extra_charges' => [
                    'type' => 'array',
                    'items' => self::object([
                        'label' => ['type' => 'string'],
                        'amount' => ['type' => 'number'],
                    ]),
                ],
                'tip' => ['type' => ['number', 'null']],        // null = sin propina
                'total' => ['type' => 'number'],
                'currency' => ['type' => 'string'],
                'timestamp' => ['type' => ['string', 'null']],  // ISO 8601 o null
                'raw_anomalies' => ['type' => 'array', 'items' => ['type' => 'string']],
                // Sin "source": lo decide nuestro código, no Claude.
            ],
            'required' => [
                'order_id', 'items', 'subtotal', 'extra_charges', 'tip',
                'total', 'currency', 'timestamp', 'raw_anomalies',
            ],
            'additionalProperties' => false,
        ];
    }

    // Objeto con todas sus propiedades obligatorias y sin campos extra.
    /**
     * @param array<string, array<string, mixed>> $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }
}
