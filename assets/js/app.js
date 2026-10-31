async function postJSON(url, data) {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const body = new FormData();
    Object.entries(data).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach(v => body.append(key + '[]', v));
        } else {
            body.append(key, value);
        }
    });
    body.append('csrf', token);
    const response = await fetch(url, { method: 'POST', body, headers: { 'Accept': 'application/json' } });
    const json = await response.json().catch(() => ({ ok: false, error: 'Respuesta inválida del servidor' }));
    if (!response.ok || !json.ok) {
        throw new Error(json.error || 'Ocurrió un error');
    }
    return json;
}

function showToast(message, type = 'success') {
    let zone = document.getElementById('toast-zone');
    if (!zone) {
        zone = document.createElement('div');
        zone.id = 'toast-zone';
        zone.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(zone);
    }
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    el.querySelector('.toast-body').textContent = message;
    zone.appendChild(el);
    new bootstrap.Toast(el, { delay: 3000 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

document.querySelectorAll('form.needs-validation').forEach(form => {
    form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.querySelectorAll(':invalid').forEach(campo => {
                const contenedor = campo.closest('.input-group') || campo;
                let feedback = contenedor.parentElement.querySelector('.invalid-feedback');
                if (!feedback) {
                    feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    contenedor.after(feedback);
                }
                if (!feedback.dataset.fijo) {
                    feedback.textContent = campo.dataset.msg || campo.validationMessage;
                }
            });
            form.querySelector(':invalid')?.focus();
        }
        form.classList.add('was-validated');
    });
    form.querySelectorAll('.invalid-feedback').forEach(f => { if (f.textContent.trim()) f.dataset.fijo = '1'; });
});

document.querySelectorAll('[data-contador]').forEach(campo => {
    const span = document.getElementById(campo.dataset.contador);
    const actualizar = () => { span.textContent = campo.value.length; };
    campo.addEventListener('input', actualizar);
    actualizar();
});

function cargando(boton, activo) {
    if (!boton) return;
    if (activo) {
        boton.dataset.textoOriginal = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + boton.textContent.trim();
    } else if (boton.dataset.textoOriginal) {
        boton.innerHTML = boton.dataset.textoOriginal;
        boton.disabled = false;
    }
}

function confirmarAccion(texto, textoBoton = 'Sí, eliminar') {
    let el = document.getElementById('modal-confirmar-accion');
    if (!el) {
        el = document.createElement('div');
        el.className = 'modal fade';
        el.id = 'modal-confirmar-accion';
        el.tabIndex = -1;
        el.innerHTML = `<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">¿Estás segura(o)?</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="mb-0"></p></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-danger" data-ok></button></div></div></div>`;
        document.body.appendChild(el);
    }
    el.querySelector('.modal-body p').textContent = texto;
    el.querySelector('[data-ok]').textContent = textoBoton;
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    return new Promise(resolve => {
        let aceptado = false;
        el.querySelector('[data-ok]').onclick = () => { aceptado = true; modal.hide(); };
        el.addEventListener('hidden.bs.modal', () => resolve(aceptado), { once: true });
        modal.show();
    });
}

document.querySelectorAll('form[data-confirmar]').forEach(formulario => {
    formulario.addEventListener('submit', async event => {
        if (formulario.dataset.confirmado) return;
        event.preventDefault();
        if (await confirmarAccion(formulario.dataset.confirmar, 'Sí, continuar')) {
            formulario.dataset.confirmado = '1';
            formulario.requestSubmit();
        }
    });
});

document.querySelectorAll('.flash-zone .alert-success, .flash-zone .alert-info').forEach(alerta => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(alerta).close(), 6000);
});

document.querySelectorAll('.tn-search').forEach(form => {
    const input = form.querySelector('input[name="q"]');
    const lista = document.createElement('ul');
    lista.className = 'search-suggest';
    lista.id = 'sugerencias-busqueda';
    lista.setAttribute('role', 'listbox');
    lista.hidden = true;
    form.appendChild(lista);
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-controls', lista.id);
    input.setAttribute('aria-autocomplete', 'list');

    let timer;
    let seleccion = -1;
    const opciones = () => [...lista.querySelectorAll('a')];

    function cerrar() { lista.hidden = true; seleccion = -1; input.setAttribute('aria-expanded', 'false'); }
    function marcar(i) {
        const items = opciones();
        items.forEach((a, j) => a.classList.toggle('active', j === i));
        seleccion = i;
        if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
    }

    function pintar(resultados, texto) {
        lista.innerHTML = '';
        if (!resultados.length) {
            const li = document.createElement('li');
            li.className = 'vacio';
            li.textContent = `Sin resultados para “${texto}”`;
            lista.appendChild(li);
        }
        resultados.forEach(r => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            const a = document.createElement('a');
            a.href = r.url;
            a.innerHTML = `<i class="bi ${r.icono}" aria-hidden="true"></i><span><strong></strong><small></small></span><em></em>`;
            a.querySelector('strong').textContent = r.titulo;
            a.querySelector('small').textContent = r.detalle;
            a.querySelector('em').textContent = r.tipo;
            li.appendChild(a);
            lista.appendChild(li);
        });
        const todos = document.createElement('li');
        todos.innerHTML = '<a class="ver-todos" href="#">Ver todos los resultados <i class="bi bi-arrow-right"></i></a>';
        todos.querySelector('a').href = form.action + '?q=' + encodeURIComponent(texto);
        lista.appendChild(todos);
        lista.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        seleccion = -1;
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const texto = input.value.trim();
        if (texto.length < 2) return cerrar();
        timer = setTimeout(async () => {
            try {
                const res = await fetch('buscar.php?q=' + encodeURIComponent(texto)).then(r => r.json());
                if (input.value.trim() === texto) pintar(res.resultados, texto);
            } catch { cerrar(); }
        }, 200);
    });

    input.addEventListener('keydown', event => {
        if (lista.hidden) return;
        const items = opciones();
        if (event.key === 'ArrowDown') { event.preventDefault(); marcar(Math.min(seleccion + 1, items.length - 1)); }
        if (event.key === 'ArrowUp') { event.preventDefault(); marcar(Math.max(seleccion - 1, 0)); }
        if (event.key === 'Escape') cerrar();
        if (event.key === 'Enter' && seleccion >= 0) { event.preventDefault(); location.href = items[seleccion].href; }
    });
    document.addEventListener('click', event => { if (!form.contains(event.target)) cerrar(); });
});
