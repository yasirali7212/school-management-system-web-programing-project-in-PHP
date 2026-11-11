        </main>

        <!-- Sticky Footer -->
        <footer class="bg-white border-top py-3 px-4 text-center text-muted small mt-auto">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
                <span>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</span>
                <span class="mt-1 mt-sm-0">Role: <strong class="text-primary text-uppercase"><?php echo htmlspecialchars($currentUser['role'] ?? 'Guest'); ?></strong></span>
            </div>
        </footer>
    </div> <!-- .app-main -->
</div> <!-- .app-wrapper -->

<!-- Bootstrap 5.3 Bundle JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Mobile Sidebar & App Interactions -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('appSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (toggleBtn && sidebar && backdrop) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        });

        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }

    // Auto-dismiss standard alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 6000);
    });
});
</script>

</body>
</html>
