<?php

namespace Tests\Feature\Infrastructure\Anthropic;

use App\Domain\Order\Order;
use App\Infrastructure\Anthropic\ClaudeOrderNormalizer;
use App\Infrastructure\Anthropic\InvalidClaudeResponseException;
use App\Infrastructure\Anthropic\NormalizedOrderMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use LogicException;
use Tests\TestCase;

// Extiende el TestCase de Laravel: Http::fake() necesita la app levantada.
// Ningún test llama a la API real ni gasta créditos.
final class ClaudeOrderNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake(); // las esperas entre reintentos no demoran los tests
    }

    public function test_valid_response_returns_order(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->claudeResponse())]);

        $order = $this->normalizer()->normalize($this->rawOrder(), 'pos1');

        $this->assertSame('SU-4471', $order->orderId);
        $this->assertSame('pos1', $order->source);
        $this->assertSame(2750, $order->totalCents);
    }

    // Verifica QUÉ le mandamos a la API: headers, modelo, reglas, pedido crudo y schema.
    public function test_request_contains_headers_model_prompt_and_schema(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->claudeResponse())]);

        $this->normalizer()->normalize($this->rawOrder(), 'pos1');

        Http::assertSent(
            fn(Request $request) =>
            $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request['model'] === 'test-model'
                && str_contains($request['system'], 'normalization agent')
                && str_contains($request['messages'][0]['content'], 'SU-4471')
                && $request['output_config']['format']['type'] === 'json_schema'
        );
    }

    // 500 es transitorio: se reintenta hasta 3 intentos en total y después se rechaza.
    public function test_server_error_is_retried_then_rejected(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Overloaded']], 500)]);

        try {
            $this->normalizer()->normalize($this->rawOrder(), 'pos1');
            $this->fail('Expected InvalidClaudeResponseException');
        } catch (InvalidClaudeResponseException $e) {
            $this->assertSame('Claude API returned HTTP 500: Overloaded', $e->getMessage());
        }

        Http::assertSentCount(3);
    }

    // 400 = nuestro request está mal: no tiene sentido reintentar.
    public function test_client_error_is_not_retried(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Bad request']], 400)]);

        try {
            $this->normalizer()->normalize($this->rawOrder(), 'pos1');
            $this->fail('Expected InvalidClaudeResponseException');
        } catch (InvalidClaudeResponseException) {
        }

        Http::assertSentCount(1);
    }

    // Respuesta cortada por max_tokens: el JSON está incompleto, se rechaza.
    public function test_truncated_response_is_rejected(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->claudeResponse(stopReason: 'max_tokens'))]);

        $this->expectException(InvalidClaudeResponseException::class);
        $this->expectExceptionMessage('Unexpected stop_reason "max_tokens"');

        $this->normalizer()->normalize($this->rawOrder(), 'pos1');
    }

    // ---- Corrección (paso 3 del loop) ----

    // La corrección devuelve el Order que manda Claude, con el source que decide nuestro código.
    public function test_correction_returns_corrected_order(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->claudeResponse())]);

        $order = $this->normalizer()->correct($this->rawOrder(), $this->previousOrder(), [$this->violation()], 'pos1');

        $this->assertSame(2500, $order->items[0]->lineTotalCents);  // el valor corregido
        $this->assertSame('pos1', $order->source);
    }

    // Verifica la conversación: crudo (user) -> respuesta anterior (assistant) -> violaciones (user),
    // con el mismo system prompt y el mismo schema que la normalización.
    public function test_correction_request_contains_raw_previous_answer_and_violations(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->claudeResponse())]);

        $this->normalizer()->correct($this->rawOrder(), $this->previousOrder(), [$this->violation()], 'pos1');

        Http::assertSent(
            fn(Request $request) =>
            count($request['messages']) === 3
                && $request['messages'][0]['role'] === 'user'
                && str_contains($request['messages'][0]['content'], 'SU-4471')         // el crudo
                && $request['messages'][1]['role'] === 'assistant'
                && str_contains($request['messages'][1]['content'], '"line_total":24')  // el valor mal
                && $request['messages'][2]['role'] === 'user'
                && str_contains($request['messages'][2]['content'], $this->violation())
                && str_contains($request['system'], 'normalization agent')
                && $request['output_config']['format']['type'] === 'json_schema'
        );
    }

    // Sin violaciones no hay nada que corregir: es un error del que llama, y no se llama a la API.
    public function test_correction_without_violations_is_rejected(): void
    {
        Http::fake();

        try {
            $this->normalizer()->correct($this->rawOrder(), $this->previousOrder(), [], 'pos1');
            $this->fail('Expected LogicException');
        } catch (LogicException) {
        }

        Http::assertNothingSent();
    }

    private function normalizer(): ClaudeOrderNormalizer
    {
        return new ClaudeOrderNormalizer(new NormalizedOrderMapper(), 'test-key', 'test-model', '2023-06-01');
    }

    // Pedido crudo del POS 1 (SumUp), como lo manda el POS.
    private function rawOrder(): array
    {
        return [
            'order_ref' => 'SU-4471',
            'products' => [
                ['desc' => 'Pizza Margherita', 'qty' => 2, 'price' => 12.50],
                ['desc' => 'Coca-Cola', 'qty' => 1, 'price' => 2.50],
            ],
            'amount_total' => 27.50,
            'curr' => 'EUR',
            'created_at' => '2026-09-15T20:14:00Z',
        ];
    }

    // Resultado anterior con un error: line_total de la pizza 24.00 en vez de 25.00.
    private function previousOrder(): Order
    {
        $data = $this->normalizedData();
        $data['items'][0]['line_total'] = 24.00;

        return (new NormalizedOrderMapper())->map($data, 'pos1');
    }

    // Mensaje con el formato que usa OrderConsistencyValidator.
    private function violation(): string
    {
        return 'Item "Pizza Margherita": line_total 24.00 != quantity 2 × unit_price 12.50 (expected 25.00).';
    }

    // Imita la respuesta de la API: el JSON normalizado viaja como texto en content[0].text.
    private function claudeResponse(string $stopReason = 'end_turn'): array
    {
        return [
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => json_encode($this->normalizedData())]],
            'stop_reason' => $stopReason,
        ];
    }

    // Pedido del POS 1 ya normalizado, correcto (como lo devolvería Claude).
    private function normalizedData(): array
    {
        return [
            'order_id' => 'SU-4471',
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
            'raw_anomalies' => ['subtotal calculated from items'],
        ];
    }
}
