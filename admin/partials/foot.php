<?php
/**
 * Admin layout foot — closes content/main/layout and loads scripts.
 * Set $load_charts = true before including to load Chart.js on that page.
 */
?>
        </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<?php if (!empty($load_charts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
<!-- Lucide icons (admin UI) -->
<script src="https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js"></script>
<script>
    (function () {
        function draw() { try { window.lucide && window.lucide.createIcons(); } catch (e) {} }
        draw(); document.addEventListener('DOMContentLoaded', draw); window.PWFdrawIcons = draw;
    })();
</script>
<?php if (!empty($load_analytics)): ?>
<!-- Analytics dashboard: export libs (jsPDF + SheetJS) then the dashboard app -->
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="<?= e(asset('js/analytics.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
