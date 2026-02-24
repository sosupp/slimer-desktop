<?php

namespace Sosupp\SlimerDesktop\Listeners\Local;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Native\Desktop\Events\AutoUpdater\UpdateAvailable;
use Native\Desktop\Facades\Notification;

class AppHasUpdate
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UpdateAvailable $event): void
    {
        Notification::title('Bulksaler Updates Available')
        ->message('The new update will be downloaded automatically.')
        ->show();
    }
}
