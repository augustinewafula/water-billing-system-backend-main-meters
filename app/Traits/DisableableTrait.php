<?php

namespace App\Traits;

use App\Scopes\DisabledUserScope;
use Illuminate\Database\Eloquent\Builder;

trait DisableableTrait
{
    /**
     * Boot the disable functionality for a model.
     *
     * @return void
     */
    public static function bootDisableableTrait()
    {
        static::addGlobalScope(new DisabledUserScope());
    }

    /**
     * Disable the user.
     *
     * @return bool
     */
    public function disable()
    {
        return $this->update(['is_disabled' => true]);
    }

    /**
     * Enable the user.
     *
     * @return bool
     */
    public function enable()
    {
        return $this->update(['is_disabled' => false]);
    }

    /**
     * Scope a query to include disabled users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDisabled($query)
    {
        return $query->withoutGlobalScope(DisabledUserScope::class);
    }
}
