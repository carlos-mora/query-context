<?php

namespace CarlosMora\QueryContext\Tests;

use CarlosMora\QueryContext\QueryContext;
use CarlosMora\QueryContext\QueryContextCollection;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Orchestra\Testbench\TestCase;

class QueryContextCollectionTest extends TestCase
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

    private function model(): Model
    {
        return new class extends Model {
            protected $table = 'items';
        };
    }

    private function collection(): QueryContextCollection
    {
        return new class (collect()) extends QueryContextCollection {
            public $collects = JsonResource::class;
        };
    }

    public function test_pagination_information_removes_meta_links(): void
    {
        $paginator = $this->model()->newQuery()->paginate(2);

        $collection = $this->collection();

        $default = [
            'meta' => [
                'current_page' => 1,
                'last_page'    => 3,
                'per_page'     => 2,
                'total'        => 5,
                'links'        => [
                    ['url' => null,  'label' => '&laquo; Previous', 'active' => false],
                    ['url' => '?page=1', 'label' => '1', 'active' => true],
                ],
            ],
            'links' => ['first' => '?page=1', 'last' => '?page=3', 'prev' => null, 'next' => '?page=2'],
        ];

        $result = $collection->paginationInformation(new Request(), [], $default);

        $this->assertArrayNotHasKey('links', $result['meta']);
        $this->assertArrayHasKey('current_page', $result['meta']);
        $this->assertArrayHasKey('total', $result['meta']);
        $this->assertArrayHasKey('links', $result); // top-level links preserved
    }
}
