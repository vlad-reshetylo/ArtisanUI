<?php

namespace VladReshet\ArtisanUi\Console\Commands;

use Illuminate\Console\Command;
use VladReshet\ArtisanUi\ArtisanUi;
use Illuminate\Contracts\Console\Kernel;

class Ui extends Command
{
    protected $signature = 'ui';

    protected $description = 'Run User Interface for artisan';

    public function handle()
    {
        (new ArtisanUI(
            app()->make(Kernel::class)
        ))->launch();
    }
}
