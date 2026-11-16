const modalReportes = document.getElementById('modal-reportes');
const formReportes = document.getElementById('form-reportes');
const tarjetas = document.getElementById('reporte-tarjetas');
const tablaReporte = document.getElementById('reporte-tabla');

async function cargarReporte() {
    const params = new URLSearchParams(new FormData(formReportes));
    params.set('modulo', modalReportes.dataset.modulo);
    const url = modalReportes.dataset.url + '?' + params;
    document.getElementById('exportar-csv').href = url + '&formato=csv';
    document.getElementById('exportar-pdf').href = url + '&formato=pdf';
    try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const datos = await res.json();
        if (!datos.ok) throw new Error(datos.error);
        mostrarReporte(datos);
    } catch (err) {
        showToast(err.message || 'No se pudo cargar el reporte.', 'danger');
    }
}

function celda(etiqueta, texto) {
    const el = document.createElement(etiqueta);
    el.textContent = texto ?? '—';
    return el;
}

function mostrarReporte(datos) {
    tarjetas.innerHTML = '';
    datos.tarjetas.forEach(([etiqueta, valor]) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-lg-3';
        col.innerHTML = '<div class="stat-card"><div class="value"></div><div class="label"></div></div>';
        col.querySelector('.value').textContent = valor;
        col.querySelector('.label').textContent = etiqueta;
        tarjetas.appendChild(col);
    });

    const encabezado = document.createElement('tr');
    datos.columnas.forEach(nombre => encabezado.appendChild(celda('th', nombre)));
    tablaReporte.tHead.replaceChildren(encabezado);

    const cuerpo = tablaReporte.tBodies[0];
    cuerpo.replaceChildren();
    datos.filas.forEach(fila => {
        const tr = document.createElement('tr');
        fila.forEach(valor => tr.appendChild(celda('td', valor)));
        cuerpo.appendChild(tr);
    });
    if (!datos.filas.length) {
        const td = celda('td', 'No hay datos en ese rango de fechas.');
        td.colSpan = datos.columnas.length;
        td.className = 'text-center text-secondary py-4';
        cuerpo.appendChild(document.createElement('tr')).appendChild(td);
    }
}

modalReportes.addEventListener('show.bs.modal', cargarReporte);
formReportes.addEventListener('change', cargarReporte);
formReportes.addEventListener('submit', event => event.preventDefault());
