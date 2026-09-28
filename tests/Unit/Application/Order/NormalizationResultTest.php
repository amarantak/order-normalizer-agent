<?php

namespace Tests\Unit\Application\Order;

use App\Application\Order\NormalizationResult;
use App\Application\Order\NormalizationStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use LogicException;
use PHPUnit\Framework\TestCase;

// TestCase de PHPUnit (no el de Laravel): el resultado es código puro, no necesita el framework.
final class NormalizationResultTest extends TestCase
{
    public function test_approved_has_order_and_nothing_pending(): void
    {
        $result = NormalizationResult::approved($this->order(), correctionAttempted: false);

        $this->assertSame(NormalizationStatus::Approved, $result->status);
        $this->assertNotNull($result->order);
        $this->assertFalse($result->correctionAttempted);
        $this->assertSame([], $result->violations);
        $this->assertNull($result->error);
    }

    // Caso B de la prueba real: se intentó corregir y el total sigue sin cerrar.
    public function test_needs_review_keeps_order_and_remaining_violations(): void
    {
        $violations = ['total 30.00 != subtotal 27.50 + extra_charges 0.00 + tip 0.00 (expected 27.50).'];

        $result = NormalizationResult::needsReview($this->order(), $violations, correctionAttempted: true);

        $this->assertSame(NormalizationStatus::NeedsReview, $result->status);
        $this->assertSame($violations, $result->violations);
        $this->assertTrue($result->correctionAttempted);
    }

    // InvalidOrderException: los datos no tienen forma de pedido, así que no hay Order.
    public function test_needs_review_without_order_carries_the_error(): void
    {
        $result = NormalizationResult::needsReview(null, [], false, 'Quantity must be greater than zero.');

        $this->assertNull($result->order);
        $this->assertSame('Quantity must be greater than zero.', $result->error);
    }

    public function test_needs_review_without_reason_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        NormalizationResult::needsReview($this->order(), [], false);
    }

    // Falla de la API durante la corrección: se conserva el pedido anterior y sus violaciones.
    public function test_failed_can_keep_previous_order(): void
    {
        $result = NormalizationResult::failed('Claude API returned HTTP 500.', $this->order(), ['subtotal mismatch'], true);

        $this->assertSame(NormalizationStatus::Failed, $result->status);
        $this->assertNotNull($result->order);
        $this->assertSame(['subtotal mismatch'], $result->violations);
        $this->assertSame('Claude API returned HTTP 500.', $result->error);
    }

    public function test_violations_without_order_are_rejected(): void
    {
        $this->expectException(LogicException::class);

        NormalizationResult::failed('Timeout.', null, ['subtotal mismatch']);
    }

    // Los valores del enum son el contrato con el front: si cambian, el front se rompe.
    public function test_status_values_for_the_frontend(): void
    {
        $this->assertSame('approved', NormalizationStatus::Approved->value);
        $this->assertSame('needs_review', NormalizationStatus::NeedsReview->value);
        $this->assertSame('failed', NormalizationStatus::Failed->value);
    }

    // Pedido del POS 1 (consistente).
    private function order(): Order
    {
        return new Order(
            orderId: 'SU-4471',
            source: 'pos1',
            items: [
                new OrderItem('Pizza Margherita', 2, 1250, 2500),
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
