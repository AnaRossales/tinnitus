<?php
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$r = q(RECINTO_SELECT . ' WHERE r.id = ?', [$id])->fetch();
if (!$r) {
    http_response_code(404);
    flash('warning', 'Ese recinto no existe.');
    redirect('recintos.php');
}

$conciertos = q(CONCIERTO_SELECT . ' WHERE c.recinto_id = ? GROUP BY c.id ORDER BY c.fecha DESC', [$id])->fetchAll();
$proximos = array_reverse(array_values(array_filter($conciertos, fn($c) => !ya_ocurrio($c['fecha']))));
$pasados = array_values(array_filter($conciertos, fn($c) => ya_ocurrio($c['fecha'])));

$mejor = null;
foreach ($pasados as $c) {
    if ($c['promedio'] && (!$mejor || $c['promedio'] > $mejor['promedio'])) $mejor = $c;
}

$pageTitle = $r['nombre'];
$active = 'recintos';
require __DIR__ . '/includes/header.php';
?>
<section class="event-header">
    <div class="container">
        <nav aria-label="Ruta de navegación" class="mb-4">
            <ol class="breadcrumb breadcrumb-dark mb-0">
                <li class="breadcrumb-item"><a href="<?= url('recintos.php') ?>">Recintos</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($r['nombre']) ?></li>
            </ol>
        </nav>
        <p class="artista"><i class="bi <?= ICONOS_RECINTO[$r['tipo']] ?? 'bi-geo-alt' ?>"></i> <?= e($r['tipo']) ?></p>
        <h1><?= e($r['nombre']) ?></h1>
        <p class="lugar">
            <a class="link-claro" href="<?= url('recintos.php?ciudad=' . urlencode($r['ciudad'])) ?>"><?= e($r['ciudad']) ?></a>
            | Capacidad para <?= number_format($r['capacidad']) ?> personas
        </p>
        <div class="rating-pill">
            <span class="item"><i class="bi bi-star-fill"></i><span><?= $r['promedio'] ? $r['promedio'] . '/5' : '–' ?></span></span>
            <span class="item" title="Conciertos"><i class="bi bi-music-note-list"></i><?= $r['conciertos'] ?></span>
            <span class="item" title="Reseñas"><i class="bi bi-chat-quote-fill"></i><?= $r['resenas'] ?></span>
        </div>
        <?php if ($r['estado'] === 'inactivo'): ?>
            <p class="mt-4 mb-0"><i class="bi bi-info-circle"></i> Este recinto ya no recibe conciertos nuevos; aquí se conserva su historial.</p>
        <?php endif; ?>
    </div>
</section>

<div class="container py-5">
    <?php if ($mejor): ?>
        <a class="highlight-card mb-5" href="<?= url('event-details.php?id=' . $mejor['id']) ?>">
            <div class="highlight-poster"><?= poster($mejor) ?></div>
            <div>
                <span class="kicker"><i class="bi bi-trophy-fill"></i> El mejor calificado aquí</span>
                <h2><?= e($mejor['artista']) ?></h2>
                <p class="mb-1"><?= e($mejor['gira']) ?> · <?= fecha_larga($mejor['fecha']) ?></p>
                <p class="mb-0"><?= stars((float) $mejor['promedio']) ?> <strong><?= $mejor['promedio'] ?></strong> · <?= $mejor['total_resenas'] ?> reseña(s)</p>
            </div>
        </a>
    <?php endif; ?>

    <?php if ($proximos): ?>
        <h2 class="section-title">Próximamente</h2>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4 mb-5">
            <?php foreach ($proximos as $c): ?><div class="col"><?= concert_card($c) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Conciertos que se vivieron aquí</h2>
    <?php if (!$pasados): ?>
        <div class="empty-state">
            <i class="bi bi-music-note-beamed"></i>
            <p class="mb-0">Todavía no hay conciertos registrados en este recinto.</p>
        </div>
    <?php endif; ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4">
        <?php foreach ($pasados as $c): ?><div class="col"><?= concert_card($c) ?></div><?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
