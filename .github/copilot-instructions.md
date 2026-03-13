# ShopTest API - Copilot Instructions

## ⚠️ PEST SKILL REQUIREMENT (MANDATORY)

**BEFORE implementing any backend feature or test, you MUST:**

1. **Consult the Pest v4 skill documentation** if it's available
2. **Review existing tests** in `tests/Feature/` and `tests/Unit/` directories
3. **Follow the AAA pattern** (Arrange/Act/Assert) with explicit comments before writing ANY code
4. **Write tests FIRST** (Test-Driven Development), then write the implementation
5. **Use the requerimientos pattern** for test organization as documented below
6. **Run tests after implementation** to verify everything passes

**This is non-negotiable. Implementation without corresponding tests violates the TDD methodology of this project.**

---

## Descripción General
Backend API para la **prueba técnica de aplicación de tienda en línea** de la empresa **StrappBerry**, desarrollado con **Laravel 12** y **PHP 8.2**. Utiliza **JWT (JSON Web Tokens)** para autenticación y sigue **TDD (Test-Driven Development)** con **Pest** para el desarrollo.

## Stack Tecnológico
- **Framework**: Laravel 12
- **PHP**: 8.2+
- **Autenticación**: JWT (JSON Web Tokens)
- **Testing**: Pest 4 (TDD)
- **Metodología**: Test-Driven Development (TDD)

## Convenciones de Nombres

### Clases y Controladores
- **Controllers**: PascalCase + "Controller" (ej: `AuthController`, `OrderController`, `UserController`)
- **Models**: Singular, PascalCase (ej: `User`, `Order`, `Table`, `Saucer`)
- **Namespaces**: `App\Http\Controllers\Api\` para controladores de API
- **Namespaces de modelos**: `App\Models\`

### Métodos y Variables
- **Métodos públicos**: camelCase (ej: `login`, `logout`, `updateProfile`, `getAllRoles`)
- **Variables**: camelCase (ej: `$userId`, `$orderData`, `$credentials`)
- **Propiedades protegidas**: snake_case (ej: `$fillable`, `$hidden`, `$table`)

### Rutas
- **Prefijos de rutas**: kebab-case (ej: `/api/cash-cuts`, `/api/product-sync`)
- **Parámetros dinámicos**: `{id}` para identificadores
- **Nombres de rutas**: kebab-case (ej: `orders.index`, `cash-cuts.open`)

## Formato de Código (K&R)
- Llaves de apertura en la **misma línea**
- Solo if simples con retornos tempranos, evitar else/elseif

```php
class AuthController extends Controller {
    public function login(Request $request) {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful',
        ]);
    }
}
```

## Estructura de Rutas
- **Autenticación pública**: `Route::prefix('api')` sin middleware
- **Rutas protegidas**: Usan `middleware('auth:sanctum')`
- **Rutas API**: Usan `Route::apiResource()` para operaciones REST
- **Controlador en grupo**: `controller(ControllerName::class)->group()`

Ejemplo:
```php
Route::prefix('api')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('/tables', TableController::class);
    Route::put('/tables/{id}/restore', [TableController::class, 'restore']);
    
    Route::prefix('orders')->controller(OrderController::class)->group(function () {
        Route::get('/', 'index')->name('orders.index');
        Route::get('/{id}', 'show')->name('orders.show');
    });
});
```

## Estructura del Proyecto
```
app/
  ├── Http/Controllers/API/       # Controladores para endpoints API
  ├── Models/                     # Modelos Eloquent
  ├── Services/                   # Lógica de negocio reutilizable
  ├── Events/                     # Eventos de aplicación
  └── Listeners/                  # Escuchadores de eventos
routes/
  └── api.php                     # Rutas principales de API
config/
  ├── database.php
  ├── auth.php
  ├── cache.php
  ├── filesystems.php
  ├── logging.php
  ├── mail.php
  ├── queue.php
  ├── services.php
  └── session.php
```

## Patrones y Prácticas
- **Controllers**: Delegan lógica a Services cuando es compleja. NO permitir métodos helpers en el controlador.
- **Responses**: Usar `response()->json()` para respuestas API, incluir siempre el código de estado HTTP adecuado
  - **Formato**: `{ "message": "Descripción de la acción", "data": {...} }` para éxito
  - Solo `{ "message": "..." }` para errores (respetar validación automática de Form Requests)
  - Estructura de `data` definida por el test, puede contener múltiples campos cuando es necesario
- **Validación**: Usar `$request->validate()` en controladores
- **Try-Catch**: SOLO usar try-catch cuando se usen transacciones de base de datos o manejo especial de errores. Evitar try-catch para validación o control de flujo normal

## Migraciones - Relaciones de Clave Foránea

**OBLIGATORIO:** Todas las foreign keys se registran en la migración especial `3000_07_01_100000_add_foreign_keys.php`, que se ejecuta al final después de que todas las tablas hayan sido creadas. Esto evita problemas de orden de ejecución de migraciones.

**En la migración de creación de tabla:**
```php
$table->unsignedBigInteger('user_id')->nullable();
$table->unsignedBigInteger('category_id')->nullable();
```

**En la migración 3000_... (add_foreign_keys.php):**
```php
Schema::table('products', function (Blueprint $table) {
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
});
```

## Migraciones - Fase de Desarrollo

**Durante desarrollo (sin producto en uso):** Modificar migraciones existentes directamente en lugar de crear migraciones de tipo "add". Ejemplo: Modificar `create_users_table.php` para agregar soft deletes, en lugar de crear `add_soft_deletes_to_users_table.php`.

## Form Requests

**OBLIGATORIO:** Crear Form Request para cada acción de controlador.

**Ubicación:** `app/Http/Requests/Api/{Recurso}/{AccionRecursoRequest}.php`

Donde:
- `{Recurso}` = Nombre del recurso en PascalCase (ej: `User`, `CashCut`, `Employee`)
- `{AccionRecursoRequest}` = Acción + Nombre del recurso (ej: `StoreUserRequest`, `CloseCashCutRequest`)

**Ejemplos concretos:**
- `app/Http/Requests/Api/User/StoreUserRequest.php` (para `POST /api/users`)
- `app/Http/Requests/Api/User/UpdateUserRequest.php` (para `PUT /api/users/{id}`)
- `app/Http/Requests/Api/CashCut/OpenCashCutRequest.php` (para `POST /api/cash-cuts/open`)
- `app/Http/Requests/Api/CashCut/CloseCashCutRequest.php` (para `POST /api/cash-cuts/close`)

## Testing

**OBLIGATORIO:** Crear tests para cada controlador/funcionalidad.

**Ubicación:** `tests/Feature/Api/{NombreTest}.php`

Donde:
- `{NombreTest}` = Nombre del recurso + `ManagementTest` o `Test` (ej: `UserManagementTest`, `CashCutTest`)

**Ejemplos concretos:**
- `tests/Feature/Api/UserManagementTest.php` (para tests de `/api/users`)
- `tests/Feature/Api/CashCutManagementTest.php` (para tests de `/api/cash-cuts`)

**Estructura de Tests - Patrón AAA:**

Cada test debe usar el patrón Arrange/Act/Assert con comentarios explícitos:

```php
test('descripción del comportamiento esperado', function () {
    // Arrange: Preparar datos y contexto necesario para el test
    $data = ['field' => 'value'];
    
    // Act: Ejecutar la acción que se quiere probar
    $response = $this->postJsonAs('admin', '/api/endpoint', $data);
    
    // Assert: Validar que el resultado es el esperado
    $response->assertStatus(201)->assertJsonStructure(['message']);
    $this->assertDatabaseHas('table', ['field' => 'value']);
});
```

**Reglas del patrón AAA:**
- Agregar comentarios `// Arrange`, `// Act`, `// Assert` en cada test
- Arrange: Setup datos, mocks, contexto previo
- Act: Una sola acción (generalmente una llamada HTTP)
- Assert: Validaciones de respuesta y estado de BD

**Lista de requerimientos en cada archivo de test:**

Todo archivo de test DEBE incluir al inicio un bloque de comentario con la lista resumida de requerimientos que cubre el archivo. Esto sirve como documentación viva de la funcionalidad.

```php
/**
 * Lista de requerimientos
 * // Grupo de funcionalidad
 * - El actor puede hacer X
 * - El actor no puede hacer Y cuando Z
 *
 * // Otro grupo
 * - ...
 */
```

- Cada requerimiento es una línea en lenguaje natural, legible por cualquier persona del equipo
- Agrupar por contexto o acción (listar, crear, actualizar, cancelar, etc.)
- Un requerimiento = un `test()` en el archivo
- Mantener la lista sincronizada con los tests existentes

**Ejecución de tests:**
```bash
php artisan test tests/Feature/Api        # Ejecutar todos los tests de Api
php artisan test                          # Ejecutar todos los tests
```

## Guía para Asistente IA
Al generar código para este proyecto:
1. Formato K&R: llaves en misma línea, retornos tempranos, sin else/elseif
2. Nombres: PascalCase (clases), camelCase (métodos), kebab-case (rutas)
3. **PEST REQUIREMENT**: Consult Pest skill and write tests FIRST before implementation
4. Migraciones: 
   - Fase desarrollo → modificar directamente en lugar de crear "add_*"
   - Crear columnas con `unsignedBigInteger()` en la migración de creación
   - Registrar FKs en `3000_07_01_100000_add_foreign_keys.php` (se ejecuta al final)
5. Form Requests: SIEMPRE crear para cada acción: `{Accion}{Recurso}Request` en `app/Http/Requests/Api/{Recurso}/`
6. Tests: Patrón AAA (Arrange/Act/Assert) con comentarios explícitos; ubicar en `tests/Feature/Api/`
7. Rutas: prefijos y grupos con `middleware('auth:sanctum')` en rutas protegidas
8. Responses: siempre `response()->json()` con status code adecuado
9. Validación: usar Form Request o `$request->validate()` en controladores
10. Eloquent ORM para todas las interacciones con BD
11. Try-Catch: SOLO para transacciones BD, no para validación o control de flujo normal
