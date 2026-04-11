# carlos-mora/query-context

Convención genérica para la interacción front-end con tablas de datos en Laravel. Permite
filtrar, ordenar, paginar y cargar relaciones en endpoints GET a través de una interfaz
agnóstica que se puede consumir desde cualquier cliente.

## Instalación

**Desde GitHub:**
```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/carlos-mora/query-context" }
],
"require": {
    "carlos-mora/query-context": "^1.0"
}
```

**Desarrollo local (path repository):**
```json
"repositories": [
    { "type": "path", "url": "../packages/query-context" }
],
"require": {
    "carlos-mora/query-context": "*"
}
```

---

## Uso rápido

```php
// En el modelo:
use CarlosMora\QueryContext\Concerns\HasContextFilter;

class Post extends Model
{
    use HasContextFilter;

    protected array $searchColumns   = ['title', 'body'];
    protected array $filterProtected = ['password'];
}

// En el controlador:
use CarlosMora\QueryContext\ContextResult;
use CarlosMora\QueryContext\QueryContext;

public function index(Request $request)
{
    $context = QueryContext::fromRequest($request);
    $query   = Post::query()->applyContext($context);

    return ContextResult::of($query, $context, PostResource::class);
}
```

---

## Parámetros de la request

```json
{
    "fields":   ["id", "title", "author:*", "tags.id", "tags.name", "comments:body,created_at", "count(likes)"],
    "page":     1,
    "perPage":  25,
    "sortBy":   "created_at",
    "sortDesc": true,
    "search":   "laravel",
    "filters": {
        "status":     "published",
        "created_at": { "ge": "2024-01-01" },
        "category_id": { "in": [1, 2, 3] }
    },
    "scopes": [
        { "published": [] },
        { "ownedBy": [42] }
    ]
}
```

---

## `fields` — columnas y relaciones

Array de tokens. Cada elemento se clasifica por su sintaxis:

| Sintaxis | Equivalente Eloquent | Descripción |
|----------|----------------------|-------------|
| `"id"` | `select('id')` | columna del modelo principal |
| `"relation:*"` | `with('relation')` | relación completa (todos los campos) |
| `"relation:col1,col2"` | `with('relation:col1,col2')` | relación con columnas específicas |
| `"relation.column"` | `with('relation:column')` | notación punto, se agrupa por relación |
| `"count(relation)"` | `withCount('relation')` | contador de la relación |

Si se omite `fields` se aplica `SELECT *` sin relaciones.

### Ejemplo de request

```json
"fields": [
    "id", "title", "created_at",
    "author:id,name,email",
    "tags.id", "tags.name",
    "comments:body,created_at",
    "count(likes)"
]
```

Equivale a:
```php
Post::query()
    ->select(['id', 'title', 'created_at'])
    ->with(['author:id,name,email', 'tags:id,name', 'comments:body,created_at'])
    ->withCount(['likes'])
```

### Respuesta JSON resultante

Con `perPage` (paginado):

```json
{
    "data": [
        {
            "id": 1,
            "title": "Introducción a Laravel",
            "created_at": "2024-03-15T10:00:00Z",
            "author": {
                "id": 7,
                "name": "Ana García",
                "email": "ana@example.com"
            },
            "tags": [
                { "id": 2, "name": "Laravel" },
                { "id": 5, "name": "PHP" }
            ],
            "comments": [
                { "body": "Muy buen artículo", "created_at": "2024-03-16T08:30:00Z" }
            ],
            "likes_count": 142
        }
    ],
    "links": {
        "first": "https://api.example.com/posts?page=1",
        "last":  "https://api.example.com/posts?page=12",
        "prev":  null,
        "next":  "https://api.example.com/posts?page=2"
    },
    "meta": {
        "current_page": 1,
        "from":         1,
        "last_page":    12,
        "per_page":     25,
        "to":           25,
        "total":        287
    }
}
```

Sin `perPage` (listado completo):

```json
{
    "data": [
        { "id": 1, "title": "...", "author": { ... }, "tags": [...], "likes_count": 142 },
        { "id": 2, "title": "...", "author": { ... }, "tags": [...], "likes_count": 37 }
    ]
}
```

> `likes_count` aparece como atributo en cada ítem gracias a `withCount`. Laravel lo nombra
> automáticamente como `{relation}_count`.

---

## `filters` — filtrado de datos

### Formato plano — AND implícito

Todas las condiciones al mismo nivel se combinan con AND:

```json
{
    "status":      "published",
    "category_id": { "in": [1, 2] },
    "created_at":  { "ge": "2024-01-01", "le": "2024-12-31" },
    "deleted_at":  { "isNull": true }
}
```
Genera:
```sql
WHERE status = 'published'
  AND category_id IN (1, 2)
  AND created_at >= '2024-01-01'
  AND created_at <= '2024-12-31'
  AND deleted_at IS NULL
```

### Operadores de columna

| Clave | SQL generado | Notas |
|-------|-------------|-------|
| escalar (`"active"`) | `= 'active'` | igualdad directa |
| `eq` | `= value` | igualdad explícita, útil dentro de grupos |
| `gt` | `> value` | mayor que |
| `lt` | `< value` | menor que |
| `ge` | `>= value` | mayor o igual que |
| `le` | `<= value` | menor o igual que |
| `in` | `IN (...)` | acepta array |
| `nin` | `NOT IN (...)` | acepta array |
| `isNull: true` | `IS NULL` | — |
| `isNull: false` | `IS NOT NULL` | — |
| `like` | `LIKE '%v%'` | contiene; array → OR entre términos |
| `rlike` / `startsWith` | `LIKE 'v%'` | empieza por; acepta array |
| `llike` / `endsWith` | `LIKE '%v'` | termina en; acepta array |

### `like` con múltiples términos

```json
{ "title": { "like": ["laravel", "eloquent"] } }
```
Genera: `WHERE (title LIKE '%laravel%' OR title LIKE '%eloquent%')`

### Operadores lógicos `and` / `or` / `not`

Los operadores lógicos permiten construir condiciones compuestas de cualquier profundidad.
Son anidables entre sí.

#### `or` — al menos una condición se cumple

```json
{
    "or": [
        { "status": "featured" },
        { "likes_count": { "gt": 1000 } }
    ]
}
```
Genera: `WHERE (status = 'featured' OR likes_count > 1000)`

#### `and` — todas las condiciones se cumplen (útil dentro de un `or`)

```json
{
    "or": [
        { "and": [{ "status": "published" }, { "category_id": { "in": [1, 2] } }] },
        { "and": [{ "status": "featured"  }, { "category_id": { "in": [3, 4] } }] }
    ]
}
```
Genera:
```sql
WHERE (status = 'published' AND category_id IN (1, 2))
   OR (status = 'featured'  AND category_id IN (3, 4))
```

#### `not` — niega el grupo de condiciones

```json
{ "not": { "status": { "in": ["draft", "deleted"] } } }
```
Genera: `WHERE NOT (status IN ('draft', 'deleted'))`

```json
{ "not": { "and": [{ "role": "guest" }, { "verified": { "eq": false } }] } }
```
Genera: `WHERE NOT (role = 'guest' AND verified = 0)`

#### Combinación avanzada: `or` de grupos `and` con `not`

```json
{
    "or": [
        {
            "and": [
                { "published_at": { "isNull": false } },
                { "category_id": { "in": [1, 2, 3] } }
            ]
        },
        {
            "and": [
                { "status": "featured" },
                { "not": { "author_id": { "in": [99, 100] } } }
            ]
        }
    ]
}
```
Genera:
```sql
WHERE (published_at IS NOT NULL AND category_id IN (1, 2, 3))
   OR (status = 'featured' AND NOT (author_id IN (99, 100)))
```

#### Mezcla de formato plano y grupos lógicos

El formato plano puede coexistir con grupos en el mismo objeto. Las claves planas se
combinan con AND entre sí y con los grupos:

```json
{
    "status": "active",
    "or": [
        { "role": "admin" },
        { "role": "editor" }
    ]
}
```
Genera: `WHERE status = 'active' AND (role = 'admin' OR role = 'editor')`

> `and`, `or` y `not` son claves reservadas y no pueden usarse como nombres de columna.

---

## `page` / `perPage`

| Parámetro | Tipo    | Por defecto | Restricciones   |
|-----------|---------|-------------|-----------------|
| `page`    | integer | `1`         | mínimo: `1`     |
| `perPage` | integer | `null`      | entre `1`–`500` |

Si `perPage` se omite no se aplica paginación y se devuelve el resultado completo sin
metadata de paginación.

---

## `sortBy` / `sortDesc`

| Parámetro  | Tipo    | Por defecto |
|------------|---------|-------------|
| `sortBy`   | string  | `null`      |
| `sortDesc` | boolean | `false`     |

---

## `search`

Cadena de texto (máximo 255 caracteres). Aplica `WHERE (col1 LIKE %term% OR col2 LIKE %term%)`
sobre las columnas declaradas en `$searchColumns` del modelo.

```php
protected array $searchColumns = ['title', 'body', 'excerpt'];
```

---

## `scopes`

Llama a named scopes de Eloquent con los parámetros indicados:

```json
"scopes": [
    { "published": [] },
    { "ownedBy": [42] },
    { "withMinScore": [7.5] }
]
```
Equivale a: `->published()->ownedBy(42)->withMinScore(7.5)`

---

## Clases

### `QueryContext`

DTO inmutable construido y validado a partir de la request. Lanza `ValidationException`
si algún parámetro no cumple las reglas.

```php
$context = QueryContext::fromRequest($request);
```

### `HasContextFilter`

Trait para modelos Eloquent. Añade el scope `applyContext()`.

```php
class Post extends Model
{
    use HasContextFilter;

    // Columnas donde se aplica la búsqueda con LIKE
    protected array $searchColumns = ['title', 'body'];

    // Columnas que nunca se filtran aunque vengan en el payload
    protected array $filterProtected = ['password', 'remember_token'];
}

Post::query()->applyContext($context)->get();
```

### `ContextResult`

Ejecuta la query y devuelve una `AnonymousResourceCollection` con paginación automática.

```php
// Con resource propio:
return ContextResult::of($query, $context, PostResource::class);

// Sin resource específico (devuelve los atributos del modelo tal cual):
return ContextResult::of($query, $context);
```

Si `perPage` está definido usa `paginate()` y la respuesta incluye automáticamente la
metadata de paginación de Laravel en la clave `meta` y los links en `links`.

### `QueryContextRequest`

FormRequest alternativo para inyectar directamente en el controlador:

```php
use CarlosMora\QueryContext\Requests\QueryContextRequest;

public function index(QueryContextRequest $request)
{
    $context = $request->toQueryContext();
    $query   = Post::query()->applyContext($context);

    return ContextResult::of($query, $context, PostResource::class);
}
```

---

## Tests

```bash
composer install
vendor/bin/phpunit
```

50 tests, 95 assertions.
