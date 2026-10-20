    </div><!-- /.page-body -->
</div><!-- /.main-content -->
</div><!-- /.app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
    const GLEMS_FLASHES = <?= json_encode(get_flashes()) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        GLEMS_FLASHES.forEach(f => showToast(f.type, f.message));
        setInterval(fetchNotificationCount, 30000);
    });
</script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>