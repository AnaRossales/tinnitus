<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';
    $ids = array_map('intval', $_POST['ids'] ?? []);

    if ($accion === 'eliminar') {
        if (!$ids) json_response(['ok' => false, 'error' => 'No seleccionaste ningún registro.'], 422);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $enUso = (int) q("SELECT COUNT(*) FROM conciertos WHERE artista_id IN ($marks)", $ids)->fetchColumn();
        if ($enUso) {
            json_response(['ok' => false, 'error' => 'No se puede eliminar: el artista tiene conciertos registrados. Borra primero sus conciertos.'], 409);
        }
        q("DELETE FROM artistas WHERE id IN ($marks)", $ids);
        json_response(['ok' => true, 'ids' => $ids]);
    }

    if ($accion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $d = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'genero' => trim($_POST['genero'] ?? '') ?: null,
            'pais'   => trim($_POST['pais'] ?? '') ?: null,
        ];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 120) $errores[] = 'Escribe el nombre del artista.';
        if (mb_strlen($d['genero'] ?? '') > 60 || mb_strlen($d['pais'] ?? '') > 60) $errores[] = 'Género y país admiten máximo 60 caracteres.';
        $duplicado = q('SELECT id FROM artistas WHERE nombre = ? AND id <> ?', [$d['nombre'], $id])->fetchColumn();
        if ($duplicado) $errores[] = 'Ya existe un artista con ese nombre.';

        if ($errores && es_ajax()) {
            json_response(['ok' => false, 'error' => implode(' ', $errores), 'errores' => $errores], 422);
        }
        if (!$errores) {
            if ($id) {
                q('UPDATE artistas SET nombre = :nombre, genero = :genero, pais = :pais WHERE id = :id', $d + ['id' => $id]);
                $mensaje = 'Artista actualizado.';
            } else {
                q('INSERT INTO artistas (nombre, genero, pais) VALUES (:nombre, :genero, :pais)', $d);
                $mensaje = 'Artista creado.';
            }
            if (es_ajax()) json_response(['ok' => true, 'mensaje' => $mensaje]);
            flash('success', $mensaje);
            redirect('admin-artists.php');
        }
    }
}

$buscar = trim($_GET['buscar'] ?? '');
$busquedaParams = ["%$buscar%", "%$buscar%"];
$total = (int) q('SELECT COUNT(*) FROM artistas a WHERE a.nombre LIKE ? OR a.genero LIKE ?', $busquedaParams)->fetchColumn();
[$pagina, $paginas, $offset] = paginar($total, 10);
$artistas = q("SELECT a.*, COUNT(c.id) AS conciertos FROM artistas a
               LEFT JOIN conciertos c ON c.artista_id = a.id
               WHERE a.nombre LIKE ? OR a.genero LIKE ?
               GROUP BY a.id ORDER BY a.nombre LIMIT 10 OFFSET $offset", $busquedaParams)->fetchAll();

$pageTitle = 'Admin · Artistas';
$active = 'admin';
$adminTitle = 'Artistas';
$adminTab = 'artists';
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
                Filtro<?= $buscar ? ' (activo)' : '' ?>
            </button>
            <form class="dropdown-menu p-3" method="get" style="min-width: 260px">
                <label class="form-label small" for="f-buscar">Buscar</label>
                <input class="form-control form-control-sm mb-3" id="f-buscar" type="search" name="buscar" placeholder="Nombre o género" value="<?= e($buscar) ?>">
                <div class="d-flex gap-2">
                    <button class="btn btn-indigo btn-sm flex-grow-1">Aplicar</button>
                    <a class="btn btn-light btn-sm" href="<?= url('admin-artists.php') ?>">Limpiar</a>
                </div>
            </form>
        </div>
        <div class="dropdown">
            <button class="btn btn-gray dropdown-toggle" type="button" data-bs-toggle="dropdown" id="btn-masiva" disabled>
                Acción Masiva (<span id="sel-count">0</span>)
            </button>
            <ul class="dropdown-menu">
                <li><button class="dropdown-item text-danger" data-bulk="eliminar"><i class="bi bi-trash me-2"></i>Eliminar seleccionados</button></li>
            </ul>
        </div>
        <div class="extra">
            <button class="btn btn-indigo" type="button" data-nuevo><i class="bi bi-plus-lg"></i> Nuevo artista</button>
        </div>
    </div>

    <div class="card-table mb-5">
        <div class="table-responsive">
            <table class="table align-middle" id="tabla">
                <thead><tr>
                    <th>ID</th><th>Nombre</th><th>Género</th><th>País</th><th>Conciertos</th>
                    <th class="acciones"><input class="form-check-input" type="checkbox" id="check-all" aria-label="Seleccionar todos"></th>
                </tr></thead>
                <tbody>
                <?php foreach ($artistas as $a):
                    $json = json_encode(['id' => $a['id'], 'nombre' => $a['nombre'], 'genero' => $a['genero'], 'pais' => $a['pais']]); ?>
                    <tr data-id="<?= $a['id'] ?>">
                        <td><?= $a['id'] ?></td>
                        <td class="fw-semibold"><?= e($a['nombre']) ?></td>
                        <td><?= e($a['genero']) ?></td>
                        <td><?= e($a['pais']) ?></td>
                        <td><?= $a['conciertos'] ?></td>
                        <td class="acciones">
                            <button class="btn-tool btn-eliminar" data-nombre="<?= e($a['nombre']) ?>" title="Eliminar" aria-label="Eliminar"><i class="bi bi-trash3"></i></button>
                            <button class="btn-tool btn-editar" data-json='<?= e($json) ?>' title="Editar" aria-label="Editar"><i class="bi bi-pencil-fill"></i></button>
                            <input class="form-check-input row-check" type="checkbox" value="<?= $a['id'] ?>" aria-label="Seleccionar">
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$artistas): ?><tr><td colspan="6" class="text-center text-secondary py-4">No hay artistas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pie">
            <span><?= $total ?> registro(s) · página <?= $pagina ?> de <?= $paginas ?></span>
            <?= paginacion($pagina, $paginas, 'admin-artists.php') ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content needs-validation" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="">
            <div class="modal-header"><h2 class="modal-title h5" data-titulo-nuevo="Nuevo artista" data-titulo-editar="Editar artista">Nuevo artista</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label" for="m-nombre">Nombre</label><input class="form-control" id="m-nombre" name="nombre" required maxlength="120"></div>
                <div class="col-md-6"><label class="form-label" for="m-genero">Género</label><input class="form-control" id="m-genero" name="genero" maxlength="60"></div>
                <div class="col-md-6"><label class="form-label" for="m-pais">País</label><input class="form-control" id="m-pais" name="pais" maxlength="60"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/confirm-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
