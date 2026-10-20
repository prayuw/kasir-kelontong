(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle  = document.getElementById('menuToggle');

    // Menu samping untuk tampilan ponsel
    function setSidebar(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        if (overlay) overlay.classList.toggle('show', open);
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setSidebar(!sidebar.classList.contains('open'));
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function () { setSidebar(false); });
    }

    // Notifikasi menghilang otomatis setelah 4 detik
    document.querySelectorAll('[data-autohide]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });

    // Konfirmasi sebelum aksi berbahaya.
    // Pemakaian: <button data-confirm="Yakin ingin menghapus?">Hapus</button>
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-confirm]');
        if (el && !window.confirm(el.dataset.confirm)) {
            e.preventDefault();
        }
    });

    // Format rupiah untuk tampilan (perhitungan final tetap dilakukan di server)
    window.formatRupiah = function (angka) {
        return 'Rp ' + Number(angka).toLocaleString('id-ID');
    };

        /* ---------- Tampilkan/sembunyikan password ---------- */
    // Tombol: <button data-toggle-password="id_input"> (dibuat oleh tombol_toggle_password())
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) { return; }

        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) { return; }

        var sedangTerlihat = input.type === 'text';
        input.type = sedangTerlihat ? 'password' : 'text';

        btn.classList.toggle('is-visible', !sedangTerlihat);
        btn.setAttribute('aria-pressed', String(!sedangTerlihat));
        btn.setAttribute('aria-label', sedangTerlihat ? 'Tampilkan password' : 'Sembunyikan password');
        btn.setAttribute('title', sedangTerlihat ? 'Tampilkan password' : 'Sembunyikan password');

        // Kursor tetap berada di kolom input setelah tombol diklik
        input.focus();
    });
    
})();

