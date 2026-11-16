<?php
require_once __DIR__ . '/includes/reportes.php';
require_admin();

$modulo = $_GET['modulo'] ?? '';
$reporte = $_GET['reporte'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$formato = $_GET['formato'] ?? '';

if (!isset(REPORTES[$modulo][$reporte])) {
    json_response(['ok' => false, 'error' => 'Ese reporte no existe.'], 400);
}

$periodo = $desde || $hasta
    ? ($desde ? date('d/m/Y', strtotime($desde)) : 'el inicio') . ' a ' . ($hasta ? date('d/m/Y', strtotime($hasta)) : 'hoy')
    : 'Todo el historial';
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

$titulo = REPORTES[$modulo][$reporte];
$tarjetas = reporte_tarjetas($modulo, $desde, $hasta);
[$columnas, $filas] = reporte_datos($modulo, $reporte, $desde, $hasta);
$archivo = $modulo . '-' . $reporte . '-' . date('Y-m-d');

if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $archivo . '.csv"');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, $columnas);
    foreach ($filas as $fila) {
        fputcsv($salida, $fila);
    }
    fclose($salida);
    exit;
}

if ($formato === 'pdf') {
    require_once __DIR__ . '/lib/dompdf/autoload.inc.php';
    ob_start();
    require __DIR__ . '/includes/reporte-pdf.php';
    $pdf = new Dompdf\Dompdf();
    $pdf->loadHtml(ob_get_clean());
    $pdf->setPaper('letter', count($columnas) > 5 ? 'landscape' : 'portrait');
    $pdf->render();
    $pdf->stream($archivo . '.pdf');
    exit;
}

json_response([
    'ok'       => true,
    'titulo'   => $titulo,
    'tarjetas' => $tarjetas,
    'columnas' => $columnas,
    'filas'    => $filas,
]);
