<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Commands\Csd\ContactSyncCommand;
use Amplify\ErpApi\Commands\Csd\ManufactureSyncCommand;
use Amplify\ErpApi\Commands\Csd\TokenRefreshCommand as CsdTokenRefreshCommand;
use Amplify\ErpApi\Commands\Apprise\TokenRefreshCommand as AppriseTokenRefreshCommand;
use Amplify\ErpApi\Commands\PriceSyncCommand;
use Amplify\ErpApi\Commands\ProductSyncCommand;
use Amplify\ErpApi\Interfaces\ProductSyncNameResolver;
use Amplify\ErpApi\Resolvers\DefaultProductSyncNameResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

class ErpApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/erp.php',
            'amplify.erp'
        );

        $this->app->singleton('ErpApi', function () {
            return new ErpApiService;
        });

        $this->app->bind(ProductSyncNameResolver::class, DefaultProductSyncNameResolver::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {

            $this->commands([
                ProductSyncCommand::class,
                PriceSyncCommand::class,
                ContactSyncCommand::class,
                ManufactureSyncCommand::class,
                CsdTokenRefreshCommand::class,
                AppriseTokenRefreshCommand::class,
            ]);

            $this->registerScheduler();
        }

        Http::macro('appriseErp', function () {

            return Http::timeout(7 * MINUTE)
                ->withoutVerifying()
                ->contentType('application/json')
                ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36')
                ->acceptJson()
                ->withToken(config('amplify.erp.configurations.apprise-erp.access_token'));
        });

        Http::macro('csdErp', function () {

            return Http::timeout(7 * MINUTE)
                ->withoutVerifying()
                ->contentType('application/json')
                ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36')
                ->acceptJson()
                ->withToken(config('amplify.erp.configurations.csd-erp.access_token'));
        });

        Http::macro('appriseErp', function () {

            return Http::timeout(7 * MINUTE)
                ->withoutVerifying()
                ->contentType('application/json')
                ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36')
                ->acceptJson()
                ->withToken(config('amplify.erp.configurations.apprise-erp.access_token'));
        });

        Http::macro('factErp', function () {

            return Http::withoutVerifying()
                ->contentType('application/json')
                ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36')
                ->acceptJson()
                ->baseUrl(config('amplify.erp.configurations.facts-erp.url'))
                ->withHeaders([
                    'Consumerkey' => config('amplify.erp.configurations.facts-erp.username'),
                    'Password' => config('amplify.erp.configurations.facts-erp.password'),
                ]);
        });
    }

    private function registerScheduler()
    {
        $this->app->booted(function () {

            /**
             * @var \Illuminate\Console\Scheduling\Schedule $schedule
             */
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

            if (config('amplify.erp.default') == 'csd-erp') {
                $schedule->command(CsdTokenRefreshCommand::class)
                    ->hourly()
                    ->withoutOverlapping()
                    ->onOneServer();
            }

            if (config('amplify.erp.default') == 'apprise-erp') {
                $schedule->command(AppriseTokenRefreshCommand::class)
                    ->hourly()
                    ->withoutOverlapping()
                    ->onOneServer();
            }

            if (config('amplify.basic.enable_guest_pricing')) {
                $schedule->command(AppriseTokenRefreshCommand::class)
                    ->dailyAt('05:00')
                    ->when(fn () => now()->day % 2 === 1)
                    ->withoutOverlapping()
                    ->onOneServer();
            }
        });
    }
}
