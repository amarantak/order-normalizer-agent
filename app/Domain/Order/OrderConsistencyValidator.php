<?php

namespace App\Domain\Order;

use LogicException;

// Verifica que un Order ya normalizado sea coherente
// (ej. line_total = quantity × unit_price, que las sumas den el total).
// Clase concreta sin interfaz: son reglas de negocio determinísticas,
// con una sola implementación posible y sin dependencias externas.
final class OrderConsistencyValidator
{
    /**
     * Devuelve la lista de reglas que no se cumplen, en texto.
     * Lista vacía = el pedido es consistente.
     *
     * @return list<string>
     */
    public function validate(Order $order): array
    {
        // Las reglas se implementan en el chat de "análisis + validación".
        // Lanzamos error en vez de devolver [] para que nadie crea por accidente
        // que un pedido pasó la validación cuando en realidad no se validó nada.
        throw new LogicException('OrderConsistencyValidator::validate() is not implemented yet.');
    }
}