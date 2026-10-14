<?php

namespace App\Console\Commands;

use App\Services\Wiki\WikiSessionRevoker;
use Illuminate\Console\Command;

class WikiRevokePending extends Command
{
    protected $signature = 'wiki:revoke-pending';
    protected $description = 'Retry pending Wiki session revocations';

    public function handle(WikiSessionRevoker $revoker): int
    {
        $result=$revoker->process();
        $this->info('Processed: '.$result['processed'].'; pending: '.$result['pending']);
        return self::SUCCESS;
    }
}
