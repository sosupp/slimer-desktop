<?php

namespace Sosupp\SlimerDesktop\Traits;

use Sosupp\SlimerDesktop\Models\Tenant\SyncableBelongsToMany;

trait HasSyncableRelations
{
    public function belongsToMany($related, $table = null, $foreignPivotKey = null,
        $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null)
    {
        $instance = $this->newRelatedInstance($related);

        $relation = $relation ?: $this->guessBelongsToManyRelation();

        return new SyncableBelongsToMany(
            $instance->newQuery(),
            $this,
            $table ?: $this->joiningTable($related),
            $foreignPivotKey ?: $this->getForeignKey(),
            $relatedPivotKey ?: $instance->getForeignKey(),
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }
}
