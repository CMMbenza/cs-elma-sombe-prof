<?php // prof/layout/footer.php ?>
<footer class="footer mt-5 py-3 bg-white border-top">
    <div class="container text-center">
        <div class="row align-items-center">
            <div class="col-md-6 text-md-start mb-2 mb-md-0 text-muted small">
                © <?= date('Y') ?> — <strong>Espace Enseignant</strong>. Tous droits réservés.
            </div>
            <div class="col-md-6 text-md-end text-muted small">
                <span class="me-3">Version 2.0</span>
                <a href="#" class="text-decoration-none text-muted me-2">Aide</a>
                <a href="/prof/logout.php" class="text-decoration-none text-muted">Déconnexion</a>
            </div>
        </div>
    </div>
</footer>
    <script>
    // Maintient la session active en envoyant un signal discret toutes les 5 minutes (300 000 ms)
    setInterval(() => {
        fetch(window.location.href, {
            method: 'HEAD'
        });
    }, 300000);
    </script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>