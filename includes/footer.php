</main>
<footer class="tn-footer">
    <div class="container">
        <a href="<?= url('index.php') ?>"><img class="tn-logo" src="<?= url('assets/img/logo-blanco.png') ?>" alt="Tinnitus"></a>
        <ul class="footer-links">
            <li><a href="<?= url('conciertos.php') ?>">Conciertos</a></li>
            <li><a href="<?= url('recintos.php') ?>">Recintos</a></li>
            <li><a href="<?= url('about.php') ?>">Sobre Tinnitus</a></li>
            <li><a href="<?= url('solicitar.php') ?>">Solicitar un registro</a></li>
            <li><a href="<?= url('contact.php') ?>">Contacto</a></li>
            <li><a href="<?= url('contact.php#politicas') ?>">Políticas de la comunidad</a></li>
        </ul>
        <p class="footer-small">FIME · UANL · Programación Web · Grupo 238 · © <?= date('Y') ?> Tinnitus</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?php if (!empty($pageScript)): ?>
<script src="<?= url('assets/js/' . $pageScript) ?>"></script>
<?php endif; ?>
</body>
</html>
