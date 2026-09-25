<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cuando alguien pida la interfaz OrderNormalizer, Laravel entrega
        // un ClaudeOrderNormalizer armado con la config real.
        $this->app->bind(
            \App\Domain\Order\OrderNormalizer::class,
            fn($app) =>
            new \App\Infrastructure\Anthropic\ClaudeOrderNormalizer(
                mapper: $app->make(\App\Infrastructure\Anthropic\NormalizedOrderMapper::class),
                apiKey: (string) config('services.anthropic.key'),
                model: config('services.anthropic.model'),
                apiVersion: config('services.anthropic.version'),
            )
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
