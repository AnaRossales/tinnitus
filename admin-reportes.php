<?php
require_once __DIR__ . '/includes/reportes.php';
require_admin();

$modulo = $_GET['modulo'] ?? '';
$reporte = $_GET['reporte'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';

if (!isset(REPORTES[$modulo][$reporte])) {
    json_response(['ok' => false, 'error' => 'Ese reporte no existe.'], 400);
}

$desde = $desde ?: FECHA_MIN_CONCIERTO;
$hasta = $hasta ?: '2100-12-31';
foreach ([$desde, $hasta] as $fecha) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        json_response(['ok' => false, 'error' => 'Escribe una fecha válida.'], 422);
    }
}
if ($desde > $hasta) {
    json_response(['ok' => false, 'error' => 'La fecha "Desde" no puede ser posterior a "Hasta".'], 422);
}

[$columnas, $filas] = reporte_datos($modulo, $reporte, $desde, $hasta);

json_response([
    'ok'       => true,
    'titulo'   => REPORTES[$modulo][$reporte],
    'tarjetas' => reporte_tarjetas($modulo, $desde, $hasta),
    'columnas' => $columnas,
    'filas'    => $filas,
]);
