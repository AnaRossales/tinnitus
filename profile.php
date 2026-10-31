<?php
require_once __DIR__ . '/includes/auth.php';

$me = current_user();
$username = $_GET['u'] ?? null;
if ($username === null) {
    require_login();
    $username = $me['username'];
}

$perfil = q('SELECT id, nombre, username, bio, avatar_url, creado_en FROM usuarios WHERE username = ?', [$username])->fetch();
if (!$perfil) {
    http_response_code(404);
    flash('warning', 'Ese usuario no existe.');
    redirect('index.php');
}
$esMio = $me && $me['id'] === (int) $perfil['id'];

$errores = [];
if ($esMio && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nombre = trim($_POST['nombre'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $avatarUrl = $perfil['avatar_url'];

    if ($e = validar_nombre($nombre)) $errores[] = $e;
    if (mb_strlen($bio) > BIO_MAX) $errores[] = 'La biografía no puede pasar de ' . BIO_MAX . ' caracteres.';

    if (!$errores && !empty($_FILES['avatar']['name'])) {
        $archivo = $_FILES['avatar'];
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $archivo['error'] === UPLOAD_ERR_OK ? mime_content_type($archivo['tmp_name']) : null;
        if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['size'] > AVATAR_MAX_BYTES) {
            $errores[] = 'La foto no puede pesar más de 2 MB.';
        } elseif (!isset($tipos[$mime]) || !getimagesize($archivo['tmp_name'])) {
            $errores[] = 'La foto debe ser una imagen JPG, PNG o WEBP.';
        } else {
            $nombreArchivo = 'u' . $perfil['id'] . '_' . time() . '.' . $tipos[$mime];
            $carpeta = __DIR__ . '/uploads/avatars/';
            if (!is_dir($carpeta)) mkdir($carpeta, 0775, true);
            move_uploaded_file($archivo['tmp_name'], $carpeta . $nombreArchivo);
            if ($perfil['avatar_url']) {
                $anterior = $carpeta . basename($perfil['avatar_url']);
                if (is_file($anterior)) unlink($anterior);
            }
            $avatarUrl = url('uploads/avatars/' . $nombreArchivo);
        }
    }

    if (!$errores) {
        q('UPDATE usuarios SET nombre = ?, bio = ?, avatar_url = ? WHERE id = ?', [$nombre, $bio ?: null, $avatarUrl, $perfil['id']]);
        $_SESSION['user']['nombre'] = $nombre;
        flash('success', 'Perfil actualizado.');
        redirect('profile.php');
    }
}

$top4 = [];
foreach (q('SELECT t.posicion, c.id, c.gira, c.poster_url, a.nombre AS artista FROM top4 t
            JOIN conciertos c ON c.id = t.concierto_id JOIN artistas a ON a.id = c.artista_id
            WHERE t.usuario_id = ?', [$perfil['id']]) as $row) {
    $top4[(int) $row['posicion']] = $row;
}

$historial = q('SELECT c.id, c.gira, c.fecha, a.nombre AS artista, r.nombre AS recinto,
                       s.calificacion, s.texto
                FROM asistencias asi
                JOIN conciertos c ON c.id = asi.concierto_id
                JOIN artistas a ON a.id = c.artista_id
                JOIN recintos r ON r.id = c.recinto_id
                LEFT JOIN resenas s ON s.concierto_id = c.id AND s.usuario_id = asi.usuario_id
                WHERE asi.usuario_id = ?
                ORDER BY c.fecha DESC', [$perfil['id']])->fetchAll();

$totalResenas = (int) q('SELECT COUNT(*) FROM resenas WHERE usuario_id = ?', [$perfil['id']])->fetchColumn();

$catalogo = $esMio
    ? array_map(fn($h) => $h + ['anio' => substr($h['fecha'], 0, 4)], $historial)
    : [];

$pageTitle = $perfil['nombre'];
$active = $esMio ? 'profile' : '';
$pageScript = 'profile.js';
require __DIR__ . '/includes/header.php';
?>
<section class="profile-header">
    <div class="container">
        <div class="profile-top">
            <?= avatar($perfil, 'xl') ?>
            <div class="pb-1">
                <h1><?= e($perfil['nombre']) ?></h1>
                <p class="handle">@<?= e($perfil['username']) ?></p>
            </div>
            <?php if ($esMio): ?>
                <button class="btn btn-outline-white btn-editar-perfil" data-bs-toggle="modal" data-bs-target="#modal-perfil"><i class="bi bi-pencil"></i> Editar perfil</button>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="container">
    <p class="profile-bio"><?= $perfil['bio'] ? nl2br(e($perfil['bio'])) : 'Sin biografía todavía.' ?>
        <span class="d-block small mt-2"><?= count($historial) ?> conciertos · <?= $totalResenas ?> reseñas</span></p>

    <?php if ($errores): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <h2 class="section-title text-center mt-5">Top 4</h2>
    <div class="top4-grid mb-5 pb-5">
        <?php for ($pos = 1; $pos <= 4; $pos++): $item = $top4[$pos] ?? null; ?>
            <div class="position-relative">
                <?php if ($item): ?>
                    <a class="concert-card" href="<?= url('event-details.php?id=' . $item['id']) ?>" aria-label="<?= e($item['artista']) ?>"><?= poster($item) ?></a>
                    <?php if ($esMio): ?>
                        <div class="top4-tools">
                            <button class="btn btn-light btn-sm btn-top4" data-posicion="<?= $pos ?>" title="Cambiar" aria-label="Cambiar"><i class="bi bi-arrow-repeat"></i></button>
                            <button class="btn btn-light btn-sm btn-top4-quitar" data-posicion="<?= $pos ?>" title="Quitar" aria-label="Quitar"><i class="bi bi-x-lg"></i></button>
                        </div>
                    <?php endif; ?>
                <?php elseif ($esMio): ?>
                    <a href="#" class="top4-slot btn-top4" data-posicion="<?= $pos ?>"><i class="bi bi-plus-lg fs-2"></i><span class="small">Agregar concierto</span></a>
                <?php else: ?>
                    <div class="top4-slot"><i class="bi bi-music-note fs-2"></i></div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <div class="history-box mb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
            <h2>Historial</h2>
            <?php if ($esMio): ?>
                <a class="small fw-semibold" href="<?= url('solicitar.php') ?>"><i class="bi bi-plus-circle"></i> ¿Falta un concierto? Solicítalo</a>
            <?php endif; ?>
        </div>
        <?php if (!$historial): ?>
            <p class="text-secondary mb-0"><?= $esMio ? 'Aún no marcas ningún concierto. Entra a uno y presiona "Marcar que fui".' : 'Esta persona aún no registra conciertos.' ?></p>
        <?php endif; ?>
        <?php foreach ($historial as $h): ?>
            <a class="history-item flex-column flex-sm-row" href="<?= url('event-details.php?id=' . $h['id']) ?>">
                <div>
                    <strong><?= e($h['artista']) ?></strong> <span class="text-secondary">— <?= e($h['gira']) ?></span>
                    <div class="small text-secondary"><?= e($h['recinto']) ?> · <?= fecha_larga($h['fecha']) ?></div>
                    <?php if ($h['texto']): ?><div class="small mt-1 fst-italic">“<?= e(mb_strimwidth($h['texto'], 0, 120, '…')) ?>”</div><?php endif; ?>
                </div>
                <div class="text-nowrap"><?= $h['calificacion'] ? stars((float) $h['calificacion']) : '<span class="small text-secondary">Sin reseña</span>' ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($esMio): ?>
<div class="modal fade" id="modal-perfil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content needs-validation" method="post" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <div class="modal-header"><h2 class="modal-title h5">Editar perfil</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="p-nombre">Nombre</label>
                    <input class="form-control" id="p-nombre" name="nombre" required maxlength="100"
                           pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ '\-]{1,99}" value="<?= e($perfil['nombre']) ?>">
                    <div class="invalid-feedback">De 2 a 100 caracteres: solo letras, espacios, apóstrofos o guiones.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="p-bio">Biografía</label>
                    <textarea class="form-control" id="p-bio" name="bio" rows="3" maxlength="<?= BIO_MAX ?>" data-contador="bio-contador"><?= e($perfil['bio']) ?></textarea>
                    <div class="form-text"><span id="bio-contador">0</span>/<?= BIO_MAX ?></div>
                </div>
                <div>
                    <label class="form-label" for="p-avatar">Foto de perfil (JPG, PNG o WEBP, máx. 2 MB)</label>
                    <input class="form-control" id="p-avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= AVATAR_MAX_BYTES ?>">
                    <div class="invalid-feedback" id="avatar-feedback">La foto debe ser JPG, PNG o WEBP de máximo 2 MB.</div>
                    <img id="avatar-preview" class="avatar avatar-lg mt-3 d-none" alt="Vista previa">
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modal-top4" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">Elige un concierto para la posición <span id="top4-pos"></span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="small text-secondary">Solo puedes elegir conciertos de tu historial (a los que marcaste que fuiste).</p>
                <?php if (!$catalogo): ?>
                    <div class="empty-state"><i class="bi bi-journal-x"></i><p class="mb-0">Tu historial está vacío. Entra a un concierto y presiona "Marcar que fui".</p></div>
                <?php else: ?>
                    <input class="form-control mb-3" id="top4-buscar" type="search" placeholder="Buscar artista o gira...">
                <?php endif; ?>
                <div class="list-group" id="top4-lista">
                    <?php foreach ($catalogo as $c): ?>
                        <button type="button" class="list-group-item list-group-item-action" data-concierto="<?= $c['id'] ?>">
                            <strong><?= e($c['artista']) ?></strong> — <?= e($c['gira']) ?> <span class="text-secondary">(<?= $c['anio'] ?>)</span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
