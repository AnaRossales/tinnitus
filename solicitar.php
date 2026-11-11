<?php
require_once __DIR__ . '/includes/solicitudes.php';
require_login();

$user = current_user();
$errores = [];
$tipo = ($_GET['tipo'] ?? '') === 'recinto' ? 'recinto' : 'concierto';
$old = [
    'artista' => mb_substr(trim($_GET['artista'] ?? ''), 0, 120), 'gira' => '', 'fecha' => '', 'recinto_id' => (int) ($_GET['recinto'] ?? 0),
    'nombre' => mb_substr(trim($_GET['nombre'] ?? ''), 0, 120), 'ciudad' => '', 'capacidad' => '', 'tipo_recinto' => '', 'comentario' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cancelar') {
        $borradas = q("DELETE FROM solicitudes WHERE id = ? AND usuario_id = ? AND estado = 'pendiente'",
            [(int) ($_POST['id'] ?? 0), $user['id']])->rowCount();
        flash($borradas ? 'info' : 'warning', $borradas ? 'Solicitud cancelada.' : 'Esa solicitud ya no se puede cancelar.');
        redirect('solicitar.php#mis-solicitudes');
    }

    if ($accion === 'solicitar') {
        $d = datos_solicitud($_POST);
        $tipo = $d['tipo'];
        $old = array_merge($old, array_map(fn($v) => $v ?? '', $d));

        $pendientes = (int) q("SELECT COUNT(*) FROM solicitudes WHERE usuario_id = ? AND estado = 'pendiente'", [$user['id']])->fetchColumn();
        if ($pendientes >= MAX_SOLICITUDES_PENDIENTES) {
            $errores[] = 'Ya tienes ' . MAX_SOLICITUDES_PENDIENTES . ' solicitudes en revisión. Espera a que el equipo las revise.';
        } else {
            $errores = validar_solicitud($d);
        }

        if (!$errores) {
            $columnas = array_keys($d);
            q('INSERT INTO solicitudes (usuario_id, ' . implode(', ', $columnas) . ') VALUES (?' . str_repeat(', ?', count($columnas)) . ')',
                array_merge([$user['id']], array_values($d)));
            flash('success', '¡Gracias! Tu solicitud quedó en revisión. Aquí abajo puedes ver cómo va.');
            redirect('solicitar.php#mis-solicitudes');
        }
    }
}

$recintos = q("SELECT id, nombre, ciudad FROM recintos WHERE estado = 'activo' ORDER BY nombre")->fetchAll();
$artistas = q('SELECT nombre FROM artistas ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);
$ciudades = q('SELECT DISTINCT ciudad FROM recintos ORDER BY ciudad')->fetchAll(PDO::FETCH_COLUMN);
$mias = q('SELECT s.*, r.nombre AS recinto FROM solicitudes s LEFT JOIN recintos r ON r.id = s.recinto_id
           WHERE s.usuario_id = ? ORDER BY s.estado = \'pendiente\' DESC, s.creado_en DESC', [$user['id']])->fetchAll();

$pageTitle = 'Solicitar un registro';
$pageScript = 'solicitar.js';
require __DIR__ . '/includes/header.php';
?>
<section class="page-header">
    <div class="container">
        <h1>¿Falta algo?</h1>
        <p>Solicita que agreguemos un concierto o un recinto que no está en Tinnitus. El equipo lo revisa y, si todo está bien, aparece en el catálogo.</p>
    </div>
</section>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-7">
            <?php if ($errores): ?>
                <div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3"><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" class="needs-validation gray-card" id="form-solicitud" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="solicitar">

                <fieldset class="mb-4">
                    <legend class="form-label fw-semibold fs-6">¿Qué quieres agregar?</legend>
                    <div class="tipo-switch" role="radiogroup">
                        <input type="radio" class="btn-check" name="tipo" id="tipo-concierto" value="concierto" <?= $tipo === 'concierto' ? 'checked' : '' ?>>
                        <label class="tipo-opcion" for="tipo-concierto"><i class="bi bi-music-note-beamed"></i><span><strong>Un concierto</strong><small>Un show que ya pasó o que viene</small></span></label>
                        <input type="radio" class="btn-check" name="tipo" id="tipo-recinto" value="recinto" <?= $tipo === 'recinto' ? 'checked' : '' ?>>
                        <label class="tipo-opcion" for="tipo-recinto"><i class="bi bi-building"></i><span><strong>Un recinto</strong><small>Estadio, arena, foro, bar…</small></span></label>
                    </div>
                </fieldset>

                <fieldset id="campos-concierto" class="row g-3" <?= $tipo === 'concierto' ? '' : 'hidden disabled' ?>>
                    <div class="col-md-6">
                        <label class="form-label" for="s-artista">Artista o banda</label>
                        <input class="form-control" id="s-artista" name="artista" list="lista-artistas" required maxlength="120" value="<?= e($old['artista']) ?>" placeholder="Ej. Muse">
                        <datalist id="lista-artistas"><?php foreach ($artistas as $a): ?><option value="<?= e($a) ?>"><?php endforeach; ?></datalist>
                        <div class="form-text">Si no existe todavía, se dará de alta al aprobarse.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="s-gira">Gira o nombre del evento</label>
                        <input class="form-control" id="s-gira" name="gira" required maxlength="150" value="<?= e($old['gira']) ?>" placeholder="Ej. Will of the People">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="s-recinto">Recinto</label>
                        <select class="form-select" id="s-recinto" name="recinto_id" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($recintos as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= (int) $old['recinto_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nombre'] . ' (' . $r['ciudad'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">¿No está? <a href="#" data-cambiar-tipo="recinto">Solicita primero el recinto</a>.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="s-fecha">Fecha</label>
                        <input class="form-control" id="s-fecha" name="fecha" type="date" required value="<?= e($old['fecha']) ?>"
                               min="<?= FECHA_MIN_CONCIERTO ?>" max="<?= fecha_max_concierto() ?>"
                               data-msg="La fecha debe estar entre el 01/01/1950 y el <?= date('d/m/Y', strtotime(fecha_max_concierto())) ?>.">
                    </div>
                </fieldset>

                <fieldset id="campos-recinto" class="row g-3" <?= $tipo === 'recinto' ? '' : 'hidden disabled' ?>>
                    <div class="col-md-7">
                        <label class="form-label" for="s-nombre">Nombre del recinto</label>
                        <input class="form-control" id="s-nombre" name="nombre" required maxlength="120" value="<?= e($old['nombre']) ?>" placeholder="Ej. Showcenter Complex">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="s-tipo-recinto">Tipo</label>
                        <select class="form-select" id="s-tipo-recinto" name="tipo_recinto" required>
                            <option value="">Selecciona...</option>
                            <?php foreach (TIPOS_RECINTO as $t): ?><option <?= $old['tipo_recinto'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="s-ciudad">Ciudad</label>
                        <input class="form-control" id="s-ciudad" name="ciudad" list="lista-ciudades" required maxlength="80" value="<?= e($old['ciudad']) ?>" placeholder="Ej. Monterrey, N.L.">
                        <datalist id="lista-ciudades"><?php foreach ($ciudades as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="s-capacidad">Capacidad aproximada</label>
                        <input class="form-control" id="s-capacidad" name="capacidad" type="number" min="1" max="<?= CAPACIDAD_MAX ?>" step="1" required
                               value="<?= e((string) $old['capacidad']) ?>" data-msg="Entre 1 y <?= number_format(CAPACIDAD_MAX) ?> personas.">
                    </div>
                </fieldset>

                <div class="mt-3">
                    <label class="form-label" for="s-comentario">Comentario para el equipo (opcional)</label>
                    <textarea class="form-control" id="s-comentario" name="comentario" rows="3" maxlength="<?= COMENTARIO_SOLICITUD_MAX ?>"
                              data-contador="s-contador" placeholder="¿Algo que debamos saber? Por ejemplo, de dónde sacaste la información."><?= e($old['comentario']) ?></textarea>
                    <div class="form-text"><span id="s-contador">0</span>/<?= COMENTARIO_SOLICITUD_MAX ?></div>
                </div>

                <button class="btn btn-indigo mt-4 px-4" type="submit"><i class="bi bi-send"></i> Enviar solicitud</button>
            </form>
        </div>

        <aside class="col-lg-5">
            <h2 class="h4">¿Cómo funciona?</h2>
            <ol class="pasos">
                <li><strong>Envías la solicitud</strong> con los datos del concierto o recinto.</li>
                <li><strong>El equipo la revisa</strong>: que no esté repetida y que los datos tengan sentido.</li>
                <li><strong>Si se aprueba</strong>, aparece en el catálogo y ya puedes marcar que fuiste y reseñarlo.</li>
            </ol>
            <p class="small text-secondary mb-0">Puedes tener hasta <?= MAX_SOLICITUDES_PENDIENTES ?> solicitudes en revisión al mismo tiempo, y cancelarlas mientras no se revisen.</p>
        </aside>
    </div>

    <h2 class="section-title mt-5" id="mis-solicitudes">Mis solicitudes</h2>
    <?php if (!$mias): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i><p class="mb-0">Todavía no has enviado solicitudes.</p></div>
    <?php endif; ?>
    <div class="solicitudes-lista">
        <?php foreach ($mias as $s): ?>
            <article class="solicitud-item">
                <i class="bi <?= $s['tipo'] === 'concierto' ? 'bi-music-note-beamed' : 'bi-building' ?> solicitud-icono" aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <strong><?= e($s['tipo'] === 'concierto' ? $s['artista'] . ' – ' . $s['gira'] : $s['nombre']) ?></strong>
                        <?= badge_estado_solicitud($s['estado']) ?>
                    </div>
                    <div class="small text-secondary"><?= e(detalle_solicitud($s)) ?> · enviada el <?= date('d/m/Y', strtotime($s['creado_en'])) ?></div>
                    <?php if ($s['estado'] === 'rechazada' && $s['motivo_rechazo']): ?>
                        <div class="small mt-1"><?= texto_rechazo($s) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($s['estado'] === 'aprobada' && $s['creado_id']): ?>
                    <a class="btn btn-sm btn-peach" href="<?= url(($s['tipo'] === 'concierto' ? 'event-details.php' : 'recinto.php') . '?id=' . $s['creado_id']) ?>">Ver en el catálogo</a>
                <?php elseif ($s['estado'] === 'pendiente'): ?>
                    <form method="post" data-confirmar="¿Cancelar esta solicitud? El equipo ya no la revisará.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="cancelar">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Cancelar</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
