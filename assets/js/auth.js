const subtitulos = {
    login: 'Ingresa y sigue registrando noches inolvidables',
    registro: 'Crea tu cuenta y empieza tu diario de conciertos',
};
document.querySelectorAll('[data-go-tab]').forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        const destino = link.dataset.goTab;
        document.getElementById('tab-login').hidden = destino !== 'login';
        document.getElementById('tab-registro').hidden = destino !== 'registro';
        document.getElementById('auth-subtitulo').textContent = subtitulos[destino];
        document.title = (destino === 'login' ? 'Iniciar sesión' : 'Crear cuenta') + ' · Tinnitus';
        document.querySelector(`#tab-${destino} input:not([type=hidden])`).focus();
    });
});

document.querySelectorAll('.toggle-pass').forEach(button => {
    button.addEventListener('click', () => {
        const input = button.parentElement.querySelector('input');
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

const username = document.getElementById('username');
const pass = document.getElementById('reg-password');
const pass2 = document.getElementById('password2');

const usernameFeedback = document.getElementById('username-feedback');
const formatoUsuario = usernameFeedback.textContent;
let timerUsuario;
username.addEventListener('input', () => {
    username.value = username.value.toLowerCase().replace(/\s/g, '');
    username.setCustomValidity('');
    username.classList.remove('is-valid', 'is-invalid');
    usernameFeedback.textContent = formatoUsuario;
    clearTimeout(timerUsuario);
    if (!username.checkValidity()) return;
    timerUsuario = setTimeout(async () => {
        const res = await fetch('auth.php?check_username=' + encodeURIComponent(username.value)).then(r => r.json());
        if (res.disponible) {
            username.classList.add('is-valid');
        } else {
            username.setCustomValidity(res.error);
            usernameFeedback.textContent = res.error;
            username.classList.add('is-invalid');
        }
    }, 400);
    revisarPassword();
});

function revisarPassword() {
    const v = pass.value;
    const reglas = {
        largo: v.length >= 8 && v.length <= 72,
        letra: /\p{L}/u.test(v),
        numero: /\d/.test(v),
        usuario: v.length > 0 && (username.value === '' || !v.toLowerCase().includes(username.value)),
    };
    let cumplidas = 0;
    Object.entries(reglas).forEach(([regla, ok]) => {
        const li = document.querySelector(`#pass-rules [data-rule="${regla}"]`);
        li.classList.toggle('ok', ok);
        li.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        if (ok) cumplidas++;
    });
    const bar = document.getElementById('pass-meter-bar');
    bar.style.width = (cumplidas * 25) + '%';
    bar.dataset.nivel = cumplidas;
    pass.setCustomValidity(cumplidas === 4 ? '' : 'No cumple los requisitos');
    revisarCoincidencia();
}

function revisarCoincidencia() {
    pass2.setCustomValidity(pass.value === pass2.value ? '' : 'No coinciden');
}

pass.addEventListener('input', revisarPassword);
pass2.addEventListener('input', revisarCoincidencia);
