<?php

namespace App\Providers;

use App\View\Composers\NavigationComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.app', NavigationComposer::class);

        $this->app->booted(function ($app) {
            if ($app->runningInConsole()) {
                $app->make('view')->replaceNamespace('laravel-exceptions-renderer', []);
            }
        });

        $this->bootLazyLoadingGuard();
    }

    /**
     * Cegah N+1: exception hanya saat test, catat peringatan saat local,
     * dan tetap nonaktif di production.
     */
    protected function bootLazyLoadingGuard(): void
    {
        if (app()->runningUnitTests()) {
            Model::preventLazyLoading(true);
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                throw new LazyLoadingViolationException($model, $relation);
            });

            return;
        }

        if (app()->environment('local')) {
            Model::preventLazyLoading(true);
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                Log::warning('Lazy loading terdeteksi', [
                    'model' => $model::class,
                    'relation' => $relation,
                ]);
            });
        }
    }
}
