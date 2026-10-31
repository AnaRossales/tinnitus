<?php
require_once __DIR__ . '/includes/auth.php';

$texto = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
if (mb_strlen($texto) < 2) {
    json_response(['ok' => true, 'resultados' => []]);
}
$like = '%' . $texto . '%';
$resultados = [];

foreach (q('SELECT c.id, c.gira, c.fecha, a.nombre AS artista, r.nombre AS recinto
            FROM conciertos c JOIN artistas a ON a.id = c.artista_id JOIN recintos r ON r.id = c.recinto_id
            WHERE a.nombre LIKE ? OR c.gira LIKE ?
            ORDER BY c.fecha DESC LIMIT 4', [$like, $like]) as $c) {
    $resultados[] = ['tipo' => 'Concierto', 'icono' => 'bi-music-note-beamed', 'titulo' => $c['artista'] . ' – ' . $c['gira'],
        'detalle' => $c['recinto'] . ' · ' . date('d/m/Y', strtotime($c['fecha'])), 'url' => url('event-details.php?id=' . $c['id'])];
}
foreach (q('SELECT a.nombre, a.genero, COUNT(c.id) AS n FROM artistas a LEFT JOIN conciertos c ON c.artista_id = a.id
            WHERE a.nombre LIKE ? GROUP BY a.id ORDER BY n DESC LIMIT 3', [$like]) as $a) {
    $resultados[] = ['tipo' => 'Artista', 'icono' => 'bi-person-badge', 'titulo' => $a['nombre'],
        'detalle' => trim(($a['genero'] ?? '') . ' · ' . $a['n'] . ' concierto(s)', ' ·'), 'url' => url('conciertos.php?q=' . urlencode($a['nombre']))];
}
foreach (q('SELECT id, nombre, ciudad, tipo FROM recintos WHERE nombre LIKE ? OR ciudad LIKE ? ORDER BY nombre LIMIT 3', [$like, $like]) as $r) {
    $resultados[] = ['tipo' => 'Recinto', 'icono' => ICONOS_RECINTO[$r['tipo']] ?? 'bi-geo-alt', 'titulo' => $r['nombre'],
        'detalle' => $r['tipo'] . ' · ' . $r['ciudad'], 'url' => url('recinto.php?id=' . $r['id'])];
}

json_response(['ok' => true, 'resultados' => $resultados]);
