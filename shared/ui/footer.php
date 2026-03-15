</div><!-- /container-fluid -->

<footer class="footer mt-auto py-2 bg-light border-top">
    <div class="container-fluid">
        <small class="text-muted"><?= APP_NAME ?> &middot; <?= date('Y') ?></small>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (für DataTables) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<!-- DataTables Deutsch -->
<script>
const dtDeutsch = {
    language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/de-DE.json'
    }
};
</script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<!-- Jagd JS -->
<script src="<?= BASE_URL ?>shared/ui/assets/js/jagd.js"></script>

<?= $extraJs ?? '' ?>

<!-- Toast-Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999" id="toastContainer"></div>

</body>
</html>
