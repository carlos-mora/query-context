<?php

namespace CarlosMora\QueryContext\Requests;

use CarlosMora\QueryContext\QueryContext;
use Illuminate\Foundation\Http\FormRequest;

class QueryContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
        ];
    }

    public function toQueryContext(): QueryContext
    {
        return QueryContext::fromRequest($this);
    }
}
