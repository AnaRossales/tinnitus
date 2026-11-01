<?php
require_once __DIR__ . '/includes/auth.php';

$equipo = [
    ['Ana Haydee Rosales Martínez', '2065579'],
];

$pageTitle = 'Sobre Tinnitus';
$active = 'about';
require __DIR__ . '/includes/header.php';
?>
<section class="page-header">
    <div class="container">
        <h1>Sobre Tinnitus</h1>
        <p class="mb-0 col-lg-8">Ese pitido en los oídos después de una noche frente al escenario es la prueba de que estuviste ahí.</p>
    </div>
</section>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-7">
            <h2 class="section-title">Nuestra historia</h2>
            <p>El nombre viene del fenómeno auditivo: el zumbido residual que queda después de un concierto con decibeles altos.
                Lo tomamos como metáfora de cómo la música en vivo sigue resonando mucho después de que se apagan las luces del recinto.</p>
            <p>Notamos que no existía un lugar dedicado a guardar el historial de los conciertos a los que vamos.
                Las fotos se pierden en el celular y las pulseras en un cajón. Tinnitus es un diario digital para no olvidar ninguno.</p>
            <h2 class="section-title mt-5">Misión</h2>
            <p>Construir la memoria colectiva de la música en vivo: un catálogo ordenado de artistas, recintos y conciertos,
                alimentado por las reseñas honestas de quienes estuvieron ahí.</p>
        </div>
        <div class="col-lg-5">
            <h2 class="section-title">Nuestra comunidad</h2>
            <div class="d-flex gap-3 mb-4">
                <span class="feature-icon"><i class="bi bi-journal-bookmark"></i></span>
                <div><strong>Diario personal</strong><p class="text-secondary mb-0">Marca los conciertos a los que fuiste y arma tu historial.</p></div>
            </div>
            <div class="d-flex gap-3 mb-4">
                <span class="feature-icon"><i class="bi bi-star"></i></span>
                <div><strong>Reseñas honestas</strong><p class="text-secondary mb-0">Califica de 1 a 5 estrellas y cuenta qué tal estuvo.</p></div>
            </div>
            <div class="d-flex gap-3 mb-4">
                <span class="feature-icon"><i class="bi bi-trophy"></i></span>
                <div><strong>Tu Top 4</strong><p class="text-secondary mb-0">Presume los cuatro conciertos de tu vida en tu perfil.</p></div>
            </div>
            <div class="d-flex gap-3">
                <span class="feature-icon"><i class="bi bi-shield-check"></i></span>
                <div><strong>Espacio seguro</strong><p class="text-secondary mb-0">Cualquiera puede reportar reseñas ofensivas y el equipo las modera.</p></div>
            </div>
        </div>
    </div>

    <h2 class="section-title mt-5">Desarrollo</h2>
    <p class="text-secondary">Grupo 238 · Laboratorio de Programación Web · FIME UANL</p>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
        <?php foreach ($equipo as [$nombre, $matricula]): ?>
            <div class="col">
                <div class="team-card">
                    <?= avatar(['nombre' => $nombre], 'md') ?>
                    <div class="fw-semibold mt-2 small"><?= e($nombre) ?></div>
                    <div class="text-secondary small"><?= e($matricula) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
