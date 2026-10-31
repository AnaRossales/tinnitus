<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';
    $ids = array_map('intval', $_POST['ids'] ?? []);

    if (in_array($accion, ['eliminar', 'activar', 'desactivar'], true)) {
        if (!$ids) json_response(['ok' => false, 'error' => 'No seleccionaste ningún registro.'], 422);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        if ($accion === 'eliminar') {
            $enUso = (int) q("SELECT COUNT(DISTINCT recinto_id) FROM conciertos WHERE recinto_id IN ($marks)", $ids)->fetchColumn();
            if ($enUso) {
                json_response(['ok' => false, 'error' => 'No se puede eliminar: hay conciertos registrados en ese recinto. Márcalo como inactivo o borra primero sus conciertos.'], 409);
            }
            q("DELETE FROM recintos WHERE id IN ($marks)", $ids);
        } else {
            q("UPDATE recintos SET estado = ? WHERE id IN ($marks)", array_merge([$accion === 'activar' ? 'activo' : 'inactivo'], $ids));
        }
        json_response(['ok' => true, 'ids' => $ids]);
    }

    if ($accion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $d = [
            'nombre'    => trim($_POST['nombre'] ?? ''),
            'ciudad'    => trim($_POST['ciudad'] ?? ''),
            'capacidad' => (int) ($_POST['capacidad'] ?? 0),
            'tipo'      => $_POST['tipo'] ?? '',
            'estado'    => ($_POST['estado'] ?? '') === 'inactivo' ? 'inactivo' : 'activo',
        ];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 120) $errores[] = 'Escribe el nombre del recinto.';
        if ($d['ciudad'] === '' || mb_strlen($d['ciudad']) > 80) $errores[] = 'Escribe la ciudad.';
        if ($d['capacidad'] < 1 || $d['capacidad'] > CAPACIDAD_MAX) {
            $errores[] = 'La capacidad debe estar entre 1 y ' . number_format(CAPACIDAD_MAX) . ' personas.';
        }
        if (!in_array($d['tipo'], TIPOS_RECINTO, true)) $errores[] = 'Elige un tipo de recinto.';
        if (!$errores && q('SELECT 1 FROM recintos WHERE nombre = ? AND ciudad = ? AND id <> ?', [$d['nombre'], $d['ciudad'], $id])->fetchColumn()) {
            $errores[] = 'Ya existe un recinto con ese nombre en esa ciudad.';
        }

        if ($errores && es_ajax()) {
            json_response(['ok' => false, 'error' => implode(' ', $errores), 'errores' => $errores], 422);
        }
        if (!$errores) {
            if ($id) {
                q('UPDATE recintos SET nombre = :nombre, ciudad = :ciudad, capacidad = :capacidad, tipo = :tipo, estado = :estado WHERE id = :id', $d + ['id' => $id]);
                $mensaje = 'Recinto actualizado.';
            } else {
                q('INSERT INTO recintos (nombre, ciudad, capacidad, tipo, estado) VALUES (:nombre, :ciudad, :capacidad, :tipo, :estado)', $d);
                $mensaje = 'Recinto creado.';
            }
            if (es_ajax()) json_response(['ok' => true, 'mensaje' => $mensaje]);
            flash('success', $mensaje);
            redirect('admin-venues.php');
        }
    }
}

$buscar = trim($_GET['buscar'] ?? '');
$filtroTipo = in_array($_GET['tipo'] ?? '', TIPOS_RECINTO, true) ? $_GET['tipo'] : '';
$filtroEstado = in_array($_GET['estado'] ?? '', ['activo', 'inactivo'], true) ? $_GET['estado'] : '';

$cond = [];
$params = [];
if ($buscar !== '') { $cond[] = '(r.nombre LIKE ? OR r.ciudad LIKE ?)'; $params[] = "%$buscar%"; $params[] = "%$buscar%"; }
if ($filtroTipo) { $cond[] = 'r.tipo = ?'; $params[] = $filtroTipo; }
if ($filtroEstado) { $cond[] = 'r.estado = ?'; $params[] = $filtroEstado; }
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

$total = (int) q("SELECT COUNT(*) FROM recintos r $where", $params)->fetchColumn();
[$pagina, $paginas, $offset] = paginar($total, 10);
$recintos = q("SELECT r.*, COUNT(c.id) AS conciertos FROM recintos r
               LEFT JOIN conciertos c ON c.recinto_id = r.id
               $where GROUP BY r.id ORDER BY r.nombre LIMIT 10 OFFSET $offset", $params)->fetchAll();

$pageTitle = 'Admin · Recintos';
$active = 'admin';
$adminTitle = 'Recintos';
$adminTab = 'venues';
$pageScript = 'admin.js';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/admin-nav.php';
?>
<div class="container">
    <?php if ($errores): ?>
        <div class="alert alert-danger mt-4"><ul class="mb-0"><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <div id="reabrir" data-json='<?= e(json_encode($_POST)) ?>'></div>
    <?php endif; ?>

    <div class="admin-toolbar">
        <div class="dropdown">
            <button class="btn btn-gray dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                Filtro<?= ($buscar || $filtroTipo || $filtroEstado) ? ' (activo)' : '' ?>
            </button>
            <form class="dropdown-menu p-3" method="get" style="min-width: 260px">
                <label class="form-label small" for="f-buscar">Buscar</label>
                <input class="form-control form-control-sm mb-2" id="f-buscar" type="search" name="buscar" placeholder="Nombre o ciudad" value="<?= e($buscar) ?>">
                <label class="form-label small" for="f-tipo">Tipo</label>
                <select class="form-select form-select-sm mb-2" id="f-tipo" name="tipo">
                    <option value="">Todos</option>
                    <?php foreach (TIPOS_RECINTO as $t): ?><option <?= $filtroTipo === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
                </select>
                <label class="form-label small" for="f-estado">Estado</label>
                <select class="form-select form-select-sm mb-3" id="f-estado" name="estado">
                    <option value="">Todos</option>
                    <option value="activo" <?= $filtroEstado === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
                <div class="d-flex gap-2">
                    <button class="btn btn-indigo btn-sm flex-grow-1">Aplicar</button>
                    <a class="btn btn-light btn-sm" href="<?= url('admin-venues.php') ?>">Limpiar</a>
                </div>
            </form>
        </div>
        <div class="dropdown">
            <button class="btn btn-gray dropdown-toggle" type="button" data-bs-toggle="dropdown" id="btn-masiva" disabled>
                Acción Masiva (<span id="sel-count">0</span>)
            </button>
            <ul class="dropdown-menu">
                <li><button class="dropdown-item" data-bulk="activar"><i class="bi bi-check-circle me-2"></i>Marcar activos</button></li>
                <li><button class="dropdown-item" data-bulk="desactivar"><i class="bi bi-pause-circle me-2"></i>Marcar inactivos</button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger" data-bulk="eliminar"><i class="bi bi-trash me-2"></i>Eliminar seleccionados</button></li>
            </ul>
        </div>
        <div class="extra">
            <button class="btn btn-indigo" type="button" data-nuevo><i class="bi bi-plus-lg"></i> Nuevo recinto</button>
        </div>
    </div>

    <div class="card-table mb-5">
        <div class="table-responsive">
            <table class="table align-middle" id="tabla">
                <thead>
                    <tr>
                        <th>ID</th><th>Nombre</th><th>Ubicación</th><th>Capacidad</th><th>Tipo</th><th>Estado</th>
                        <th class="acciones"><input class="form-check-input" type="checkbox" id="check-all" aria-label="Seleccionar todos"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recintos as $r):
                    $json = json_encode(['id' => $r['id'], 'nombre' => $r['nombre'], 'ciudad' => $r['ciudad'], 'capacidad' => $r['capacidad'], 'tipo' => $r['tipo'], 'estado' => $r['estado']]); ?>
                    <tr data-id="<?= $r['id'] ?>">
                        <td><?= $r['id'] ?></td>
                        <td class="fw-semibold"><a class="text-reset text-decoration-none" href="<?= url('recinto.php?id=' . $r['id']) ?>" title="Ver página pública"><?= e($r['nombre']) ?></a><div class="small text-secondary fw-normal"><?= $r['conciertos'] ?> concierto(s)</div></td>
                        <td><?= e($r['ciudad']) ?></td>
                        <td><?= number_format($r['capacidad']) ?></td>
                        <td><?= e($r['tipo']) ?></td>
                        <td><?= $r['estado'] === 'activo' ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
                        <td class="acciones">
                            <button class="btn-tool btn-eliminar" data-nombre="<?= e($r['nombre']) ?>" title="Eliminar" aria-label="Eliminar"><i class="bi bi-trash3"></i></button>
                            <button class="btn-tool btn-editar" data-json='<?= e($json) ?>' title="Editar" aria-label="Editar"><i class="bi bi-pencil-fill"></i></button>
                            <input class="form-check-input row-check" type="checkbox" value="<?= $r['id'] ?>" aria-label="Seleccionar">
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recintos): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No hay recintos con esos filtros.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pie">
            <span><?= $total ?> registro(s) · página <?= $pagina ?> de <?= $paginas ?></span>
            <?= paginacion($pagina, $paginas, 'admin-venues.php') ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content needs-validation" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="">
            <div class="modal-header"><h2 class="modal-title h5" data-titulo-nuevo="Nuevo recinto" data-titulo-editar="Editar recinto">Nuevo recinto</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label" for="m-nombre">Nombre</label>
                    <input class="form-control" id="m-nombre" name="nombre" required maxlength="120">
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="m-ciudad">Ciudad</label>
                    <input class="form-control" id="m-ciudad" name="ciudad" required maxlength="80">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="m-capacidad">Capacidad (aforo)</label>
                    <input class="form-control" id="m-capacidad" name="capacidad" type="number" min="1" max="<?= CAPACIDAD_MAX ?>" step="1" required
                           data-msg="Entre 1 y <?= number_format(CAPACIDAD_MAX) ?> personas.">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="m-tipo">Tipo</label>
                    <select class="form-select" id="m-tipo" name="tipo" required>
                        <option value="">Selecciona...</option>
                        <?php foreach (TIPOS_RECINTO as $t): ?><option><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="m-estado">Estado</label>
                    <select class="form-select" id="m-estado" name="estado">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/confirm-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
