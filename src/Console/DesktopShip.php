<?php

namespace Sosupp\SlimerDesktop\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Sosupp\SlimerDesktop\Services\GithubReleaseService;
use Symfony\Component\Process\Process;

class DesktopShip extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slimer:desktop-ship {--os=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For Production: One command to prep desktop settings, update .envs, get latest release, build and publish your desktop app to update provider';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 NativePHP - Preping for Production");

        if($this->ask('Is nativephp git repo issue as in electron-builder.mjs fixed?')){
            // 1. Pre-build Environment Check
            if (PHP_OS_FAMILY === 'Windows') {
                $this->warn("Checking for locked processes...");

                // Kill any dangling Electron or Node processes that lock the vendor folder
                exec('taskkill /F /IM electron.exe /T 2>NUL');
                exec('taskkill /F /IM node.exe /T 2>NUL');

                // Small sleep to allow Windows to release file handles
                sleep(2);
            }

            // 2. Verify Directory Access
            $electronPath = base_path('vendor/nativephp/desktop/resources/electron/node_modules');
            if (File::exists($electronPath) && !is_writable($electronPath)) {
                $this->error("❌ The Electron node_modules directory is locked or not writable.");
                $this->line("Try running your terminal as Administrator or closing VS Code.");
                return 1;
            }

            // Change some .env variables values
            updateEnv(key: 'APP_ENV', value: 'production');
            updateEnv(key: 'APP_DEBUG', value: 'false');
            updateEnv(key: 'DB_CONNECTION', value: 'sqlite');
            updateEnv(key: 'DB_PORT', value: 3306);
            updateEnv(key: 'DB_DATABASE', value: 'database/database.sqlite');
            updateEnv(key: 'SLIMER_DESKTOP_ENABLED', value: 'true');
            updateEnv(key: 'SLIMER_DESKTOP_SETUP', value: 'false');
            updateEnv(key: 'SLIMER_DESKTOP_TENANT_KEY', value: null);

            $tagVersion = $this->updateEnvs();

            // $newTag = $this->getLatestReleases($tagVersion);
            // $this->createDraftRelease($newTag);

            // run user provided commands
            $this->runUserCommands();


            $forOs = $this->option('os');
            $this->nativePublish($forOs);

            return 0;

        }

        $this->comment('Temporarly fix nativephp repo issue in electron-builder.mjs and run again');
        return 1;
    }

    private function updateEnvs()
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('.env file not found.');
            return 1;
        }

        $env = File::get($envPath);

        $keyPattern = "/^" . preg_quote("NATIVEPHP_APP_VERSION") . "=(.*)$/m";

        preg_match($keyPattern, $env, $matches);

        // dd($matches);
        if (!isset($matches[1])) {
            $this->error('NATIVEPHP_APP_VERSION not found in .env');
            return 1;
        }

        $current = $matches[1];
        $this->line("Current app version: {$current}");

        $nextVersion = $this->ask('Enter new version (without v)');
        $this->line("Next app version: {$nextVersion}");

        if (!$this->confirm("Increment version to {$nextVersion}?")) {
            return 0;
        }

        $env = preg_replace(
            $keyPattern,
            "NATIVEPHP_APP_VERSION={$nextVersion}",
            $env
        );

        File::put($envPath, $env);
        $this->info("✔ Updated .env version");

        // Reload env
        putenv("NATIVEPHP_APP_VERSION={$nextVersion}");

        return $nextVersion;
    }

    private function getLatestReleases($newTag)
    {
        // Get latest git tag
        $tagProcess = new Process(['git', 'describe', '--tags', '--abbrev=0']);
        $tagProcess->run();

        $currentTag = $tagProcess->isSuccessful()
            ? trim($tagProcess->getOutput())
            : 'none';

        $newTag = "v{$newTag}";
        $this->line("Current git tag: {$currentTag}");
        $this->line("New git tag will be: {$newTag}");

        // $newTag = $this->ask('Enter new release tag (e.g. v1.0.0)');

        if (!str_starts_with($newTag, 'v')) {
            $this->error('Tag must start with v (example: v1.0.0)');
            $newTag = $this->ask('Enter new release tag (e.g. v1.0.0)');
        }

        if(empty($newTag)){
            $newTag = $this->ask('Enter new release tag (e.g. v1.0.0)');
        }

        return $newTag;
    }

    private function createDraftRelease($newTag)
    {
        // $newTag = 'v'.$newTag;
        $github = app(GithubReleaseService::class);

        // 1) Ensure draft exists FIRST (no git tag yet)
        $draft = $github->ensureDraft($newTag);

        $this->info("✔ Draft release ready: {$draft['html_url']}");

        // 2) Now create local tag (if missing)
        $check = new Process(['git', 'rev-parse', $newTag]);
        $check->run();

        if (!$check->isSuccessful()) {
            $this->info("Creating and pushing tag {$newTag}");
            (new Process(['git', 'tag', $newTag]))->mustRun();
            (new Process(['git', 'push', 'origin', $newTag]))->mustRun();
            $this->info("✔ Tag {$newTag} pushed");
        } else {
            $this->info("ℹ Tag {$newTag} already exists");
        }


    }

    private function nativePublish($os = 'win')
    {
        // Run NativePHP publish
        $this->info("Building and publishing app...");

        $publish = new Process([
            'php', 'artisan', 'native:publish', '--env=production'
        ], base_path());

        $publish->setTimeout(null);
        $publish->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$publish->isSuccessful()) {
            $this->error("native:publish failed.");
            return 1;
        }

        $this->info("🎉 Release artifacts uploaded to draft GitHub Release!");
        $this->comment(
            'If release and build artefacts are not being available and not sent to github or updater provider, check issue with git repo in electron-builder.mjs from the nativephp package.'
        );
    }


    private function runUserCommands()
    {
        $commands = config('slimerdesktop.commands.ship');

        if(empty($commands)){
            return;
        }

        foreach ($commands as $command) {
            $this->call($command);
        }
    }

}
