# Tinnitus

Sitio web para registrar y reseñar conciertos en vivo. Proyecto de Programación Web, FIME UANL, grupo 238.

## Requisitos

- XAMPP con PHP 8 y MySQL/MariaDB.
- Navegador web actualizado.

## Instalación

1. Copia la carpeta `tinnitus` dentro de `C:\xampp\htdocs`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Crea la base de datos importando, en este orden, `database/schema.sql` y `database/seed.sql` (desde phpMyAdmin o con la consola de MySQL).
4. Si tu MySQL tiene otro usuario o contraseña, cámbialos en `config/config.php`.
5. Abre `http://localhost/tinnitus/` en el navegador.

Si la base ya existía con una versión anterior, aplica los scripts de `database/migraciones/` en orden en lugar de volver a crearla.

## Usuarios de prueba

| Usuario | Rol |
|---|---|
| admin | Administrador |
| melomana | Usuario |
| rockero92 | Usuario |
| indiekid | Usuario |

## Estructura

- `*.php` en la raíz: páginas del sitio y del panel de administración (`admin-*.php`).
- `includes/`: conexión a la base, sesión, reglas de validación, encabezado, pie y reportes.
- `assets/`: estilos, JavaScript e imágenes (pósters y fotos de artistas, recintos y usuarios de prueba).
- `database/`: script de la base, datos de ejemplo y migraciones.
- `lib/dompdf/`: librería Dompdf para exportar reportes a PDF.
- `uploads/`: fotos de perfil que suben los usuarios (no se guarda en git).

## Tecnologías

PHP, MySQL (MariaDB), Bootstrap 5, JavaScript y Dompdf.
