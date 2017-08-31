<?php

namespace ElfSundae\BearyChat\Laravel;

use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Indicates if the application is Laravel 4.
     *
     * @var bool
     */
    protected $isLaravel4 = false;

    /**
     * Indicates if the application is Laravel 5.
     *
     * @var bool
     */
    protected $isLaravel5 = false;

    /**
     * Indicates if the application is Lumen.
     *
     * @var bool
     */
    protected $isLumen = false;

    /**
     * Create a new service provider instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function __construct($app)
    {
        parent::__construct($app);

        $appVersion = method_exists($app, 'version') ? $app->version() : $app::VERSION;

        $this->isLaravel4 = (int) $appVersion == 4;
        $this->isLaravel5 = (int) $appVersion == 5;
        $this->isLumen = Str::contains($appVersion, 'Lumen');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isLaravel4) {
            $this->package('elfsundae/laravel-bearychat', 'bearychat', __DIR__);
        } else {
            $this->publishes([
                $this->getConfigFromPath() => $this->getConfigToPath(),
            ], 'laravel-bearychat');
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if (! $this->isLaravel4) {
            $this->mergeConfigFrom($this->getConfigFromPath(), 'bearychat');
        }

        $this->app->singleton('bearychat', function ($app) {
            return (new ClientManager($app))
                ->setDefaultName($this->getConfig('default'))
                ->setClientsDefaults($this->getConfig('clients_defaults'))
                ->setClientsConfig($this->getConfig('clients'));
        });

        $this->app->alias('bearychat', ClientManager::class);

        $this->aliasFacades();
    }

    /**
     * Get the source config path.
     *
     * @return string
     */
    protected function getConfigFromPath()
    {
        return __DIR__.'/config/config.php';
    }

    /**
     * Get the config destination path.
     *
     * @return string
     */
    protected function getConfigToPath()
    {
        return $this->isLumen ? base_path('config/bearychat.php') : config_path('bearychat.php');
    }

    /**
     * Register facade alias.
     *
     * @return void
     */
    protected function aliasFacades()
    {
        if (class_exists('Illuminate\Foundation\AliasLoader')) {
            \Illuminate\Foundation\AliasLoader::getInstance()->alias('BearyChat', Facade::class);
        } else {
            class_alias(Facade::class, 'BearyChat');
        }
    }

    /**
     * Get the bearychat configuration.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    protected function getConfig($key, $default = null)
    {
        $prefix = 'bearychat'.($this->isLaravel4 ? '::' : '.');

        return $this->app['config']->get($prefix.$key, $default);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return ['bearychat'];
    }
}
