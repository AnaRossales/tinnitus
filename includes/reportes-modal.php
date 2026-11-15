<?php require_once __DIR__ . '/reportes.php'; ?>
<div class="modal fade" id="modal-reportes" tabindex="-1" aria-hidden="true"
     data-modulo="<?= e($moduloReportes) ?>" data-url="<?= url('admin-reportes.php') ?>">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5">Reportes de <?= e($moduloReportes) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form class="row g-3 align-items-end mb-4" id="form-reportes">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="r-desde">Desde</label>
                        <input class="form-control" id="r-desde" name="desde" type="date">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="r-hasta">Hasta</label>
                        <input class="form-control" id="r-hasta" name="hasta" type="date">
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="r-reporte">Reporte</label>
                        <select class="form-select" id="r-reporte" name="reporte">
                            <?php foreach (REPORTES[$moduloReportes] as $clave => $titulo): ?>
                                <option value="<?= $clave ?>"><?= e($titulo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <div class="row g-3 mb-4" id="reporte-tarjetas"></div>
                <div class="card-table">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" id="reporte-tabla">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
