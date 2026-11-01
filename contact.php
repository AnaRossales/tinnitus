<?php
require_once __DIR__ . '/includes/auth.php';

$errores = [];
$datos = ['nombre' => '', 'email' => '', 'asunto' => '', 'mensaje' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($datos as $campo => $_) {
        $datos[$campo] = trim($_POST[$campo] ?? '');
    }

    if (($_POST['sitio_web'] ?? '') !== '') {
        flash('success', '¡Gracias! Recibimos tu mensaje y te responderemos pronto.');
        redirect('contact.php');
    }

    if ($e = validar_nombre($datos['nombre'])) $errores[] = $e;
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL) || strlen($datos['email']) > 150) $errores[] = 'El correo no es válido.';
    if (!in_array($datos['asunto'], ASUNTOS_CONTACTO, true)) $errores[] = 'Elige un asunto de la lista.';
    $largo = mb_strlen($datos['mensaje']);
    if ($largo < MENSAJE_MIN || $largo > MENSAJE_MAX) $errores[] = 'El mensaje debe tener entre ' . MENSAJE_MIN . ' y ' . MENSAJE_MAX . ' caracteres.';

    $espera = SEGUNDOS_ENTRE_MENSAJES - (time() - ($_SESSION['ultimo_contacto'] ?? 0));
    if (!$errores && $espera > 0) $errores[] = "Ya enviaste un mensaje hace poco. Espera $espera segundos para enviar otro.";

    if (!$errores) {
        $_SESSION['ultimo_contacto'] = time();
        q('INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES (?, ?, ?, ?)',
            [$datos['nombre'], $datos['email'], $datos['asunto'], $datos['mensaje']]);
        flash('success', '¡Gracias! Recibimos tu mensaje y te responderemos pronto.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contacto';
$active = 'contact';
require __DIR__ . '/includes/header.php';
?>
<section class="page-header">
    <div class="container">
        <h1>Contacto y soporte</h1>
        <p class="mb-0">¿Falta un concierto, encontraste un error o tienes una duda? Escríbenos.</p>
    </div>
</section>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-7">
            <?php if ($errores): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" class="needs-validation gray-card" novalidate>
                <?= csrf_field() ?>
                <div class="visually-hidden" aria-hidden="true">
                    <label for="sitio_web">No llenes este campo</label>
                    <input id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input class="form-control" id="nombre" name="nombre" required maxlength="100"
                               pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ '\-]{1,99}" value="<?= e($datos['nombre']) ?>">
                        <div class="invalid-feedback">Escribe tu nombre (solo letras).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Correo</label>
                        <input class="form-control" id="email" name="email" type="email" required maxlength="150" value="<?= e($datos['email']) ?>">
                        <div class="invalid-feedback">Escribe un correo válido.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="asunto">Asunto</label>
                        <select class="form-select" id="asunto" name="asunto" required>
                            <option value="">Selecciona...</option>
                            <?php foreach (ASUNTOS_CONTACTO as $op): ?>
                                <option <?= $datos['asunto'] === $op ? 'selected' : '' ?>><?= e($op) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Elige un asunto.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="mensaje">Mensaje</label>
                        <textarea class="form-control" id="mensaje" name="mensaje" rows="5" required minlength="<?= MENSAJE_MIN ?>" maxlength="<?= MENSAJE_MAX ?>" data-contador="mensaje-contador"><?= e($datos['mensaje']) ?></textarea>
                        <div class="invalid-feedback">El mensaje debe tener entre <?= MENSAJE_MIN ?> y <?= MENSAJE_MAX ?> caracteres.</div>
                        <div class="form-text"><span id="mensaje-contador">0</span>/<?= MENSAJE_MAX ?></div>
                    </div>
                    <div class="col-12"><button class="btn btn-primary px-4" type="submit">Enviar mensaje</button></div>
                </div>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="aviso-solicitud mb-4">
                <i class="bi bi-plus-circle"></i>
                <div><strong>¿Falta un concierto o recinto?</strong>
                    <p class="mb-0 small">No uses este formulario: <a href="<?= url('solicitar.php') ?>">solicita el registro aquí</a> y podrás ver cómo va tu solicitud.</p></div>
            </div>
            <h2 class="section-title">Canales de soporte</h2>
            <p><i class="bi bi-envelope text-primary me-2"></i> soporte@tinnitus.mx</p>
            <p><i class="bi bi-geo-alt text-primary me-2"></i> FIME, Cd. Universitaria, San Nicolás de los Garza, N.L.</p>
            <p><i class="bi bi-clock text-primary me-2"></i> Lunes a viernes, 9:00 a 18:00</p>

            <h2 class="section-title mt-4">Políticas de uso</h2>
            <div class="accordion" id="politicas">
                <?php $politicas = [
                    'Respeto a la comunidad' => 'No se permiten insultos, discursos de odio ni ataques personales en las reseñas.',
                    'Reseñas auténticas' => 'Reseña solo conciertos a los que asististe. Una reseña por usuario y concierto.',
                    'Moderación' => 'Las reseñas reportadas son revisadas por un administrador, que puede ocultarlas o eliminarlas.',
                    'Privacidad' => 'Tu correo nunca se muestra públicamente. Tu perfil solo muestra nombre, usuario, biografía y conciertos.',
                ]; $i = 0; foreach ($politicas as $titulo => $texto): $i++; ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button <?= $i > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#pol<?= $i ?>"><?= e($titulo) ?></button>
                        </h3>
                        <div id="pol<?= $i ?>" class="accordion-collapse collapse <?= $i === 1 ? 'show' : '' ?>" data-bs-parent="#politicas">
                            <div class="accordion-body"><?= e($texto) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
