<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

// Test de la pantalla principal (GET /).
// Reemplaza al ExampleTest de Laravel: prueba algo propio del proyecto, no del framework.
final class NormalizerPageTest extends TestCase
{
    public function test_home_renders_the_page_where_vue_mounts(): void
    {
        // withoutVite(): @vite no busca Vite corriendo ni el build compilado (public/build).
        // Así el test prueba la vista de Laravel sin depender de Node ni de compilar el front.
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertViewIs('normalizer')                           // la ruta / muestra la vista correcta
            ->assertSee('<div id="app"></div>', escape: false);   // el punto donde se monta Vue
    }

    public function test_home_exposes_the_csrf_token_for_fetch(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        // El JS lee el token de este meta y lo manda en el header X-CSRF-TOKEN.
        // Sin él (o con content vacío), POST /orders/normalize responde 419.
        // [^"]+ exige al menos un carácter: no alcanza con que exista la etiqueta.
        $this->assertMatchesRegularExpression(
            '/<meta name="csrf-token" content="[^"]+">/',
            $response->getContent()
        );
    }
}
