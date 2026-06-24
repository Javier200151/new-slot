# Guía de instalación local — NEW SLOT

## 1. Clonar el repositorio

```bash
git clone URL_DEL_REPOSITORIO
cd new-slot
```

## 2. Instalar dependencias PHP

```bash
composer install
```

## 3. Crear archivo `.env`

```bash
copy env.example .env
```

## 4. Generar clave de Laravel

```bash
php artisan key:generate
```

## 5. Configurar base de datos

Asegurarse de tener MySQL arrancado.

En el `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=new_slot
DB_USERNAME=root
DB_PASSWORD=root

SESSION_DRIVER=file
```

## 6. Crear base de datos

En MySQL debe existir una base de datos llamada:

```sql
CREATE DATABASE new_slot;
```

## 7. Ejecutar migraciones para crear las tablas de la base de datos

```bash
php artisan migrate:fresh
```

## 8. Crear datos en la base de datos para el testeo de Filament

Entrar en Tinker:

```bash
php artisan tinker
```

Crear el estado ACTIVO:

```php
DB::table('status')->insert(['name' => 'ACTIVO']);
```

Crear usuario administrador:

```php
\App\Models\User::create([
    'nick' => 'admin',
    'email' => 'planamayorsquadalpha@gmail.com',
    'password' => 'password',
    'status_id' => 1,
]);
```

Salir:

```php
exit
```

## 9. Limpiar caché

```bash
php artisan optimize:clear
```

## 10. Arrancar el servidor local de Laravel

```bash
php artisan serve
```

Abrir:

```text
http://localhost:8000/admin
```

## 11. Acceso al panel

```text
Email: planamayorsquadalpha@gmail.com
Password: password
```

## Estado actual del proyecto

El proyecto ya tiene funcionando:

* Laravel 12
* MySQL
* Migraciones iniciales
* Foreign Keys
* Spatie Permission
* Filament 5
* Panel `/admin`
* Usuario administrador con campo `nick`
