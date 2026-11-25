<?php

namespace Wsd\InputKeyGuard;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;

class InputKeyGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/input-key-guard.php', 'input-key-guard');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/input-key-guard.php' => $this->app->configPath('input-key-guard.php'),
        ], 'input-key-guard-config');

        if (! $this->app->bound(HttpKernel::class)) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (! method_exists($kernel, 'prependMiddleware') || ! method_exists($kernel, 'hasMiddleware')) {
            return;
        }

        if (! $kernel->hasMiddleware(GuardInputKeyBytes::class)) {
            $kernel->prependMiddleware(GuardInputKeyBytes::class);
        }
    }
}
