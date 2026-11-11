const tabla = document.getElementById('tabla');
const modalRechazo = new bootstrap.Modal(document.getElementById('modal-rechazo'));
const formRechazo = document.getElementById('form-rechazo');
let filaActual = null;

async function refrescar() {
    const html = await fetch(location.href, { headers: { 'Accept': 'text/html' } }).then(r => r.text());
    const doc = new DOMParser().parseFromString(html, 'text/html');
    for (const selector of ['#tabla tbody', '.card-table .pie', '.admin-toolbar .chip-row', '.admin-nav']) {
        document.querySelector(selector).innerHTML = doc.querySelector(selector).innerHTML;
    }
}

tabla.addEventListener('click', async event => {
    const fila = event.target.closest('tr[data-id]');
    if (!fila) return;

    const btnAprobar = event.target.closest('.btn-aprobar');
    if (btnAprobar) {
        const ok = await confirmarAccion(`Se agregará al catálogo: ${fila.dataset.resumen}`, 'Sí, aprobar');
        if (!ok) return;
        cargando(btnAprobar, true);
        try {
            const res = await postJSON(location.pathname, { accion: 'aprobar', id: fila.dataset.id });
            await refrescar();
            showToast(res.mensaje);
        } catch (err) {
            cargando(btnAprobar, false);
            showToast(err.message, 'danger');
        }
    }

    if (event.target.closest('.btn-rechazar')) {
        filaActual = fila;
        formRechazo.reset();
        actualizarDetalle();
        formRechazo.classList.remove('was-validated');
        document.getElementById('rechazo-resumen').textContent = fila.dataset.resumen;
        modalRechazo.show();
    }
});

// "Otro" no dice nada por sí solo, así que en ese caso la explicación es obligatoria.
function actualizarDetalle() {
    const esOtro = formRechazo.motivo.value === 'Otro';
    formRechazo.detalle.required = esOtro;
    document.getElementById('detalle-opcional').textContent = esOtro ? '(obligatoria)' : '(opcional)';
}
formRechazo.motivo.addEventListener('change', actualizarDetalle);

formRechazo.addEventListener('submit', async event => {
    event.preventDefault();
    if (!formRechazo.checkValidity()) {
        formRechazo.classList.add('was-validated');
        return;
    }
    const boton = formRechazo.querySelector('[type="submit"]');
    cargando(boton, true);
    try {
        const res = await postJSON(location.pathname, {
            accion: 'rechazar',
            id: filaActual.dataset.id,
            motivo: formRechazo.motivo.value,
            detalle: formRechazo.detalle.value.trim(),
        });
        modalRechazo.hide();
        await refrescar();
        showToast(res.mensaje, 'secondary');
    } catch (err) {
        showToast(err.message, 'danger');
    } finally {
        cargando(boton, false);
    }
});
