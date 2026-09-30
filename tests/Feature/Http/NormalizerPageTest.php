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
}
