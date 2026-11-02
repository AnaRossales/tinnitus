<?php
require_once __DIR__ . '/includes/auth.php';

$buscar = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$ciudad = trim($_GET['ciudad'] ?? '');
$tipo = in_array($_GET['tipo'] ?? '', TIPOS_RECINTO, true) ? $_GET['tipo'] : '';
$orden = $_GET['orden'] ?? 'conciertos';

$cond = [];
$params = [];
if ($buscar !== '') { $cond[] = '(r.nombre LIKE ? OR r.ciudad LIKE ?)'; $params[] = "%$buscar%"; $params[] = "%$buscar%"; }
if ($ciudad !== '') { $cond[] = 'r.ciudad = ?'; $params[] = $ciudad; }
if ($tipo !== '') { $cond[] = 'r.tipo = ?'; $params[] = $tipo; }
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

$orderBy = match ($orden) {
    'nombre'    => 'r.nombre',
    'mejores'   => 'promedio IS NULL, promedio DESC',
    'capacidad' => 'r.capacidad DESC',
    default     => 'conciertos DESC, r.nombre',
};
$recintos = q(RECINTO_SELECT . " $where ORDER BY r.estado = 'inactivo', $orderBy", $params)->fetchAll();
$ciudades = q('SELECT DISTINCT ciudad FROM recintos ORDER BY ciudad')->fetchAll(PDO::FETCH_COLUMN);

function recintos_url(array $cambios): string
{
    $params = array_filter(array_merge($_GET, $cambios), fn($v) => $v !== '' && $v !== null);
    return url('recintos.php' . ($params ? '?' . http_build_query($params) : ''));
}

$pageTitle = 'Recintos';
$active = 'recintos';
$conBuscador = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-header">
    <div class="container">
        <h1>Recintos</h1>
        <p>Estadios, arenas, foros y festivales donde se vivieron los conciertos. Entra a uno para ver su historial y su calificación.</p>
    </div>
</section>

<div class="container py-5">
    <form class="filter-bar" method="get" role="search" aria-label="Filtrar recintos">
        <div class="filter-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input class="form-control" type="search" name="q" placeholder="Buscar recinto o ciudad" value="<?= e($buscar) ?>" aria-label="Buscar recinto o ciudad">
        </div>
        <select class="form-select" name="tipo" aria-label="Tipo de recinto" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            <?php foreach (TIPOS_RECINTO as $t): ?><option <?= $tipo === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="orden" aria-label="Ordenar" onchange="this.form.submit()">
            <option value="conciertos" <?= $orden === 'conciertos' ? 'selected' : '' ?>>Con más conciertos</option>
            <option value="mejores" <?= $orden === 'mejores' ? 'selected' : '' ?>>Mejor calificados</option>
            <option value="capacidad" <?= $orden === 'capacidad' ? 'selected' : '' ?>>Más grandes</option>
            <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre (A-Z)</option>
        </select>
        <?php if ($ciudad !== ''): ?><input type="hidden" name="ciudad" value="<?= e($ciudad) ?>"><?php endif; ?>
        <button class="btn btn-indigo" type="submit">Buscar</button>
    </form>

    <div class="chip-row my-4" aria-label="Filtrar por ciudad">
        <a class="chip-filter <?= $ciudad === '' ? 'active' : '' ?>" href="<?= recintos_url(['ciudad' => '']) ?>">Todas las ciudades</a>
        <?php foreach ($ciudades as $ci): ?>
            <a class="chip-filter <?= $ciudad === $ci ? 'active' : '' ?>" href="<?= recintos_url(['ciudad' => $ci]) ?>"><?= e($ci) ?></a>
        <?php endforeach; ?>
    </div>

    <p class="result-count" aria-live="polite">
        <?= count($recintos) ?> recinto(s)
        <?php if ($buscar || $ciudad || $tipo): ?> · <a href="<?= url('recintos.php') ?>">Quitar filtros</a><?php endif; ?>
        <a class="ms-auto small fw-semibold" href="<?= url('solicitar.php?tipo=recinto') ?>"><i class="bi bi-plus-circle"></i> ¿Falta un recinto?</a>
    </p>

    <?php if (!$recintos): ?>
        <div class="empty-state">
            <i class="bi bi-building"></i>
            <p>No encontramos recintos con los filtros seleccionados. Puedes solicitar que agreguemos uno nuevo.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-indigo" href="<?= url('solicitar.php?tipo=recinto' . ($buscar !== '' ? '&nombre=' . urlencode($buscar) : '')) ?>"><i class="bi bi-plus-circle"></i> Solicitar nuevo recinto</a>
                <a class="btn btn-light" href="<?= url('recintos.php') ?>">Ver todos los recintos</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
        <?php foreach ($recintos as $r): ?>
            <div class="col"><?= venue_card($r) ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
