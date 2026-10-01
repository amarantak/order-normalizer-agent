// Estado de las 4 etapas del loop a partir de la respuesta del endpoint.
// El loop corre entero en UNA sola llamada: mientras espera, el front no sabe en qué etapa está,
// así que todas quedan "running" (sin progreso inventado). Con el resultado, cada etapa
// se reconstruye solo con datos que vienen en la respuesta (status, correction_attempted,
// violations, error).
//
// Estados posibles: idle, running, done, failed, skipped, review.

const DESCRIPTIONS = {
    analyze: 'Claude maps the raw JSON to the unified schema (structured outputs).',
    validate: 'Deterministic PHP rules check that the amounts add up.',
    correct: 'At most one retry, sending Claude the rules that failed.',
    result: 'Approved, needs review, or failed.',
};

export function describeLoopSteps(result, loading) {
    if (loading) {
        return pendingSteps('running');
    }
    if (!result) {
        return pendingSteps('idle');
    }

    switch (result.status) {
        case 'approved':
            return approvedSteps(result);
        case 'needs_review':
            return needsReviewSteps(result);
        case 'failed':
            return failedSteps(result);
        default:
            // Un estado que no conocemos es un cambio de contrato: mejor un error visible que inventar
            throw new Error(`Unknown result status: ${result.status}`);
    }
}

function pendingSteps(state) {
    return [
        step('Analyze', state, DESCRIPTIONS.analyze),
        step('Validate', state, DESCRIPTIONS.validate),
        step('Correct', state, DESCRIPTIONS.correct),
        step('Result', state, DESCRIPTIONS.result),
    ];
}

function approvedSteps({ correction_attempted: corrected }) {
    return [
        step('Analyze', 'done', 'Raw JSON mapped to the unified schema.'),
        corrected
            ? step('Validate', 'failed', 'Some rules failed on the first pass.')
            : step('Validate', 'done', 'All consistency rules passed.'),
        corrected
            ? step('Correct', 'done', 'One correction; all rules pass now.')
            : step('Correct', 'skipped', 'Not needed.'),
        step('Approved', 'done', 'Ready to use downstream.'),
    ];
}

function needsReviewSteps({ correction_attempted: corrected, violations, error }) {
    const last = step('Needs review', 'review', "A person has to check it: retrying won't fix the data.");

    // Sin corrección: el normalizador devolvió un pedido que rompe una regla del dominio (no hay order)
    if (!corrected) {
        return [
            step('Analyze', 'failed', 'The normalized order broke a domain rule.'),
            step('Validate', 'skipped', 'Not run.'),
            step('Correct', 'skipped', 'Not run.'),
            last,
        ];
    }

    return [
        step('Analyze', 'done', 'Raw JSON mapped to the unified schema.'),
        step('Validate', 'failed', 'Some rules failed on the first pass.'),
        error
            ? step('Correct', 'failed', 'The corrected order broke a domain rule.')
            : step('Correct', 'failed', `Correction attempted; ${count(violations.length, 'violation')} left.`),
        last,
    ];
}

function failedSteps({ correction_attempted: corrected }) {
    const last = step('Failed', 'failed', 'Integration problem: retrying later should work.');

    if (!corrected) {
        return [
            step('Analyze', 'failed', "Claude didn't return a usable response."),
            step('Validate', 'skipped', 'Not run.'),
            step('Correct', 'skipped', 'Not run.'),
            last,
        ];
    }

    return [
        step('Analyze', 'done', 'Raw JSON mapped to the unified schema.'),
        step('Validate', 'failed', 'Some rules failed on the first pass.'),
        step('Correct', 'failed', "The correction call didn't return a usable response."),
        last,
    ];
}

function step(title, state, detail) {
    return { title, state, detail };
}

function count(n, word) {
    return `${n} ${word}${n === 1 ? '' : 's'}`;
}