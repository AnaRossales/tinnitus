<?php
require_once __DIR__ . '/auth.php';

const REPORTES = [
    'conciertos' => [
        'actividad'     => 'Actividad por concierto',
        'periodo'       => 'Conciertos por periodo',
        'sin_actividad' => 'Conciertos sin actividad',
    ],
    'recintos' => [
        'uso'         => 'Uso de recintos',
        'ciudad_tipo' => 'Recintos por ciudad y por tipo',
        'calificados' => 'Recintos mejor calificados',
    ],
    'artistas' => [
        'ranking'     => 'Ranking de artistas',
        'genero_pais' => 'Artistas por género y por país',
    ],
    'solicitudes' => [
        'periodo'  => 'Solicitudes por periodo y por tipo',
        'motivos'  => 'Motivos de rechazo más frecuentes',
        'usuarios' => 'Usuarios que más solicitan',
    ],
];

const MESES_SQL = "'Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'";

function reporte_tarjetas(string $modulo, string $desde, string $hasta): array
{
    $p = [$desde, $hasta];

    if ($modulo === 'conciertos') {
        $m = q("SELECT COUNT(*) AS total, SUM(fecha >= CURDATE()) AS proximos, SUM(fecha < CURDATE()) AS pasados,
                       SUM(NOT EXISTS (SELECT 1 FROM resenas s WHERE s.concierto_id = c.id)) AS sin_resenas
                FROM conciertos c WHERE fecha BETWEEN ? AND ?", $p)->fetch();
        $destacados = q('SELECT COUNT(*) FROM conciertos WHERE destacado = 1')->fetchColumn();
        return [
            ['Total de conciertos', (int) $m['total']],
            ['Próximos / ya sucedidos', (int) $m['proximos'] . ' / ' . (int) $m['pasados']],
            ['Destacados', $destacados . ' de ' . MAX_DESTACADOS],
            ['Sin reseñas', (int) $m['sin_resenas']],
        ];
    }

    if ($modulo === 'recintos') {
        $m = q("SELECT COUNT(*) AS total, SUM(estado = 'activo') AS activos, SUM(estado = 'inactivo') AS inactivos,
                       COUNT(DISTINCT ciudad) AS ciudades,
                       SUM(NOT EXISTS (SELECT 1 FROM conciertos c WHERE c.recinto_id = r.id AND c.fecha BETWEEN ? AND ?)) AS sin_conciertos
                FROM recintos r", $p)->fetch();
        return [
            ['Total de recintos', (int) $m['total']],
            ['Activos / inactivos', (int) $m['activos'] . ' / ' . (int) $m['inactivos']],
            ['Ciudades distintas', (int) $m['ciudades']],
            ['Sin conciertos', (int) $m['sin_conciertos']],
        ];
    }

    if ($modulo === 'artistas') {
        $m = q("SELECT COUNT(*) AS total, COUNT(DISTINCT genero) AS generos, COUNT(DISTINCT pais) AS paises,
                       SUM(NOT EXISTS (SELECT 1 FROM conciertos c WHERE c.artista_id = a.id AND c.fecha BETWEEN ? AND ?)) AS sin_conciertos
                FROM artistas a", $p)->fetch();
        return [
            ['Total de artistas', (int) $m['total']],
            ['Géneros distintos', (int) $m['generos']],
            ['Países distintos', (int) $m['paises']],
            ['Sin conciertos', (int) $m['sin_conciertos']],
        ];
    }

    $m = q("SELECT SUM(estado = 'pendiente') AS pendientes, SUM(estado = 'aprobada') AS aprobadas,
                   SUM(estado = 'rechazada') AS rechazadas,
                   ROUND(AVG(TIMESTAMPDIFF(MINUTE, creado_en, revisado_en)) / 60, 1) AS horas
            FROM solicitudes WHERE DATE(creado_en) BETWEEN ? AND ?", $p)->fetch();
    return [
        ['Pendientes', (int) $m['pendientes']],
        ['Aprobadas', (int) $m['aprobadas']],
        ['Rechazadas', (int) $m['rechazadas']],
        ['Tiempo promedio de respuesta', $m['horas'] === null ? '—' : $m['horas'] . ' h'],
    ];
}

function reporte_datos(string $modulo, string $reporte, string $desde, string $hasta): array
{
    $p = [$desde, $hasta];

    switch ("$modulo.$reporte") {
        case 'conciertos.actividad':
            $columnas = ['Concierto', 'Recinto', 'Fecha', 'Asistencias', 'Reseñas', 'Promedio', 'Reportes pendientes'];
            $sql = "SELECT CONCAT(a.nombre, ' – ', c.gira), r.nombre, DATE_FORMAT(c.fecha, '%d/%m/%Y'),
                           (SELECT COUNT(*) FROM asistencias x WHERE x.concierto_id = c.id) AS asistencias,
                           (SELECT COUNT(*) FROM resenas s WHERE s.concierto_id = c.id AND s.oculta = 0) AS resenas,
                           (SELECT ROUND(AVG(s.calificacion), 1) FROM resenas s WHERE s.concierto_id = c.id AND s.oculta = 0),
                           (SELECT COUNT(*) FROM reportes rp JOIN resenas s ON s.id = rp.resena_id
                            WHERE s.concierto_id = c.id AND rp.estado = 'pendiente')
                    FROM conciertos c
                    JOIN artistas a ON a.id = c.artista_id
                    JOIN recintos r ON r.id = c.recinto_id
                    WHERE c.fecha BETWEEN ? AND ?
                    ORDER BY asistencias DESC, resenas DESC";
            break;

        case 'conciertos.periodo':
            $columnas = ['Año', 'Mes', 'Conciertos'];
            $sql = "SELECT YEAR(fecha), ELT(MONTH(fecha), " . MESES_SQL . "), COUNT(*)
                    FROM conciertos WHERE fecha BETWEEN ? AND ?
                    GROUP BY YEAR(fecha), MONTH(fecha)
                    ORDER BY YEAR(fecha) DESC, MONTH(fecha) DESC";
            break;

        case 'conciertos.sin_actividad':
            $columnas = ['Concierto', 'Recinto', 'Fecha'];
            $sql = "SELECT CONCAT(a.nombre, ' – ', c.gira), r.nombre, DATE_FORMAT(c.fecha, '%d/%m/%Y')
                    FROM conciertos c
                    JOIN artistas a ON a.id = c.artista_id
                    JOIN recintos r ON r.id = c.recinto_id
                    WHERE c.fecha BETWEEN ? AND ?
                      AND NOT EXISTS (SELECT 1 FROM asistencias x WHERE x.concierto_id = c.id)
                      AND NOT EXISTS (SELECT 1 FROM resenas s WHERE s.concierto_id = c.id)
                    ORDER BY c.fecha DESC";
            break;

        case 'recintos.uso':
            $columnas = ['Recinto', 'Ciudad', 'Conciertos', 'Último concierto', 'Promedio'];
            $sql = "SELECT r.nombre, r.ciudad, COUNT(DISTINCT c.id) AS conciertos, DATE_FORMAT(MAX(c.fecha), '%d/%m/%Y'),
                           ROUND(AVG(s.calificacion), 1)
                    FROM recintos r
                    LEFT JOIN conciertos c ON c.recinto_id = r.id AND c.fecha BETWEEN ? AND ?
                    LEFT JOIN resenas s ON s.concierto_id = c.id AND s.oculta = 0
                    GROUP BY r.id ORDER BY conciertos DESC, r.nombre";
            break;

        case 'recintos.ciudad_tipo':
            $columnas = ['Ciudad', 'Tipo', 'Recintos', 'Conciertos'];
            $sql = "SELECT r.ciudad, r.tipo, COUNT(DISTINCT r.id), COUNT(c.id) AS conciertos
                    FROM recintos r
                    LEFT JOIN conciertos c ON c.recinto_id = r.id AND c.fecha BETWEEN ? AND ?
                    GROUP BY r.ciudad, r.tipo ORDER BY conciertos DESC, r.ciudad";
            break;

        case 'recintos.calificados':
            $columnas = ['Recinto', 'Ciudad', 'Reseñas', 'Promedio'];
            $sql = "SELECT r.nombre, r.ciudad, COUNT(s.id) AS resenas, ROUND(AVG(s.calificacion), 1) AS promedio
                    FROM recintos r
                    JOIN conciertos c ON c.recinto_id = r.id AND c.fecha BETWEEN ? AND ?
                    JOIN resenas s ON s.concierto_id = c.id AND s.oculta = 0
                    GROUP BY r.id ORDER BY promedio DESC, resenas DESC";
            break;

        case 'artistas.ranking':
            $columnas = ['Artista', 'Conciertos', 'Reseñas', 'Asistencias', 'Promedio'];
            $sql = "SELECT a.nombre, COUNT(DISTINCT c.id), COUNT(DISTINCT s.id) AS resenas,
                           (SELECT COUNT(*) FROM asistencias x JOIN conciertos c2 ON c2.id = x.concierto_id
                            WHERE c2.artista_id = a.id AND c2.fecha BETWEEN ? AND ?) AS asistencias,
                           ROUND(AVG(s.calificacion), 1)
                    FROM artistas a
                    LEFT JOIN conciertos c ON c.artista_id = a.id AND c.fecha BETWEEN ? AND ?
                    LEFT JOIN resenas s ON s.concierto_id = c.id AND s.oculta = 0
                    GROUP BY a.id ORDER BY resenas DESC, asistencias DESC, a.nombre";
            $p = [$desde, $hasta, $desde, $hasta];
            break;

        case 'artistas.genero_pais':
            $columnas = ['Género', 'País', 'Artistas', 'Conciertos', 'Reseñas'];
            $sql = "SELECT COALESCE(a.genero, '—'), COALESCE(a.pais, '—'), COUNT(DISTINCT a.id) AS artistas,
                           COUNT(DISTINCT c.id), COUNT(DISTINCT s.id)
                    FROM artistas a
                    LEFT JOIN conciertos c ON c.artista_id = a.id AND c.fecha BETWEEN ? AND ?
                    LEFT JOIN resenas s ON s.concierto_id = c.id AND s.oculta = 0
                    GROUP BY a.genero, a.pais ORDER BY artistas DESC, a.genero";
            break;

        case 'solicitudes.periodo':
            $columnas = ['Mes', 'De conciertos', 'De recintos', 'Aprobadas', 'Rechazadas'];
            $sql = "SELECT CONCAT(ELT(MONTH(creado_en), " . MESES_SQL . "), ' ', YEAR(creado_en)),
                           SUM(tipo = 'concierto'), SUM(tipo = 'recinto'),
                           SUM(estado = 'aprobada'), SUM(estado = 'rechazada')
                    FROM solicitudes WHERE DATE(creado_en) BETWEEN ? AND ?
                    GROUP BY YEAR(creado_en), MONTH(creado_en)
                    ORDER BY YEAR(creado_en) DESC, MONTH(creado_en) DESC";
            break;

        case 'solicitudes.motivos':
            $columnas = ['Motivo', 'Veces usado'];
            $sql = "SELECT motivo_rechazo, COUNT(*) AS veces
                    FROM solicitudes WHERE estado = 'rechazada' AND DATE(creado_en) BETWEEN ? AND ?
                    GROUP BY motivo_rechazo ORDER BY veces DESC";
            break;

        case 'solicitudes.usuarios':
            $columnas = ['Usuario', 'Enviadas', 'Aprobadas'];
            $sql = "SELECT CONCAT('@', u.username), COUNT(*) AS enviadas, SUM(s.estado = 'aprobada')
                    FROM solicitudes s JOIN usuarios u ON u.id = s.usuario_id
                    WHERE DATE(s.creado_en) BETWEEN ? AND ?
                    GROUP BY u.id ORDER BY enviadas DESC, u.username";
            break;

        default:
            return [[], []];
    }

    return [$columnas, q($sql, $p)->fetchAll(PDO::FETCH_NUM)];
}
