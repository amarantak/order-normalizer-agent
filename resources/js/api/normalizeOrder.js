// Llamada al endpoint POST /orders/normalize.
// El componente no conoce headers, CSRF ni códigos HTTP: recibe un resultado o un error.

const ENDPOINT = '/orders/normalize';

/**
 * Devuelve el cuerpo de la respuesta para 200 (approved / needs_review) y 502 (failed):
 * los tres son resultados válidos del proceso. Cualquier otra cosa lanza un Error.
 */
export async function normalizeOrder(source, rawOrder) {
    // Se lee antes del try: si falta el meta, el error tiene que decir eso, no "sin conexión"
    const token = csrfToken();

    let response;
    try {
        response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // Sin Accept, si falla la validación Laravel responde con un redirect en vez de un 422 en JSON
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ source, raw_order: rawOrder }),
        });
    } catch {
        // fetch solo lanza si no hubo respuesta (servidor apagado, sin red)
        throw new Error('Could not reach the server. Check that it is running and try again.');
    }

    if (response.status === 200 || response.status === 502) {
        try {
            return await response.json();
        } catch {
            // Ej.: un proxy que devuelve un 502 en HTML en vez de nuestro JSON
            throw new Error('The server returned an unreadable response.');
        }
    }

    if (response.status === 419) {
        throw new Error('Your session expired. Reload the page and try again.');
    }
    if (response.status === 422) {
        throw new Error('The request was rejected as invalid input.');
    }
    throw new Error(`Unexpected server error (HTTP ${response.status}).`);
}

function csrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) {
        throw new Error('CSRF token not found in the page.');
    }
    return token;
}
