<?php

namespace CarlosMora\QueryContext\Tests;

use CarlosMora\QueryContext\ContextResult;
use CarlosMora\QueryContext\QueryContext;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ContextResultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule;
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('items', function ($table): void {
            $table->id();
            $table->string('name');
        });

        for ($i = 1; $i <= 5; $i++) {
            Capsule::table('items')->insert(['name' => "Item {$i}"]);
        }
    }

    protected function tearDown(): void
    {
        Capsule::schema()->dropIfExists('items');
        parent::tearDown();
    }

    private function builder(): Builder
    {
        /** @var Model $model */
        $model = new class extends Model {
            protected $table = 'items';
        };

        return $model->newQuery();
    }

    public function test_returns_anonymous_resource_collection(): void
    {
        $result = ContextResult::of($this->builder(), new QueryContext());

        $this->assertInstanceOf(AnonymousResourceCollection::class, $result);
    }

    public function test_returns_full_collection_without_per_page(): void
    {
        $result = ContextResult::of($this->builder(), new QueryContext());

        $this->assertInstanceOf(Collection::class, $result->resource);
        $this->assertCount(5, $result->resource);
    }

    public function test_paginates_when_per_page_is_set(): void
    {
        $result = ContextResult::of($this->builder(), new QueryContext(perPage: 2, page: 1));

        $this->assertInstanceOf(LengthAwarePaginator::class, $result->resource);
        $this->assertSame(2, $result->resource->perPage());
        $this->assertSame(5, $result->resource->total());
    }

    public function test_respects_page_number(): void
    {
        $result = ContextResult::of($this->builder(), new QueryContext(perPage: 2, page: 2));

        $this->assertInstanceOf(LengthAwarePaginator::class, $result->resource);
        $this->assertSame(2, $result->resource->currentPage());
    }

    public function test_paginator_exposes_full_metadata(): void
    {
        $result    = ContextResult::of($this->builder(), new QueryContext(perPage: 2, page: 1));
        $paginator = $result->resource;

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(5, $paginator->total());
        $this->assertSame(2, $paginator->perPage());
        $this->assertSame(1, $paginator->currentPage());
        $this->assertSame(3, $paginator->lastPage());
    }
}
