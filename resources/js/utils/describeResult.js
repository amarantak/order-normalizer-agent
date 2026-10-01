// Qué muestra el panel de resultado, armado SOLO con datos de la respuesta del endpoint.
// Los mensajes para personas los arma nuestro código; Claude solo devuelve JSON (nota de diseño del proyecto).

// Las 3 reglas de OrderConsistencyValidator, para mostrar qué se chequea
export const CONSISTENCY_RULES = [
    'line_total = quantity × unit_price',
    'subtotal = Σ line_total',
    'total = subtotal + extra_charges + tip',
];

export function describeResult(result) {
    const { status, correction_attempted: corrected, order, violations, error } = result;

    return {
        ...headline(status, corrected, error),
        // error solo en needs_review: ahí es un mensaje del dominio (ej. monto negativo).
        // En failed puede ser un detalle interno de la API: no se muestra.
        details: status === 'needs_review' ? error : null,
        facts: order ? orderFacts(order) : [],
        checks: checks(status, order, violations),
        anomalies: order ? order.raw_anomalies : null,
    };
}

function headline(status, corrected, error) {
    switch (status) {
        case 'approved':
            return {
                tone: 'approved',
                title: 'Approved',
                message: corrected ? 'Passed after one correction.' : 'Passed on the first pass.',
            };
        case 'needs_review': {
            let cause = 'The normalized order broke a domain rule.';
            if (corrected) {
                cause = error ? 'The corrected order broke a domain rule.' : 'Still inconsistent after one correction.';
            }
            return { tone: 'review', title: 'Needs review', message: `${cause} A person has to review it.` };
        }
        case 'failed':
            return {
                tone: 'failed',
                title: 'Failed',
                message: "The AI service didn't return a usable response. Try again later.",
            };
        default:
            // Un estado que no conocemos es un cambio de contrato: mejor un error visible que inventar
            throw new Error(`Unknown result status: ${status}`);
    }
}

// Resumen del pedido normalizado: hechos, no explicaciones del mapeo
function orderFacts(order) {
    const units = order.items.reduce((sum, item) => sum + item.quantity, 0);
    const money = (amount) => `${amount.toFixed(2)} ${order.currency}`;

    return [
        { label: 'Products', value: `${count(order.items.length, 'line')} · ${count(units, 'unit')}` },
        {
            label: 'Extra charges',
            value: order.extra_charges.length
                ? order.extra_charges.map((charge) => `${charge.label} ${money(charge.amount)}`).join(', ')
                : 'None',
        },
        { label: 'Tip', value: order.tip === null ? 'None' : money(order.tip) },
        { label: 'Total', value: money(order.total) },
    ];
}

// Opción D: el endpoint devuelve violaciones como mensajes, no el resultado de cada regla.
// Las 3 reglas con ✓ solo cuando es cierto (approved); si no, los mensajes reales.
function checks(status, order, violations) {
    if (status === 'approved') {
        return { state: 'passed', note: 'All consistency rules passed.', violations: [] };
    }
    if (order && violations.length > 0) {
        return { state: 'violations', note: 'Still failing after the last check:', violations };
    }
    return {
        state: 'not_checked',
        note: order ? 'Not re-checked: the correction call failed.' : 'Not checked: there is no normalized order.',
        violations: [],
    };
}

function count(n, word) {
    return `${n} ${word}${n === 1 ? '' : 's'}`;
}