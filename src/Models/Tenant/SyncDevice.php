<?php

namespace Sosupp\SlimerDesktop\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sosupp\SlimerDesktop\Traits\HasUid;

class SyncDevice extends Model
{
    use HasFactory, HasUid;

    protected $fillable = [
        'branch_id', 'uid', 'name', 'platform',
        'last_seen_at', 'is_active',
    ];

    // relationships
    // public function branch()
    // {
    //     return $this->belongsTo(Branch::class);
    // }

    public function cursor()
    {
        return $this->hasOne(DeviceSyncCursor::class);
    }
}