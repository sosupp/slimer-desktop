<?php
namespace Sosupp\SlimerDesktop\Traits;

use Sosupp\SlimerDesktop\Models\Tenant\RecordChannel;

trait HasChannel
{
    public function channelRecord()
    {
        return $this->morphOne(RecordChannel::class, 'record');
    }

    public function scopeLocal($query)
    {
        return $query->whereHas('channelRecord', fn ($q) => $q->where('channel', 'local'));
    }

    public function scopeRemote($query)
    {
        return $query->whereHas('channelRecord', fn ($q) => $q->where('channel', 'remote'));
    }

    public function markAsRemote(): void
    {
        $this->channelRecord()->updateOrCreate([], ['channel' => 'remote']);
    }

    public function markAsLocal(): void
    {
        $this->channelRecord()->updateOrCreate([], ['channel' => 'local']);
    }

}
