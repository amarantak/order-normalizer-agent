<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\NormalizationStatus;
use App\Application\Order\NormalizeOrder;
use App\Domain\Order\InvalidOrderException;
use App\Domain\Order\Order;
use App\Domain\Order\OrderConsistencyValidator;
use App\Domain\Order\OrderItem;
use App\Domain\Order\OrderNormalizationFailedException;
use App\Domain\Order\OrderNormalizer;
use LogicException;
use PHPUnit\Framework\TestCase;
use Throwable;

// TestCase de PHPUnit (no el de Laravel): el orquestador depende de la interfaz
// OrderNormalizer, así que se prueba con un normalizador falso, sin framework ni API.
// El validador es el real: son reglas determinísticas sin dependencias.
final class NormalizeOrderTest extends TestCase
{
    public function test_valid_order_is_approved_without_correction(): void
    {
        $fake = $this->fakeNormalizer(normalize: $this->consistentOrder());

        $result = $this->useCase($fake)->handle([], 'pos1');

        $this->assertSame(NormalizationStatus::Approved, $result->status);
        $this->assertFalse($result->correctionAttempted);
        $this->assertSame(0, $fake->correctCalls);
    }

    // Caso A de la prueba real: Claude se equivoca, corrige y valida.
    public function test_inconsistent_order_is_corrected_and_approved(): void
    {
        $fake = $this->fakeNormalizer(normalize: $this->inconsistentOrder(), correct: $this->consistentOrder());

        $result = $this->useCase($fake)->handle([], 'pos1');

        $this->assertSame(NormalizationStatus::Approved, $result->status);
        $this->assertTrue($result->correctionAttempted);
        $this->assertSame(2500, $result->order->items[0]->lineTotalCents);  // el corregido
    }

    // Caso B de la prueba real: sigue fallando tras la corrección -> revisión humana, sin loopear.
    public function test_order_still_inconsistent_after_one_correction_needs_review(): void
    {
        $fake = $this->fakeNormalizer(normalize: $this->inconsistentOrder(), correct: $this->inconsistentOrder());

        $result = $this->useCase($fake)->handle([], 'pos1');

        $this->assertSame(NormalizationStatus::NeedsReview, $result->status);
        $this->assertNotSame([], $result->violations);
        $this->assertSame(1, $fake->correctCalls);  // una sola corrección
    }

    public function test_invalid_order_needs_review_without_order(): void
    {
        $fake = $this->fakeNormalizer(normalize: new InvalidOrderException('Quantity must be greater than zero.'));

        $result = $this->useCase($fake)->handle([], 'pos1');

        $this->assertSame(NormalizationStatus::NeedsReview, $result->status);
        $this->assertNull($result->order);
        $this->assertSame('Quantity must be greater than zero.', $result->error);
    }

    public function test_integration_failure_on_normalize_is_failed(): void
    {
        $fake = $this->fakeNormalizer(normalize: new OrderNormalizationFailedException('Claude API returned HTTP 500.'));

        $result = $this->useCase($fake)->handle([], 'pos1');

        $this->assertSame(NormalizationStatus::Failed, $result->status);
        $this->assertNull($result->order);
    }

    // La API se cae durante la corrección: Failed, pero conserva el pedido anterior y sus violaciones.
    public function test_integration_failure_on_correct_keeps_previous_order(): void
    {
        $fake = $this->fakeNormalizer(
            normalize: $this->inconsistentOrder(),
            correct: new OrderNormalizationFailedException('Could not connect to the Claude API.'),
        );

        $result = $this->useCase($fake)->handle([], 'pos1');

        $this->assertSame(NormalizationStatus::Failed, $result->status);
        $this->assertSame(2400, $result->order->items[0]->lineTotalCents);  // el anterior
        $this->assertNotSame([], $result->violations);
        $this->assertTrue($result->correctionAttempted);
    }

    // Un error de programación no se convierte en un estado: tiene que explotar.
    public function test_logic_exception_is_not_caught(): void
    {
        $fake = $this->fakeNormalizer(normalize: new LogicException('Bug.'));

        $this->expectException(LogicException::class);

        $this->useCase($fake)->handle([], 'pos1');
    }

    // ---- Helpers ----

    private function useCase(OrderNormalizer $normalizer): NormalizeOrder
    {
        return new NormalizeOrder($normalizer, new OrderConsistencyValidator());
    }

    // Normalizador falso: devuelve el Order indicado o lanza la excepción indicada,
    // y cuenta cuántas veces se llamó a correct().
    private function fakeNormalizer(Order|Throwable $normalize, Order|Throwable|null $correct = null): OrderNormalizer
    {
        return new class($normalize, $correct) implements OrderNormalizer {
            public int $correctCalls = 0;

            public function __construct(
                private Order|Throwable $normalizeReturns,
                private Order|Throwable|null $correctReturns,
            ) {
            }

            public function normalize(array $rawOrder, string $source): Order
            {
                return $this->answer($this->normalizeReturns);
            }

            public function correct(array $rawOrder, Order $previous, array $violations, string $source): Order
            {
                $this->correctCalls++;
                return $this->answer($this->correctReturns ?? throw new LogicException('correct() was not expected.'));
            }

            private function answer(Order|Throwable $value): Order
            {
                if ($value instanceof Throwable) {
                    throw $value;
                }
                return $value;
            }
        };
    }

    // POS 1: 2 × 12.50 + 1 × 2.50 = 27.50.
    private function consistentOrder(): Order
    {
        return $this->order(pizzaLineTotalCents: 2500);
    }

    // Mismo pedido con el line_total de la pizza mal (24.00): falla line_total y subtotal.
    private function inconsistentOrder(): Order
    {
        return $this->order(pizzaLineTotalCents: 2400);
    }

    private function order(int $pizzaLineTotalCents): Order
    {
        return new Order(
            orderId: 'SU-4471',
            source: 'pos1',
            items: [
                new OrderItem('Pizza Margherita', 2, 1250, $pizzaLineTotalCents),
                new OrderItem('Coca-Cola', 1, 250, 250),
            ],
            subtotalCents: 2750,
            extraCharges: [],
            tipCents: null,
            totalCents: 2750,
            currency: 'EUR',
            timestamp: null,
            rawAnomalies: [],
        );
    }
}
