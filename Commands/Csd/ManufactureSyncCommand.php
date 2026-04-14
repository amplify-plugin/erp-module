<?php

namespace Amplify\ErpApi\Commands\Csd;

use Amplify\ErpApi\Facades\ErpApi;
use Amplify\System\Backend\Models\Manufacturer;
use Illuminate\Console\Command;

class ManufactureSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amplify:erp-csd-manufacture-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync manufacturer exist in CSD ERP to local DB';

    /**
     * Execute the console command.
     * @throws \ErrorException
     */
    public function handle()
    {
        if (config('amplify.erp.default') != 'csd-erp') {
            throw new \ErrorException('The default ERP configuration is not set to CSD');
        }

        $entries = ErpApi::post('/sxapiicgetproductcategorylist', [
            'companyNumber' => intval(config('amplify.erp.configurations.csd-erp.company_number')),
            'operatorInit' => config('amplify.erp.configurations.csd-erp.operator_init'),
        ]);

        $entries = $entries['tCodeLst']['t-codeLst'] ?? [];

        foreach ($entries as $entry) {

            if ($manufacturer = Manufacturer::where('code', '=', $entry['codeValue'])->first()) {
                $manufacturer->code = $entry['codeValue'];
                $manufacturer->save();
                continue;
            }

            Manufacturer::create([
                'name' => $entry['codeDesc'],
                'code' => $entry['codeValue'],
                'company_id' => intval(config('amplify.erp.configurations.csd-erp.company_number')),
                'image' => asset(config('amplify.frontend.fallback_image_path')),
            ]);
        }

        $this->components->info(count($entries) . ' manufacturers have been synced.');
    }
}
