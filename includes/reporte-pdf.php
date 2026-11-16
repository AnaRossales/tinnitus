<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #222; }
    .marca { color: #1a1a8f; font-size: 20px; font-weight: bold; }
    h1 { font-size: 16px; margin: 6px 0 2px; }
    .datos { color: #666; margin-bottom: 14px; }
    .tarjetas { width: 100%; border-spacing: 6px 0; margin: 0 -6px 14px; }
    .tarjetas td { background: #f0f0f6; padding: 8px; width: 25%; }
    .tarjetas strong { display: block; color: #1a1a8f; font-size: 14px; }
    .tabla { width: 100%; border-collapse: collapse; }
    .tabla th { background: #1a1a8f; color: #fff; text-align: left; padding: 5px; }
    .tabla td { border-bottom: 1px solid #ddd; padding: 5px; }
</style>
</head>
<body>
    <div class="marca">Tinnitus</div>
    <h1><?= e($titulo) ?></h1>
    <div class="datos">
        Periodo: <?= e($periodo) ?> · Generado el <?= date('d/m/Y H:i') ?> por @<?= e(current_user()['username']) ?>
    </div>
    <table class="tarjetas">
        <tr>
            <?php foreach ($tarjetas as [$etiqueta, $valor]): ?>
                <td><strong><?= e((string) $valor) ?></strong><?= e($etiqueta) ?></td>
            <?php endforeach; ?>
        </tr>
    </table>
    <table class="tabla">
        <thead>
            <tr><?php foreach ($columnas as $columna): ?><th><?= e($columna) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila): ?>
                <tr><?php foreach ($fila as $valor): ?><td><?= e($valor === null ? '—' : (string) $valor) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            <?php if (!$filas): ?>
                <tr><td colspan="<?= count($columnas) ?>">No hay datos en ese rango de fechas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
