<?php

namespace CarlosMora\QueryContext\Concerns;

use CarlosMora\QueryContext\Appliers\FilterApplier;
use CarlosMora\QueryContext\QueryContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a generic applyContext() scope to any Eloquent model.
 *
 * Usage:
 *   class Media extends Model
 *   {
 *       use HasContextFilter;
 *
 *       // Optional — columns excluded from filtering (blacklist)
 *       protected array $filterProtected = ['password', 'remember_token'];
 *
 *       // Required to enable search — columns where LIKE is applied
 *       protected array $searchColumns = ['title', 'description'];
 *   }
 *
 *   Media::query()->applyContext($context)->get();
 */
trait HasContextFilter
{
    public function scopeApplyContext(Builder $query, QueryContext $context): Builder
    {
        $this->applySearch($query, $context);
        $this->applyFilters($query, $context);
        $this->applyScopes($query, $context);
        $this->applyWith($query, $context);
        $this->applyWithCount($query, $context);
        $this->applySort($query, $context);
        $this->applyFields($query, $context);

        return $query;
    }

    private function applySearch(Builder $query, QueryContext $context): void
    {
        if ($context->search === null || $context->search === '') {
            return;
        }

        $searchColumns = $this->searchColumns ?? [];

        if (empty($searchColumns)) {
            return;
        }

        $term = $context->search;

        $query->where(function (Builder $q) use ($searchColumns, $term): void {
            foreach ($searchColumns as $column) {
                $q->orWhere($column, 'LIKE', '%'.$term.'%');
            }
        });
    }

    private function applyFilters(Builder $query, QueryContext $context): void
    {
        if (empty($context->filters)) {
            return;
        }

        $protected = $this->filterProtected ?? [];

        FilterApplier::apply($query, $context->filters, $protected);
    }

    private function applyScopes(Builder $query, QueryContext $context): void
    {
        if (empty($context->scopes)) {
            return;
        }

        foreach ($context->scopes as $scopeEntry) {
            if (! is_array($scopeEntry)) {
                continue;
            }

            foreach ($scopeEntry as $name => $params) {
                $scopeMethod = 'scope'.ucfirst((string) $name);

                if (! method_exists($this, $scopeMethod)) {
                    continue;
                }

                $query->$name(...(array) $params);
            }
        }
    }

    private function applyWith(Builder $query, QueryContext $context): void
    {
        if (empty($context->with)) {
            return;
        }

        $query->with($context->with);
    }

    private function applyWithCount(Builder $query, QueryContext $context): void
    {
        if (empty($context->withCount)) {
            return;
        }

        $query->withCount($context->withCount);
    }

    private function applySort(Builder $query, QueryContext $context): void
    {
        if ($context->sortBy === null) {
            return;
        }

        $query->orderBy($context->sortBy, $context->sortDesc ? 'DESC' : 'ASC');
    }

    private function applyFields(Builder $query, QueryContext $context): void
    {
        if (empty($context->fields)) {
            return;
        }

        $query->select($context->fields);
    }
}
