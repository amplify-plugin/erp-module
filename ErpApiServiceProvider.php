<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Commands\ProductSyncCommand;
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

        $this->registerBladeDirectives();
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
            ]);
        }

        Http::macro('factErp', function () {
            $username = config('amplify.erp.configurations.facts-erp.username');
            $password = config('amplify.erp.configurations.facts-erp.password');

            return Http::timeout(10)
                ->withoutVerifying()
                ->contentType('application/json')
                ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36')
                ->acceptJson()
                ->withHeaders([
                    'Consumerkey' => $username,
                    'Password' => $password,
                ]);
        });

        Http::macro('csdErp', function () {
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

    private function registerBladeDirectives()
    {
        $this->app->afterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
            $bladeCompiler->directive('erp', function () {
                return '<?php if(erp()->enabled()): ?>';
            });

            $bladeCompiler->directive('enderp', function () {
                return '<?php endif; ?>';
            });

            $bladeCompiler->directive('selectwarehouse', function () {
                return '<?php if(erp()->allowMultiWarehouse()): ?>';
            });

            $bladeCompiler->directive('endselectwarehouse', function () {
                return '<?php endif; ?>';
            });

            $bladeCompiler->directive('defaultwarehouse', function () {
                return '<?php if(!erp()->allowMultiWarehouse()): ?>';
            });

            $bladeCompiler->directive('enddefaultwarehouse', function () {
                return '<?php endif; ?>';
            });

            $bladeCompiler->directive('multiwarehouse', function () {
                return '<?php if(\ErpApi::allowMultiWarehouse()): ?>';
            });

            $bladeCompiler->directive('endmultiwarehouse', function () {
                return '<?php endif; ?>';
            });
        });
    }
}
