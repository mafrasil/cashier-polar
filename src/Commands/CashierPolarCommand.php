<?php

namespace Mafrasil\CashierPolar\Commands;

use Illuminate\Console\Command;

class CashierPolarCommand extends Command
{
    public $signature = 'cashier-polar:install';
    public $description = 'Install the Cashier Polar package';

    public function handle(): int
    {
        $this->comment('Publishing configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'cashier-polar-config']);

        $this->comment('Publishing migrations...');
        $this->callSilent('vendor:publish', ['--tag' => 'cashier-polar-migrations']);

        $this->info('Cashier Polar was installed successfully.');

        return self::SUCCESS;
    }
}
