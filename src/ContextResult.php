<?php

namespace CarlosMora\QueryContext;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ContextResult
{
    /**
     * Execute the query and return a resource collection.
     *
     * If $context->perPage is set, results are paginated; otherwise all rows
     * are returned. The response format (including pagination metadata) is
     * handled automatically by Laravel's ResourceCollection.
     *
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function of(
        Builder $query,
        QueryContext $context,
        string $resourceClass = JsonResource::class
    ): AnonymousResourceCollection {
        $result = $context->perPage !== null
            ? $query->paginate($context->perPage, page: $context->page)
            : $query->get();

        return $resourceClass::collection($result);
    }
}
