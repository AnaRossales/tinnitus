<?php
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$c = q(CONCIERTO_SELECT . ' WHERE c.id = ? GROUP BY c.id', [$id])->fetch();
if (!$c) {
    http_response_code(404);
    flash('warning', 'Ese concierto no existe.');
    redirect('conciertos.php');
}

$stats = stats_concierto($id);
$user = current_user();
$pasado = ya_ocurrio($c['fecha']);
$diasFaltan = $pasado ? 0 : (int) ceil((strtotime($c['fecha']) - strtotime(date('Y-m-d'))) / 86400);

$resenas = q('SELECT s.*, u.nombre, u.username, u.avatar_url FROM resenas s
              JOIN usuarios u ON u.id = s.usuario_id
              WHERE s.concierto_id = ? AND s.oculta = 0
              ORDER BY s.creado_en DESC', [$id])->fetchAll();

$mismoRecinto = q(CONCIERTO_SELECT . ' WHERE c.recinto_id = ? AND c.id <> ? GROUP BY c.id ORDER BY c.fecha DESC LIMIT 5',
    [$c['recinto_id'], $id])->fetchAll();

$miResena = null;
$asistio = false;
$yaReporte = [];
if ($user) {
    foreach ($resenas as $r) {
        if ((int) $r['usuario_id'] === $user['id']) $miResena = $r;
    }
    $asistio = (bool) q('SELECT 1 FROM asistencias WHERE usuario_id = ? AND concierto_id = ?', [$user['id'], $id])->fetchColumn();
    $yaReporte = q('SELECT rp.resena_id FROM reportes rp JOIN resenas s ON s.id = rp.resena_id
                    WHERE rp.usuario_id = ? AND s.concierto_id = ?', [$user['id'], $id])->fetchAll(PDO::FETCH_COLUMN);
}

const ICONO_REPORTE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">'
    . '<path d="M5 3.5h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-5l-2 3-2-3H5a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2z"/>'
    . '<path d="M12 7v5" stroke-linecap="round"/><circle cx="12" cy="14.3" r=".6" fill="currentColor"/></svg>';

$pageTitle = $c['artista'] . ' – ' . $c['gira'];
$pageScript = 'event.js';
require __DIR__ . '/includes/header.php';
?>
<section class="event-header">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-md-4"><?= poster($c) ?></div>
            <div class="col-md-8">
                <p class="artista"><?= e($c['artista']) ?></p>
                <h1><?= e($c['gira']) ?></h1>
                <p class="lugar">
                    <a class="link-claro" href="<?= url('recinto.php?id=' . $c['recinto_id']) ?>"><i class="bi bi-geo-alt"></i> <?= e($c['recinto']) ?></a>
                    | <a class="link-claro" href="<?= url('recintos.php?ciudad=' . urlencode($c['ciudad'])) ?>"><?= e($c['ciudad']) ?></a>
                    | <?= fecha_larga($c['fecha']) ?>
                </p>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <?php if ($pasado): ?>
                        <div class="rating-pill">
                            <span class="item"><i class="bi bi-star-fill"></i><span><span id="stat-promedio"><?= number_format($stats['promedio'], 1) ?></span>/5</span></span>
                            <span class="item" title="Asistentes"><i class="bi bi-person-fill"></i><span id="stat-asistentes"><?= number_format($stats['asistentes']) ?></span></span>
                            <span class="d-none" id="stat-total"><?= $stats['total'] ?></span>
                        </div>
                        <?php if ($user): ?>
                            <button class="btn <?= $asistio ? 'btn-peach' : 'btn-outline-white' ?>" id="btn-asistencia" aria-pressed="<?= $asistio ? 'true' : 'false' ?>"
                                    data-concierto="<?= $id ?>" data-tiene-resena="<?= $miResena ? '1' : '0' ?>">
                                <i class="bi <?= $asistio ? 'bi-check-circle-fill' : 'bi-plus-circle' ?>"></i>
                                <span><?= $asistio ? 'Fui a este concierto' : 'Marcar que fui' ?></span>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="rating-pill chico"><i class="bi bi-hourglass-split"></i> Próximamente · faltan <?= $diasFaltan ?> día(s)</div>
                    <?php endif; ?>
                </div>
                <?php if ($c['descripcion']): ?><p class="mt-4 mb-0 opacity-75"><?= e($c['descripcion']) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="container py-5 my-lg-4">
    <div class="row g-5">
        <div class="col-lg-7">
            <?php if (!$pasado): ?>
                <div class="empty-state">
                    <i class="bi bi-hourglass-split"></i>
                    <p class="mb-0">Este concierto todavía no sucede. Las reseñas se abren el <?= date('d/m/Y', strtotime($c['fecha'])) ?>.</p>
                </div>
            <?php elseif ($user): ?>
                <form id="form-resena" class="review review-form" data-concierto="<?= $id ?>" novalidate>
                    <?= avatar(['nombre' => $user['nombre']], 'sm', 'avatar-propio') ?>
                    <div class="nombre" id="form-resena-titulo"><?= e($user['nombre']) ?></div>
                    <div class="star-input" role="radiogroup" aria-label="Calificación">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="calificacion" id="star<?= $i ?>" value="<?= $i ?>" <?= $miResena && (int) $miResena['calificacion'] === $i ? 'checked' : '' ?>>
                            <label for="star<?= $i ?>" title="<?= $i ?> estrella(s)"><i class="bi bi-star-fill"></i></label>
                        <?php endfor; ?>
                        <span class="star-label" id="etiqueta-estrellas" aria-live="polite"></span>
                    </div>
                    <textarea class="form-control mt-2" name="texto" rows="2" minlength="<?= RESENA_MIN ?>" maxlength="<?= RESENA_MAX ?>"
                              aria-label="Tu reseña" placeholder="Escribe tu reseña..."><?= e($miResena['texto'] ?? '') ?></textarea>
                    <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                        <small class="meta" id="ayuda-resena" data-min="<?= RESENA_MIN ?>" data-max="<?= RESENA_MAX ?>">
                            <span id="contador">0</span>/<?= RESENA_MAX ?> · mínimo <?= RESENA_MIN ?>
                        </small>
                        <div class="d-flex gap-2">
                            <button type="button" id="btn-borrar-resena" class="btn btn-outline-danger btn-sm <?= $miResena ? '' : 'd-none' ?>">Eliminar</button>
                            <button type="submit" id="btn-publicar" class="btn btn-indigo btn-sm px-4 d-none"><?= $miResena ? 'Actualizar' : 'Publicar' ?></button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="review">
                    <span class="avatar avatar-sm avatar-propio"><i class="bi bi-lock"></i></span>
                    <div class="nombre">Escribe tu reseña</div>
                    <p class="texto"><a href="<?= url('auth.php?next=' . urlencode($_SERVER['REQUEST_URI'])) ?>">Inicia sesión</a> para calificar este concierto.</p>
                </div>
            <?php endif; ?>

            <div id="lista-resenas">
                <?php foreach ($resenas as $r): ?>
                    <?php if ($user && (int) $r['usuario_id'] === $user['id']) continue; ?>
                    <article class="review">
                        <?= avatar($r, 'sm') ?>
                        <a class="nombre" href="<?= url('profile.php?u=' . urlencode($r['username'])) ?>"><?= e($r['nombre']) ?></a>
                        <div class="d-flex align-items-center gap-2"><?= stars((float) $r['calificacion']) ?> <span class="meta">@<?= e($r['username']) ?> · <?= date('d/m/Y', strtotime($r['creado_en'])) ?></span></div>
                        <p class="texto"><?= nl2br(e($r['texto'])) ?></p>
                        <?php if ($user): $reportada = in_array($r['id'], $yaReporte); ?>
                            <button class="btn-reportar <?= $reportada ? 'reportada' : '' ?>" data-resena="<?= $r['id'] ?>"
                                    <?= $reportada ? 'disabled title="Ya la reportaste"' : 'title="Reportar reseña"' ?> aria-label="Reportar reseña"><?= ICONO_REPORTE ?></button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if ($pasado && !$resenas): ?>
                    <div class="empty-state" id="sin-resenas">
                        <i class="bi bi-chat-square-heart"></i>
                        <p class="mb-0">Todavía no hay reseñas. ¡Sé la primera persona en opinar!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="col-lg-5 col-xl-4 offset-xl-1">
            <div class="dist-card position-sticky" style="top: 100px" id="distribucion">
                <?php for ($i = 5; $i >= 1; $i--):
                    $pct = $stats['total'] ? round($stats['dist'][$i] * 100 / $stats['total']) : 0; ?>
                    <div class="dist-row" data-estrellas="<?= $i ?>">
                        <span class="num"><?= $i ?> <i class="bi bi-star-fill"></i></span>
                        <div class="progress" role="progressbar" aria-label="<?= $i ?> estrellas" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: <?= $pct ?>%"></div></div>
                        <span class="pct"><?= $pct ?>%</span>
                    </div>
                <?php endfor; ?>
            </div>
        </aside>
    </div>

    <?php if ($mismoRecinto): ?>
        <div class="d-flex justify-content-between align-items-baseline mt-5 pt-4">
            <h2 class="section-title mb-0">Más en <?= e($c['recinto']) ?></h2>
            <a class="ver-mas" href="<?= url('recinto.php?id=' . $c['recinto_id']) ?>">Ver recinto...</a>
        </div>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4 mt-1">
            <?php foreach ($mismoRecinto as $otro): ?><div class="col"><?= concert_card($otro) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modal-reporte" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="form-reporte">
            <div class="modal-header">
                <h2 class="modal-title h5">Reportar reseña</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary">Un administrador revisará la reseña. Si varias personas la reportan, se oculta mientras tanto. ¿Cuál es el problema?</p>
                <input type="hidden" name="resena_id">
                <?php foreach (['Lenguaje ofensivo', 'Spam o publicidad', 'No es sobre este concierto', 'Otro'] as $i => $motivo): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="motivo" id="motivo<?= $i ?>" value="<?= e($motivo) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="motivo<?= $i ?>"><?= e($motivo) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Enviar reporte</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
