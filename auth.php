<?php
require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['check_username'])) {
    $u = strtolower(trim($_GET['check_username']));
    $error = validar_username($u);
    if (!$error && q('SELECT 1 FROM usuarios WHERE username = ?', [$u])->fetchColumn()) {
        $error = 'Ese nombre de usuario ya está ocupado.';
    }
    json_response(['ok' => true, 'disponible' => $error === null, 'error' => $error]);
}

$next = $_GET['next'] ?? $_POST['next'] ?? '';
if (!preg_match('#^/(?!/)#', $next)) {
    $next = '';
}

function go_after_login(string $next): void
{
    if ($next !== '') {
        header('Location: ' . $next);
        exit;
    }
    redirect(is_admin() ? 'admin-dashboard.php' : 'profile.php');
}

if (is_logged_in()) {
    go_after_login($next);
}

$tab = ($_GET['tab'] ?? '') === 'registro' ? 'registro' : 'login';
$errores = [];
$old = ['login' => '', 'nombre' => '', 'username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'login') {
        $tab = 'login';
        $old['login'] = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($old['login'] === '' || $password === '') {
            $errores[] = 'Escribe tu usuario y contraseña.';
        } else {
            $user = q('SELECT *, (bloqueado_hasta > NOW()) AS bloqueado,
                              CEIL(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) / 60) AS minutos_restantes
                       FROM usuarios WHERE username = ? OR email = ? LIMIT 1',
                [strtolower($old['login']), strtolower($old['login'])])->fetch();

            if ($user && $user['bloqueado']) {
                $errores[] = 'Cuenta bloqueada por demasiados intentos fallidos. Intenta de nuevo en ' . $user['minutos_restantes'] . ' minuto(s).';
            } elseif ($user && password_verify($password, $user['password_hash'])) {
                q('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?', [$user['id']]);
                login_user($user);
                flash('success', '¡Hola de nuevo, ' . $user['nombre'] . '!');
                go_after_login($next);
            } elseif ($user) {
                q('UPDATE usuarios
                   SET bloqueado_hasta = IF(intentos_fallidos + 1 >= ?, NOW() + INTERVAL ? MINUTE, NULL),
                       intentos_fallidos = IF(intentos_fallidos + 1 >= ?, 0, intentos_fallidos + 1)
                   WHERE id = ?', [MAX_INTENTOS_LOGIN, MINUTOS_BLOQUEO, MAX_INTENTOS_LOGIN, $user['id']]);
                $restantes = MAX_INTENTOS_LOGIN - ((int) $user['intentos_fallidos'] + 1);
                $errores[] = $restantes > 0
                    ? "Usuario o contraseña incorrectos. Te quedan $restantes intento(s)."
                    : 'Demasiados intentos fallidos. Tu cuenta quedó bloqueada ' . MINUTOS_BLOQUEO . ' minutos.';
            } else {
                $errores[] = 'Usuario o contraseña incorrectos.';
            }
        }
    }

    if ($accion === 'registro') {
        $tab = 'registro';
        $old['nombre'] = trim($_POST['nombre'] ?? '');
        $old['username'] = strtolower(trim($_POST['username'] ?? ''));
        $old['email'] = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($e = validar_nombre($old['nombre'])) $errores[] = $e;
        if ($e = validar_username($old['username'])) $errores[] = $e;
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 150) {
            $errores[] = 'El correo no es válido.';
        }
        if ($e = validar_password($password, $old['username'])) $errores[] = $e;
        if ($password !== $password2) $errores[] = 'Las contraseñas no coinciden.';

        if (!$errores) {
            $existe = q('SELECT username, email FROM usuarios WHERE username = ? OR email = ?',
                [$old['username'], $old['email']])->fetch();
            if ($existe) {
                $errores[] = $existe['username'] === $old['username'] ? 'Ese nombre de usuario ya está ocupado.' : 'Ya existe una cuenta con ese correo.';
            }
        }

        if (!$errores) {
            q('INSERT INTO usuarios (nombre, username, email, password_hash) VALUES (?, ?, ?, ?)',
                [$old['nombre'], $old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $user = q('SELECT * FROM usuarios WHERE id = ?', [db()->lastInsertId()])->fetch();
            login_user($user);
            flash('success', '¡Bienvenido a Tinnitus! Empieza marcando los conciertos a los que fuiste.');
            go_after_login($next);
        }
    }
}

$pageTitle = $tab === 'registro' ? 'Crear cuenta' : 'Iniciar sesión';
$pageScript = 'auth.js';
$navMinima = true;
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-page">
    <h1>Sube el volumen</h1>
    <p class="subtitulo" id="auth-subtitulo"><?= $tab === 'registro' ? 'Crea tu cuenta y empieza tu diario de conciertos' : 'Ingresa y sigue registrando noches inolvidables' ?></p>

    <div class="auth-card">
        <?php if ($errores): ?>
            <div class="alert alert-danger d-flex gap-2 mx-auto mb-4" style="max-width: 520px" role="alert">
                <i class="bi bi-exclamation-octagon"></i>
                <ul class="mb-0 ps-3"><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <div class="auth-panel" id="tab-login" <?= $tab === 'login' ? '' : 'hidden' ?>>
            <form method="post" class="needs-validation auth-fields" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="login">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="line-field">
                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                    <input class="form-control" id="login" name="login" required maxlength="150" autocomplete="username"
                           placeholder="Nombre de usuario" aria-label="Nombre de usuario o correo" value="<?= e($old['login']) ?>">
                    <div class="invalid-feedback">Escribe tu usuario o correo.</div>
                </div>
                <div class="line-field">
                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                    <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password"
                           placeholder="Contraseña" aria-label="Contraseña">
                    <button class="toggle-pass" type="button" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                    <div class="invalid-feedback">Escribe tu contraseña.</div>
                </div>
                <div class="text-center">
                    <button class="btn btn-indigo" type="submit">Ingresar</button>
                    <p class="auth-switch">¿Aún no tienes una cuenta? <a href="#" data-go-tab="registro">Regístrate</a></p>
                </div>
            </form>
        </div>

        <div class="auth-panel" id="tab-registro" <?= $tab === 'registro' ? '' : 'hidden' ?>>
            <form method="post" class="needs-validation auth-fields" id="form-registro" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="registro">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="line-field">
                    <i class="bi bi-person-vcard-fill" aria-hidden="true"></i>
                    <input class="form-control" id="nombre" name="nombre" required maxlength="100" placeholder="Nombre" aria-label="Nombre"
                           pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ '\-]{1,99}" value="<?= e($old['nombre']) ?>">
                    <div class="invalid-feedback">Solo letras, espacios, apóstrofos o guiones (2 a 100).</div>
                </div>
                <div class="line-field">
                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                    <input class="form-control" id="username" name="username" required maxlength="30" placeholder="Nombre de usuario" aria-label="Nombre de usuario"
                           pattern="[a-z0-9_]{3,30}" autocomplete="off" value="<?= e($old['username']) ?>">
                    <div class="invalid-feedback" id="username-feedback">3 a 30: minúsculas, números o guion bajo.</div>
                    <div class="valid-feedback">¡Disponible!</div>
                </div>
                <div class="line-field">
                    <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                    <input class="form-control" id="email" name="email" type="email" required maxlength="150" autocomplete="email"
                           placeholder="Correo" aria-label="Correo" value="<?= e($old['email']) ?>">
                    <div class="invalid-feedback">Escribe un correo válido.</div>
                </div>
                <div class="line-field">
                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                    <input class="form-control" id="reg-password" name="password" type="password" required placeholder="Contraseña" aria-label="Contraseña"
                           minlength="<?= PASSWORD_MIN ?>" maxlength="<?= PASSWORD_MAX ?>" autocomplete="new-password">
                    <button class="toggle-pass" type="button" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                </div>
                <div class="pass-meter" aria-hidden="true"><span id="pass-meter-bar"></span></div>
                <ul class="pass-rules" id="pass-rules">
                    <li data-rule="largo"><i class="bi bi-circle"></i> Entre <?= PASSWORD_MIN ?> y <?= PASSWORD_MAX ?> caracteres</li>
                    <li data-rule="letra"><i class="bi bi-circle"></i> Al menos una letra</li>
                    <li data-rule="numero"><i class="bi bi-circle"></i> Al menos un número</li>
                    <li data-rule="usuario"><i class="bi bi-circle"></i> No contiene tu nombre de usuario</li>
                </ul>
                <div class="line-field">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <input class="form-control" id="password2" name="password2" type="password" required autocomplete="new-password"
                           placeholder="Confirmar contraseña" aria-label="Confirmar contraseña">
                    <div class="invalid-feedback">Las contraseñas no coinciden.</div>
                </div>
                <div class="text-center">
                    <button class="btn btn-indigo" type="submit">Crear cuenta</button>
                    <p class="auth-switch">¿Ya tienes cuenta? <a href="#" data-go-tab="login">Inicia sesión</a></p>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
