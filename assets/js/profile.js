const avatar = document.getElementById('p-avatar');
if (avatar) {
    const preview = document.getElementById('avatar-preview');
    avatar.addEventListener('change', () => {
        const archivo = avatar.files[0];
        avatar.setCustomValidity('');
        avatar.classList.remove('is-invalid');
        preview.classList.add('d-none');
        if (!archivo) return;
        const tipoOk = ['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type);
        const pesoOk = archivo.size <= Number(avatar.dataset.maxBytes);
        if (!tipoOk || !pesoOk) {
            const msg = !tipoOk ? 'La foto debe ser JPG, PNG o WEBP.' : 'La foto pesa más de 2 MB.';
            avatar.setCustomValidity(msg);
            document.getElementById('avatar-feedback').textContent = msg;
            avatar.classList.add('is-invalid');
            return;
        }
        preview.src = URL.createObjectURL(archivo);
        preview.classList.remove('d-none');
    });
}

const modalTop4 = document.getElementById('modal-top4');
if (modalTop4) {
    const modal = new bootstrap.Modal(modalTop4);
    let posicion = null;

    document.querySelectorAll('.btn-top4').forEach(btn => {
        btn.addEventListener('click', event => {
            event.preventDefault();
            posicion = btn.dataset.posicion;
            document.getElementById('top4-pos').textContent = posicion;
            modal.show();
        });
    });

    document.getElementById('top4-buscar')?.addEventListener('input', event => {
        const texto = event.target.value.toLowerCase();
        document.querySelectorAll('#top4-lista button').forEach(item => {
            item.classList.toggle('d-none', !item.textContent.toLowerCase().includes(texto));
        });
    });

    document.querySelectorAll('#top4-lista button').forEach(item => {
        item.addEventListener('click', async () => {
            try {
                await postJSON('api.php', { accion: 'top4', posicion, concierto_id: item.dataset.concierto });
                location.reload();
            } catch (err) {
                showToast(err.message, 'danger');
            }
        });
    });

    document.querySelectorAll('.btn-top4-quitar').forEach(btn => {
        btn.addEventListener('click', async () => {
            try {
                await postJSON('api.php', { accion: 'top4', posicion: btn.dataset.posicion, concierto_id: 0 });
                location.reload();
            } catch (err) {
                showToast(err.message, 'danger');
            }
        });
    });
}
