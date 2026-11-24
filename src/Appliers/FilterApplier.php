<?php

namespace CarlosMora\QueryContext\Appliers;

use CarlosMora\QueryContext\Enums\FilterOperator;
use Illuminate\Database\Eloquent\Builder;

class FilterApplier
{
    /**
     * Apply all filters from the QueryContext to the given builder.
     *
     * Supports flat (implicit AND) format and nested AND/OR/NOT logic:
     *
     *   Flat:   ['age' => ['gt' => 25], 'status' => 'active']
     *   Nested: ['or' => [['age' => ['gt' => 25]], ['status' => 'inactive']]]
     *
     * @param  array<string, mixed>  $filters
     * @param  string[]  $protected  Columns to skip
     */
    public static function apply(Builder $builder, array $filters, array $protected = []): void
    {
        self::applyNode($builder, $filters, $protected);
    }

    /**
     * Apply a condition node. Keys can be column names, 'and', 'or', or 'not'.
     * All keys at this level are AND-combined (default Eloquent behaviour).
     *
     * @param  array<string, mixed>  $node
     * @param  string[]  $protected
     */
    private static function applyNode(Builder $builder, array $node, array $protected): void
    {
        foreach ($node as $key => $value) {
            match ($key) {
                'and'   => self::applyAndGroup($builder, (array) $value, $protected),
                'or'    => self::applyOrGroup($builder, (array) $value, $protected),
                'not'   => self::applyNotGroup($builder, (array) $value, $protected),
                default => self::applyColumn($builder, (string) $key, $value, $protected),
            };
        }
    }

    /**
     * AND group: each item applied as a WHERE, grouped in a closure.
     *
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  string[]  $protected
     */
    private static function applyAndGroup(Builder $builder, array $conditions, array $protected): void
    {
        $builder->where(function (Builder $q) use ($conditions, $protected): void {
            foreach ($conditions as $condition) {
                self::applyNode($q, (array) $condition, $protected);
            }
        });
    }

    /**
     * NOT group: negates all conditions inside the closure.
     *
     * @param  array<string, mixed>  $condition
     * @param  string[]  $protected
     */
    private static function applyNotGroup(Builder $builder, array $condition, array $protected): void
    {
        $builder->whereNot(function (Builder $q) use ($condition, $protected): void {
            self::applyNode($q, $condition, $protected);
        });
    }

    /**
     * OR group: first item AND-appended, subsequent items OR-appended, all inside a closure.
     *
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  string[]  $protected
     */
    private static function applyOrGroup(Builder $builder, array $conditions, array $protected): void
    {
        $builder->where(function (Builder $q) use ($conditions, $protected): void {
            foreach ($conditions as $i => $condition) {
                if ($i === 0) {
                    self::applyNode($q, (array) $condition, $protected);
                } else {
                    $q->orWhere(function (Builder $q2) use ($condition, $protected): void {
                        self::applyNode($q2, (array) $condition, $protected);
                    });
                }
            }
        });
    }

    /**
     * Apply a single column condition (scalar equality or operator dict).
     *
     * @param  string[]  $protected
     */
    private static function applyColumn(Builder $builder, string $column, mixed $value, array $protected): void
    {
        if (in_array($column, $protected, true)) {
            return;
        }

        if (is_array($value)) {
            self::applyOperators($builder, $column, $value);
        } else {
            $builder->where($column, $value);
        }
    }

    /**
     * Apply operator-based conditions for a single column.
     *
     * @param  array<string, mixed>  $operators
     */
    private static function applyOperators(Builder $builder, string $column, array $operators): void
    {
        foreach ($operators as $key => $operand) {
            $operator = FilterOperator::tryFrom($key);

            if ($operator === null) {
                continue;
            }

            match ($operator) {
                FilterOperator::Eq => $builder->where($column, $operand),
                FilterOperator::Gt,
                FilterOperator::Lt,
                FilterOperator::Ge,
                FilterOperator::Le     => $builder->where($column, $operator->toSqlOperator(), $operand),
                FilterOperator::In     => $builder->whereIn($column, (array) $operand),
                FilterOperator::Nin    => $builder->whereNotIn($column, (array) $operand),
                FilterOperator::IsNull => $operand
                    ? $builder->whereNull($column)
                    : $builder->whereNotNull($column),
                FilterOperator::Like => $builder->where(function (Builder $q) use ($column, $operand): void {
                    foreach ((array) $operand as $term) {
                        $q->orWhereLike($column, '%'.$term.'%', caseSensitive: false);
                    }
                }),
                FilterOperator::Rlike,
                FilterOperator::StartsWith => $builder->where(function (Builder $q) use ($column, $operand): void {
                    foreach ((array) $operand as $term) {
                        $q->orWhereLike($column, $term.'%', caseSensitive: false);
                    }
                }),
                FilterOperator::Llike,
                FilterOperator::EndsWith => $builder->where(function (Builder $q) use ($column, $operand): void {
                    foreach ((array) $operand as $term) {
                        $q->orWhereLike($column, '%'.$term, caseSensitive: false);
                    }
                }),
            };
        }
    }
}
