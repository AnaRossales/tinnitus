<?php
require_once __DIR__ . '/auth.php';

function datos_solicitud(array $post): array
{
    $tipo = ($post['tipo'] ?? '') === 'recinto' ? 'recinto' : 'concierto';
    $vacio = fn($v) => ($v = trim((string) $v)) === '' ? null : $v;
    $d = ['tipo' => $tipo, 'comentario' => $vacio($post['comentario'] ?? '')];
    if ($tipo === 'concierto') {
        $d += [
            'artista'    => $vacio($post['artista'] ?? ''),
            'gira'       => $vacio($post['gira'] ?? ''),
            'fecha'      => $vacio($post['fecha'] ?? ''),
            'recinto_id' => (int) ($post['recinto_id'] ?? 0) ?: null,
        ];
    } else {
        $d += [
            'nombre'       => $vacio($post['nombre'] ?? ''),
            'ciudad'       => $vacio($post['ciudad'] ?? ''),
            'capacidad'    => (int) ($post['capacidad'] ?? 0),
            'tipo_recinto' => $post['tipo_recinto'] ?? '',
        ];
    }
    return $d;
}

function validar_solicitud(array $d, int $ignorarId = 0): array
{
    $errores = [];
    if (mb_strlen($d['comentario'] ?? '') > COMENTARIO_SOLICITUD_MAX) {
        $errores[] = 'El comentario admite máximo ' . COMENTARIO_SOLICITUD_MAX . ' caracteres.';
    }

    if ($d['tipo'] === 'concierto') {
        if (!$d['artista'] || mb_strlen($d['artista']) > 120) $errores[] = 'Escribe el nombre del artista o banda (máx. 120 caracteres).';
        if (!$d['gira'] || mb_strlen($d['gira']) > 150) $errores[] = 'Escribe el nombre de la gira o evento (máx. 150 caracteres).';
        if ($e = validar_fecha_concierto((string) $d['fecha'])) $errores[] = $e;
        $recinto = $d['recinto_id'] ? q('SELECT estado FROM recintos WHERE id = ?', [$d['recinto_id']])->fetch() : null;
        if (!$recinto) {
            $errores[] = 'Elige el recinto. Si no aparece, primero solicita el recinto.';
        } elseif ($recinto['estado'] === 'inactivo') {
            $errores[] = 'Ese recinto ya no recibe conciertos nuevos.';
        }
        if (!$errores) {
            if (q('SELECT 1 FROM conciertos c JOIN artistas a ON a.id = c.artista_id
                   WHERE a.nombre = ? AND c.recinto_id = ? AND c.fecha = ?', [$d['artista'], $d['recinto_id'], $d['fecha']])->fetchColumn()) {
                $errores[] = 'Ese concierto ya está registrado en el catálogo.';
            } elseif (q("SELECT 1 FROM solicitudes WHERE estado = 'pendiente' AND tipo = 'concierto' AND id <> ?
                         AND artista = ? AND recinto_id = ? AND fecha = ?", [$ignorarId, $d['artista'], $d['recinto_id'], $d['fecha']])->fetchColumn()) {
                $errores[] = 'Ya hay una solicitud en revisión para ese mismo concierto.';
            }
        }
    } else {
        if (!$d['nombre'] || mb_strlen($d['nombre']) > 120) $errores[] = 'Escribe el nombre del recinto (máx. 120 caracteres).';
        if (!$d['ciudad'] || mb_strlen($d['ciudad']) > 80) $errores[] = 'Escribe la ciudad (máx. 80 caracteres).';
        if ($d['capacidad'] < 1 || $d['capacidad'] > CAPACIDAD_MAX) {
            $errores[] = 'La capacidad debe estar entre 1 y ' . number_format(CAPACIDAD_MAX) . ' personas.';
        }
        if (!in_array($d['tipo_recinto'], TIPOS_RECINTO, true)) $errores[] = 'Elige el tipo de recinto.';
        if (!$errores) {
            if (q('SELECT 1 FROM recintos WHERE nombre = ? AND ciudad = ?', [$d['nombre'], $d['ciudad']])->fetchColumn()) {
                $errores[] = 'Ese recinto ya está registrado.';
            } elseif (q("SELECT 1 FROM solicitudes WHERE estado = 'pendiente' AND tipo = 'recinto' AND id <> ?
                         AND nombre = ? AND ciudad = ?", [$ignorarId, $d['nombre'], $d['ciudad']])->fetchColumn()) {
                $errores[] = 'Ya hay una solicitud en revisión para ese mismo recinto.';
            }
        }
    }
    return $errores;
}

function aprobar_solicitud(int $id, int $adminId): array
{
    $s = q("SELECT * FROM solicitudes WHERE id = ? AND estado = 'pendiente'", [$id])->fetch();
    if (!$s) return [false, 'La solicitud no existe o ya fue revisada.'];

    $d = ['tipo' => $s['tipo'], 'comentario' => null] + array_intersect_key($s, array_flip(
        ['artista', 'gira', 'fecha', 'recinto_id', 'nombre', 'ciudad', 'capacidad', 'tipo_recinto']));
    $d['capacidad'] = (int) $d['capacidad'];
    if ($errores = validar_solicitud($d, $id)) return [false, 'No se puede aprobar: ' . implode(' ', $errores)];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($s['tipo'] === 'concierto') {
            $artistaId = q('SELECT id FROM artistas WHERE nombre = ?', [$s['artista']])->fetchColumn();
            if (!$artistaId) {
                q('INSERT INTO artistas (nombre) VALUES (?)', [$s['artista']]);
                $artistaId = $pdo->lastInsertId();
            }
            q('INSERT INTO conciertos (artista_id, recinto_id, gira, fecha) VALUES (?, ?, ?, ?)',
                [$artistaId, $s['recinto_id'], $s['gira'], $s['fecha']]);
        } else {
            q('INSERT INTO recintos (nombre, ciudad, capacidad, tipo) VALUES (?, ?, ?, ?)',
                [$s['nombre'], $s['ciudad'], $s['capacidad'], $s['tipo_recinto']]);
        }
        $creado = $pdo->lastInsertId();
        q("UPDATE solicitudes SET estado = 'aprobada', creado_id = ?, revisado_por = ?, revisado_en = NOW() WHERE id = ?",
            [$creado, $adminId, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return [false, 'No se pudo aprobar la solicitud: ' . $e->getMessage()];
    }
    return [true, ($s['tipo'] === 'concierto' ? 'Concierto' : 'Recinto') . ' agregado al catálogo.'];
}

function resumen_solicitud(array $s): string
{
    if ($s['tipo'] === 'concierto') {
        return $s['artista'] . ' – ' . $s['gira'] . ' · ' . ($s['recinto'] ?? 'recinto #' . $s['recinto_id']) . ' · ' . date('d/m/Y', strtotime($s['fecha']));
    }
    return $s['nombre'] . ' · ' . $s['tipo_recinto'] . ' en ' . $s['ciudad'] . ' · ' . number_format($s['capacidad']) . ' personas';
}

function detalle_solicitud(array $s): string
{
    if ($s['tipo'] === 'concierto') {
        return ($s['recinto'] ?? 'recinto #' . $s['recinto_id']) . ' · ' . date('d/m/Y', strtotime($s['fecha']));
    }
    return $s['tipo_recinto'] . ' en ' . $s['ciudad'] . ' · ' . number_format($s['capacidad']) . ' personas';
}

function badge_estado_solicitud(string $estado): string
{
    return match ($estado) {
        'aprobada'  => '<span class="badge text-bg-success">Aprobada</span>',
        'rechazada' => '<span class="badge text-bg-danger">Rechazada</span>',
        default     => '<span class="badge text-bg-warning">En revisión</span>',
    };
}

function texto_rechazo(array $s): string
{
    $html = '<strong>Motivo:</strong> ' . e($s['motivo_rechazo']);
    if (!empty($s['detalle_rechazo'])) $html .= ' — ' . e($s['detalle_rechazo']);
    return $html;
}
