<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Valida lo que manda el front ANTES de llegar al controller (responsabilidad única:
// las reglas de entrada viven acá, no mezcladas con la coordinación del controller).
// Si algo no cumple, Laravel responde 422 con los errores y el controller ni se ejecuta.
final class NormalizeOrderRequest extends FormRequest
{
    // POS que el sistema conoce. El source lo decide nuestro código: lista cerrada.
    private const SOURCES = ['pos1', 'pos2', 'pos3'];

    // Demo sin usuarios ni permisos: cualquiera puede llamar (ver protección contra abuso en el doc).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(self::SOURCES)],
            'raw_order' => [
                'required',
                'array',
                // Un pedido es un objeto JSON ({"order_ref": ...}), no una lista ([1, 2]).
                // La regla 'array' de Laravel acepta las dos cosas, por eso este chequeo extra.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && array_is_list($value)) {
                        $fail('The raw order must be a JSON object, not a list.');
                    }
                },
            ],
        ];
    }

    // Accesos tipados a los datos ya validados: el controller no trabaja con strings sueltos.

    /** @return array<string, mixed> */
    public function rawOrder(): array
    {
        return $this->validated('raw_order');
    }

    public function source(): string
    {
        return $this->validated('source');
    }
}
