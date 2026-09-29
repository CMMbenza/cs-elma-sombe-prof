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

<!-- ICÔNE FLOTTANTE EN POSITION FIXE (NOTIFICATIONS PROF) -->
<?php if (!empty($_SESSION['prof']['id'])): ?>
<!-- Inclusion des icônes Bootstrap -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<div id="notif-floating-container" style="position: fixed; bottom: 25px; right: 25px; z-index: 9999;">
    <!-- Bouton Cloche -->
    <button id="notifBtn" type="button"
        class="btn btn-primary rounded-circle shadow-lg position-relative d-flex align-items-center justify-content-center"
        style="width: 58px; height: 58px;">
        <i class="bi bi-bell-fill fs-4"></i>
        <span id="notifBadge"
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light"
            style="display: none; font-size: 11px;">
            0
        </span>
    </button>

    <!-- Panneau de notification -->
    <div id="notifBox" class="card border-0 shadow-lg rounded-4 position-absolute"
        style="display: none; bottom: 70px; right: 0; width: 340px; z-index: 10000; overflow: hidden;">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-bell-fill text-primary"></i>
                <h6 class="fw-bold mb-0 text-dark">Notifications</h6>
            </div>
            <!-- Bouton Fermer (X) -->
            <button id="closeNotifBtn" type="button" class="btn-close" aria-label="Fermer"></button>
        </div>
        
        <!-- Conteneur défilable (scrollable) -->
        <div class="card-body p-0" id="notifContent" style="max-height: 380px; overflow-y: auto;">
            <div class="text-center py-3 text-muted small">Vérification...</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('notifBtn');
    const box = document.getElementById('notifBox');
    const closeBtn = document.getElementById('closeNotifBtn');
    const badge = document.getElementById('notifBadge');
    const content = document.getElementById('notifContent');

    let previousTotal = null;

    function checkPendingActions() {
        const notifUrl = window.location.origin + '/prof/layout/notification.php';

        fetch(notifUrl)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const currentTotal = data.total;

                    if (currentTotal > 0) {
                        badge.textContent = currentTotal;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }

                    if (!data.items || data.items.length === 0) {
                        content.innerHTML = `
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-check-circle-fill text-success fs-5 d-block mb-1"></i>
                                Tout est à jour ! Aucune notification.
                            </div>`;
                        previousTotal = 0;
                        return;
                    }

                    let html = '<div class="list-group list-group-flush">';
                    data.items.forEach(item => {
                        const badgeColor = item.badge_style || 'secondary';
                        html += `
                            <a href="${item.lien}" class="list-group-item list-group-item-action p-3 border-0 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark small">${item.titre}</span>
                                    <span class="badge bg-${badgeColor} rounded-pill">${item.badge}</span>
                                </div>
                                <div class="small text-muted mb-0">${item.message}</div>
                            </a>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;

                    if (previousTotal !== null && currentTotal > previousTotal) {
                        box.style.display = 'block';
                    }

                    previousTotal = currentTotal;
                }
            })
            .catch(err => {
                console.error("Erreur notifications prof:", err);
            });
    }

    if (btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                box.style.display = 'none';
            });
        }

        document.addEventListener('click', function(e) {
            if (!box.contains(e.target) && !btn.contains(e.target)) {
                box.style.display = 'none';
            }
        });

        checkPendingActions();
        setInterval(checkPendingActions, 5000);
    }
});
</script>
<?php endif; ?>

<script>
// Maintient la session active
setInterval(() => {
    fetch(window.location.href, { method: 'HEAD' });
}, 300000);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>