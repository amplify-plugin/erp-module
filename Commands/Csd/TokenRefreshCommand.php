<?php

namespace Amplify\ErpApi\Commands\Csd;

use Amplify\ErpApi\Facades\ErpApi;
use Illuminate\Console\Command;

class TokenRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amplify:erp-csd-token-refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update csd-erp oauth2 token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (config('amplify.erp.default', 'default') == 'csd-erp') {
            ErpApi::refreshToken(true);
        }
        return self::SUCCESS;
    }
}
