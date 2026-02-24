<?php
namespace Sosupp\SlimerDesktop\Traits;

use Illuminate\Support\Str;

trait HasUid
{
    public static function bootHasUid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uid)) {
            $model->uid = (string) Str::uuid();
            }
        });
    }
}
