<?php
require_once __DIR__ . '/includes/solicitudes.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($accion === 'aprobar') {
        [$ok, $mensaje] = aprobar_solicitud($id, current_user()['id']);
        json_response(['ok' => $ok, 'mensaje' => $mensaje, 'error' => $ok ? null : $mensaje], $ok ? 200 : 422);
    }
    if ($accion === 'rechazar') {
        $motivo = trim($_POST['motivo'] ?? '');
        if (mb_strlen($motivo) < MOTIVO_RECHAZO_MIN || mb_strlen($motivo) > 255) {
            json_response(['ok' => false, 'error' => 'Escribe un motivo de ' . MOTIVO_RECHAZO_MIN . ' a 255 caracteres; el usuario lo verá.'], 422);
        }
        $n = q("UPDATE solicitudes SET estado = 'rechazada', motivo_rechazo = ?, revisado_por = ?, revisado_en = NOW()
                WHERE id = ? AND estado = 'pendiente'", [$motivo, current_user()['id'], $id])->rowCount();
        if (!$n) json_response(['ok' => false, 'error' => 'La solicitud no existe o ya fue revisada.'], 409);
        json_response(['ok' => true, 'mensaje' => 'Solicitud rechazada.']);
    }
    json_response(['ok' => false, 'error' => 'Acción desconocida.'], 400);
}

$estado = in_array($_GET['estado'] ?? 'pendiente', ['pendiente', 'aprobada', 'rechazada', 'todas'], true) ? ($_GET['estado'] ?? 'pendiente') : 'pendiente';
$tipo = in_array($_GET['tipo'] ?? '', ['concierto', 'recinto'], true) ? $_GET['tipo'] : '';

$cond = [];
$params = [];
if ($estado !== 'todas') { $cond[] = 's.estado = ?'; $params[] = $estado; }
if ($tipo) { $cond[] = 's.tipo = ?'; $params[] = $tipo; }
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

$total = (int) q("SELECT COUNT(*) FROM solicitudes s $where", $params)->fetchColumn();
[$pagina, $paginas, $offset] = paginar($total, 10);
$solicitudes = q("SELECT s.*, u.username, r.nombre AS recinto, rev.username AS revisor
                  FROM solicitudes s
                  JOIN usuarios u ON u.id = s.usuario_id
                  LEFT JOIN recintos r ON r.id = s.recinto_id
                  LEFT JOIN usuarios rev ON rev.id = s.revisado_por
                  $where ORDER BY s.estado = 'pendiente' DESC, s.creado_en DESC
                  LIMIT 10 OFFSET $offset", $params)->fetchAll();
$conteo = q("SELECT estado, COUNT(*) FROM solicitudes GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

function solicitudes_url(array $cambios): string
{
    return url('admin-solicitudes.php?' . http_build_query(array_merge($_GET, ['pagina' => null], $cambios)));
}

$pageTitle = 'Admin · Solicitudes';
$active = 'admin';
$adminTitle = 'Solicitudes';
$adminTab = 'solicitudes';
$pageScript = 'admin-solicitudes.js';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/admin-nav.php';
?>
<div class="container">
    <div class="admin-toolbar">
        <div class="chip-row" aria-label="Filtrar por estado">
            <?php foreach (['pendiente' => 'En revisión', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'todas' => 'Todas'] as $valor => $texto): ?>
                <a class="chip-filter <?= $estado === $valor ? 'active' : '' ?>" href="<?= solicitudes_url(['estado' => $valor]) ?>">
                    <?= $texto ?><?= $valor !== 'todas' ? ' (' . ($conteo[$valor] ?? 0) . ')' : '' ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="extra">
            <select class="form-select" aria-label="Tipo" onchange="location.href = this.value">
                <option value="<?= solicitudes_url(['tipo' => '']) ?>">Conciertos y recintos</option>
                <option value="<?= solicitudes_url(['tipo' => 'concierto']) ?>" <?= $tipo === 'concierto' ? 'selected' : '' ?>>Solo conciertos</option>
                <option value="<?= solicitudes_url(['tipo' => 'recinto']) ?>" <?= $tipo === 'recinto' ? 'selected' : '' ?>>Solo recintos</option>
            </select>
        </div>
    </div>

    <div class="card-table mb-5">
        <div class="table-responsive">
            <table class="table align-middle" id="tabla">
                <thead><tr><th>ID</th><th>Tipo</th><th>Solicitud</th><th>Usuario</th><th>Enviada</th><th>Estado</th><th class="acciones">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr data-id="<?= $s['id'] ?>" data-resumen="<?= e(resumen_solicitud($s)) ?>">
                        <td><?= $s['id'] ?></td>
                        <td class="text-nowrap"><i class="bi <?= $s['tipo'] === 'concierto' ? 'bi-music-note-beamed' : 'bi-building' ?>"></i> <?= ucfirst($s['tipo']) ?></td>
                        <td style="min-width: 260px">
                            <strong><?= e($s['tipo'] === 'concierto' ? $s['artista'] . ' – ' . $s['gira'] : $s['nombre']) ?></strong>
                            <div class="small text-secondary"><?= e(detalle_solicitud($s)) ?></div>
                            <?php if ($s['comentario']): ?><div class="small fst-italic mt-1">“<?= e($s['comentario']) ?>”</div><?php endif; ?>
                            <?php if ($s['motivo_rechazo']): ?><div class="small mt-1"><strong>Motivo:</strong> <?= e($s['motivo_rechazo']) ?></div><?php endif; ?>
                        </td>
                        <td><a href="<?= url('profile.php?u=' . urlencode($s['username'])) ?>">@<?= e($s['username']) ?></a></td>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($s['creado_en'])) ?></td>
                        <td>
                            <?= badge_estado_solicitud($s['estado']) ?>
                            <?php if ($s['revisor']): ?><div class="small text-secondary">por @<?= e($s['revisor']) ?></div><?php endif; ?>
                        </td>
                        <td class="acciones">
                            <?php if ($s['estado'] === 'pendiente'): ?>
                                <button class="btn btn-sm btn-success btn-aprobar" title="Aprobar y agregar al catálogo"><i class="bi bi-check-lg"></i> Aprobar</button>
                                <button class="btn btn-sm btn-outline-danger btn-rechazar" title="Rechazar"><i class="bi bi-x-lg"></i> Rechazar</button>
                            <?php elseif ($s['estado'] === 'aprobada' && $s['creado_id']): ?>
                                <a class="btn btn-sm btn-light" href="<?= url(($s['tipo'] === 'concierto' ? 'event-details.php' : 'recinto.php') . '?id=' . $s['creado_id']) ?>">Ver registro</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$solicitudes): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4"><i class="bi bi-check2-circle"></i> No hay solicitudes en esta vista.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pie">
            <span><?= $total ?> solicitud(es) · página <?= $pagina ?> de <?= $paginas ?></span>
            <?= paginacion($pagina, $paginas, 'admin-solicitudes.php') ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-rechazo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="form-rechazo" novalidate>
            <div class="modal-header"><h2 class="modal-title h5">Rechazar solicitud</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="small text-secondary" id="rechazo-resumen"></p>
                <label class="form-label" for="motivo">Motivo (lo verá el usuario)</label>
                <select class="form-select mb-2" id="motivo-rapido" aria-label="Motivos frecuentes">
                    <option value="">Motivos frecuentes…</option>
                    <option>Ese registro ya existe en el catálogo.</option>
                    <option>No pudimos confirmar que el evento existió.</option>
                    <option>Los datos están incompletos o tienen errores.</option>
                </select>
                <textarea class="form-control" id="motivo" name="motivo" rows="3" minlength="<?= MOTIVO_RECHAZO_MIN ?>" maxlength="255" required></textarea>
                <div class="invalid-feedback">Escribe un motivo de al menos <?= MOTIVO_RECHAZO_MIN ?> caracteres.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Rechazar</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
