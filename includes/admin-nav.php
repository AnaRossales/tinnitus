<?php
$tabs = [
    'dashboard'   => ['admin-dashboard.php', 'Reportes y métricas'],
    'events'      => ['admin-events.php', 'Conciertos'],
    'venues'      => ['admin-venues.php', 'Recintos'],
    'artists'     => ['admin-artists.php', 'Artistas'],
    'solicitudes' => ['admin-solicitudes.php', 'Solicitudes'],
];
$solicitudesPendientes = (int) q("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'")->fetchColumn();
?>
<section class="admin-header">
    <div class="container">
        <h1><?= e($adminTitle) ?></h1>
        <ul class="nav admin-nav">
            <?php foreach ($tabs as $key => [$file, $label]): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $adminTab === $key ? 'active' : '' ?>" href="<?= url($file) ?>"><?= $label ?>
                        <?php if ($key === 'solicitudes' && $solicitudesPendientes): ?>
                            <span class="badge rounded-pill text-bg-danger" title="Solicitudes en revisión"><?= $solicitudesPendientes ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
