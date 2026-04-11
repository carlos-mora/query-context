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

class Media extends Model
{
    use HasContextFilter;

    protected array $searchColumns   = ['title', 'description'];
    protected array $filterProtected = ['password'];
}

// En el controlador:
use CarlosMora\QueryContext\ContextResult;
use CarlosMora\QueryContext\QueryContext;

public function index(Request $request)
{
    $context = QueryContext::fromRequest($request);
    $query   = Media::query()->applyContext($context);

    return ContextResult::of($query, $context, MediaResource::class);
}
```

---

## Parámetros de la request

```json
{
    "fields":   ["id", "name", "folder:*", "users.id", "users.email", "posts:title,content", "count(tags)"],
    "page":     1,
    "perPage":  25,
    "sortBy":   "name",
    "sortDesc": false,
    "search":   "texto a buscar",
    "filters": {
        "status":     "active",
        "age":        { "ge": 18, "le": 65 },
        "type":       { "in": [1, 2, 3] },
        "deleted_at": { "isNull": true },
        "title":      { "like": ["foo", "bar"] }
    },
    "scopes": [
        { "published": [] },
        { "ownedBy": [42] }
    ]
}
```

---

## Referencia de parámetros

### `fields`

Array de tokens que define qué columnas devolver y qué relaciones cargar.

| Sintaxis | Resultado |
|----------|-----------|
| `"id"` | columna SELECT del modelo principal |
| `"relation:*"` | `with('relation')` — todos los campos |
| `"relation:col1,col2"` | `with('relation:col1,col2')` — columnas específicas |
| `"relation.column"` | notación punto, se agrupa por relación |
| `"count(relation)"` | `withCount('relation')` |

**Ejemplo:**
```json
"fields": ["id", "name", "folder:*", "users.id", "users.email", "posts:title,content", "count(tags)"]
```
Equivale a:
```
SELECT id, name
with(['folder', 'users:id,email', 'posts:title,content'])
withCount(['tags'])
```

> Si se omite `fields` se aplica `SELECT *` sin relaciones.

---

### `page` / `perPage`

| Parámetro | Tipo    | Por defecto | Restricciones   |
|-----------|---------|-------------|-----------------|
| `page`    | integer | `1`         | mínimo: `1`     |
| `perPage` | integer | `null`      | entre `1`–`500` |

Si `perPage` se omite no se aplica paginación y se devuelve el resultado completo.

---

### `sortBy` / `sortDesc`

| Parámetro  | Tipo    | Por defecto |
|------------|---------|-------------|
| `sortBy`   | string  | `null`      |
| `sortDesc` | boolean | `false`     |

---

### `search`

Cadena de texto (máximo 255 caracteres). Se aplica `LIKE %term%` sobre las columnas
declaradas en `$searchColumns` del modelo.

---

### `filters`

Filtrado plano (AND implícito) o lógica AND/OR/NOT anidada.

#### Formato plano

```json
{ "status": "active", "age": { "ge": 18, "le": 65 } }
```
Genera: `WHERE status = 'active' AND age >= 18 AND age <= 65`

#### Operadores de columna

| Clave | Operador SQL | Descripción |
|-------|-------------|-------------|
| escalar | `= value` | igualdad exacta |
| `eq` | `= value` | igualdad explícita |
| `gt` | `>` | mayor que |
| `lt` | `<` | menor que |
| `ge` | `>=` | mayor o igual que |
| `le` | `<=` | menor o igual que |
| `in` | `IN (...)` | valor en lista |
| `nin` | `NOT IN (...)` | valor fuera de lista |
| `isNull` | `IS NULL / NOT NULL` | nulo o no nulo |
| `like` | `LIKE '%v%'` | contiene — acepta array (OR entre términos) |
| `rlike` / `startsWith` | `LIKE 'v%'` | empieza por — acepta array |
| `llike` / `endsWith` | `LIKE '%v'` | termina en — acepta array |

#### Operadores lógicos `and` / `or` / `not`

```json
{
    "or": [
        { "and": [{ "age": { "gt": 25 } }, { "active": { "eq": true } }] },
        { "and": [{ "age": { "gt": 30 } }, { "active": { "eq": false } }] }
    ]
}
```
Genera: `WHERE (age > 25 AND active = 1) OR (age > 30 AND active = 0)`

```json
{ "not": { "status": { "in": ["draft", "deleted"] } } }
```
Genera: `WHERE NOT (status IN ('draft', 'deleted'))`

> `and`, `or` y `not` son claves reservadas; no pueden usarse como nombres de columna.

---

### `scopes`

```json
"scopes": [{ "published": [] }, { "ownedBy": [42] }]
```
Equivale a: `->published()->ownedBy(42)`

---

## Clases

### `QueryContext`

DTO inmutable construido a partir de la request. Lanza `ValidationException` si algún
parámetro no cumple las reglas.

```php
$context = QueryContext::fromRequest($request);
```

### `HasContextFilter`

Trait para modelos Eloquent. Añade el scope `applyContext()`.

```php
class Media extends Model
{
    use HasContextFilter;

    protected array $searchColumns   = ['title', 'description'];
    protected array $filterProtected = ['password'];
}

Media::query()->applyContext($context)->get();
```

### `ContextResult`

Ejecuta la query y devuelve una `AnonymousResourceCollection` con paginación automática.

```php
// Con resource propio:
return ContextResult::of($query, $context, MediaResource::class);

// Sin resource específico:
return ContextResult::of($query, $context);
```

Si `perPage` está definido usa `paginate()` e incluye automáticamente la metadata de
Laravel (`total`, `current_page`, `last_page`, `per_page`, etc.).

### `QueryContextRequest`

FormRequest alternativo a `QueryContext::fromRequest()` para usar directamente como
parámetro del controlador:

```php
public function index(QueryContextRequest $request)
{
    $context = $request->toQueryContext();
    // ...
}
```

---

## Tests

```bash
composer install
vendor/bin/phpunit
```

50 tests, 95 assertions.
