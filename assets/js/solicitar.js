const grupos = {
    concierto: document.getElementById('campos-concierto'),
    recinto: document.getElementById('campos-recinto'),
};

function mostrarTipo(tipo) {
    Object.entries(grupos).forEach(([nombre, grupo]) => {
        const activo = nombre === tipo;
        grupo.hidden = !activo;
        grupo.disabled = !activo;
    });
    document.getElementById('tipo-' + tipo).checked = true;
    document.getElementById('form-solicitud').classList.remove('was-validated');
}

document.querySelectorAll('input[name="tipo"]').forEach(radio => {
    radio.addEventListener('change', () => mostrarTipo(radio.value));
});

document.querySelectorAll('[data-cambiar-tipo]').forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        mostrarTipo(link.dataset.cambiarTipo);
        grupos[link.dataset.cambiarTipo].querySelector('input, select').focus();
    });
});
