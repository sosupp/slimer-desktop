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

        $this->call('app:desktop-prep');
        // Change some .env variables values
        updateEnv(key: 'APP_ENV', value: 'production');
        updateEnv(key: 'APP_DEBUG', value: 'false');
        updateEnv(key: 'DB_CONNECTION', value: 'sqlite');
        updateEnv(key: 'DB_PORT', value: 3306);
        updateEnv(key: 'DB_DATABASE', value: 'database/database.sqlite');
        updateEnv(key: 'SLIMER_DESKTOP_ENABLED', value: 'true');
        updateEnv(key: 'SLIMER_DESKTOP_SETUP', value: 'false');
        updateEnv(key: 'SLIMER_DESKTOP_TENANT_KEY', value: null);
        
        $this->updateEnvs();

        // $this->getLatestReleases($tagVersion);
        // $this->createDraftRelease($newTag);

        $forOs = $this->option('os');
        $this->nativePublish($forOs);

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
            "NATIVEPHP_APP_VERSION='{$nextVersion}'",
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

        $publish = new Process(['php', 'artisan', 'native:publish '.$os], base_path());
        $publish->setTimeout(null);
        $publish->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$publish->isSuccessful()) {
            $this->error("native:publish failed.");
            return 1;
        }

        $this->info("🎉 Release artifacts uploaded to draft GitHub Release!");
    }


}
