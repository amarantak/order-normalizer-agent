<?php

namespace App\Infrastructure\Anthropic;

use App\Domain\Order\Order;
use App\Domain\Order\OrderNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

// Implementación de OrderNormalizer con la API de Claude.
// Responsabilidad: hablar con la API (request + control de la respuesta).
// La traducción al dominio la hace NormalizedOrderMapper.
final class ClaudeOrderNormalizer implements OrderNormalizer
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MAX_TOKENS = 2048;       // un pedido normalizado usa unos pocos cientos
    private const TIMEOUT_SECONDS = 60;
    private const MAX_ATTEMPTS = 3;        // 1 intento + 2 reintentos
    private const RETRY_SLEEP_MS = 1000;

    // Recibe todo por constructor (no llama a config() por dentro):
    // el binding con la config real se hace en AppServiceProvider.
    public function __construct(
        private readonly NormalizedOrderMapper $mapper,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $apiVersion,
    ) {
    }

    public function normalize(array $rawOrder, string $source): Order
    {
        $response = $this->send($rawOrder);
        $data = $this->extractJson($response);

        return $this->mapper->map($data, $source);
    }

    private function send(array $rawOrder): Response
    {
        try {
            return Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->apiVersion,
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                // Reintenta solo errores transitorios. throw: false -> si todos los intentos
                // fallan, devuelve la última respuesta y la controlamos abajo.
                ->retry(self::MAX_ATTEMPTS, self::RETRY_SLEEP_MS, fn(Throwable $e) => $this->isTransient($e), throw: false)
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'max_tokens' => self::MAX_TOKENS,
                    'system' => $this->systemPrompt(),
                    'messages' => [
                        // El JSON crudo del POS va como mensaje de usuario, separado de las reglas.
                        ['role' => 'user', 'content' => json_encode($rawOrder, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                    ],
                    'output_config' => [
                        'format' => ['type' => 'json_schema', 'schema' => NormalizedOrderSchema::definition()],
                    ],
                ]);
        } catch (ConnectionException $e) {
            // Envolvemos la excepción de Laravel: quien use OrderNormalizer no tiene por qué conocerla.
            throw new InvalidClaudeResponseException('Could not connect to the Claude API: '.$e->getMessage(), previous: $e);
        }
    }

    // Transitorio = puede salir bien si se reintenta: sin conexión, rate limit (429) o error del servidor (5xx).
    // Un 400 significa que nuestro request está mal: reintentarlo da el mismo error.
    private function isTransient(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }
        if ($e instanceof RequestException) {
            $status = $e->response->status();
            return $status === 429 || $status >= 500;
        }
        return false;
    }

    /** @return array<string, mixed> */
    private function extractJson(Response $response): array
    {
        // 1) Status HTTP.
        if ($response->failed()) {
            throw new InvalidClaudeResponseException(sprintf(
                'Claude API returned HTTP %d: %s',
                $response->status(),
                $response->json('error.message') ?? $response->body(),
            ));
        }

        // 2) Solo end_turn es una respuesta completa (max_tokens = JSON cortado, refusal, etc.).
        $stopReason = $response->json('stop_reason');
        if ($stopReason !== 'end_turn') {
            throw new InvalidClaudeResponseException(
                sprintf('Unexpected stop_reason "%s" (expected "end_turn").', $stopReason ?? 'null'),
            );
        }

        // 3) Primer bloque de texto: ahí viene el JSON.
        $text = null;
        foreach ($response->json('content') ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text = $block['text'] ?? null;
                break;
            }
        }
        if (! is_string($text)) {
            throw new InvalidClaudeResponseException('Claude response has no text block.');
        }

        // 4) Decodificar. Con structured outputs no debería fallar, pero es el borde con un sistema externo.
        try {
            $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidClaudeResponseException('Claude response is not valid JSON.', previous: $e);
        }
        if (! is_array($data)) {
            throw new InvalidClaudeResponseException('Claude response JSON is not an object.');
        }

        return $data;
    }

    // Reglas de negocio de la normalización. La forma de la respuesta la garantiza el schema.
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a data normalization agent for restaurant orders.
            You will receive a raw order JSON from a POS system. Its format varies:
            different field names, languages, missing or mistyped values.
            Transform it into the structure defined by the response schema.

            Rules:
            - Amounts are decimals in currency units (12.50, not 1250).
            - If a field is missing but can be calculated from others (e.g. total from
              subtotal + extra_charges + tip), calculate it and note it in raw_anomalies.
            - Any charge that is not an item or the tip (e.g. "coperto", cover or service
              charge) goes into extra_charges, with its original name as label.
            - If there is no tip, use null.
            - If a value has the wrong type (e.g. a price as a string), convert it and
              note it in raw_anomalies.
            - timestamp must be ISO 8601 (YYYY-MM-DDTHH:MM:SS). Include a timezone only if
              the source has one; never invent it. If there is no date, use null and note
              it in raw_anomalies.
            - Write raw_anomalies as short technical notes in English.
            PROMPT;
    }
}
