<?php

namespace Amplify\ErpApi\Commands\Apprise;

use Amplify\ErpApi\Facades\ErpApi;
use Illuminate\Console\Command;

class TokenRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amplify:erp-apprise-token-refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update apprise-erp oauth2 token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (config('amplify.erp.default', 'default') == 'apprise-erp') {
            ErpApi::refreshToken(true);
        }
        return self::SUCCESS;
    }
}
