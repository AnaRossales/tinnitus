<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';
    $ids = array_map('intval', $_POST['ids'] ?? []);

    if ($accion === 'eliminar' || $accion === 'destacar' || $accion === 'quitar_destacado') {
        if (!$ids) json_response(['ok' => false, 'error' => 'No seleccionaste ningún registro.'], 422);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        if ($accion === 'eliminar') {
            q("DELETE FROM conciertos WHERE id IN ($marks)", $ids);
        } else {
            if ($accion === 'destacar') {
                $otros = (int) q("SELECT COUNT(*) FROM conciertos WHERE destacado = 1 AND id NOT IN ($marks)", $ids)->fetchColumn();
                if ($otros + count($ids) > MAX_DESTACADOS) {
                    json_response(['ok' => false, 'error' => 'Solo puede haber ' . MAX_DESTACADOS . " destacados a la vez y ya hay $otros. Quita alguno primero."], 422);
                }
            }
            q("UPDATE conciertos SET destacado = ? WHERE id IN ($marks)", array_merge([$accion === 'destacar' ? 1 : 0], $ids));
        }
        json_response(['ok' => true, 'ids' => $ids]);
    }

    if ($accion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $d = [
            'artista_id'  => (int) ($_POST['artista_id'] ?? 0),
            'recinto_id'  => (int) ($_POST['recinto_id'] ?? 0),
            'gira'        => trim($_POST['gira'] ?? ''),
            'fecha'       => $_POST['fecha'] ?? '',
            'poster_url'  => trim($_POST['poster_url'] ?? '') ?: null,
            'descripcion' => trim($_POST['descripcion'] ?? '') ?: null,
            'destacado'   => isset($_POST['destacado']) ? 1 : 0,
        ];
        $anterior = $id ? q('SELECT recinto_id FROM conciertos WHERE id = ?', [$id])->fetch() : null;
        $recinto = q('SELECT estado FROM recintos WHERE id = ?', [$d['recinto_id']])->fetch();

        if (!q('SELECT 1 FROM artistas WHERE id = ?', [$d['artista_id']])->fetchColumn()) $errores[] = 'Elige un artista.';
        if (!$recinto) {
            $errores[] = 'Elige un recinto.';
        } elseif ($recinto['estado'] === 'inactivo' && (!$anterior || (int) $anterior['recinto_id'] !== $d['recinto_id'])) {
            $errores[] = 'Ese recinto está inactivo; no se le pueden asignar conciertos nuevos.';
        }
        if ($d['gira'] === '' || mb_strlen($d['gira']) > 150) $errores[] = 'Escribe el nombre de la gira (máx. 150 caracteres).';
        if ($e = validar_fecha_concierto($d['fecha'])) $errores[] = $e;
        if ($e = validar_url_http($d['poster_url'])) $errores[] = $e;
        if (mb_strlen($d['descripcion'] ?? '') > 2000) $errores[] = 'La descripción no puede pasar de 2000 caracteres.';

        if (!$errores && q('SELECT 1 FROM conciertos WHERE artista_id = ? AND recinto_id = ? AND fecha = ? AND id <> ?',
                [$d['artista_id'], $d['recinto_id'], $d['fecha'], $id])->fetchColumn()) {
            $errores[] = 'Ya existe un concierto de ese artista en ese recinto y fecha.';
        }
        if ($d['destacado'] && q('SELECT COUNT(*) FROM conciertos WHERE destacado = 1 AND id <> ?', [$id])->fetchColumn() >= MAX_DESTACADOS) {
            $errores[] = 'Ya hay ' . MAX_DESTACADOS . ' conciertos destacados. Quita alguno antes de destacar otro.';
        }

        if ($errores && es_ajax()) {
            json_response(['ok' => false, 'error' => implode(' ', $errores), 'errores' => $errores], 422);
        }
        if (!$errores) {
            if ($id) {
                q('UPDATE conciertos SET artista_id = :artista_id, recinto_id = :recinto_id, gira = :gira, fecha = :fecha,
                   poster_url = :poster_url, descripcion = :descripcion, destacado = :destacado WHERE id = :id', $d + ['id' => $id]);
                $mensaje = 'Concierto actualizado.';
            } else {
                q('INSERT INTO conciertos (artista_id, recinto_id, gira, fecha, poster_url, descripcion, destacado)
                   VALUES (:artista_id, :recinto_id, :gira, :fecha, :poster_url, :descripcion, :destacado)', $d);
                $mensaje = 'Concierto creado.';
            }
            if (es_ajax()) json_response(['ok' => true, 'mensaje' => $mensaje]);
            flash('success', $mensaje);
            redirect('admin-events.php');
        }
    }
}

$buscar = trim($_GET['buscar'] ?? '');
$filtroRecinto = (int) ($_GET['recinto'] ?? 0);
$filtroAnio = (int) ($_GET['anio'] ?? 0);
$estatus = in_array($_GET['estatus'] ?? '', ['proximos', 'pasados', 'destacados'], true) ? $_GET['estatus'] : '';
$porPagina = 10;

$cond = [];
$params = [];
if ($buscar !== '') {
    $cond[] = '(a.nombre LIKE ? OR c.gira LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}
if ($filtroRecinto) { $cond[] = 'c.recinto_id = ?'; $params[] = $filtroRecinto; }
if ($filtroAnio) { $cond[] = 'YEAR(c.fecha) = ?'; $params[] = $filtroAnio; }
if ($estatus === 'proximos') $cond[] = 'c.fecha > CURDATE()';
if ($estatus === 'pasados') $cond[] = 'c.fecha <= CURDATE()';
if ($estatus === 'destacados') $cond[] = 'c.destacado = 1';
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

$total = (int) q("SELECT COUNT(*) FROM conciertos c JOIN artistas a ON a.id = c.artista_id $where", $params)->fetchColumn();
[$pagina, $paginas, $offset] = paginar($total, $porPagina);

$conciertos = q(CONCIERTO_SELECT . " $where GROUP BY c.id ORDER BY c.fecha DESC LIMIT $porPagina OFFSET $offset", $params)->fetchAll();
$artistas = q('SELECT id, nombre FROM artistas ORDER BY nombre')->fetchAll();
$recintos = q('SELECT id, nombre, ciudad, estado FROM recintos ORDER BY nombre')->fetchAll();
$totalDestacados = (int) q('SELECT COUNT(*) FROM conciertos WHERE destacado = 1')->fetchColumn();
$anios = q('SELECT DISTINCT YEAR(fecha) AS y FROM conciertos ORDER BY y DESC')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Admin · Conciertos';
$active = 'admin';
$adminTitle = 'Conciertos';
$adminTab = 'events';
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
                Filtro<?= ($buscar || $filtroRecinto || $filtroAnio || $estatus) ? ' (activo)' : '' ?>
            </button>
            <form class="dropdown-menu p-3" method="get" style="min-width: 280px">
                <label class="form-label small" for="f-buscar">Buscar</label>
                <input class="form-control form-control-sm mb-2" id="f-buscar" type="search" name="buscar" placeholder="Artista o gira" value="<?= e($buscar) ?>">
                <label class="form-label small" for="f-recinto">Recinto</label>
                <select class="form-select form-select-sm mb-2" id="f-recinto" name="recinto">
                    <option value="0">Todos</option>
                    <?php foreach ($recintos as $r): ?><option value="<?= $r['id'] ?>" <?= $filtroRecinto === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option><?php endforeach; ?>
                </select>
                <label class="form-label small" for="f-anio">Año</label>
                <select class="form-select form-select-sm mb-2" id="f-anio" name="anio">
                    <option value="0">Todos</option>
                    <?php foreach ($anios as $y): ?><option <?= $filtroAnio === (int) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
                </select>
                <label class="form-label small" for="f-estatus">Estatus</label>
                <select class="form-select form-select-sm mb-3" id="f-estatus" name="estatus">
                    <option value="">Todos</option>
                    <option value="proximos" <?= $estatus === 'proximos' ? 'selected' : '' ?>>Próximos</option>
                    <option value="pasados" <?= $estatus === 'pasados' ? 'selected' : '' ?>>Ya sucedieron</option>
                    <option value="destacados" <?= $estatus === 'destacados' ? 'selected' : '' ?>>Destacados en el inicio</option>
                </select>
                <div class="d-flex gap-2">
                    <button class="btn btn-indigo btn-sm flex-grow-1">Aplicar</button>
                    <a class="btn btn-light btn-sm" href="<?= url('admin-events.php') ?>">Limpiar</a>
                </div>
            </form>
        </div>
        <div class="dropdown">
            <button class="btn btn-gray dropdown-toggle" type="button" data-bs-toggle="dropdown" id="btn-masiva" disabled>
                Acción Masiva (<span id="sel-count">0</span>)
            </button>
            <ul class="dropdown-menu">
                <li><button class="dropdown-item" data-bulk="destacar"><i class="bi bi-star me-2"></i>Marcar como destacados</button></li>
                <li><button class="dropdown-item" data-bulk="quitar_destacado"><i class="bi bi-star-half me-2"></i>Quitar destacado</button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger" data-bulk="eliminar"><i class="bi bi-trash me-2"></i>Eliminar seleccionados</button></li>
            </ul>
        </div>
        <div class="extra">
            <button class="btn btn-indigo" type="button" data-nuevo><i class="bi bi-plus-lg"></i> Nuevo concierto</button>
        </div>
    </div>

    <div class="card-table mb-5">
        <div class="table-responsive">
            <table class="table align-middle" id="tabla">
                <thead>
                    <tr>
                        <th>ID</th><th>Banda</th><th>Gira</th><th>Recinto</th><th>Fecha</th><th>Calificación</th>
                        <th class="acciones"><input class="form-check-input" type="checkbox" id="check-all" aria-label="Seleccionar todos"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($conciertos as $c):
                    $json = json_encode(['id' => $c['id'], 'artista_id' => $c['artista_id'], 'recinto_id' => $c['recinto_id'], 'gira' => $c['gira'],
                        'fecha' => $c['fecha'], 'poster_url' => $c['poster_url'], 'descripcion' => $c['descripcion'], 'destacado' => (int) $c['destacado']]); ?>
                    <tr data-id="<?= $c['id'] ?>">
                        <td><?= $c['id'] ?></td>
                        <td>
                            <a class="fw-semibold text-reset text-decoration-none" href="<?= url('event-details.php?id=' . $c['id']) ?>"><?= e($c['artista']) ?></a>
                            <?php if ($c['destacado']): ?><i class="bi bi-star-fill text-warning ms-1" title="Destacado en el inicio"></i><?php endif; ?>
                        </td>
                        <td><?= e($c['gira']) ?></td>
                        <td><?= e($c['recinto']) ?><div class="small text-secondary"><?= e($c['ciudad']) ?></div></td>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
                        <td class="text-nowrap"><?= $c['promedio'] ? stars((float) $c['promedio']) . ' <small>' . $c['promedio'] . '</small>' : '<span class="small text-secondary">Sin reseñas</span>' ?></td>
                        <td class="acciones">
                            <button class="btn-tool btn-eliminar" data-nombre="<?= e($c['artista'] . ' – ' . $c['gira']) ?>"
                                    <?= $c['total_resenas'] ? 'data-aviso="También se borrarán sus ' . $c['total_resenas'] . ' reseña(s)."' : '' ?> title="Eliminar" aria-label="Eliminar"><i class="bi bi-trash3"></i></button>
                            <button class="btn-tool btn-editar" data-json='<?= e($json) ?>' title="Editar" aria-label="Editar"><i class="bi bi-pencil-fill"></i></button>
                            <input class="form-check-input row-check" type="checkbox" value="<?= $c['id'] ?>" aria-label="Seleccionar">
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$conciertos): ?>
                    <tr><td colspan="7" class="text-center text-secondary py-4">No hay conciertos con esos filtros.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pie">
            <span><?= $total ?> registro(s) · página <?= $pagina ?> de <?= $paginas ?></span>
            <?= paginacion($pagina, $paginas, 'admin-events.php') ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content needs-validation" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="">
            <div class="modal-header"><h2 class="modal-title h5" data-titulo-nuevo="Nuevo concierto" data-titulo-editar="Editar concierto">Nuevo concierto</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="m-artista">Artista</label>
                    <select class="form-select" id="m-artista" name="artista_id" required>
                        <option value="">Selecciona...</option>
                        <?php foreach ($artistas as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['nombre']) ?></option><?php endforeach; ?>
                    </select>
                    <div class="form-text">¿No aparece? <a href="<?= url('admin-artists.php') ?>">Agrega el artista</a>.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="m-recinto">Recinto</label>
                    <select class="form-select" id="m-recinto" name="recinto_id" required>
                        <option value="">Selecciona...</option>
                        <?php foreach ($recintos as $r): $inactivo = $r['estado'] === 'inactivo'; ?>
                            <option value="<?= $r['id'] ?>" <?= $inactivo ? 'data-inactivo="1"' : '' ?>><?= e($r['nombre'] . ' (' . $r['ciudad'] . ')') . ($inactivo ? ' · inactivo' : '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback" id="recinto-feedback">Elige un recinto.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="m-gira">Gira / nombre del evento</label>
                    <input class="form-control" id="m-gira" name="gira" required maxlength="150">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="m-fecha">Fecha</label>
                    <input class="form-control" id="m-fecha" name="fecha" type="date" required
                           min="<?= FECHA_MIN_CONCIERTO ?>" max="<?= fecha_max_concierto() ?>"
                           data-msg="La fecha debe estar entre el 01/01/1950 y el <?= date('d/m/Y', strtotime(fecha_max_concierto())) ?>.">
                </div>
                <div class="col-12">
                    <label class="form-label" for="m-poster">URL del póster (opcional)</label>
                    <input class="form-control" id="m-poster" name="poster_url" type="url" maxlength="500" pattern="https?://.+"
                           placeholder="https://..." data-msg="Debe ser una dirección que empiece con http:// o https://">
                </div>
                <div class="col-12">
                    <label class="form-label" for="m-desc">Descripción</label>
                    <textarea class="form-control" id="m-desc" name="descripcion" rows="3" maxlength="2000"></textarea>
                </div>
                <div class="col-12 form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="m-destacado" name="destacado">
                    <label class="form-check-label" for="m-destacado">Mostrar como destacado en el inicio</label>
                    <div class="form-text" id="destacados-info">Hay <?= $totalDestacados ?> de <?= MAX_DESTACADOS ?> destacados permitidos.</div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/confirm-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
