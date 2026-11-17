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
    if ($accion === 'editar') {
        $tipo = q("SELECT tipo FROM solicitudes WHERE id = ? AND estado = 'pendiente'", [$id])->fetchColumn();
        if (!$tipo) json_response(['ok' => false, 'error' => 'La solicitud no existe o ya fue revisada.'], 409);
        $d = datos_solicitud(['tipo' => $tipo] + $_POST);
        if ($errores = validar_solicitud($d, $id)) {
            json_response(['ok' => false, 'error' => implode(' ', $errores)], 422);
        }
        $campos = array_diff_key($d, ['tipo' => 0, 'comentario' => 0]);
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
        q("UPDATE solicitudes SET $set WHERE id = ? AND estado = 'pendiente'", array_merge(array_values($campos), [$id]));
        json_response(['ok' => true, 'mensaje' => 'Solicitud actualizada.']);
    }
    if ($accion === 'rechazar') {
        $motivo = trim($_POST['motivo'] ?? '');
        $detalle = trim($_POST['detalle'] ?? '');
        if ($error = validar_rechazo($motivo, $detalle)) {
            json_response(['ok' => false, 'error' => $error], 422);
        }
        $n = q("UPDATE solicitudes SET estado = 'rechazada', motivo_rechazo = ?, detalle_rechazo = ?, revisado_por = ?, revisado_en = NOW()
                WHERE id = ? AND estado = 'pendiente'", [$motivo, $detalle ?: null, current_user()['id'], $id])->rowCount();
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
$recintosActivos = q("SELECT id, nombre, ciudad FROM recintos WHERE estado = 'activo' ORDER BY nombre")->fetchAll();

function solicitudes_url(array $cambios): string
{
    return url('admin-solicitudes.php?' . http_build_query(array_merge($_GET, ['pagina' => null], $cambios)));
}

$pageTitle = 'Admin · Solicitudes';
$active = 'admin';
$adminTitle = 'Solicitudes';
$adminTab = 'solicitudes';
$pageScript = 'admin-solicitudes.js';
$moduloReportes = 'solicitudes';
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
            <button class="btn btn-gray" type="button" data-bs-toggle="modal" data-bs-target="#modal-reportes"><i class="bi bi-bar-chart-line"></i> Reportes</button>
            <select class="form-select w-auto" aria-label="Tipo" onchange="location.href = this.value">
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
                    <tr data-id="<?= $s['id'] ?>" data-resumen="<?= e(resumen_solicitud($s)) ?>"
                        data-datos="<?= e(json_encode(array_intersect_key($s, array_flip(['tipo', 'artista', 'gira', 'fecha', 'recinto_id', 'nombre', 'ciudad', 'capacidad', 'tipo_recinto', 'comentario'])))) ?>">
                        <td><?= $s['id'] ?></td>
                        <td class="text-nowrap"><i class="bi <?= $s['tipo'] === 'concierto' ? 'bi-music-note-beamed' : 'bi-building' ?>"></i> <?= ucfirst($s['tipo']) ?></td>
                        <td style="min-width: 260px">
                            <strong><?= e($s['tipo'] === 'concierto' ? $s['artista'] . ' – ' . $s['gira'] : $s['nombre']) ?></strong>
                            <div class="small text-secondary"><?= e(detalle_solicitud($s)) ?></div>
                            <?php if ($s['comentario']): ?><div class="small fst-italic mt-1">“<?= e($s['comentario']) ?>”</div><?php endif; ?>
                            <?php if ($s['motivo_rechazo']): ?><div class="small mt-1"><?= texto_rechazo($s) ?></div><?php endif; ?>
                        </td>
                        <td><a href="<?= url('profile.php?u=' . urlencode($s['username'])) ?>">@<?= e($s['username']) ?></a></td>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($s['creado_en'])) ?></td>
                        <td>
                            <?= badge_estado_solicitud($s['estado']) ?>
                            <?php if ($s['revisor']): ?><div class="small text-secondary">por @<?= e($s['revisor']) ?></div><?php endif; ?>
                        </td>
                        <td class="acciones">
                            <?php if ($s['estado'] === 'pendiente'): ?>
                                <button class="btn btn-sm btn-light btn-editar" title="Corregir los datos"><i class="bi bi-pencil-fill"></i> Editar</button>
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

<div class="modal fade" id="modal-editar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="form-editar" novalidate>
            <div class="modal-header"><h2 class="modal-title h5">Editar solicitud</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <fieldset class="row g-3" id="campos-concierto">
                    <div class="col-12"><label class="form-label" for="e-artista">Artista o banda</label><input class="form-control" id="e-artista" name="artista" required maxlength="120"></div>
                    <div class="col-12"><label class="form-label" for="e-gira">Gira o evento</label><input class="form-control" id="e-gira" name="gira" required maxlength="150"></div>
                    <div class="col-md-7">
                        <label class="form-label" for="e-recinto">Recinto</label>
                        <select class="form-select" id="e-recinto" name="recinto_id" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($recintosActivos as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['nombre'] . ' · ' . $r['ciudad']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5"><label class="form-label" for="e-fecha">Fecha</label><input class="form-control" id="e-fecha" name="fecha" type="date" required min="<?= FECHA_MIN_CONCIERTO ?>" max="<?= fecha_max_concierto() ?>"></div>
                </fieldset>
                <fieldset class="row g-3" id="campos-recinto">
                    <div class="col-12"><label class="form-label" for="e-nombre">Nombre del recinto</label><input class="form-control" id="e-nombre" name="nombre" required maxlength="120"></div>
                    <div class="col-md-6">
                        <label class="form-label" for="e-tipo">Tipo</label>
                        <select class="form-select" id="e-tipo" name="tipo_recinto" required>
                            <option value="">Selecciona...</option>
                            <?php foreach (TIPOS_RECINTO as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label" for="e-capacidad">Capacidad</label><input class="form-control" id="e-capacidad" name="capacidad" type="number" min="1" max="<?= CAPACIDAD_MAX ?>" required></div>
                    <div class="col-12"><label class="form-label" for="e-ciudad">Ciudad</label><input class="form-control" id="e-ciudad" name="ciudad" required maxlength="80"></div>
                </fieldset>
                <p class="small text-secondary fst-italic mt-3 mb-0" id="e-comentario"></p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modal-rechazo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="form-rechazo" novalidate>
            <div class="modal-header"><h2 class="modal-title h5">Rechazar solicitud</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="small text-secondary" id="rechazo-resumen"></p>
                <label class="form-label" for="motivo">Motivo (lo verá el usuario)</label>
                <select class="form-select" id="motivo" name="motivo" required>
                    <option value="">Elige un motivo…</option>
                    <?php foreach (MOTIVOS_RECHAZO as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Elige un motivo de la lista.</div>
                <label class="form-label mt-3" for="detalle">Explicación <span class="text-secondary" id="detalle-opcional">(opcional)</span></label>
                <textarea class="form-control" id="detalle" name="detalle" rows="3" minlength="<?= DETALLE_RECHAZO_MIN ?>" maxlength="<?= DETALLE_RECHAZO_MAX ?>"></textarea>
                <div class="invalid-feedback">Explica el motivo en al menos <?= DETALLE_RECHAZO_MIN ?> caracteres.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Rechazar</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
