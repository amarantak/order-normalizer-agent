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
<body class="bg-slate-50">
    {{-- Blade arma la página; Vue se monta en este div y maneja todo lo que está adentro --}}
    <div id="app"></div>
</body>
</html>