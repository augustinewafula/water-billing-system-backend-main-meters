<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Str;

trait FiltersRequestQuery
{
    public function searchEagerLoadedQuery(Builder $query, string $search, string $search_filter): Builder
    {
        return $query->where(function ($query) use ($search, $search_filter) {
            if(Str::of($search_filter)->contains('.')) {
                $search_filter = explode('.', $search_filter);
                if (!is_numeric($search_filter[0])) {
                    $query->whereHas(Str::of($search_filter[0])->singular()->toString(), function ($query) use ($search_filter, $search) {
                        $query->where($search_filter[1], 'like', '%' . $search . '%');
                    });
                } else {
                    $query->where($search_filter, 'like', '%' . $search . '%');
                }
            } else {
                $query->where($search_filter, 'like', '%' . $search . '%');
            }
        });
    }

}
