<?php

namespace CarlosMora\QueryContext;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Base collection for QueryContext responses.
 *
 * Removes the `meta.links` pagination array (numbered page URLs) that Laravel
 * includes by default, keeping only the useful metadata: current_page, last_page,
 * per_page, total, from, to.
 *
 * Usage:
 *   class PostCollection extends QueryContextCollection
 *   {
 *       public $collects = PostResource::class;
 *   }
 *
 *   return ContextResult::of($query, $context, PostCollection::class);
 */
abstract class QueryContextCollection extends ResourceCollection
{
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        unset($default['meta']['links']);

        return $default;
    }
}
