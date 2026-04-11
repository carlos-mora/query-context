<?php

namespace CarlosMora\QueryContext\Tests;

use CarlosMora\QueryContext\Parsers\FieldsParser;
use PHPUnit\Framework\TestCase;

class FieldsParserTest extends TestCase
{
    public function test_null_returns_empty_result(): void
    {
        $result = FieldsParser::parse(null);

        $this->assertNull($result['select']);
        $this->assertSame([], $result['with']);
        $this->assertSame([], $result['withCount']);
    }

    public function test_plain_tokens_go_to_select(): void
    {
        $result = FieldsParser::parse(['id', 'name', 'created_at']);

        $this->assertSame(['id', 'name', 'created_at'], $result['select']);
        $this->assertSame([], $result['with']);
    }

    public function test_colon_notation_all_columns(): void
    {
        $result = FieldsParser::parse(['folder:*']);

        $this->assertNull($result['select']);
        $this->assertSame(['folder'], $result['with']);
    }

    public function test_colon_notation_with_columns(): void
    {
        $result = FieldsParser::parse(['posts:title,content']);

        $this->assertSame(['posts:title,content'], $result['with']);
    }

    public function test_dot_notation_groups_by_relation(): void
    {
        $result = FieldsParser::parse(['users.id', 'users.email']);

        $this->assertSame(['users:id,email'], $result['with']);
    }

    public function test_dot_notation_multiple_relations(): void
    {
        $result = FieldsParser::parse(['users.id', 'posts.title', 'users.email']);

        $this->assertContains('users:id,email', $result['with']);
        $this->assertContains('posts:title', $result['with']);
    }

    public function test_count_notation(): void
    {
        $result = FieldsParser::parse(['count(tags)', 'count(posts)']);

        $this->assertSame(['tags', 'posts'], $result['withCount']);
        $this->assertNull($result['select']);
    }

    public function test_mixed_tokens(): void
    {
        $result = FieldsParser::parse([
            'id', 'name',
            'folder:*',
            'users.id', 'users.email',
            'posts:title,content',
            'count(tags)',
        ]);

        $this->assertSame(['id', 'name'], $result['select']);
        $this->assertContains('folder', $result['with']);
        $this->assertContains('users:id,email', $result['with']);
        $this->assertContains('posts:title,content', $result['with']);
        $this->assertSame(['tags'], $result['withCount']);
    }

    public function test_empty_array_returns_null_select(): void
    {
        $result = FieldsParser::parse([]);

        $this->assertNull($result['select']);
        $this->assertSame([], $result['with']);
        $this->assertSame([], $result['withCount']);
    }

    public function test_count_notation_is_case_insensitive(): void
    {
        $result = FieldsParser::parse(['COUNT(users)']);

        $this->assertSame(['users'], $result['withCount']);
    }
}
