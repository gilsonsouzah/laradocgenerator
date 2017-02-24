<?php

namespace LaraDocGenerator\Doc;

use Illuminate\Support\ServiceProvider;
use LaraDocGenerator\Doc\Commands\UpdateDocumentation;
use LaraDocGenerator\Doc\Commands\GenerateDocumentation;

class ApiDocGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views/', 'apidoc');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'apidoc');

        $this->publishes([
            __DIR__.'/../../resources/lang' => $this->absolutePath('lang/vendor/apidoc'),
            __DIR__.'/../../resources/views' => $this->absolutePath('views/vendor/apidoc'),
        ]);
    }

    /**
     * Register the API doc commands.
     *
     * @return void
     */
    public function register()
    {
        $this->registerLaravelSharedBingind('apidoc.generate', function () {
            return new GenerateDocumentation();
        });

        $this->registerLaravelSharedBingind('apidoc.parse', function () {
            return new UpdateDocumentation();
        });

        $this->commands([
            'apidoc.generate',
            'apidoc.parse',
        ]);
    }

    /**
     * Register Laravel Shared Binginds according with Laravel version.
     *
     * @param $abstractName string Abstract binging name
     * @param callable $concreteObject Concrete object value
     */
    private function registerLaravelSharedBingind($abstractName, callable $concreteObject)
    {
        if (version_compare($this->app->version(), '5.4.0', '>=')) {
            $this->app->singleton($abstractName, $concreteObject);
        } else {
            $this->app[$abstractName] = $this->app->share($concreteObject);
        }
    }

    /**
     * Return a fully qualified path to a given file.
     *
     * @param string $path
     *
     * @return string
     */
    public function absolutePath($path = '')
    {
        return app()->basePath().'/resources'.($path ? '/'.$path : $path);
    }
}
