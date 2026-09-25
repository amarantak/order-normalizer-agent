<?php

namespace App\Infrastructure\Anthropic;

use App\Domain\Order\Order;
use App\Domain\Order\OrderNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use LogicException;
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
    ) {}

    public function normalize(array $rawOrder, string $source): Order
    {
        // Conversación de un solo mensaje: el pedido crudo.
        $response = $this->send([
            $this->rawOrderMessage($rawOrder),
        ]);

        return $this->mapper->map($this->extractJson($response), $source);
    }

    public function correct(array $rawOrder, Order $previous, array $violations, string $source): Order
    {
        // Corregir sin nada que corregir es un error del orquestador, no un caso de negocio:
        // fallamos fuerte en vez de gastar una llamada a la API.
        if ($violations === []) {
            throw new LogicException('correct() needs at least one violation: the order is already consistent.');
        }

        // Conversación de 3 mensajes: Claude ve el crudo, su respuesta anterior como propia,
        // y qué reglas no cumplió. El system prompt es el mismo de normalize().
        $response = $this->send([
            $this->rawOrderMessage($rawOrder),
            ['role' => 'assistant', 'content' => $this->encode($this->mapper->toArray($previous))],
            ['role' => 'user', 'content' => $this->correctionMessage($violations)],
        ]);

        return $this->mapper->map($this->extractJson($response), $source);
    }

    // El pedido crudo del POS como mensaje de usuario, separado de las reglas (van en system).
    /** @return array{role: string, content: string} */
    private function rawOrderMessage(array $rawOrder): array
    {
        return ['role' => 'user', 'content' => $this->encode($rawOrder)];
    }

    // JSON_UNESCAPED_UNICODE: los acentos y símbolos viajan legibles (no como \u00e9).
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    // Recibe la conversación ya armada: normalize() y correct() deciden QUÉ decir,
    // send() sabe CÓMO hablar con la API (headers, reintentos, schema).
    /** @param list<array{role: string, content: string}> $messages */
    private function send(array $messages): Response
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
                    'messages' => $messages,
                    'output_config' => [
                        'format' => ['type' => 'json_schema', 'schema' => NormalizedOrderSchema::definition()],
                    ],
                ]);
        } catch (ConnectionException $e) {
            // Envolvemos la excepción de Laravel: quien use OrderNormalizer no tiene por qué conocerla.
            throw new InvalidClaudeResponseException('Could not connect to the Claude API: ' . $e->getMessage(), previous: $e);
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

    // Instrucciones de corrección, con las violaciones que devolvió el validador.
    // La regla clave: corregir errores de normalización, nunca inventar valores
    // para que cierren las cuentas. Si el crudo es inconsistente, el pedido
    // vuelve a fallar la validación y termina en revisión humana.
    /** @param list<string> $violations */
    private function correctionMessage(array $violations): string
    {
        $list = implode("\n", array_map(fn(string $violation) => "- {$violation}", $violations));

        return <<<PROMPT
            Your normalized order fails these consistency checks:
            {$list}

            Re-check it against the raw order and return the full corrected order.
            - Fix any value you normalized incorrectly.
            - If the raw data itself is inconsistent, keep the raw values and explain it
              in raw_anomalies. Never invent or adjust values just to make totals match.
            - Keep the raw_anomalies that still apply.
            PROMPT;
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
