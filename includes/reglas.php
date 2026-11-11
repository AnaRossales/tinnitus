<?php

const USERNAMES_RESERVADOS = ['admin', 'administrador', 'root', 'soporte', 'tinnitus', 'moderador', 'sistema'];
const PASSWORD_MIN = 8;
const PASSWORD_MAX = 72;
const MAX_INTENTOS_LOGIN = 5;
const MINUTOS_BLOQUEO = 15;
const MINUTOS_SESION = 120;

const FECHA_MIN_CONCIERTO = '1950-01-01';
const ANIOS_FUTURO_CONCIERTO = 2;
const MAX_DESTACADOS = 6;
const CAPACIDAD_MAX = 200000;
const TIPOS_RECINTO = ['Estadio', 'Arena', 'Auditorio', 'Foro', 'Festival', 'Bar'];
const ICONOS_RECINTO = [
    'Estadio' => 'bi-trophy', 'Arena' => 'bi-bullseye', 'Auditorio' => 'bi-music-note-beamed',
    'Foro' => 'bi-mic', 'Festival' => 'bi-tree', 'Bar' => 'bi-cup-straw',
];

const MAX_SOLICITUDES_PENDIENTES = 5;
const COMENTARIO_SOLICITUD_MAX = 500;
const MOTIVOS_RECHAZO = ['Ya existe en el catálogo', 'Datos incompletos', 'Información incorrecta', 'Otro'];
const DETALLE_RECHAZO_MIN = 5;
const DETALLE_RECHAZO_MAX = 255;

const RESENA_MIN = 10;
const RESENA_MAX = 1000;
const REPORTES_PARA_OCULTAR = 3;

const BIO_MAX = 500;
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;
const MENSAJE_MIN = 10;
const MENSAJE_MAX = 2000;
const ASUNTOS_CONTACTO = ['Agregar un concierto', 'Problema con mi cuenta', 'Reportar un error', 'Otro'];
const SEGUNDOS_ENTRE_MENSAJES = 60;

function validar_nombre(string $nombre): ?string
{
    if (!preg_match("/^\p{L}[\p{L} '\-]{1,99}$/u", $nombre)) {
        return 'El nombre debe tener de 2 a 100 caracteres y solo letras, espacios, apóstrofos o guiones.';
    }
    return null;
}

function validar_username(string $username): ?string
{
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        return 'El usuario debe tener de 3 a 30 caracteres: minúsculas, números o guion bajo.';
    }
    if (in_array($username, USERNAMES_RESERVADOS, true)) {
        return 'Ese nombre de usuario está reservado. Elige otro.';
    }
    return null;
}

function validar_password(string $password, string $username): ?string
{
    $largo = strlen($password);
    if ($largo < PASSWORD_MIN || $largo > PASSWORD_MAX) {
        return 'La contraseña debe tener de ' . PASSWORD_MIN . ' a ' . PASSWORD_MAX . ' caracteres.';
    }
    if (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
        return 'La contraseña debe incluir al menos una letra y un número.';
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        return 'La contraseña no puede contener tu nombre de usuario.';
    }
    return null;
}

function contiene_lenguaje_ofensivo(string $texto): bool
{
    $normal = strtr(mb_strtolower($texto), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    return (bool) preg_match('/\b(pendej\w*|idiota\w*|estupid\w*|imbecil\w*|put[oa]s?|mierda\w*|cabron\w*|verga\w*|chinga\w*|maric\w*)\b/u', $normal);
}

function validar_resena(int $calificacion, string $texto): ?string
{
    if ($calificacion < 1 || $calificacion > 5) return 'Elige de 1 a 5 estrellas.';
    $largo = mb_strlen($texto);
    if ($largo < RESENA_MIN || $largo > RESENA_MAX) return 'La reseña debe tener entre ' . RESENA_MIN . ' y ' . RESENA_MAX . ' caracteres.';
    if (contiene_lenguaje_ofensivo($texto)) return 'Tu reseña contiene lenguaje ofensivo. Revisa las políticas de la comunidad.';
    return null;
}

function validar_rechazo(string $motivo, string $detalle): ?string
{
    if (!in_array($motivo, MOTIVOS_RECHAZO, true)) return 'Elige un motivo de la lista.';
    $largo = mb_strlen($detalle);
    if ($largo > DETALLE_RECHAZO_MAX) return 'La explicación no puede pasar de ' . DETALLE_RECHAZO_MAX . ' caracteres.';
    if ($motivo === 'Otro' && $largo < DETALLE_RECHAZO_MIN) {
        return 'Si eliges "Otro", explica el motivo en al menos ' . DETALLE_RECHAZO_MIN . ' caracteres; el usuario lo verá.';
    }
    return null;
}

function fecha_max_concierto(): string
{
    return date('Y-m-d', strtotime('+' . ANIOS_FUTURO_CONCIERTO . ' years'));
}

function validar_fecha_concierto(string $fecha): ?string
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) return 'La fecha no es válida.';
    if ($fecha < FECHA_MIN_CONCIERTO || $fecha > fecha_max_concierto()) {
        return 'La fecha debe estar entre el 01/01/1950 y el ' . date('d/m/Y', strtotime(fecha_max_concierto())) . '.';
    }
    return null;
}

function ya_ocurrio(string $fecha): bool
{
    return $fecha <= date('Y-m-d');
}

function validar_url_http(?string $url): ?string
{
    if ($url === null || $url === '') return null;
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return 'La URL del póster debe empezar con http:// o https://';
    }
    return null;
}
