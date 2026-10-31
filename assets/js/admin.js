const PAGE = location.pathname;
const tabla = document.getElementById('tabla');
const btnMasiva = document.getElementById('btn-masiva');
const selCount = document.getElementById('sel-count');
const checkAll = document.getElementById('check-all');
const formModalEl = document.getElementById('modal-form');
const formModal = formModalEl ? new bootstrap.Modal(formModalEl) : null;
const form = formModalEl?.querySelector('form');
const confirmModal = new bootstrap.Modal(document.getElementById('modal-confirm'));

async function refrescarTabla() {
    const html = await fetch(location.href, { headers: { 'Accept': 'text/html' } }).then(r => r.text());
    const doc = new DOMParser().parseFromString(html, 'text/html');
    tabla.querySelector('tbody').innerHTML = doc.querySelector('#tabla tbody').innerHTML;
    document.querySelector('.card-table .pie').innerHTML = doc.querySelector('.card-table .pie').innerHTML;
    const info = document.getElementById('destacados-info');
    if (info) info.textContent = doc.getElementById('destacados-info').textContent;
    checkAll.checked = false;
    actualizarSeleccion();
}

function seleccionados() {
    return [...tabla.querySelectorAll('.row-check:checked')].map(ch => ch.value);
}
function actualizarSeleccion() {
    const n = seleccionados().length;
    selCount.textContent = n;
    btnMasiva.disabled = n === 0;
    const total = tabla.querySelectorAll('.row-check').length;
    checkAll.checked = n > 0 && n === total;
    checkAll.indeterminate = n > 0 && n < total;
}
checkAll.addEventListener('change', () => {
    tabla.querySelectorAll('.row-check').forEach(ch => (ch.checked = checkAll.checked));
    actualizarSeleccion();
});
tabla.addEventListener('change', event => {
    if (event.target.classList.contains('row-check')) actualizarSeleccion();
});

function confirmar(texto) {
    return new Promise(resolve => {
        document.getElementById('confirm-texto').textContent = texto;
        const ok = document.getElementById('confirm-ok');
        const onOk = () => { cleanup(); confirmModal.hide(); resolve(true); };
        const onHide = () => { cleanup(); resolve(false); };
        function cleanup() {
            ok.removeEventListener('click', onOk);
            document.getElementById('modal-confirm').removeEventListener('hidden.bs.modal', onHide);
        }
        ok.addEventListener('click', onOk);
        document.getElementById('modal-confirm').addEventListener('hidden.bs.modal', onHide);
        confirmModal.show();
    });
}

async function eliminar(ids, texto) {
    if (!(await confirmar(texto))) return;
    try {
        await postJSON(PAGE, { accion: 'eliminar', ids });
        ids.forEach(id => tabla.querySelector(`tr[data-id="${id}"]`)?.classList.add('removing'));
        setTimeout(refrescarTabla, 300);
        showToast(ids.length === 1 ? 'Registro eliminado' : `${ids.length} registros eliminados`);
    } catch (err) {
        showToast(err.message, 'danger');
    }
}

tabla.addEventListener('click', event => {
    const btnEliminar = event.target.closest('.btn-eliminar');
    if (btnEliminar) {
        const id = btnEliminar.closest('tr').dataset.id;
        const aviso = btnEliminar.dataset.aviso ? ' ' + btnEliminar.dataset.aviso : '';
        eliminar([id], `¿Seguro que quieres eliminar "${btnEliminar.dataset.nombre}"?${aviso} Esta acción no se puede deshacer.`);
    }
    const btnEditar = event.target.closest('.btn-editar');
    if (btnEditar) abrirFormulario(JSON.parse(btnEditar.dataset.json));
});

document.querySelectorAll('[data-bulk]').forEach(item => {
    item.addEventListener('click', async () => {
        const ids = seleccionados();
        const accion = item.dataset.bulk;
        if (accion === 'eliminar') {
            const resenas = ids.reduce((suma, id) => {
                const btn = tabla.querySelector(`tr[data-id="${id}"] .btn-eliminar`);
                return suma + Number(btn.dataset.aviso?.match(/\d+/)?.[0] || 0);
            }, 0);
            const aviso = resenas ? ` También se borrarán ${resenas} reseña(s).` : '';
            eliminar(ids, `¿Eliminar ${ids.length} registro(s) seleccionados?${aviso} Esta acción no se puede deshacer.`);
            return;
        }
        try {
            await postJSON(PAGE, { accion, ids });
            await refrescarTabla();
            showToast(`${ids.length} registro(s) actualizados`);
        } catch (err) {
            showToast(err.message, 'danger');
        }
    });
});

const titulo = form?.querySelector('.modal-title');
const btnGuardar = form?.querySelector('[type="submit"]');

const cajaErrores = document.createElement('div');
cajaErrores.className = 'col-12';
cajaErrores.setAttribute('role', 'alert');
form?.querySelector('.modal-body').prepend(cajaErrores);
function mostrarErrores(lista) {
    cajaErrores.innerHTML = '';
    if (!lista.length) return;
    const alerta = document.createElement('div');
    alerta.className = 'alert alert-danger mb-0';
    const ul = document.createElement('ul');
    ul.className = 'mb-0 ps-3';
    lista.forEach(texto => { const li = document.createElement('li'); li.textContent = texto; ul.appendChild(li); });
    alerta.appendChild(ul);
    cajaErrores.appendChild(alerta);
}

function llenarFormulario(datos) {
    Object.entries(datos).forEach(([campo, valor]) => {
        const input = form.elements[campo];
        if (!input || campo === 'csrf' || campo === 'accion') return;
        if (input.type === 'checkbox') {
            input.checked = Boolean(Number(valor)) || valor === 'on';
        } else {
            input.value = valor ?? '';
        }
    });
}

const selectRecinto = form?.elements.recinto_id;
let recintoOriginal = '';
function revisarRecinto() {
    const opcion = selectRecinto.selectedOptions[0];
    const invalido = opcion?.dataset.inactivo && selectRecinto.value !== recintoOriginal;
    const msg = 'Ese recinto está inactivo; no se le pueden asignar conciertos nuevos.';
    selectRecinto.setCustomValidity(invalido ? msg : '');
    document.getElementById('recinto-feedback').textContent = invalido ? msg : 'Elige un recinto.';
}
selectRecinto?.addEventListener('change', revisarRecinto);

function abrirFormulario(datos = null) {
    form.reset();
    form.classList.remove('was-validated');
    mostrarErrores([]);
    form.elements.id.value = '';
    if (datos) llenarFormulario(datos);
    recintoOriginal = String(datos?.recinto_id ?? '');
    if (selectRecinto) revisarRecinto();
    titulo.textContent = datos?.id ? titulo.dataset.tituloEditar : titulo.dataset.tituloNuevo;
    formModal.show();
}
document.querySelector('[data-nuevo]')?.addEventListener('click', event => {
    event.preventDefault();
    abrirFormulario();
});
formModalEl?.addEventListener('shown.bs.modal', () => form.querySelector('input:not([type=hidden]), select')?.focus());

form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.checkValidity()) return;
    cargando(btnGuardar, true);
    try {
        const datos = Object.fromEntries(new FormData(form));
        const res = await postJSON(PAGE, datos);
        formModal.hide();
        await refrescarTabla();
        showToast(res.mensaje);
    } catch (err) {
        mostrarErrores(err.message.split(/(?<=\.)\s+/));
    } finally {
        cargando(btnGuardar, false);
    }
});

const reabrir = document.getElementById('reabrir');
if (reabrir && form) abrirFormulario(JSON.parse(reabrir.dataset.json));
