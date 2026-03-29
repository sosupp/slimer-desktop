<?php
namespace Sosupp\SlimerDesktop\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkSyncEvent
{
    use Dispatchable, SerializesModels;

    public array $logs;

    public function __construct(array $logs)
    {
        $this->logs = $logs;
    }
}