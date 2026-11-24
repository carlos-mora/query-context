<?php

namespace CarlosMora\QueryContext\Tests;

use CarlosMora\QueryContext\Appliers\FilterApplier;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class FilterApplierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule;
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    private function builder(): Builder
    {
        /** @var Model $model */
        $model = new class extends Model
        {
            protected $table = 'items';
        };

        return $model->newQuery();
    }

    private function toSql(Builder $builder): string
    {
        return $builder->toSql();
    }

    public function test_scalar_value_generates_where_equals(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['status' => 'active']);

        $this->assertStringContainsString('"status" = ?', $this->toSql($builder));
    }

    public function test_gt_operator(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['age' => ['gt' => 18]]);

        $this->assertStringContainsString('"age" > ?', $this->toSql($builder));
    }

    public function test_lt_operator(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['age' => ['lt' => 65]]);

        $this->assertStringContainsString('"age" < ?', $this->toSql($builder));
    }

    public function test_ge_operator(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['score' => ['ge' => 10]]);

        $this->assertStringContainsString('"score" >= ?', $this->toSql($builder));
    }

    public function test_le_operator(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['score' => ['le' => 100]]);

        $this->assertStringContainsString('"score" <= ?', $this->toSql($builder));
    }

    public function test_in_operator(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['type' => ['in' => [1, 2, 3]]]);

        $this->assertStringContainsString('"type" in (?, ?, ?)', $this->toSql($builder));
    }

    public function test_nin_operator(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['type' => ['nin' => [1, 2, 3]]]);

        $this->assertStringContainsString('"type" not in (?, ?, ?)', $this->toSql($builder));
    }

    public function test_is_null_true(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['deleted_at' => ['isNull' => true]]);

        $this->assertStringContainsString('"deleted_at" is null', $this->toSql($builder));
    }

    public function test_is_null_false(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['deleted_at' => ['isNull' => false]]);

        $this->assertStringContainsString('"deleted_at" is not null', $this->toSql($builder));
    }

    public function test_like_operator_generates_or_where(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['title' => ['like' => ['foo', 'bar']]]);

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('"title" like ?', $sql);
    }

    public function test_rlike_operator_generates_starts_with(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['name' => ['rlike' => 'foo']]);

        $this->assertStringContainsString('"name" like ?', $this->toSql($builder));
    }

    public function test_starts_with_alias_for_rlike(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['name' => ['startsWith' => 'foo']]);

        $this->assertStringContainsString('"name" like ?', $this->toSql($builder));
    }

    public function test_llike_operator_generates_ends_with(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['name' => ['llike' => 'bar']]);

        $this->assertStringContainsString('"name" like ?', $this->toSql($builder));
    }

    public function test_ends_with_alias_for_llike(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['name' => ['endsWith' => 'bar']]);

        $this->assertStringContainsString('"name" like ?', $this->toSql($builder));
    }

    public function test_protected_column_is_skipped(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['password' => 'secret'], ['password']);

        $this->assertStringNotContainsString('"password"', $this->toSql($builder));
    }

    public function test_unknown_operator_key_is_ignored(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['col' => ['unknown_op' => 'val']]);

        $this->assertStringNotContainsString('where', strtolower($this->toSql($builder)));
    }

    public function test_eq_operator(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['active' => ['eq' => true]]);

        $this->assertStringContainsString('"active" = ?', $this->toSql($builder));
    }

    public function test_and_group_applies_all_conditions(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, [
            'and' => [
                ['age' => ['gt' => 50]],
                ['name' => ['like' => 'John']],
            ],
        ]);

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('"age" > ?', $sql);
        $this->assertStringContainsString('"name" like ?', $sql);
    }

    public function test_or_group_uses_or_where(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, [
            'or' => [
                ['age' => ['gt' => 60]],
                ['name' => ['like' => 'Jane']],
            ],
        ]);

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('"age" > ?', $sql);
        $this->assertStringContainsString('or', strtolower($sql));
    }

    public function test_nested_or_of_ands(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, [
            'or' => [
                ['and' => [['age' => ['gt' => 25]], ['active' => ['eq' => true]]]],
                ['and' => [['age' => ['gt' => 30]], ['active' => ['eq' => false]]]],
            ],
        ]);

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('"age" > ?', $sql);
        $this->assertStringContainsString('"active" = ?', $sql);
        $this->assertStringContainsString('or', strtolower($sql));
    }

    public function test_not_group_negates_condition(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['not' => ['year' => '2027']]);

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertStringContainsString('"year"', $sql);
    }

    public function test_flat_filters_remain_backward_compatible(): void
    {
        $builder = $this->builder();
        FilterApplier::apply($builder, ['age' => ['gt' => 50], 'status' => 'active']);

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('"age" > ?', $sql);
        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertStringNotContainsString('or', strtolower($sql));
    }

    private function builderOn(string $driver): Builder
    {
        // Solo se compila el SQL: no hace falta un servidor real de PostgreSQL o MySQL.
        $capsule = new Capsule;
        $capsule->addConnection(['driver' => $driver, 'database' => 'x', 'host' => '127.0.0.1', 'username' => 'x', 'password' => 'x']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $this->builder();
    }

    public function test_like_is_case_insensitive_on_postgres(): void
    {
        $builder = $this->builderOn('pgsql');
        FilterApplier::apply($builder, ['title' => ['like' => 'Tijera']]);

        $this->assertStringContainsString('"title"::text ilike ?', $this->toSql($builder));
    }

    public function test_like_is_case_insensitive_on_mysql(): void
    {
        $builder = $this->builderOn('mysql');
        FilterApplier::apply($builder, ['title' => ['startsWith' => 'Tij']]);

        // MySQL: LIKE sin BINARY, que con su collation por defecto no distingue mayúsculas.
        $sql = $this->toSql($builder);
        $this->assertStringContainsString('`title` like ?', $sql);
        $this->assertStringNotContainsString('binary', $sql);
    }
}
