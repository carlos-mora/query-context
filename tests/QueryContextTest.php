<?php

namespace CarlosMora\QueryContext\Tests;

use CarlosMora\QueryContext\QueryContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\TestCase;

class QueryContextTest extends TestCase
{
    // ── fromRequest defaults ──────────────────────────────────────────────

    public function test_defaults_when_request_is_empty(): void
    {
        $context = QueryContext::fromRequest(new Request());

        $this->assertNull($context->fields);
        $this->assertSame(1, $context->page);
        $this->assertNull($context->perPage);
        $this->assertNull($context->sortBy);
        $this->assertFalse($context->sortDesc);
        $this->assertNull($context->search);
        $this->assertSame([], $context->filters);
        $this->assertSame([], $context->scopes);
        $this->assertSame([], $context->with);
        $this->assertSame([], $context->withCount);
    }

    // ── fields ────────────────────────────────────────────────────────────

    public function test_fields_plain_columns(): void
    {
        $context = QueryContext::fromRequest(new Request(['fields' => ['id', 'name', 'created_at']]));

        $this->assertSame(['id', 'name', 'created_at'], $context->fields);
        $this->assertSame([], $context->with);
        $this->assertSame([], $context->withCount);
    }

    public function test_fields_colon_notation_extracts_with(): void
    {
        $context = QueryContext::fromRequest(new Request(['fields' => ['id', 'folder:*', 'posts:title,content']]));

        $this->assertSame(['id'], $context->fields);
        $this->assertContains('folder', $context->with);
        $this->assertContains('posts:title,content', $context->with);
    }

    public function test_fields_dot_notation_groups_relation_columns(): void
    {
        $context = QueryContext::fromRequest(new Request(['fields' => ['id', 'users.id', 'users.email']]));

        $this->assertSame(['id'], $context->fields);
        $this->assertContains('users:id,email', $context->with);
    }

    public function test_fields_count_notation_extracts_with_count(): void
    {
        $context = QueryContext::fromRequest(new Request(['fields' => ['id', 'count(tags)', 'count(posts)']]));

        $this->assertSame(['id'], $context->fields);
        $this->assertSame(['tags', 'posts'], $context->withCount);
    }

    public function test_fields_null_when_only_relations(): void
    {
        $context = QueryContext::fromRequest(new Request(['fields' => ['folder:*']]));

        $this->assertNull($context->fields);
        $this->assertSame(['folder'], $context->with);
    }

    // ── pagination ────────────────────────────────────────────────────────

    public function test_page_and_per_page_are_cast_to_int(): void
    {
        $context = QueryContext::fromRequest(new Request(['page' => '3', 'perPage' => '25']));

        $this->assertSame(3, $context->page);
        $this->assertSame(25, $context->perPage);
    }

    public function test_validation_fails_for_per_page_above_max(): void
    {
        $this->expectException(ValidationException::class);

        QueryContext::fromRequest(new Request(['perPage' => 501]));
    }

    // ── sort ──────────────────────────────────────────────────────────────

    public function test_sort_desc_is_cast_to_bool(): void
    {
        $context = QueryContext::fromRequest(new Request(['sortBy' => 'name', 'sortDesc' => '1']));

        $this->assertSame('name', $context->sortBy);
        $this->assertTrue($context->sortDesc);
    }

    // ── filters ───────────────────────────────────────────────────────────

    public function test_scalar_filter_is_kept_as_is(): void
    {
        $context = QueryContext::fromRequest(new Request(['filters' => ['status' => 'active']]));

        $this->assertSame(['status' => 'active'], $context->filters);
    }

    public function test_operator_filter_is_kept_as_nested_array(): void
    {
        $context = QueryContext::fromRequest(new Request([
            'filters' => ['age' => ['ge' => 18, 'le' => 65]],
        ]));

        $this->assertSame(['age' => ['ge' => 18, 'le' => 65]], $context->filters);
    }

    // ── validation failures ───────────────────────────────────────────────

    public function test_validation_fails_for_page_below_one(): void
    {
        $this->expectException(ValidationException::class);

        QueryContext::fromRequest(new Request(['page' => 0]));
    }

    public function test_validation_fails_for_search_too_long(): void
    {
        $this->expectException(ValidationException::class);

        QueryContext::fromRequest(new Request(['search' => str_repeat('a', 256)]));
    }
}
