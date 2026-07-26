<?php
// includes/footer.php

// Include footer navigasi mobile jika role admin/nasabah (optional, karena sidebar sudah responsif)
/* if (isset($_SESSION['role'])) {
   if ($_SESSION['role'] == 'admin') include 'footer_admin_mobile.php';
   if ($_SESSION['role'] == 'nasabah') include 'footer_nasabah_mobile.php';
}
*/
?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar-wrapper');
        const toggleBtn = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('mobile-overlay');

        if (sidebar && toggleBtn && overlay) {
            // Fungsi Buka/Tutup Sidebar
            function toggleSidebar() {
                // Toggle class translate untuk menampilkan/menyembunyikan
                const isClosed = sidebar.classList.contains('-translate-x-full');
                
                if (isClosed) {
                    // Buka Sidebar
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.remove('hidden');
                } else {
                    // Tutup Sidebar
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                }
            }

            // Event Klik Tombol Hamburger
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // Mencegah event bubbling
                toggleSidebar();
            });

            // Event Klik Overlay (Tutup Sidebar)
            overlay.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            });
        }
    });
</script>

</body>
</html>