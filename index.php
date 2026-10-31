<?php
require_once __DIR__ . '/includes/auth.php';

$tendencia = q(CONCIERTO_SELECT . "
    WHERE c.fecha <= CURDATE()
    GROUP BY c.id
    ORDER BY total_resenas DESC, promedio DESC
    LIMIT 5")->fetchAll();

$destacados = q(CONCIERTO_SELECT . " WHERE c.destacado = 1 GROUP BY c.id ORDER BY c.fecha DESC LIMIT 3")->fetchAll();

$pageTitle = 'Inicio';
$conBuscador = true;
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h1>Sigue el <span>zumbido</span></h1>
                <p class="mt-3">Registra los conciertos a los que fuiste, califica la experiencia y descubre qué está viendo tu comunidad musical esta semana.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="btn btn-outline-white" href="<?= url('conciertos.php') ?>">Explorar conciertos</a>
                    <?php if (!is_logged_in()): ?>
                        <a class="btn btn-peach" href="<?= url('auth.php?tab=registro') ?>">Registrarme</a>
                    <?php else: ?>
                        <a class="btn btn-peach" href="<?= url('profile.php') ?>">Mi perfil</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 d-none d-md-block">
                <div class="hero-stack">
                    <?php foreach ($destacados as $c): ?>
                        <a href="<?= url('event-details.php?id=' . $c['id']) ?>" class="hero-card" aria-label="<?= e($c['artista']) ?>"><?= poster_con_rating($c) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="intro">
    <?= deco_star('deco-star-right') ?>
    <?= deco_star('deco-star-left') ?>
    <div class="container">
        <img class="logo-centro" src="<?= url('assets/img/logo-azul.png') ?>" alt="Tinnitus">
        <p>Tinnitus es el diario de la música en vivo. Ese zumbido que queda en los oídos después de una noche frente al escenario
            es la prueba de que estuviste ahí, y aquí lo convertimos en memoria: registra cada concierto al que asististe,
            califícalo de una a cinco estrellas, lee lo que opinó el resto del público y arma tu Top 4 de conciertos de toda la vida
            para presumirlo en tu perfil.</p>
    </div>
</section>

<section class="container pb-5">
    <h2 class="section-title">En tendencia</h2>
    <div class="trend-row">
        <?php foreach ($tendencia as $c): ?>
            <?= concert_card($c, false) ?>
        <?php endforeach; ?>
    </div>
    <div class="text-end mt-3"><a class="ver-mas" href="<?= url('conciertos.php?orden=populares') ?>">Ver más...</a></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
