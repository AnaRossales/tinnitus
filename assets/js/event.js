const API = 'api.php';
const btnAsistencia = document.getElementById('btn-asistencia');
const ETIQUETAS = { 1: 'Malo', 2: 'Regular', 3: 'Bueno', 4: 'Muy bueno', 5: '¡Excelente!' };

function actualizarStats(stats) {
    document.getElementById('stat-promedio').textContent = Number(stats.promedio).toFixed(1);
    document.getElementById('stat-total').textContent = stats.total;
    document.getElementById('stat-asistentes').textContent = Number(stats.asistentes).toLocaleString('en-US');
    document.querySelectorAll('#distribucion .dist-row').forEach(row => {
        const estrellas = row.dataset.estrellas;
        const pct = stats.total ? Math.round(stats.dist[estrellas] * 100 / stats.total) : 0;
        row.querySelector('.progress-bar').style.width = pct + '%';
        row.querySelector('.pct').textContent = pct + '%';
    });
}

const form = document.getElementById('form-resena');
if (form) {
    const texto = form.querySelector('textarea');
    const btnPublicar = document.getElementById('btn-publicar');
    const btnBorrar = document.getElementById('btn-borrar-resena');
    const ayuda = document.getElementById('ayuda-resena');
    const contador = document.getElementById('contador');
    const etiqueta = document.getElementById('etiqueta-estrellas');
    const MIN = Number(ayuda.dataset.min);
    const MAX = Number(ayuda.dataset.max);
    const original = { texto: texto.value, estrellas: form.querySelector('input:checked')?.value };
    const estrellasMarcadas = () => form.querySelector('input[name="calificacion"]:checked')?.value;
    const hayCambios = () => texto.value !== original.texto || estrellasMarcadas() !== original.estrellas;

    function mostrarEtiqueta(valor) {
        etiqueta.textContent = valor ? ETIQUETAS[valor] : 'Elige tus estrellas';
    }
    form.querySelectorAll('.star-input label').forEach(label => {
        const valor = document.getElementById(label.htmlFor).value;
        label.addEventListener('mouseenter', () => mostrarEtiqueta(valor));
        label.addEventListener('mouseleave', () => mostrarEtiqueta(estrellasMarcadas()));
    });
    mostrarEtiqueta(estrellasMarcadas());

    function revisarBoton() {
        const largo = texto.value.trim().length;
        const largoOk = largo >= MIN && largo <= MAX;
        btnPublicar.classList.toggle('d-none', !(estrellasMarcadas() && largoOk && hayCambios()));
        contador.textContent = texto.value.length;
        ayuda.classList.toggle('text-danger', largo > 0 && !largoOk);
        mostrarEtiqueta(estrellasMarcadas());
    }
    form.addEventListener('input', revisarBoton);
    revisarBoton();

    window.addEventListener('beforeunload', event => {
        if (hayCambios() && texto.value.trim()) event.preventDefault();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        let nuevaEtiqueta = null;
        cargando(btnPublicar, true);
        try {
            const res = await postJSON(API, {
                accion: 'resena',
                concierto_id: form.dataset.concierto,
                calificacion: estrellasMarcadas(),
                texto: texto.value.trim(),
            });
            actualizarStats(res.stats);
            original.texto = texto.value;
            original.estrellas = String(res.resena.calificacion);
            nuevaEtiqueta = 'Actualizar';
            btnBorrar.classList.remove('d-none');
            document.getElementById('sin-resenas')?.remove();
            marcarAsistencia(true, true);
            showToast('¡Reseña guardada!');
        } catch (err) {
            showToast(err.message, 'danger');
        } finally {
            cargando(btnPublicar, false);
            if (nuevaEtiqueta) btnPublicar.textContent = nuevaEtiqueta;
            revisarBoton();
        }
    });

    btnBorrar.addEventListener('click', async () => {
        if (!(await confirmarAccion('Se eliminará tu reseña de este concierto. Esta acción no se puede deshacer.', 'Sí, eliminar reseña'))) return;
        cargando(btnBorrar, true);
        try {
            const res = await postJSON(API, { accion: 'borrar_resena', concierto_id: form.dataset.concierto });
            actualizarStats(res.stats);
            form.reset();
            texto.value = '';
            original.texto = '';
            original.estrellas = undefined;
            btnPublicar.textContent = 'Publicar';
            marcarAsistencia(true, false);
            showToast('Reseña eliminada', 'secondary');
        } catch (err) {
            showToast(err.message, 'danger');
        } finally {
            cargando(btnBorrar, false);
            btnBorrar.classList.toggle('d-none', !original.estrellas);
            revisarBoton();
        }
    });
}

let asistio = btnAsistencia?.classList.contains('btn-peach');

function marcarAsistencia(fue, tieneResena = btnAsistencia?.dataset.tieneResena === '1') {
    if (!btnAsistencia) return;
    asistio = fue;
    btnAsistencia.dataset.tieneResena = tieneResena ? '1' : '0';
    btnAsistencia.className = 'btn ' + (fue ? 'btn-peach' : 'btn-outline-white');
    btnAsistencia.setAttribute('aria-pressed', fue ? 'true' : 'false');
    btnAsistencia.innerHTML = `<i class="bi ${fue ? 'bi-check-circle-fill' : 'bi-plus-circle'}"></i> <span>${fue ? 'Fui a este concierto' : 'Marcar que fui'}</span>`;
}

if (btnAsistencia) {
    btnAsistencia.addEventListener('click', async () => {
        if (asistio && btnAsistencia.dataset.tieneResena === '1') {
            showToast('Ya reseñaste este concierto. Para quitarlo de tu historial primero elimina tu reseña.', 'warning');
            return;
        }
        cargando(btnAsistencia, true);
        try {
            const res = await postJSON(API, { accion: 'asistencia', concierto_id: btnAsistencia.dataset.concierto });
            btnAsistencia.disabled = false;
            marcarAsistencia(res.asistio);
            actualizarStats(res.stats);
            showToast(res.asistio ? 'Agregado a tu historial' : 'Quitado de tu historial (y de tu Top 4)', res.asistio ? 'success' : 'secondary');
        } catch (err) {
            cargando(btnAsistencia, false);
            showToast(err.message, 'danger');
        }
    });
}

const modal = new bootstrap.Modal(document.getElementById('modal-reporte'));
const formReporte = document.getElementById('form-reporte');
document.querySelectorAll('.btn-reportar').forEach(btn => {
    btn.addEventListener('click', () => {
        formReporte.resena_id.value = btn.dataset.resena;
        modal.show();
    });
});
formReporte.addEventListener('submit', async event => {
    event.preventDefault();
    const id = formReporte.resena_id.value;
    const btn = document.querySelector(`.btn-reportar[data-resena="${id}"]`);
    const enviar = formReporte.querySelector('[type="submit"]');
    cargando(enviar, true);
    try {
        const res = await postJSON(API, {
            accion: 'reportar',
            resena_id: id,
            motivo: formReporte.querySelector('input[name="motivo"]:checked').value,
        });
        modal.hide();
        btn.classList.add('reportada');
        btn.disabled = true;
        if (res.ocultada) {
            btn.closest('.review').remove();
            showToast('La reseña se ocultó mientras un administrador la revisa.', 'warning');
        } else {
            showToast('Gracias, un administrador revisará la reseña.');
        }
    } catch (err) {
        modal.hide();
        showToast(err.message, 'danger');
    } finally {
        cargando(enviar, false);
    }
});
