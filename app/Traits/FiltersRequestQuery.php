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
                    $relationshipName = Str::of($search_filter[0])->singular()->toString();
                    $columnName = $search_filter[1];
                    if (count($search_filter) > 2) {
                        $relationshipName = "$relationshipName.$search_filter[1]";
                        $columnName = $search_filter[2];
                    }
                    $query->whereHas($relationshipName, function ($query) use ($search_filter, $search, $columnName) {
                        $query->where($columnName, 'like', '%' . $search . '%');
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
