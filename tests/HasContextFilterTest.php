<?php

namespace CarlosMora\QueryContext\Tests;

use CarlosMora\QueryContext\Concerns\HasContextFilter;
use CarlosMora\QueryContext\QueryContext;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class HasContextFilterTest extends TestCase
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
        $model = new class extends Model {
            use HasContextFilter;

            protected $table = 'items';
        };

        return $model->newQuery();
    }

    private function toSql(Builder $builder): string
    {
        return $builder->toSql();
    }

    // ── applySort ─────────────────────────────────────────────────────────

    public function test_sort_adds_id_tiebreaker_when_sorting_by_other_column(): void
    {
        $builder = $this->builder();
        $builder->applyContext(new QueryContext(sortBy: 'name'));

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('order by "name" asc, "id" asc', strtolower($sql));
    }

    public function test_sort_desc_adds_id_tiebreaker_with_same_direction(): void
    {
        $builder = $this->builder();
        $builder->applyContext(new QueryContext(sortBy: 'name', sortDesc: true));

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('order by "name" desc, "id" desc', strtolower($sql));
    }

    public function test_sort_by_id_does_not_duplicate_id(): void
    {
        $builder = $this->builder();
        $builder->applyContext(new QueryContext(sortBy: 'id'));

        $sql = $this->toSql($builder);
        $this->assertSame(1, substr_count(strtolower($sql), '"id"'));
    }

    public function test_no_sort_generates_no_order_clause(): void
    {
        $builder = $this->builder();
        $builder->applyContext(new QueryContext());

        $this->assertStringNotContainsString('order by', strtolower($this->toSql($builder)));
    }

    public function test_sort_respects_custom_primary_key(): void
    {
        $model = new class extends Model {
            use HasContextFilter;

            protected $table      = 'items';
            protected $primaryKey = 'uuid';
        };

        $builder = $model->newQuery()->applyContext(new QueryContext(sortBy: 'name'));

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('"uuid"', $sql);
        $this->assertStringNotContainsString('"id"', $sql);
    }

    // ── applySearch ───────────────────────────────────────────────────────

    public function test_search_is_case_insensitive_on_postgres(): void
    {
        // Solo se compila el SQL: no hace falta un servidor PostgreSQL.
        $capsule = new Capsule;
        $capsule->addConnection(['driver' => 'pgsql', 'database' => 'x', 'host' => '127.0.0.1', 'username' => 'x', 'password' => 'x']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $model = new class extends Model {
            use HasContextFilter;

            protected $table = 'items';

            protected array $searchColumns = ['name', 'sku'];
        };

        $builder = $model->newQuery();
        $builder->applyContext(new QueryContext(search: 'tije'));

        $sql = $this->toSql($builder);
        $this->assertStringContainsString('"name"::text ilike ?', $sql);
        $this->assertStringContainsString('"sku"::text ilike ?', $sql);
        $this->assertContains('%tije%', $builder->getBindings());
    }
}
