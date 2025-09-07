<?php

namespace Amplify\ErpApi;

use Amplify\ErpApi\Commands\ProductSyncCommand;
use Amplify\ErpApi\Facades\ErpApi;
use Illuminate\Foundation\AliasLoader;
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
            __DIR__.'/Config/erp.php',
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
        AliasLoader::getInstance()->alias('ErpApi', ErpApi::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProductSyncCommand::class,
            ]);
        }
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

    public function provides()
    {
        return ['ErpApi', ErpApi::class];
    }
}
