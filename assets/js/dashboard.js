document.querySelectorAll('[data-mod]').forEach(btn => {
    btn.addEventListener('click', async () => {
        const accion = btn.dataset.mod;
        if (accion === 'eliminar' && !(await confirmarAccion('Se eliminará la reseña definitivamente. Esta acción no se puede deshacer.', 'Sí, eliminar reseña'))) return;
        cargando(btn, true);
        try {
            await postJSON(location.pathname, { accion, resena_id: btn.closest('tr').dataset.resena });
            location.reload();
        } catch (err) {
            cargando(btn, false);
            showToast(err.message, 'danger');
        }
    });
});

document.querySelectorAll('.btn-leido').forEach(btn => {
    btn.addEventListener('click', async () => {
        const item = btn.closest('[data-mensaje]');
        cargando(btn, true);
        try {
            await postJSON(location.pathname, { accion: 'leido', mensaje_id: item.dataset.mensaje });
            item.classList.add('text-secondary');
            btn.remove();
        } catch (err) {
            cargando(btn, false);
            showToast(err.message, 'danger');
        }
    });
});
