<?php

namespace CarlosMora\QueryContext;

use CarlosMora\QueryContext\Parsers\FieldsParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class QueryContext
{
    /**
     * @param  string[]|null  $fields     SELECT columns (null = all)
     * @param  array<string, mixed>  $filters
     * @param  array<array<string, mixed>>  $scopes
     * @param  string[]  $with       Each entry: relation name or 'relation:col1,col2'
     * @param  string[]  $withCount
     */
    public function __construct(
        public readonly ?array  $fields    = null,
        public readonly int     $page      = 1,
        public readonly ?int    $perPage   = null,
        public readonly ?string $sortBy    = null,
        public readonly bool    $sortDesc  = false,
        public readonly ?string $search    = null,
        public readonly array   $filters   = [],
        public readonly array   $scopes    = [],
        public readonly array   $with      = [],
        public readonly array   $withCount = [],
    ) {}

    /**
     * Build a QueryContext from an HTTP request, validating the structure.
     *
     * `fields` is an array where each element may be:
     *   - a plain column name  → SELECT column
     *   - `relation:col1,col2` → eager-load with specific columns
     *   - `relation:*`         → eager-load all columns
     *   - `relation.column`    → dot-notation, grouped by relation
     *   - `count(relation)`    → withCount
     *
     * @throws ValidationException
     */
    public static function fromRequest(Request $request): static
    {
        $data = $request->only([
            'fields', 'page', 'perPage',
            'sortBy', 'sortDesc',
            'search',
            'filters', 'scopes',
        ]);

        $validator = Validator::make($data, [
            'fields'    => ['sometimes', 'nullable', 'array'],
            'fields.*'  => ['string'],
            'page'      => ['sometimes', 'integer', 'min:1'],
            'perPage'   => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'sortBy'    => ['sometimes', 'nullable', 'string'],
            'sortDesc'  => ['sometimes', 'boolean'],
            'search'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'filters'   => ['sometimes', 'array'],
            'filters.*' => ['sometimes'],
            'scopes'    => ['sometimes', 'array'],
            'scopes.*'  => ['sometimes', 'array'],
        ]);

        $validator->validate();

        $validated = $validator->validated();

        $parsed = FieldsParser::parse($validated['fields'] ?? null);

        return new static(
            fields:    $parsed['select'],
            page:      (int) ($validated['page'] ?? 1),
            perPage:   isset($validated['perPage']) ? (int) $validated['perPage'] : null,
            sortBy:    $validated['sortBy'] ?? null,
            sortDesc:  (bool) ($validated['sortDesc'] ?? false),
            search:    $validated['search'] ?? null,
            filters:   $validated['filters'] ?? [],
            scopes:    $validated['scopes'] ?? [],
            with:      $parsed['with'],
            withCount: $parsed['withCount'],
        );
    }
}
