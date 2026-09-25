<?php

namespace Tests\Unit\Domain\Order;

use App\Domain\Order\ExtraCharge;
use App\Domain\Order\Order;
use App\Domain\Order\OrderConsistencyValidator;
use App\Domain\Order\OrderItem;
use PHPUnit\Framework\TestCase;

// Extiende el TestCase de PHPUnit (no el de Laravel): el validador no necesita
// el framework, así que no lo levantamos. Si algún día lo necesitara, esto fallaría.
final class OrderConsistencyValidatorTest extends TestCase
{
    // Pedido del POS español (SumUp): 2 × 12.50 + 1 × 2.50 = 27.50, sin propina ni cargos.
    public function test_consistent_order_without_tip_has_no_errors(): void
    {
        $order = $this->makeOrder(
            items: [
                new OrderItem('Pizza Margherita', 2, 1250, 2500),
                new OrderItem('Coca-Cola', 1, 250, 250),
            ],
            subtotalCents: 2750,
            totalCents: 2750,
        );

        $this->assertSame([], (new OrderConsistencyValidator())->validate($order));
    }

    // Pedido del POS italiano: subtotal 28.80 + coperto 4.00 + mancia 5.00 = 37.80.
    public function test_consistent_order_with_tip_and_extra_charge_has_no_errors(): void
    {
        $order = $this->makeOrder(
            items: [
                new OrderItem('Margherita Pizza', 2, 1300, 2600),
                new OrderItem('Coca-Cola', 1, 280, 280),
            ],
            subtotalCents: 2880,
            totalCents: 3780,
            extraCharges: [new ExtraCharge('coperto', 400)],
            tipCents: 500,
        );

        $this->assertSame([], (new OrderConsistencyValidator())->validate($order));
    }

    // Solo la regla 1 rota: la Coca-Cola dice 3.00 pero 1 × 2.50 = 2.50.
    // Subtotal y total se armaron con ese 3.00, así que las otras reglas cierran.
    public function test_wrong_line_total_is_reported(): void
    {
        $order = $this->makeOrder(
            items: [
                new OrderItem('Pizza Margherita', 2, 1250, 2500),
                new OrderItem('Coca-Cola', 1, 250, 300),
            ],
            subtotalCents: 2800,
            totalCents: 2800,
        );

        $this->assertSame(
            ['Item "Coca-Cola": line_total 3.00 != quantity 1 × unit_price 2.50 (expected 2.50).'],
            (new OrderConsistencyValidator())->validate($order),
        );
    }

    // Las 3 reglas rotas a la vez: el validador tiene que devolver TODOS los errores,
    // no cortar en el primero (así el prompt de corrección los recibe juntos).
    public function test_all_broken_rules_are_reported_together(): void
    {
        $order = $this->makeOrder(
            items: [
                new OrderItem('Pizza Margherita', 2, 1250, 2500),
                new OrderItem('Coca-Cola', 1, 250, 300),  // regla 1: debería ser 2.50
            ],
            subtotalCents: 2750,                           // regla 2: los ítems suman 28.00
            totalCents: 3000,                              // regla 3: debería ser 27.50
        );

        $this->assertSame(
            [
                'Item "Coca-Cola": line_total 3.00 != quantity 1 × unit_price 2.50 (expected 2.50).',
                'subtotal 27.50 != sum of items line_total 28.00.',
                'total 30.00 != subtotal 27.50 + extra_charges 0.00 + tip 0.00 (expected 27.50).',
            ],
            (new OrderConsistencyValidator())->validate($order),
        );
    }

    // Arma un Order con valores por defecto para lo que no importa en cada test,
    // así cada test muestra solo los datos que está probando.
    private function makeOrder(
        array $items,
        int $subtotalCents,
        int $totalCents,
        array $extraCharges = [],
        ?int $tipCents = null,
    ): Order {
        return new Order(
            orderId: 'TEST-1',
            source: 'test',
            items: $items,
            subtotalCents: $subtotalCents,
            extraCharges: $extraCharges,
            tipCents: $tipCents,
            totalCents: $totalCents,
            currency: 'EUR',
            timestamp: null,
            rawAnomalies: [],
        );
    }
}
