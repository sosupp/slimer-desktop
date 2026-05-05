<?php

namespace Sosupp\SlimerDesktop\Console;

use Illuminate\Console\Command;

class DesktopInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slimer:desktop-install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For dev purposes - use this command to setup the necessary files for using the package';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Adding necessary files to use package");

        // move migrations
        $this->line('Pulishing migration files');
        $this->call('vendor:publish', [
            '--tag' => [
                'slimer-desktop-migrations',
            ]
        ]);

        // update .env files: call desktop prep command
        $this->line('Updating .env file');
        $this->call('slimer:desktop-prep');

        

    }


}
 