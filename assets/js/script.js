/* =====================================================================
   SISTEM PENYIMPANAN FILE — JAVASCRIPT (VANILLA)
   ---------------------------------------------------------------------
   Tanpa framework (tidak ada React/Vue/jQuery).
   Semua kode dibungkus IIFE agar tidak mengotori scope global,
   kecuali fungsi yang memang perlu dipanggil dari HTML (window.showToast).

   Fitur:
     1. Sidebar mobile (hamburger)
     2. Modal upload
     3. Toast notification
     4. Validasi ukuran file sebelum upload
     5. Preview nama file terpilih
     6. Konfirmasi hapus
     7. Search & filter (debounce)
     8. Loading indicator
     9. Sortable table header
    10. Format ukuran file
   ===================================================================== */

(function () {
    'use strict';

    /* ========================================================
       KONSTANTA
       ======================================================== */

    var MAX_SIZE = 10 * 1024 * 1024;      // 10 MB
    var MAX_SIZE_LABEL = '10 MB';

    var ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'zip'
    ];

    var EXTENSION_LABELS = {
        pdf: 'PDF', doc: 'DOC', docx: 'DOCX', xls: 'XLS', xlsx: 'XLSX',
        ppt: 'PPT', pptx: 'PPTX', jpg: 'JPG', jpeg: 'JPEG',
        png: 'PNG', zip: 'ZIP'
    };

    /* ========================================================
       UTILITAS
       ======================================================== */

    /** Ambil elemen berdasarkan selector. */
    function $(selector, context) {
        return (context || document).querySelector(selector);
    }

    /** Ambil semua elemen berdasarkan selector. */
    function $$(selector, context) {
        return Array.prototype.slice.call(
            (context || document).querySelectorAll(selector)
        );
    }

    /**
     * Ubah ukuran byte menjadi format enak dibaca.
     * 1536 -> "1.5 KB"
     */
    function formatBytes(bytes) {
        if (!bytes) { return '0 B'; }

        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = 0;
        var value = bytes;

        while (value >= 1024 && i < units.length - 1) {
            value = value / 1024;
            i++;
        }

        return (i === 0 ? Math.round(value) : value.toFixed(1)) + ' ' + units[i];
    }

    /** Ambil ekstensi dari nama file (huruf kecil, tanpa titik). */
    function getExtension(filename) {
        var parts = String(filename).split('.');
        if (parts.length < 2) { return ''; }
        return parts.pop().toLowerCase();
    }

    /**
     * Debounce: jeda pemanggilan sampai user berhenti mengetik.
     * Dipakai pada search & filter supaya tidak request tiap ketikan.
     */
    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, wait);
        };
    }

    /* ========================================================
       1. SIDEBAR MOBILE
       ======================================================== */

    function initSidebar() {
        var sidebar = $('#sidebar');
        var overlay = $('#sidebarOverlay');
        var toggle = $('#sidebarToggle');
        var closeBtn = $('#sidebarClose');

        if (!sidebar || !toggle) { return; }

        function openSidebar() {
            sidebar.classList.add('open');
            if (overlay) { overlay.classList.add('show'); }
            toggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            if (overlay) { overlay.classList.remove('show'); }
            toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function () {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        // Tutup sidebar saat tekan Escape (aksesibilitas)
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Tutup otomatis bila layar membesar ke mode desktop
        window.addEventListener('resize', debounce(function () {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        }, 150));
    }

    /* ========================================================
       2. MODAL UPLOAD
       ======================================================== */

    function initModal() {
        var modal = $('#uploadModal');
        if (!modal) { return; }

        // Semua tombol pemicu modal
        $$('[data-modal-open]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                openModal();
            });
        });

        // Semua tombol penutup modal
        $$('[data-modal-close]', modal).forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });
        });

        // Klik area gelap di luar modal
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        // Tekan Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        function openModal() {
            modal.hidden = false;
            document.body.style.overflow = 'hidden';

            // Fokuskan field pertama
            var firstInput = $('input, select, textarea', modal);
            if (firstInput) {
                setTimeout(function () { firstInput.focus(); }, 60);
            }
        }

        function closeModal() {
            modal.hidden = true;
            document.body.style.overflow = '';
        }
    }

    /* ========================================================
       3. TOAST NOTIFICATION
       ======================================================== */

    /**
     * Tampilkan toast.
     * Dipakai juga dari HTML: onclick="showToast('success','Tersimpan!')"
     *
     * @param {string} type    'success' | 'error' | 'info'
     * @param {string} message pesan yang ditampilkan
     */
    function showToast(type, message) {
        var container = $('#toastContainer');
        if (!container) { return; }

        var icons = { success: '✓', error: '!', info: 'i' };

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'status');

        // Bagian-bagian toast
        var icon = document.createElement('span');
        icon.className = 'toast-icon';
        icon.textContent = icons[type] || 'i';

        var body = document.createElement('div');
        body.className = 'toast-body';
        body.textContent = message;   // textContent aman dari XSS

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', 'Tutup');
        close.innerHTML = '&times;';

        toast.appendChild(icon);
        toast.appendChild(body);
        toast.appendChild(close);
        container.appendChild(toast);

        // Tombol tutup
        function dismiss() {
            toast.classList.add('hiding');
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        }

        close.addEventListener('click', dismiss);

        // Hilang otomatis setelah 4.5 detik
        setTimeout(dismiss, 4500);
    }

    // Expose ke window agar bisa dipakai dari atribut onclick di HTML
    window.showToast = showToast;

    /** Pasang penutup untuk toast yang sudah ada di HTML (dari PHP). */
    function initExistingToasts() {
        $$('[data-toast]').forEach(function (toast) {
            var close = $('[data-toast-close]', toast);
            if (!close) { return; }

            function dismiss() {
                toast.classList.add('hiding');
                setTimeout(function () {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 220);
            }

            close.addEventListener('click', dismiss);
            setTimeout(dismiss, 4500);
        });
    }

    /* ========================================================
       4 & 5. VALIDASI UKURAN + PREVIEW NAMA FILE
       ======================================================== */

    /**
     * Pasang validasi client pada sebuah input file.
     *
     * Dipakai dua kali:
     *   - input di dalam modal upload   (#upload-file)
     *   - input di halaman upload       (#upload-page-files)
     */
    function attachFileInput(input) {
        if (!input) { return; }

        // Daftar file & input nama file berada di form yang sama
        var form = input.closest('form');
        if (!form) { return; }

        var list = $('.file-list', form);
        var nameInput = $('input[name="file_name"]', form);

        input.addEventListener('change', function () {
            var files = input.files;

            if (list) { list.innerHTML = ''; }

            if (!files || files.length === 0) { return; }

            var totalValid = 0;
            var firstValidName = '';

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var ext = getExtension(file.name);
                var allowed = ALLOWED_EXTENSIONS.indexOf(ext) !== -1;
                var smallEnough = file.size <= MAX_SIZE;
                var isValid = allowed && smallEnough;

                var item = document.createElement('li');
                item.className = 'file-list-item' + (isValid ? '' : ' invalid');

                // Nama file
                var name = document.createElement('span');
                name.className = 'fl-name';
                name.textContent = file.name;
                name.title = file.name;

                // Ukuran
                var size = document.createElement('span');
                size.className = 'fl-size';
                size.textContent = formatBytes(file.size);

                item.appendChild(name);
                item.appendChild(size);

                // Catatan kalau tidak valid
                if (!allowed) {
                    var noteExt = document.createElement('span');
                    noteExt.className = 'fl-size';
                    noteExt.textContent = 'Format .' + (ext || '?').toUpperCase() + ' tidak diizinkan';
                    item.appendChild(noteExt);
                } else if (!smallEnough) {
                    var noteSize = document.createElement('span');
                    noteSize.className = 'fl-size';
                    noteSize.textContent = 'Maksimal ' + MAX_SIZE_LABEL;
                    item.appendChild(noteSize);
                } else {
                    totalValid++;
                    if (!firstValidName) {
                        // Nama tanpa ekstensi, untuk mengisi field "Nama File"
                        firstValidName = file.name.replace(/\.[^.]+$/, '');
                    }
                }

                list.appendChild(item);
            }

            // Isi otomatis field "Nama File" dengan nama file pertama,
            // hanya bila user belum mengetik sendiri.
            if (nameInput && firstValidName && !nameInput.value.trim()) {
                nameInput.value = firstValidName;
            }

            // Matikan tombol submit bila tidak ada satu pun file yang valid
            var submit = $('[type="submit"]', form);
            if (submit) {
                submit.disabled = (totalValid === 0);
            }

            if (totalValid > 0) {
                showToast('info', totalValid + ' file siap diunggah.');
            }
        });
    }

    function initFileInput() {
        // Input di dalam modal upload cepat
        attachFileInput($('#upload-file'));

        // Input di halaman upload.php
        attachFileInput($('#upload-page-files'));
    }

    /* ========================================================
       6. KONFIRMASI HAPUS
       ======================================================== */

    function initDeleteConfirm() {
        $$('[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-confirm')
                    || 'Yakin ingin menghapus item ini?';

                if (!window.confirm(message)) {
                    // Batal: jangan kirim form
                    event.preventDefault();
                }
            });
        });
    }

    /* ========================================================
       7. SEARCH & FILTER (submit otomatis dengan debounce)
       ======================================================== */

    function initSearchFilter() {
        var form = $('#filterForm');
        if (!form) { return; }

        var searchInput = $('#searchInput', form);
        var loading = $('#tableLoading');

        function showLoading() {
            if (loading) { loading.classList.add('show'); }
        }

        // Mengetik di kotak search -> submit form otomatis (tunda 500ms)
        if (searchInput) {
            var autoSubmit = debounce(function () {
                showLoading();
                form.submit();
            }, 500);

            searchInput.addEventListener('input', autoSubmit);
        }

        // Mengubah dropdown filter/sort -> submit langsung
        $$('select[data-auto-submit]', form).forEach(function (select) {
            select.addEventListener('change', function () {
                showLoading();
                form.submit();
            });
        });

        // Klik tombol "Terapkan" -> submit
        var applyBtn = $('[data-filter-submit]', form);
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                showLoading();
            });
        }
    }

    /* ========================================================
       8. LOADING INDICATOR
       ======================================================== */

    /**
     * Tampilkan spinner pada tombol saat form dikirim.
     * Loop: menambahkan class "is-loading" ke tombol submit
     * supaya user tahu request sedang diproses.
     */
    function initFormLoading() {
        $$('form[data-loading]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = $('[type="submit"]', form);
                if (!button) { return; }

                // Jangan diubah bila form tidak valid (HTML5 validation)
                if (!form.checkValidity()) { return; }

                button.setAttribute('data-loading', 'true');

                // Cegah klik ganda
                setTimeout(function () {
                    button.disabled = true;
                }, 10);
            });
        });
    }

    /**
     * Tampilkan overlay loading penuh halaman.
     * Dipakai setelah redirect (halaman memuat data).
     */
    function showPageLoading() {
        var overlay = $('#pageLoading');
        if (!overlay) { return; }

        overlay.classList.add('show');

        // Hilangkan lagi setelah halaman selesai (jaring pengaman)
        window.addEventListener('load', function () {
            overlay.classList.remove('show');
        });

        // Hilangkan maksimal 3 detik walau event load tidak pernah datang
        setTimeout(function () {
            overlay.classList.remove('show');
        }, 3000);
    }

    /* ========================================================
       9. SORTABLE TABLE HEADER
       ======================================================== */
    // Header tabel punya data-sort="uploaded_at". Klik menukar
    // arah sort lalu mengarahkan ke URL yang sama dengan ?sort=...

    function initSortableTable() {
        $$('th[data-sort]').forEach(function (th) {
            th.style.cursor = 'pointer';
            th.setAttribute('title', 'Klik untuk urutkan');

            // Nilai sort & arah yang sedang aktif (di-set dari PHP)
            var currentSort = th.getAttribute('data-sort-active');
            var currentDir = th.getAttribute('data-dir-active');   // 'asc' atau 'desc'

            // Tampilkan panah pada kolom yang sedang aktif
            if (currentSort && th.getAttribute('data-sort') === currentSort) {
                var arrow = (currentDir === 'asc') ? ' ▲' : ' ▼';
                // Buang panah lama bila ada, lalu pasang yang baru
                th.textContent = th.textContent.replace(/[ ▲▼]+$/, '') + arrow;
            }

            th.addEventListener('click', function () {
                var field = th.getAttribute('data-sort');

                // Klik kolom yang sama: arah sort dibalik
                // Klik kolom lain: selalu urut naik (asc) dulu
                var dir = (currentSort === field && currentDir === 'asc') ? 'desc' : 'asc';

                var url = new URL(window.location.href);
                url.searchParams.set('sort', field);
                url.searchParams.set('dir', dir);
                url.searchParams.set('page', '1');

                window.location.href = url.toString();
            });
        });
    }

    /* ========================================================
       INISIALISASI
       ======================================================== */

    function init() {
        initSidebar();
        initModal();
        initExistingToasts();
        initFileInput();
        initDeleteConfirm();
        initSearchFilter();
        initFormLoading();
        initSortableTable();
        showPageLoading();
    }

    // Jalankan setelah DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
