<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';
    $resenaId = (int) ($_POST['resena_id'] ?? 0);
    $mensajeId = (int) ($_POST['mensaje_id'] ?? 0);

    switch ($accion) {
        case 'ocultar':
            q('UPDATE resenas SET oculta = 1 WHERE id = ?', [$resenaId]);
            q("UPDATE reportes SET estado = 'resuelto' WHERE resena_id = ?", [$resenaId]);
            break;
        case 'descartar':
        case 'restaurar':
            q('UPDATE resenas SET oculta = 0 WHERE id = ?', [$resenaId]);
            q("UPDATE reportes SET estado = 'resuelto' WHERE resena_id = ?", [$resenaId]);
            break;
        case 'eliminar':
            q('DELETE FROM resenas WHERE id = ?', [$resenaId]);
            break;
        case 'leido':
            q('UPDATE mensajes_contacto SET leido = 1 WHERE id = ?', [$mensajeId]);
            break;
        default:
            json_response(['ok' => false, 'error' => 'Acción desconocida.'], 400);
    }
    json_response(['ok' => true]);
}

$m = q("SELECT
    (SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario') AS usuarios,
    (SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario' AND creado_en >= NOW() - INTERVAL 30 DAY) AS usuarios_nuevos,
    (SELECT COUNT(*) FROM conciertos) AS conciertos,
    (SELECT COUNT(*) FROM recintos WHERE estado = 'activo') AS recintos,
    (SELECT COUNT(*) FROM resenas) AS resenas,
    (SELECT ROUND(AVG(calificacion), 2) FROM resenas WHERE oculta = 0) AS promedio,
    (SELECT COUNT(DISTINCT resena_id) FROM reportes WHERE estado = 'pendiente') AS pendientes,
    (SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0) AS mensajes,
    (SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente') AS solicitudes")->fetch();

$topConciertos = q(CONCIERTO_SELECT . ' GROUP BY c.id HAVING total_resenas > 0 ORDER BY promedio DESC, total_resenas DESC LIMIT 5')->fetchAll();

$porGenero = q('SELECT a.genero, COUNT(s.id) AS n FROM resenas s
                JOIN conciertos c ON c.id = s.concierto_id JOIN artistas a ON a.id = c.artista_id
                GROUP BY a.genero ORDER BY n DESC')->fetchAll();
$maxGenero = max(array_column($porGenero, 'n') ?: [1]);

$reportadas = q("SELECT s.id, s.texto, s.calificacion, s.oculta, u.username,
                        a.nombre AS artista, c.id AS concierto_id,
                        COUNT(CASE WHEN rp.estado = 'pendiente' THEN 1 END) AS pendientes,
                        GROUP_CONCAT(DISTINCT rp.motivo SEPARATOR ', ') AS motivos
                 FROM resenas s
                 JOIN usuarios u ON u.id = s.usuario_id
                 JOIN conciertos c ON c.id = s.concierto_id
                 JOIN artistas a ON a.id = c.artista_id
                 JOIN reportes rp ON rp.resena_id = s.id
                 GROUP BY s.id
                 HAVING pendientes > 0 OR s.oculta = 1
                 ORDER BY pendientes DESC, s.id DESC")->fetchAll();

$mensajes = q('SELECT * FROM mensajes_contacto ORDER BY leido, creado_en DESC LIMIT 10')->fetchAll();

$pageTitle = 'Admin · Dashboard';
$active = 'admin';
$adminTitle = 'Reportes y métricas';
$adminTab = 'dashboard';
$pageScript = 'dashboard.js';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/admin-nav.php';
?>
<div class="container py-5">
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['bi-people', $m['usuarios'], 'usuarios registrados', '+' . $m['usuarios_nuevos'] . ' en 30 días'],
            ['bi-music-note-list', $m['conciertos'], 'conciertos cargados', ''],
            ['bi-chat-quote', $m['resenas'], 'reseñas publicadas', 'promedio ' . ($m['promedio'] ?? '–') . '/5'],
            ['bi-flag', $m['pendientes'], 'reseñas por moderar', $m['mensajes'] . ' mensajes sin leer · ' . $m['solicitudes'] . ' solicitudes'],
        ] as [$icon, $value, $label, $extra]): ?>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <i class="bi <?= $icon ?>"></i>
                    <div class="value"><?= e((string) $value) ?></div>
                    <div class="label"><?= $label ?></div>
                    <?php if ($extra): ?><div class="small text-secondary mt-1"><?= e($extra) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card-table h-100">
                <div class="p-3 border-bottom"><h2 class="h5 mb-0">Mejor calificados</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Concierto</th><th>Recinto</th><th>Promedio</th><th>Reseñas</th></tr></thead>
                        <tbody>
                        <?php foreach ($topConciertos as $c): ?>
                            <tr>
                                <td><a href="<?= url('event-details.php?id=' . $c['id']) ?>" class="fw-semibold text-decoration-none"><?= e($c['artista']) ?></a><div class="small text-secondary"><?= e($c['gira']) ?></div></td>
                                <td><?= e($c['recinto']) ?></td>
                                <td class="text-nowrap"><?= stars((float) $c['promedio']) ?> <?= $c['promedio'] ?></td>
                                <td><?= $c['total_resenas'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card-table h-100 p-3">
                <h2 class="h5 mb-3">Reseñas por género musical</h2>
                <?php foreach ($porGenero as $g): ?>
                    <div class="dist-row" style="grid-template-columns: 8rem 1fr 2rem">
                        <span class="small text-truncate"><?= e($g['genero']) ?></span>
                        <div class="progress"><div class="progress-bar" style="width: <?= round($g['n'] * 100 / $maxGenero) ?>%"></div></div>
                        <span class="small text-end"><?= $g['n'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card-table mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Moderación de reseñas reportadas</h2>
            <span class="badge text-bg-danger"><?= $m['pendientes'] ?> pendientes</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle" id="tabla-moderacion">
                <thead><tr><th>Usuario</th><th>Concierto</th><th>Reseña</th><th>Motivo(s)</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($reportadas as $r): ?>
                    <tr data-resena="<?= $r['id'] ?>">
                        <td><a href="<?= url('profile.php?u=' . urlencode($r['username'])) ?>">@<?= e($r['username']) ?></a></td>
                        <td><a href="<?= url('event-details.php?id=' . $r['concierto_id']) ?>"><?= e($r['artista']) ?></a></td>
                        <td style="max-width: 320px"><?= stars((float) $r['calificacion']) ?><div class="small"><?= e($r['texto']) ?></div></td>
                        <td class="small"><?= e($r['motivos']) ?></td>
                        <td>
                            <?php if ($r['pendientes'] && $r['oculta']): ?>
                                <span class="badge text-bg-danger">Oculta automáticamente</span><div class="small text-secondary"><?= $r['pendientes'] ?> reportes</div>
                            <?php elseif ($r['pendientes']): ?>
                                <span class="badge text-bg-warning"><?= $r['pendientes'] ?> reporte(s)</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Oculta</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($r['pendientes']): ?>
                                <button class="btn btn-sm btn-outline-success" data-mod="descartar" title="El reporte no procede: la reseña se muestra">Descartar</button>
                                <button class="btn btn-sm btn-outline-warning" data-mod="ocultar" title="La reseña deja de mostrarse"><?= $r['oculta'] ? 'Mantener oculta' : 'Ocultar' ?></button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" data-mod="restaurar">Restaurar</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger btn-icon" data-mod="eliminar" title="Eliminar reseña"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$reportadas): ?><tr><td colspan="6" class="text-center text-secondary py-4"><i class="bi bi-check2-circle"></i> No hay reseñas reportadas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-table">
        <div class="p-3 border-bottom"><h2 class="h5 mb-0">Mensajes de contacto</h2></div>
        <div class="list-group list-group-flush">
            <?php foreach ($mensajes as $msg): ?>
                <div class="list-group-item d-flex justify-content-between gap-3 <?= $msg['leido'] ? 'text-secondary' : '' ?>" data-mensaje="<?= $msg['id'] ?>">
                    <div>
                        <strong><?= e($msg['asunto']) ?></strong> · <?= e($msg['nombre']) ?> &lt;<?= e($msg['email']) ?>&gt;
                        <div class="small"><?= nl2br(e($msg['mensaje'])) ?></div>
                        <div class="small text-secondary"><?= date('d/m/Y H:i', strtotime($msg['creado_en'])) ?></div>
                    </div>
                    <?php if (!$msg['leido']): ?><button class="btn btn-sm btn-indigo align-self-start btn-leido">Marcar leído</button><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$mensajes): ?><div class="list-group-item text-secondary">Sin mensajes.</div><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
