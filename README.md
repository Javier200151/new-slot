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

En MySQL debe existir una base de datos llamada new_slot:

Para levantar la base de datos en local, hay que abrir la aplicación de docker y escribir el comando:
```sql
docker compose up -d
```

Importante asegurarse que docker-compose.yml existe en la carpeta del proyecto.

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

# Importación masiva de datos

La aplicación incluye varios comandos Artisan para importar datos de forma masiva desde archivos JSON o desde carpetas del servidor.

Para ejecutar comandos desde la VM hay que entrar primero al bash del docker:
```
docker exec -it web-php-1 bash
```
---

## 1. Importar metopas

Importa automáticamente todas las imágenes de metopas existentes en el servidor y crea (o actualiza) sus registros en la base de datos.

### Carpetas utilizadas

Imágenes pequeñas:

```
storage/app/public/metopas/
```

Imágenes grandes (opcional):

```
storage/app/public/metopas_large/
```

Si una imagen no dispone de versión `image_large`, la metopa se importará igualmente y el comando mostrará un aviso.

### Ejecutar

```bash
php artisan metopas:import
```

### Funcionamiento

- Crea las metopas que no existan.
- Actualiza las existentes.
- El nombre se genera automáticamente a partir del nombre del archivo.
- Elimina el sufijo `_ribbon`.
- Sustituye `_` por espacios.
- Asocia automáticamente la imagen grande si existe.

Ejemplo:

```
balticfortress_bronce_ribbon.jpg
```

se convertirá en:

```
Balticfortress Bronce
```

---

## 2. Importar usuarios

Permite crear o actualizar usuarios desde un archivo JSON.

### Ruta del archivo

```
storage/app/imports/usuarios.json
```

### Ejecutar

```bash
php artisan users:import storage/app/imports/usuarios.json --password=NewSlot123
```

### Ejemplo de JSON

```json
[
    {
        "nick": "Rylod",
        "email": "rylod@email.com",
        "promo_id": 120,
        "status_id": 3
    },
    {
        "nick": "Setano",
        "email": "setano@email.com",
        "promo_id": 135,
        "status_id": 3
    }
]
```

### Funcionamiento

- Crea los usuarios nuevos.
- Actualiza los existentes.
- Asigna la contraseña indicada al ejecutar el comando.
- Genera automáticamente la imagen de promoción si es necesaria.
- Aplica las reglas automáticas de firma.

---

## 3. Importar metopas de usuarios

Permite asignar todas las metopas de los usuarios mediante un único archivo JSON.

### Ruta del archivo

```
storage/app/imports/metopas_usuarios.json
```

### Ejecutar

```bash
php artisan metopas:users storage/app/imports/metopas_usuarios.json --sync
```

### Ejemplo de JSON

```json
[
    {
        "nombre": "Rylod",
        "metopas": [
            "Gra",
            "Tutor",
            "Alpha",
            "Veterano Bronce"
        ]
    },
    {
        "nombre": "Setano",
        "metopas": [
            "Gra",
            "Cifo",
            "JTAC"
        ]
    }
]
```

### Funcionamiento

- Busca el usuario por su `nick`.
- Busca las metopas por su nombre.
- Sincroniza las metopas del usuario con las indicadas en el JSON.
- Si una metopa no existe, muestra un aviso y continúa con el resto de la importación.


# Restaurar un backup de la base de datos (Docker)

## 1. Comprobar que el backup es válido

Verifica que el fichero no contenga la línea `Enter password:` al principio:

```bash
head -n 5 /opt/backups/new-slot/new_slot_YYYY-MM-DD_HH-MM-SS.sql
```

Debe comenzar con algo similar a:

```sql
-- MySQL dump 10.13
--
-- Host: localhost
```

Si aparece la línea:

```text
Enter password:
```

elimínala ejecutando:

```bash
sed -i '/^Enter password:/d' /opt/backups/new-slot/new_slot_YYYY-MM-DD_HH-MM-SS.sql
```

---

## 2. Copiar el backup al contenedor MySQL

```bash
docker cp /opt/backups/new-slot/new_slot_YYYY-MM-DD_HH-MM-SS.sql new_slot_mysql:/tmp/backup.sql
```

---

## 3. Acceder al contenedor MySQL

```bash
docker exec -it new_slot_mysql bash
```

---

## 4. Restaurar el backup

Ejecutar:

```bash
mysql -u new_slot -p new_slot < /tmp/backup.sql
```

Introducir la contraseña del usuario `new_slot` cuando sea solicitada.

Si se utiliza el usuario `root`:

```bash
mysql -u root -p new_slot < /tmp/backup.sql
```

Introducir la contraseña del usuario `root`.

---

## 5. Salir del contenedor

```bash
exit
```

---

# Alternativa - Restaurar desde cero (opcional)

Si se desea eliminar completamente la base de datos antes de restaurar:

```bash
docker exec -it web-php-1 php artisan migrate:fresh
```

Una vez finalice correctamente, seguir los pasos anteriores para restaurar el backup.

---

# Verificación

Comprobar que las tablas existen:

```bash
docker exec -it new_slot_mysql mysql -u new_slot -p
```

Dentro de MySQL:

```sql
USE new_slot;
SHOW TABLES;
SELECT COUNT(*) FROM users;
```

Salir:

```sql
EXIT;
```

---

# Errores comunes

### ERROR 1064 cerca de "Enter password"

El fichero SQL contiene la línea:

```text
Enter password:
```

Eliminarla con:

```bash
sed -i '/^Enter password:/d' /tmp/backup.sql
```

o directamente sobre el backup original:

```bash
sed -i '/^Enter password:/d' /opt/backups/new-slot/new_slot_YYYY-MM-DD_HH-MM-SS.sql
```

---

### ERROR 1045 Access denied

Verificar:

* Usuario (`new_slot` o `root`).
* Contraseña.
* Que la base de datos `new_slot` exista.

---

### Comprobar las credenciales configuradas en el contenedor

```bash
docker exec -it new_slot_mysql env | grep MYSQL
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
* Firma augenerada con sus metopas
* Comandos de importación masiva de datos
