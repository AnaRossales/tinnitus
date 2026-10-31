<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
}
if (!is_logged_in()) {
    json_response(['ok' => false, 'error' => 'Inicia sesión para hacer esto.'], 401);
}
verify_csrf();

$userId = current_user()['id'];
$accion = $_POST['accion'] ?? '';
$conciertoId = (int) ($_POST['concierto_id'] ?? 0);

function error(string $mensaje, int $status = 422): void
{
    json_response(['ok' => false, 'error' => $mensaje], $status);
}

function concierto_o_404(int $id): array
{
    $c = q('SELECT id, fecha FROM conciertos WHERE id = ?', [$id])->fetch();
    if (!$c) error('El concierto no existe.', 404);
    return $c;
}

function asistio(int $userId, int $conciertoId): bool
{
    return (bool) q('SELECT 1 FROM asistencias WHERE usuario_id = ? AND concierto_id = ?', [$userId, $conciertoId])->fetchColumn();
}

switch ($accion) {
    case 'resena':
        $c = concierto_o_404($conciertoId);
        if (!ya_ocurrio($c['fecha'])) error('Este concierto todavía no sucede; podrás reseñarlo después del ' . date('d/m/Y', strtotime($c['fecha'])) . '.');
        $calificacion = (int) ($_POST['calificacion'] ?? 0);
        $texto = trim($_POST['texto'] ?? '');
        if ($e = validar_resena($calificacion, $texto)) error($e);

        q('INSERT INTO resenas (usuario_id, concierto_id, calificacion, texto) VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), texto = VALUES(texto), creado_en = NOW()',
            [$userId, $conciertoId, $calificacion, $texto]);
        q('INSERT IGNORE INTO asistencias (usuario_id, concierto_id) VALUES (?, ?)', [$userId, $conciertoId]);

        $resena = q('SELECT s.id, s.calificacion, s.texto FROM resenas s WHERE s.usuario_id = ? AND s.concierto_id = ?',
            [$userId, $conciertoId])->fetch();
        json_response(['ok' => true, 'resena' => [
            'id' => $resena['id'],
            'calificacion' => (int) $resena['calificacion'],
            'texto' => $resena['texto'],
        ], 'stats' => stats_concierto($conciertoId)]);

    case 'borrar_resena':
        q('DELETE FROM resenas WHERE usuario_id = ? AND concierto_id = ?', [$userId, $conciertoId]);
        json_response(['ok' => true, 'stats' => stats_concierto($conciertoId)]);

    case 'reportar':
        $resenaId = (int) ($_POST['resena_id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '') ?: 'Contenido inapropiado';
        $resena = q('SELECT usuario_id, oculta FROM resenas WHERE id = ?', [$resenaId])->fetch();
        if (!$resena || $resena['oculta']) error('La reseña no existe o ya está en revisión.', 404);
        if ((int) $resena['usuario_id'] === $userId) error('No puedes reportar tu propia reseña.');
        $nuevo = q('INSERT IGNORE INTO reportes (resena_id, usuario_id, motivo) VALUES (?, ?, ?)',
            [$resenaId, $userId, mb_substr($motivo, 0, 255)])->rowCount();
        if (!$nuevo) error('Ya habías reportado esta reseña.');

        $pendientes = (int) q("SELECT COUNT(*) FROM reportes WHERE resena_id = ? AND estado = 'pendiente'", [$resenaId])->fetchColumn();
        $ocultada = $pendientes >= REPORTES_PARA_OCULTAR;
        if ($ocultada) {
            q('UPDATE resenas SET oculta = 1 WHERE id = ?', [$resenaId]);
        }
        json_response(['ok' => true, 'ocultada' => $ocultada]);

    case 'asistencia':
        $c = concierto_o_404($conciertoId);
        if (asistio($userId, $conciertoId)) {
            if (q('SELECT 1 FROM resenas WHERE usuario_id = ? AND concierto_id = ?', [$userId, $conciertoId])->fetchColumn()) {
                error('Ya reseñaste este concierto. Para quitarlo de tu historial primero elimina tu reseña.', 409);
            }
            q('DELETE FROM asistencias WHERE usuario_id = ? AND concierto_id = ?', [$userId, $conciertoId]);
            q('DELETE FROM top4 WHERE usuario_id = ? AND concierto_id = ?', [$userId, $conciertoId]);
            $ahora = false;
        } else {
            if (!ya_ocurrio($c['fecha'])) error('No puedes marcar asistencia a un concierto que todavía no sucede.');
            q('INSERT INTO asistencias (usuario_id, concierto_id) VALUES (?, ?)', [$userId, $conciertoId]);
            $ahora = true;
        }
        json_response(['ok' => true, 'asistio' => $ahora, 'stats' => stats_concierto($conciertoId)]);

    case 'top4':
        $posicion = (int) ($_POST['posicion'] ?? 0);
        if ($posicion < 1 || $posicion > 4) error('Posición inválida.');
        if ($conciertoId === 0) {
            q('DELETE FROM top4 WHERE usuario_id = ? AND posicion = ?', [$userId, $posicion]);
            json_response(['ok' => true]);
        }
        concierto_o_404($conciertoId);
        if (!asistio($userId, $conciertoId)) error('Solo puedes poner en tu Top 4 conciertos de tu historial.');
        q('DELETE FROM top4 WHERE usuario_id = ? AND (posicion = ? OR concierto_id = ?)', [$userId, $posicion, $conciertoId]);
        q('INSERT INTO top4 (usuario_id, posicion, concierto_id) VALUES (?, ?, ?)', [$userId, $posicion, $conciertoId]);
        json_response(['ok' => true]);

    default:
        error('Acción desconocida.', 400);
}
