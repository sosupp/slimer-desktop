<?php

namespace Sosupp\SlimerDesktop\Console;

use App\Services\Api\GithubReleaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

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

        $this->call('app:desktop-prep');
        updateEnv(key: 'APP_ENV', value: 'local');
        
        $this->call('native:run');

    }


}
