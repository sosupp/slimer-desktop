<?php

namespace Sosupp\SlimerDesktop\Console;

use Illuminate\Console\Command;

class DesktopBuild extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slimer:desktop-build {--os=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For Dev: One command to prep desktop settings, update .envs, and build for development and testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 NativePHP - Preping for local development");

        $this->call('slimer:desktop-prep');
        updateEnv(key: 'APP_ENV', value: 'local');

        // run user provided commands
        $commands = config('slimerdesktop.commands.build');

        if(empty($commands)){
            return;
        }

        foreach ($commands as $command) {
            $this->call($command);
        }

        $this->call('native:run');

    }


}
 