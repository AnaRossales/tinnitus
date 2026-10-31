<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$active = $active ?? '';
$conBuscador = $conBuscador ?? false;
$navMinima = $navMinima ?? false;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Tinnitus') ?> · Tinnitus</title>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Open+Sans:ital,wght@0,300;0,400;0,600;0,700;1,400&display=swap">
    <link rel="stylesheet" href="<?= url('assets/css/styles.css') ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark tn-navbar sticky-top">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand py-0" href="<?= url('index.php') ?>">
            <img class="tn-logo" src="<?= url('assets/img/logo-blanco.png') ?>" alt="Tinnitus">
        </a>
        <?php if (!$navMinima): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <?php if ($conBuscador): ?>
            <form class="tn-search mx-lg-auto my-3 my-lg-0" action="<?= url('conciertos.php') ?>" method="get" role="search">
                <input class="form-control" type="search" name="q" placeholder="Busca conciertos, artistas o recintos..."
                       value="<?= e($_GET['q'] ?? '') ?>" aria-label="Buscar" maxlength="100">
                <button class="btn" type="submit" aria-label="Buscar"><i class="bi bi-search"></i></button>
            </form>
            <?php endif; ?>
            <ul class="navbar-nav align-items-lg-center gap-lg-4 ms-auto">
                <li class="nav-item"><a class="nav-link nav-dim <?= $active === 'conciertos' ? 'active' : '' ?>" href="<?= url('conciertos.php') ?>" <?= $active === 'conciertos' ? 'aria-current="page"' : '' ?>>Conciertos</a></li>
                <li class="nav-item"><a class="nav-link nav-dim <?= $active === 'recintos' ? 'active' : '' ?>" href="<?= url('recintos.php') ?>" <?= $active === 'recintos' ? 'aria-current="page"' : '' ?>>Recintos</a></li>
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link <?= $active === 'profile' ? 'active' : '' ?>" href="<?= url('profile.php') ?>">Mi perfil</a></li>
                    <?php if ($user['rol'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link nav-dim <?= $active === 'admin' ? 'active' : '' ?>" href="<?= url('admin-dashboard.php') ?>">Admin</a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <form action="<?= url('logout.php') ?>" method="post">
                            <?= csrf_field() ?>
                            <button class="btn btn-peach" type="submit">Salir</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= url('auth.php') ?>">Iniciar Sesión</a></li>
                    <li class="nav-item"><a class="btn btn-peach mt-2 mt-lg-0" href="<?= url('auth.php?tab=registro') ?>">Registrarme</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>

<?php $flashes = take_flashes(); if ($flashes): ?>
<div class="container mt-3 flash-zone">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<main>
