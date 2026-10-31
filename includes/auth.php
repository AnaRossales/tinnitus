<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reglas.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'], $_SESSION['ultima_actividad'])
    && time() - $_SESSION['ultima_actividad'] > MINUTOS_SESION * 60) {
    unset($_SESSION['user']);
    session_regenerate_id(true);
    $_SESSION['flash'][] = ['type' => 'info', 'message' => 'Tu sesión expiró por inactividad. Vuelve a iniciar sesión.'];
}
$_SESSION['ultima_actividad'] = time();

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['rol'] ?? null) === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'nombre'   => $user['nombre'],
        'username' => $user['username'],
        'rol'      => $user['rol'],
    ];
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Inicia sesión para continuar.');
        redirect('auth.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('danger', 'No tienes permisos para entrar a esa sección.');
        redirect('index.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function es_ajax(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function paginar(int $total, int $porPagina): array
{
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min(max(1, (int) ($_GET['pagina'] ?? 1)), $paginas);
    return [$pagina, $paginas, ($pagina - 1) * $porPagina];
}

function paginacion(int $pagina, int $paginas, string $archivo): string
{
    if ($paginas <= 1) return '';
    $link = fn(int $p) => url($archivo . '?' . http_build_query(array_merge($_GET, ['pagina' => $p])));
    $html = '<nav aria-label="Páginas"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item ' . ($pagina <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . $link($pagina - 1) . '">Anterior</a></li>';
    for ($p = 1; $p <= $paginas; $p++) {
        $html .= '<li class="page-item ' . ($p === $pagina ? 'active' : '') . '"><a class="page-link" href="' . $link($p) . '"'
            . ($p === $pagina ? ' aria-current="page"' : '') . '>' . $p . '</a></li>';
    }
    $html .= '<li class="page-item ' . ($pagina >= $paginas ? 'disabled' : '') . '"><a class="page-link" href="' . $link($pagina + 1) . '">Siguiente</a></li>';
    return $html . '</ul></nav>';
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function stars(float $rating): string
{
    $html = '<span class="stars" aria-label="' . number_format($rating, 1) . ' de 5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="bi bi-star-fill"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="bi bi-star-half"></i>';
        } else {
            $html .= '<i class="bi bi-star"></i>';
        }
    }
    return $html . '</span>';
}

function poster(array $c, string $extraClass = ''): string
{
    if (!empty($c['poster_url'])) {
        return '<img class="poster ' . $extraClass . '" src="' . e($c['poster_url']) . '" alt="Póster de ' . e($c['artista']) . '">';
    }
    $gradiente = 'g' . (((int) $c['id'] - 1) % 7 + 1);
    return '<div class="poster poster-generated ' . $gradiente . ' ' . $extraClass . '" role="img" aria-label="Póster de ' . e($c['artista']) . '">'
        . '<span class="poster-artist">' . e($c['artista']) . '</span>'
        . '<span class="poster-tour">' . e($c['gira']) . '</span></div>';
}

function poster_con_rating(array $c): string
{
    $extra = ya_ocurrio($c['fecha'])
        ? ($c['promedio'] ? '<span class="poster-rating"><i class="bi bi-star-fill"></i> ' . $c['promedio'] . '</span>' : '')
        : '<span class="poster-badge">Próximamente</span>';
    return '<div class="poster-wrap">' . poster($c) . $extra . '</div>';
}

function concert_card(array $c, bool $conDatos = true): string
{
    $html = '<a class="concert-card" href="' . url('event-details.php?id=' . $c['id']) . '" aria-label="' . e($c['artista'] . ' – ' . $c['gira']) . '">'
        . poster_con_rating($c);
    if ($conDatos) {
        $html .= '<h3>' . e($c['artista']) . '</h3>'
            . '<div class="small text-secondary text-truncate">' . e($c['gira']) . '</div>'
            . '<div class="small text-secondary">' . e($c['recinto']) . ' · ' . date('d/m/Y', strtotime($c['fecha'])) . '</div>';
    }
    return $html . '</a>';
}

const RECINTO_SELECT = "
    SELECT r.*,
        (SELECT COUNT(*) FROM conciertos c WHERE c.recinto_id = r.id) AS conciertos,
        (SELECT COUNT(*) FROM conciertos c WHERE c.recinto_id = r.id AND c.fecha > CURDATE()) AS proximos,
        (SELECT ROUND(AVG(s.calificacion), 1) FROM resenas s JOIN conciertos c ON c.id = s.concierto_id
         WHERE c.recinto_id = r.id AND s.oculta = 0) AS promedio,
        (SELECT COUNT(*) FROM resenas s JOIN conciertos c ON c.id = s.concierto_id
         WHERE c.recinto_id = r.id AND s.oculta = 0) AS resenas
    FROM recintos r
";

function venue_card(array $r): string
{
    $gradiente = 'g' . (((int) $r['id'] + 2) % 7 + 1);
    $icono = ICONOS_RECINTO[$r['tipo']] ?? 'bi-geo-alt';
    $html = '<a class="venue-card" href="' . url('recinto.php?id=' . $r['id']) . '">'
        . '<div class="venue-band ' . $gradiente . '"><i class="bi ' . $icono . '"></i><span>' . e($r['tipo']) . '</span>'
        . ($r['estado'] === 'inactivo' ? '<span class="poster-badge">Sin eventos nuevos</span>' : '')
        . ($r['proximos'] ? '<span class="poster-badge">' . $r['proximos'] . ' próximo(s)</span>' : '')
        . '</div><div class="venue-body">'
        . '<h3>' . e($r['nombre']) . '</h3>'
        . '<p class="mb-2 text-secondary"><i class="bi bi-geo-alt"></i> ' . e($r['ciudad']) . ' · ' . number_format($r['capacidad']) . ' personas</p>'
        . '<div class="venue-stats"><span><i class="bi bi-music-note-list"></i> ' . $r['conciertos'] . ' concierto(s)</span>'
        . ($r['promedio'] ? '<span><i class="bi bi-star-fill"></i> ' . $r['promedio'] . '</span>' : '<span class="text-secondary">Sin reseñas</span>')
        . '</div></div></a>';
    return $html;
}

function avatar(array $u, string $size = 'md', string $extraClass = ''): string
{
    if (!empty($u['avatar_url'])) {
        return '<img class="avatar avatar-' . $size . ' ' . $extraClass . '" src="' . e($u['avatar_url']) . '" alt="">';
    }
    $initial = mb_strtoupper(mb_substr($u['nombre'] ?? $u['username'], 0, 1));
    return '<span class="avatar avatar-' . $size . ' avatar-initial ' . $extraClass . '">' . e($initial) . '</span>';
}

function deco_star(string $class): string
{
    return '<span class="deco-star ' . $class . '" aria-hidden="true"><svg viewBox="0 0 280 320">'
        . '<path d="M108 0 L160 80 L238 30 L203 130 L278 222 L168 182 L105 314 L100 175 L0 140 L98 113 Z"/></svg></span>';
}

function fecha_larga(string $date): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($date);
    return date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
}

const CONCIERTO_SELECT = "
    SELECT c.*, a.nombre AS artista, a.genero, r.nombre AS recinto, r.ciudad,
           ROUND(AVG(s.calificacion), 1) AS promedio, COUNT(s.id) AS total_resenas
    FROM conciertos c
    JOIN artistas a ON a.id = c.artista_id
    JOIN recintos r ON r.id = c.recinto_id
    LEFT JOIN resenas s ON s.concierto_id = c.id AND s.oculta = 0
";

function stats_concierto(int $conciertoId): array
{
    $rows = q('SELECT calificacion, COUNT(*) AS n FROM resenas
               WHERE concierto_id = ? AND oculta = 0 GROUP BY calificacion', [$conciertoId])->fetchAll();
    $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $total = 0;
    $suma = 0;
    foreach ($rows as $r) {
        $dist[(int) $r['calificacion']] = (int) $r['n'];
        $total += (int) $r['n'];
        $suma += (int) $r['calificacion'] * (int) $r['n'];
    }
    $asistentes = (int) q('SELECT COUNT(*) FROM asistencias WHERE concierto_id = ?', [$conciertoId])->fetchColumn();
    return [
        'promedio'   => $total ? round($suma / $total, 1) : 0,
        'total'      => $total,
        'dist'       => $dist,
        'asistentes' => $asistentes,
    ];
}
