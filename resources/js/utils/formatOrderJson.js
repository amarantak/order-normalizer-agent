// Imprime el pedido normalizado como JSON con sangría, igual que JSON.stringify(valor, null, 2),
// pero con los montos de dinero en dos decimales (13 → 13.00).
// Es solo presentación: el texto sigue siendo JSON válido (13.00 es un número JSON).

// Campos de dinero del schema normalizado. Si el schema suma uno, se agrega acá.
const MONEY_FIELDS = new Set(['unit_price', 'line_total', 'subtotal', 'amount', 'tip', 'total']);

export function formatOrderJson(value) {
    return format(value, null, 0);
}

// key: el nombre del campo que contiene a value (null dentro de una lista)
function format(value, key, depth) {
    if (typeof value === 'number' && MONEY_FIELDS.has(key)) {
        return value.toFixed(2);
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return '[]';
        }
        const items = value.map((item) => indent(depth + 1) + format(item, null, depth + 1));
        return `[\n${items.join(',\n')}\n${indent(depth)}]`;
    }

    if (value !== null && typeof value === 'object') {
        const entries = Object.entries(value);
        if (entries.length === 0) {
            return '{}';
        }
        const lines = entries.map(
            ([childKey, child]) => `${indent(depth + 1)}${JSON.stringify(childKey)}: ${format(child, childKey, depth + 1)}`,
        );
        return `{\n${lines.join(',\n')}\n${indent(depth)}}`;
    }

    // Textos, null, booleanos y números que no son dinero: igual que JSON.stringify
    return JSON.stringify(value);
}

function indent(depth) {
    return '  '.repeat(depth);
}