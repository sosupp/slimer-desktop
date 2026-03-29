<?php
namespace Sosupp\SlimerDesktop\Traits;

trait HasChannel
{
    public static function bootHasChannel(): void
    {
        static::creating(function ($model) {
            // Check if the model is allowed to use channels
            if ($model->usesChannel()) {
                $model->forceFill([
                    'channel' => config('slimerdesktop.app.channel')
                ]);
            }
        });
    }

    /**
     * Default check: Does this model use channels?
     * Models can override this to return false.
     */
    public function usesChannel(): bool
    {
        return true;
    }

    public function scopeLocal($query)
    {
        return $query->where('channel', 'local');
    }

    public function scopeRemote($query)
    {
        return $query->where('channel', 'remote');
    }


}
