<?php

namespace Tests\Unit\Infrastructure\Anthropic;

use App\Infrastructure\Anthropic\InvalidClaudeResponseException;
use App\Infrastructure\Anthropic\NormalizedOrderMapper;
use PHPUnit\Framework\TestCase;

// El mapper es código puro: no llama a la API ni necesita Laravel.
// Cada test le pasa un array como el que devolvería Claude (ya decodificado).
final class NormalizedOrderMapperTest extends TestCase
{
    // Pedido del POS español: decimales -> centavos, fecha ISO -> DateTimeImmutable.
    public function test_maps_order_to_domain(): void
    {
        $order = (new NormalizedOrderMapper())->map($this->sumUpData(), 'pos1');

        $this->assertSame('SU-4471', $order->orderId);
        $this->assertSame(1250, $order->items[0]->unitPriceCents);
        $this->assertSame(2500, $order->items[0]->lineTotalCents);
        $this->assertSame(2750, $order->totalCents);
        $this->assertNull($order->tipCents);
        $this->assertSame('2026-09-15T20:14:00+00:00', $order->timestamp->format(DATE_ATOM));
    }

    // El source lo decide nuestro código, no lo que diga Claude en el JSON.
    public function test_uses_source_from_caller_not_from_claude(): void
    {
        $order = (new NormalizedOrderMapper())->map($this->sumUpData(), 'pos1');

        $this->assertSame('pos1', $order->source);
    }

    // Pedido italiano: el coperto va a extra_charges y la mancia a tip.
    public function test_maps_extra_charges_and_tip(): void
    {
        $data = [
            ...$this->sumUpData(),
            'extra_charges' => [['label' => 'coperto', 'amount' => 4.00]],
            'tip' => 5.00,
        ];

        $order = (new NormalizedOrderMapper())->map($data, 'pos2');

        $this->assertSame('coperto', $order->extraCharges[0]->label);
        $this->assertSame(400, $order->extraCharges[0]->amountCents);
        $this->assertSame(500, $order->tipCents);
    }

    // 19.99 * 100 en float da 1998.9999999999998: sin round() se perdería un centavo.
    public function test_rounds_decimals_to_exact_cents(): void
    {
        $data = [...$this->sumUpData(), 'total' => 19.99];

        $order = (new NormalizedOrderMapper())->map($data, 'pos1');

        $this->assertSame(1999, $order->totalCents);
    }

    // timestamp null es válido: el POS 3 no manda fecha.
    public function test_accepts_null_timestamp(): void
    {
        $data = [...$this->sumUpData(), 'timestamp' => null];

        $order = (new NormalizedOrderMapper())->map($data, 'pos3');

        $this->assertNull($order->timestamp);
    }

    public function test_missing_field_is_rejected(): void
    {
        $data = $this->sumUpData();
        unset($data['total']);

        $this->expectException(InvalidClaudeResponseException::class);
        $this->expectExceptionMessage('Missing field "total".');

        (new NormalizedOrderMapper())->map($data, 'pos1');
    }

    // Si Claude no convirtió la fecha a ISO (ej. dejó el formato italiano), es respuesta inválida.
    public function test_non_iso_timestamp_is_rejected(): void
    {
        $data = [...$this->sumUpData(), 'timestamp' => '15/09/2026 20:14'];

        $this->expectException(InvalidClaudeResponseException::class);

        (new NormalizedOrderMapper())->map($data, 'pos2');
    }

    // Respuesta de Claude para el pedido del POS 1 (SumUp), ya normalizada.
    private function sumUpData(): array
    {
        return [
            'order_id' => 'SU-4471',
            'source' => 'SumUp',
            'items' => [
                ['name' => 'Pizza Margherita', 'quantity' => 2, 'unit_price' => 12.50, 'line_total' => 25.00],
                ['name' => 'Coca-Cola', 'quantity' => 1, 'unit_price' => 2.50, 'line_total' => 2.50],
            ],
            'subtotal' => 27.50,
            'extra_charges' => [],
            'tip' => null,
            'total' => 27.50,
            'currency' => 'EUR',
            'timestamp' => '2026-09-15T20:14:00Z',
            'raw_anomalies' => [],
        ];
    }
}
