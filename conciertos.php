<?php
require_once __DIR__ . '/includes/auth.php';

$busqueda = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$genero = trim($_GET['genero'] ?? '');
$recintoId = (int) ($_GET['recinto'] ?? 0);
$anio = (int) ($_GET['anio'] ?? 0);
$orden = $_GET['orden'] ?? 'recientes';
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
const POR_PAGINA = 15;

$cond = [];
$params = [];
if ($busqueda !== '') {
    $cond[] = '(a.nombre LIKE :q1 OR c.gira LIKE :q2 OR r.nombre LIKE :q3 OR r.ciudad LIKE :q4)';
    $like = '%' . $busqueda . '%';
    $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
}
if ($genero !== '') { $cond[] = 'a.genero = :genero'; $params['genero'] = $genero; }
if ($recintoId) { $cond[] = 'c.recinto_id = :recinto'; $params['recinto'] = $recintoId; }
if ($anio) { $cond[] = 'YEAR(c.fecha) = :anio'; $params['anio'] = $anio; }
if ($orden === 'proximos') $cond[] = 'c.fecha > CURDATE()';
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

$orderBy = match ($orden) {
    'populares' => 'total_resenas DESC, promedio DESC',
    'mejores'   => 'AVG(s.calificacion) IS NULL, promedio DESC, total_resenas DESC',
    'proximos'  => 'c.fecha ASC',
    default     => 'c.fecha DESC',
};

$total = (int) q("SELECT COUNT(*) FROM conciertos c JOIN artistas a ON a.id = c.artista_id JOIN recintos r ON r.id = c.recinto_id $where", $params)->fetchColumn();
$paginas = max(1, (int) ceil($total / POR_PAGINA));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * POR_PAGINA;
$conciertos = q(CONCIERTO_SELECT . " $where GROUP BY c.id ORDER BY $orderBy LIMIT " . POR_PAGINA . " OFFSET $offset", $params)->fetchAll();

$generos = q('SELECT DISTINCT genero FROM artistas WHERE genero IS NOT NULL ORDER BY genero')->fetchAll(PDO::FETCH_COLUMN);
$recintos = q('SELECT id, nombre FROM recintos ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
$anios = q('SELECT DISTINCT YEAR(fecha) FROM conciertos ORDER BY 1 DESC')->fetchAll(PDO::FETCH_COLUMN);

function catalogo_url(array $cambios): string
{
    $params = array_filter(array_merge($_GET, ['pagina' => null], $cambios), fn($v) => $v !== '' && $v !== null && $v !== 0);
    return url('conciertos.php' . ($params ? '?' . http_build_query($params) : ''));
}

$activos = [];
if ($busqueda !== '') $activos[] = ['“' . $busqueda . '”', ['q' => '']];
if ($genero !== '') $activos[] = [$genero, ['genero' => '']];
if ($recintoId && isset($recintos[$recintoId])) $activos[] = [$recintos[$recintoId], ['recinto' => '']];
if ($anio) $activos[] = [(string) $anio, ['anio' => '']];

$pageTitle = 'Conciertos';
$active = 'conciertos';
$conBuscador = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-header">
    <div class="container">
        <h1><?= $busqueda !== '' ? 'Resultados' : 'Conciertos' ?></h1>
        <p>Todos los conciertos registrados en Tinnitus. Filtra por género, recinto o año y entra a cualquiera para leer sus reseñas.</p>
    </div>
</section>

<div class="container py-5">
    <form class="filter-bar" method="get" role="search" aria-label="Filtrar conciertos">
        <div class="filter-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input class="form-control" type="search" name="q" placeholder="Artista, gira, recinto o ciudad" value="<?= e($busqueda) ?>" aria-label="Buscar">
        </div>
        <select class="form-select" name="recinto" aria-label="Recinto" onchange="this.form.submit()">
            <option value="">Todos los recintos</option>
            <?php foreach ($recintos as $rid => $rnombre): ?><option value="<?= $rid ?>" <?= $recintoId === $rid ? 'selected' : '' ?>><?= e($rnombre) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="anio" aria-label="Año" onchange="this.form.submit()">
            <option value="">Todos los años</option>
            <?php foreach ($anios as $y): ?><option <?= $anio === (int) $y ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="orden" aria-label="Ordenar" onchange="this.form.submit()">
            <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
            <option value="populares" <?= $orden === 'populares' ? 'selected' : '' ?>>Más reseñados</option>
            <option value="mejores" <?= $orden === 'mejores' ? 'selected' : '' ?>>Mejor calificados</option>
            <option value="proximos" <?= $orden === 'proximos' ? 'selected' : '' ?>>Próximos</option>
        </select>
        <?php if ($genero !== ''): ?><input type="hidden" name="genero" value="<?= e($genero) ?>"><?php endif; ?>
        <button class="btn btn-indigo" type="submit">Buscar</button>
    </form>

    <div class="chip-row my-4" aria-label="Filtrar por género">
        <a class="chip-filter <?= $genero === '' ? 'active' : '' ?>" href="<?= catalogo_url(['genero' => '']) ?>">Todos los géneros</a>
        <?php foreach ($generos as $g): ?>
            <a class="chip-filter <?= $genero === $g ? 'active' : '' ?>" href="<?= catalogo_url(['genero' => $g]) ?>"><?= e($g) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="result-count" aria-live="polite">
        <span><?= $total ?> concierto(s)</span>
        <?php foreach ($activos as [$texto, $quitar]): ?>
            <a class="chip-activo" href="<?= catalogo_url($quitar) ?>" aria-label="Quitar filtro <?= e($texto) ?>"><?= e($texto) ?> <i class="bi bi-x"></i></a>
        <?php endforeach; ?>
        <?php if (count($activos) > 1): ?><a href="<?= url('conciertos.php') ?>">Quitar todos</a><?php endif; ?>
        <a class="ms-auto small fw-semibold" href="<?= url('solicitar.php') ?>"><i class="bi bi-plus-circle"></i> ¿Falta un concierto?</a>
    </div>

    <?php if (!$conciertos): ?>
        <div class="empty-state">
            <i class="bi bi-music-note-beamed"></i>
            <p>No encontramos conciertos con esos filtros. Prueba con otro artista, recinto o género, o pide que lo agreguemos.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-indigo" href="<?= url('solicitar.php' . ($busqueda !== '' ? '?artista=' . urlencode($busqueda) : '')) ?>"><i class="bi bi-plus-circle"></i> Solicitar este concierto</a>
                <a class="btn btn-light" href="<?= url('conciertos.php') ?>">Ver todos los conciertos</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4">
        <?php foreach ($conciertos as $c): ?>
            <div class="col"><?= concert_card($c) ?></div>
        <?php endforeach; ?>
    </div>

    <?php if ($paginas > 1): ?>
        <nav class="mt-5" aria-label="Páginas del catálogo">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= catalogo_url(['pagina' => $pagina - 1]) ?>">Anterior</a></li>
                <?php for ($p = 1; $p <= $paginas; $p++): ?>
                    <li class="page-item <?= $p === $pagina ? 'active' : '' ?>"><a class="page-link" href="<?= catalogo_url(['pagina' => $p]) ?>" <?= $p === $pagina ? 'aria-current="page"' : '' ?>><?= $p ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>"><a class="page-link" href="<?= catalogo_url(['pagina' => $pagina + 1]) ?>">Siguiente</a></li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
