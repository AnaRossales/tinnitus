const tabla = document.getElementById('tabla');
const modalRechazo = new bootstrap.Modal(document.getElementById('modal-rechazo'));
const formRechazo = document.getElementById('form-rechazo');
const modalEditar = new bootstrap.Modal(document.getElementById('modal-editar'));
const formEditar = document.getElementById('form-editar');
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

    if (event.target.closest('.btn-editar')) {
        filaActual = fila;
        const datos = JSON.parse(fila.dataset.datos);
        const esConcierto = datos.tipo === 'concierto';
        formEditar.reset();
        formEditar.classList.remove('was-validated');
        for (const [id, mostrar] of [['campos-concierto', esConcierto], ['campos-recinto', !esConcierto]]) {
            const campos = document.getElementById(id);
            campos.hidden = !mostrar;
            campos.disabled = !mostrar;
        }
        for (const [campo, valor] of Object.entries(datos)) {
            if (formEditar.elements[campo]) formEditar.elements[campo].value = valor ?? '';
        }
        document.getElementById('e-comentario').textContent = datos.comentario ? `Comentario del usuario: “${datos.comentario}”` : '';
        modalEditar.show();
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

formEditar.addEventListener('submit', async event => {
    event.preventDefault();
    if (!formEditar.checkValidity()) {
        formEditar.classList.add('was-validated');
        return;
    }
    const boton = formEditar.querySelector('[type="submit"]');
    cargando(boton, true);
    try {
        const res = await postJSON(location.pathname, {
            accion: 'editar',
            id: filaActual.dataset.id,
            ...Object.fromEntries(new FormData(formEditar)),
        });
        modalEditar.hide();
        await refrescar();
        showToast(res.mensaje);
    } catch (err) {
        showToast(err.message, 'danger');
    } finally {
        cargando(boton, false);
    }
});
