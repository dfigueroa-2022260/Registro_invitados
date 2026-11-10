# Registro de invitados · UDEVIPO (Laravel)

Versión en **PHP + Laravel 12** de la app original (Express + Angular). Sin Node ni compilación: las pantallas son
vistas Blade con JavaScript simple, todo servido por Laravel. Pensada para **Laragon**.

## Requisitos
- Laragon con **PHP 8.2 o superior** (Laragon reciente trae 8.3) y **Composer** (incluido en Laragon).
- Extensiones de PHP activas (en Laragon ya vienen): `mbstring`, `gd`, `zip`, `fileinfo`, `xml`, `pdo_sqlite` (o `pdo_mysql`).

## Instalar en Laragon
1. Descomprime la carpeta `registro-invitados` dentro de `C:\laragon\www\`.
2. Abre la terminal de Laragon (Menu → Terminal) dentro de esa carpeta y ejecuta:

       composer install
       php artisan migrate --seed

   (o haz doble clic en `instalar.bat`).
3. Reinicia Laragon (Menu → Apache/Nginx → Reload) y abre **http://registro-invitados.test**.
   Alternativa sin virtual host: doble clic en `iniciar.bat` y abre http://localhost:8000.

El `.env` ya trae un `APP_KEY` generado y usa **SQLite** (`database/database.sqlite`), sin crear nada en MySQL.

### Usar MySQL de Laragon (opcional)
Crea la base `registro_invitados` (HeidiSQL) y en `.env` pon `DB_CONNECTION=mysql` y descomenta `DB_HOST`, `DB_PORT`,
`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (en Laragon: usuario `root`, contraseña vacía). Luego `php artisan migrate --seed`.

## Acceso de administrador
En el login, "entrar como admin". Contraseña en `.env`: `ADMIN_PASSWORD` (por defecto `admin1234`: **cámbiala**).
La sesión dura `SESSION_LIFETIME` minutos (480 = 8 h) y se cierra al cerrar el navegador. Tras 8 intentos fallidos desde una
misma IP hay que esperar 10 minutos.

Antes de exponer la app (túnel, servidor): en `.env` pon `APP_ENV=production` y `APP_DEBUG=false`, y cambia la contraseña.

## Funcionamiento (igual que la app original)
- **Invitado**: escribe nombre + empresa → firma → ve su asiento. Buscador, ojito "quién ya llegó", resaltar mi asiento,
  tamaño del mapa y modo oscuro (se guardan en el navegador). El mapa y el asiento se actualizan solos cada 20 s.
- **Admin** (`/admin`): editar asientos, quitar y volver a poner asientos, cambiar la letra de cada mesa,
  buscar, temas de color y **exportar PDF / Excel** con asistencia.
- Prueba con los invitados de ejemplo que carga `php artisan migrate --seed` (primeros asientos de la mesa central: A-1, A-2…).

## Distribución de la sala (v3)
El mapa reproduce el plano: **4 mesas y 50 asientos** con los números impresos en cada una (definidos en `config/sala.php`).

| Letra | Mesa | Asientos |
|---|---|---|
| A | Central (en U) | 1 a 23 |
| B | Superior | 24 a 33 |
| C | Inferior | 34 a 43 |
| D | Izquierda | 1 a 7 |

- Los asientos se identifican como **A-5**, **B-27**… (la letra se puede cambiar desde el mapa del admin, tocándola).
- Como los números son los de las mesas, **no se renumeran**. En el admin, *Eliminar asiento* quita la silla del mapa (con su firma) y queda un hueco con **+** para volver a ponerla.
- Si cambia el plano, se edita `config/sala.php` y la geometría en `public/js/mapa.js`.
- Al actualizar desde la v2, `php artisan migrate` pasa la sala vieja a esta: los invitados ya cargados se conservan (con firma y suplente) y se reubican **en orden** desde A-1. Revisa dónde quedó cada uno o vuelve a importar tu lista con la columna *Asiento*.

## Novedades (v4)
- **Firmas en la base de datos (Base64)**: ya no se guardan archivos PNG. `php artisan migrate` copia a la tabla `firmas` las firmas
  que estaban como archivo en `storage/app/firmas`; esos archivos viejos no se borran (elimina la carpeta cuando verifiques).
- **Exportar firmas** (botón en el admin): todas las firmas registradas, **en carpeta** (un ZIP con la carpeta `firmas` y un PNG por
  firma, ej. `A-5_ana-lopez.png`) o **en documento PDF** (10 firmas por página con asiento, nombre, empresa y fecha).
- **Mapa**: el número va dentro de la silla; el resaltado y la selección rodean solo la silla; y *Resaltar mi asiento* viene activado
  por defecto (quien ya tenía otra preferencia guardada recibe el nuevo valor una vez).

## Novedades (v2)
- **Importar invitados** (botón *Importar* en el admin): sube un Excel (.xlsx/.xls) o CSV. Columnas: `Asiento` (opcional, ej. A-5),
  `Nombres y apellidos`, `ID`, `Empresa o asociación`, `Categoría`, `Suplente (nombres y apellidos)` e `ID del suplente`.
  Se leen por el nombre del encabezado, en cualquier orden. Hay una **plantilla** descargable en el mismo cuadro.
  Sin asiento = el siguiente libre. No borra a nadie; si el asiento ya tiene otra persona, la fila se omite salvo que marques *Reemplazar*.
  Al terminar se muestra cuántos se agregaron/actualizaron/omitieron y el motivo de cada fila omitida.
  Un Excel exportado desde la app también se puede volver a importar.
- **Suplente**: cada asiento tiene campos *Suplente* e *ID del suplente*. Es de la misma empresa que el titular y ocupa **su mismo asiento**.
  El suplente se registra en el login con su nombre y la empresa, firma y ve el asiento del titular. Si la firma la puso el suplente,
  el estado dice «Asistió (suplente)». La última firma registrada en el asiento es la que cuenta.
- **PDF y Excel con firma**: cada fila lleva la firma guardada, el suplente y quién asistió.
- **Borrado de firmas**: al eliminar un asiento se borra su firma (archivo PNG y registro) y desaparece de las exportaciones.
  Si vacías el nombre de quien firmó (el titular, o el suplente si él firmó), su firma también se borra.

## Actualizar desde la versión anterior
Copia los archivos del paquete `actualizacion-v4.zip` sobre tu carpeta (no toca `.env`, la base de datos ni las firmas) y ejecuta
`php artisan migrate`. No hace falta `composer install`.

## Datos y firmas
- Asientos, invitados y letras de mesa están en la base de datos (tablas `asientos` y `lados`). Datos iniciales:
  `database/data/sala.seed.json`. Para empezar de cero: `php artisan migrate:fresh --seed`.
- **Las firmas se guardan en la base de datos, en Base64** (tabla `firmas`, una por asiento). Ya no se usa ninguna carpeta.
  Las exportaciones leen la firma desde ahí.
- Al eliminar un asiento (o vaciar a quien firmó) su firma se borra de la tabla.

## Qué cambió respecto a la versión Node/Angular
| Antes | Ahora |
|---|---|
| Express + TypeScript | Laravel 12 (rutas en `routes/web.php`, controladores en `app/Http/Controllers`) |
| Angular 18 | Vistas Blade (`resources/views`) + JS en `public/js` + CSS en `public/css` |
| JWT en el navegador | Sesión de Laravel (cookie) + protección CSRF |
| `sala.json` | Base de datos (SQLite o MySQL) |
| pdfkit / exceljs | `setasign/fpdf` / `phpoffice/phpspreadsheet` |

Pequeñas diferencias: la firma se asocia al invitado de la sesión (ya no se envía su id en la petición), y el límite de intentos
de contraseña cuenta solo los fallidos. No se portó `GET /api/sala`, que el frontend original no usaba.

## Problemas frecuentes
- **Error 500**: mira `storage/logs/laravel.log` (con `APP_DEBUG=true` se ve el detalle en pantalla).
- **"could not find driver"**: activa `pdo_sqlite` (o `pdo_mysql`) en Laragon → Menu → PHP → Extensiones.
- **419 / "página expirada"**: recarga la página (la sesión venció).
- **Se ve la lista de archivos al abrir la carpeta**: abre `registro-invitados.test`, no `localhost/registro-invitados`.
