<?php defined('ESK') || exit('No direct access.'); ?>
    </main>
  </div>

  <div id="esk-sidebar-backdrop" class="fixed inset-0 z-drawer hidden bg-slate-900/40 lg:hidden"></div>

  <!-- Admin JS libraries via CDN (no npm): SweetAlert2, Chart.js. -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="<?= e(asset('js/admin.js')) ?>"></script>
  <script>
    // Live clock in the topbar.
    (function () {
      var el = document.getElementById('esk-clock');
      if (!el) return;
      setInterval(function () {
        var d = new Date();
        var h = d.getHours(), m = d.getMinutes(), s = d.getSeconds(), ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        el.textContent = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' ' + ap;
      }, 1000);
    })();
  </script>
  <?php if (!empty($admin_scripts)) echo $admin_scripts; ?>
</body>
</html>
