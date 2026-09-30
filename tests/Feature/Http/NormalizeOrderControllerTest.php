<?php

namespace Tests\Feature\Http;

use App\Domain\Order\Order;
use App\Domain\Order\OrderNormalizationFailedException;
use App\Domain\Order\OrderNormalizer;
use App\Infrastructure\Anthropic\NormalizedOrderMapper;
use Closure;
use Tests\TestCase;

// Tests del endpoint POST /orders/normalize.
// Extiende el TestCase de Laravel: hace peticiones HTTP reales contra la app.
// El OrderNormalizer se reemplaza por un fake en el contenedor: ningún test llama a Claude.
// El validador y el orquestador son los reales.
final class NormalizeOrderControllerTest extends TestCase
{
    public function test_valid_order_returns_approved_result(): void
    {
        $this->useNormalizer(fn() => $this->order());

        $this->postJson('/orders/normalize', $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('correction_attempted', false)
            ->assertJsonPath('order.order_id', 'SU-4471')
            ->assertJsonPath('violations', [])
            ->assertJsonPath('error', null);
    }

    // El controller le pasa al caso de uso exactamente lo que mandó el front.
    public function test_raw_order_and_source_reach_the_normalizer(): void
    {
        $fake = $this->useNormalizer(fn() => $this->order());

        $this->postJson('/orders/normalize', $this->payload())->assertOk();

        $this->assertSame([[['order_ref' => 'SU-4471'], 'pos1']], $fake->calls);
    }

    // Problema de datos: es un resultado válido del proceso, por eso 200 y no un error HTTP.
    public function test_inconsistent_order_returns_needs_review_with_200(): void
    {
        // Total 30.00 vs. 27.50 de items; la "corrección" del fake devuelve lo mismo.
        $this->useNormalizer(fn() => $this->order(total: 30.00));

        $this->postJson('/orders/normalize', $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'needs_review')
            ->assertJsonPath('correction_attempted', true)
            ->assertJsonPath('order.order_id', 'SU-4471')
            ->assertJsonCount(1, 'violations');
    }

    // Problema de integración: 502, pero el cuerpo trae el resultado para que el front lo muestre.
    public function test_integration_failure_returns_502_with_failed_result(): void
    {
        $this->useNormalizer(fn() => throw new OrderNormalizationFailedException('Claude API returned HTTP 500: Overloaded'));

        $this->postJson('/orders/normalize', $this->payload())
            ->assertStatus(502)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('order', null)
            ->assertJsonPath('error', 'Claude API returned HTTP 500: Overloaded');
    }

    // Entrada inválida: 422 y el normalizador ni se llama (no se gasta una llamada a la API).
    public function test_invalid_input_is_rejected_without_calling_the_normalizer(): void
    {
        $fake = $this->useNormalizer(fn() => $this->order());

        $this->postJson('/orders/normalize', [])                                         // falta todo
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source', 'raw_order']);

        $this->postJson('/orders/normalize', ['source' => 'pos9', 'raw_order' => ['id' => '1']]) // POS desconocido
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source']);

        $this->postJson('/orders/normalize', ['source' => 'pos1', 'raw_order' => [1, 2]])    // lista, no objeto
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['raw_order']);

        $this->assertSame([], $fake->calls);
    }

    // ---- Helpers ----

    /** @return array{source: string, raw_order: array<string, mixed>} */
    private function payload(): array
    {
        return ['source' => 'pos1', 'raw_order' => ['order_ref' => 'SU-4471']];
    }

    // Reemplaza el OrderNormalizer del contenedor por un fake que registra con qué se lo llamó.
    // correct() devuelve el mismo pedido que recibió (simula una corrección que no arregla nada).
    private function useNormalizer(Closure $onNormalize): object
    {
        $fake = new class($onNormalize) implements OrderNormalizer {
            /** @var list<array{0: array<string, mixed>, 1: string}> */
            public array $calls = [];

            public function __construct(private readonly Closure $onNormalize)
            {
            }

            public function normalize(array $rawOrder, string $source): Order
            {
                $this->calls[] = [$rawOrder, $source];

                return ($this->onNormalize)();
            }

            public function correct(array $rawOrder, Order $previous, array $violations, string $source): Order
            {
                return $previous;
            }
        };

        $this->app->instance(OrderNormalizer::class, $fake);

        return $fake;
    }

    // Pedido del POS 1 ya normalizado; con otro total sirve para el caso inconsistente.
    private function order(float $total = 27.50): Order
    {
        return (new NormalizedOrderMapper())->map([
            'order_id' => 'SU-4471',
            'items' => [
                ['name' => 'Pizza Margherita', 'quantity' => 2, 'unit_price' => 12.50, 'line_total' => 25.00],
                ['name' => 'Coca-Cola', 'quantity' => 1, 'unit_price' => 2.50, 'line_total' => 2.50],
            ],
            'subtotal' => 27.50,
            'extra_charges' => [],
            'tip' => null,
            'total' => $total,
            'currency' => 'EUR',
            'timestamp' => '2026-09-15T20:14:00+00:00',
            'raw_anomalies' => [],
        ], 'pos1');
    }
}
