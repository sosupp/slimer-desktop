<?php

namespace Sosupp\SlimerDesktop\Console;

use Illuminate\Console\Command;

class DesktopPrep extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slimer:desktop-prep';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Use this command to prep necessary files such .env when you switch to the desktop branch or building for desktop';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Change some .env variables values
        updateEnv(key: 'SLIMER_DESKTOP_ENABLED', value: 'true');
        updateEnv(key: 'SLIMER_DESKTOP_SETUP', value: 'false');
        updateEnv(key: 'SLIMER_IS_DESKTOP', value: null);
        updateEnv(key: 'SLIMER_DESKTOP_APP_MODE', value: null);
        updateEnv(key: 'SLIMER_DESKTOP_APP_CHANNEL', value: null);
        updateEnv(key: 'SLIMER_DESKTOP_APP_ROLE', value: null);
        updateEnv(key: 'SLIMER_DESKTOP_API_BASE', value: null, override: false);
        updateEnv(key: 'SLIMER_DESKTOP_API_TOKEN', value: null, override: false);
        updateEnv(key: 'SLIMER_JWT_SECRET', value: null, override: false);
        updateEnv(key: 'SLIMER_JWT_ISS', value: null, override: false);

        updateEnv(key: 'SLIMER_DESKTOP_TENANT_KEY', value: null, override: false);

        updateEnv(key: 'NATIVEPHP_APP_VERSION', value: null, override: false);
        updateEnv(key: 'NATIVEPHP_APP_ID', value: null, override: false);
        updateEnv(key: 'NATIVEPHP_DEEPLINK_SCHEME', value: null, override: false);
        updateEnv(key: 'NATIVEPHP_APP_AUTHOR', value: null, override: false);
        updateEnv(key: 'NATIVEPHP_APP_COPYRIGHT', value: null, override: false);
        updateEnv(key: 'NATIVEPHP_APP_DESCRIPTION', value: null, override: false);
        updateEnv(key: 'NATIVEPHP_APP_WEBSITE', value: null, override: false);
        updateEnv(key: 'NATIVEPHP_UPDATER_ENABLED', value: 'true', override: false);
        updateEnv(key: 'NATIVEPHP_UPDATER_PROVIDER', value: 'github', override: false);
        
        updateEnv(key: 'GITHUB_REPO', value: null, override: false);
        updateEnv(key: 'GITHUB_OWNER', value: null, override: false);
        updateEnv(key: 'GITHUB_AUTOUPDATE_TOKEN', value: null, override: false);
        updateEnv(key: 'GITHUB_PRIVATE', value: true, override: false);
        updateEnv(key: 'GITHUB_CHANNEL', value: 'latest');
        updateEnv(key: 'GITHUB_RELEASE_TYPE', value: 'draft');
        updateEnv(key: 'GITHUB_RELEASE_TOKEN', value: null, override: false);

    }
}
