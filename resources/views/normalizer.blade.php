<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Token CSRF: el JS lo lee de acá y lo manda en el header X-CSRF-TOKEN al llamar a POST /orders/normalize --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Order Normalizer Agent</title>

    {{-- Carga el CSS (Tailwind) y el JS (Vue) compilados por Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-100 text-stone-900 antialiased">
    <div class="mx-auto max-w-7xl px-6 py-12">
        {{-- Encabezado estático: no tiene nada reactivo, por eso lo arma Blade y no Vue --}}
        <header class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-4">
                    <h1 class="font-serif text-4xl font-semibold tracking-tight sm:text-5xl">Order Normalizer Agent</h1>
                    <span class="rounded-full border border-dashed border-amber-600 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-amber-700">
                        Work in progress
                    </span>
                </div>
                <p class="mt-4 text-lg text-stone-600">
                    Normalizes orders from any POS into one schema with Claude, checks the math in code,
                    and asks for one correction when it doesn't add up.
                </p>
            </div>

            <ul class="flex flex-wrap gap-2">
                @foreach (['Laravel 12', 'Vue 3', 'Claude API', 'DDD · PHPUnit'] as $tag)
                    <li class="rounded bg-stone-200 px-3 py-1 font-mono text-sm text-stone-700">{{ $tag }}</li>
                @endforeach
            </ul>
        </header>

        {{-- Vue se monta en este div y maneja todo lo interactivo --}}
        <main class="mt-8">
            <div id="app"></div>
        </main>
    </div>
</body>
</html>