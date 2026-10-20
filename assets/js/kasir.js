(function () {
    'use strict';

    var root = document.getElementById('pos');
    if (!root) { return; }

    var API          = root.dataset.api;
    var cariEl       = document.getElementById('cariProduk');
    var kategoriEl   = document.getElementById('filterKategori');
    var statusEl     = document.getElementById('statusProduk');
    var listEl       = document.getElementById('hasilProduk');
    var cartEl       = document.getElementById('daftarKeranjang');
    var noticeEl     = document.getElementById('pesanPos');
    var totalItemEl  = document.getElementById('totalItem');
    var totalEl      = document.getElementById('totalBelanja');
    var bayarEl      = document.getElementById('uangBayar');
    var kembalianEl  = document.getElementById('kembalian');
    var pesanBayarEl = document.getElementById('pesanBayar');
    var formEl       = document.getElementById('formTransaksi');
    var btnSimpan    = document.getElementById('btnSimpan');
    var btnKosong    = document.getElementById('kosongkan');

    var cart        = [];
    var searchTimer = null;
    var noticeTimer = null;
    var requestSeq  = 0;

    /* ---------- Utilitas ---------- */

    function rupiah(angka) {
        return 'Rp ' + Number(angka).toLocaleString('id-ID');
    }

    function buatElemen(tag, teks, kelas) {
        var el = document.createElement(tag);
        if (teks !== undefined && teks !== null) { el.textContent = teks; }
        if (kelas) { el.className = kelas; }
        return el;
    }

    function buatHidden(name, value, kelas) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        input.className = kelas || '';
        return input;
    }

    function tampilkanPesan(teks, tipe) {
        noticeEl.textContent = teks;
        noticeEl.className = 'alert alert-' + (tipe || 'warning');
        noticeEl.hidden = false;
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(function () { noticeEl.hidden = true; }, 3500);
    }

    /* ---------- Pencarian produk ---------- */

    function muatProduk() {
        var seq = ++requestSeq;
        var params = new URLSearchParams({
            q: cariEl.value.trim(),
            kategori: kategoriEl.value
        });

        statusEl.textContent = 'Memuat produk...';

        fetch(API + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (r) {
                if (seq !== requestSeq) { return; }
                if (!r.ok || !r.data.ok) {
                    throw new Error(r.data.message || 'Gagal memuat produk.');
                }
                tampilkanProduk(r.data.items);
            })
            .catch(function (err) {
                if (seq !== requestSeq) { return; }
                listEl.textContent = '';
                statusEl.textContent = err.message || 'Gagal memuat produk. Periksa koneksi Anda.';
            });
    }

    function tampilkanProduk(items) {
        listEl.textContent = '';

        if (items.length === 0) {
            statusEl.textContent = 'Produk tidak ditemukan.';
            return;
        }

        statusEl.textContent = items.length + ' produk ditampilkan'
            + (items.length >= 30 ? ' (ketik nama untuk mempersempit)' : '');

        items.forEach(function (p) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'product-card';
            btn.disabled = p.stok <= 0;

            var kelasStok = 'product-stok';
            var teksStok  = 'Stok ' + p.stok;
            if (p.stok <= 0) {
                kelasStok += ' habis';
                teksStok = 'Stok habis';
            } else if (p.stok <= p.stok_minimum) {
                kelasStok += ' menipis';
            }

            btn.appendChild(buatElemen('strong', p.nama_barang));
            btn.appendChild(buatElemen('small', p.kode_barang + ' · ' + p.satuan));
            btn.appendChild(buatElemen('span', rupiah(p.harga_jual), 'product-price'));
            btn.appendChild(buatElemen('span', teksStok, kelasStok));

            btn.addEventListener('click', function () { tambahKeKeranjang(p); });
            listEl.appendChild(btn);
        });
    }

    /* ---------- Keranjang ---------- */

    function tambahKeKeranjang(p) {
        var item = cart.find(function (i) { return i.id === p.id; });

        if (item) {
            item.stok = p.stok;
            if (item.qty + 1 > item.stok) {
                tampilkanPesan('Stok ' + p.nama_barang + ' hanya ' + item.stok + '.');
                return;
            }
            item.qty += 1;
        } else {
            cart.push({
                id:     p.id,
                kode:   p.kode_barang,
                nama:   p.nama_barang,
                satuan: p.satuan,
                harga:  p.harga_jual,
                stok:   p.stok,
                qty:    1
            });
        }
        renderKeranjang();
    }

    function renderKeranjang() {
        cartEl.textContent = '';

        if (cart.length === 0) {
            cartEl.appendChild(buatElemen('div', 'Keranjang masih kosong. Pilih barang di sebelah kiri.', 'empty-state'));
        }

        cart.forEach(function (item, idx) {
            var row = buatElemen('div', null, 'cart-item');

            var info = buatElemen('div', null, 'cart-info');
            info.appendChild(buatElemen('strong', item.nama));
            info.appendChild(buatElemen('small',
                item.kode + ' · ' + rupiah(item.harga) + ' / ' + item.satuan + ' · stok ' + item.stok));

            var sub = buatElemen('div', rupiah(item.qty * item.harga), 'cart-sub');

            var ctrl = buatElemen('div', null, 'cart-ctrl');

            var minus = buatElemen('button', '−', 'btn btn-secondary btn-sm');
            minus.type = 'button';
            minus.dataset.act = 'kurang';
            minus.dataset.idx = String(idx);

            var input = buatElemen('input', null, 'qty-input');
            input.type = 'number';
            input.min = '1';
            input.max = String(item.stok);
            input.value = String(item.qty);
            input.dataset.act = 'set';
            input.dataset.idx = String(idx);
            input.setAttribute('aria-label', 'Jumlah ' + item.nama);

            var plus = buatElemen('button', '+', 'btn btn-secondary btn-sm');
            plus.type = 'button';
            plus.dataset.act = 'tambah';
            plus.dataset.idx = String(idx);

            ctrl.appendChild(minus);
            ctrl.appendChild(input);
            ctrl.appendChild(plus);

            var hapus = buatElemen('button', 'Hapus', 'btn btn-danger btn-sm');
            hapus.type = 'button';
            hapus.dataset.act = 'hapus';
            hapus.dataset.idx = String(idx);

            row.appendChild(info);
            row.appendChild(sub);
            row.appendChild(ctrl);
            row.appendChild(hapus);
            cartEl.appendChild(row);
        });

        perbaruiTotal();
    }

    cartEl.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }

        var idx  = Number(btn.dataset.idx);
        var item = cart[idx];
        if (!item) { return; }

        if (btn.dataset.act === 'hapus') {
            cart.splice(idx, 1);
        } else if (btn.dataset.act === 'kurang') {
            if (item.qty <= 1) {
                cart.splice(idx, 1);
            } else {
                item.qty -= 1;
            }
        } else if (btn.dataset.act === 'tambah') {
            if (item.qty + 1 > item.stok) {
                tampilkanPesan('Stok ' + item.nama + ' hanya ' + item.stok + '.');
                return;
            }
            item.qty += 1;
        }

        renderKeranjang();
    });

    cartEl.addEventListener('change', function (e) {
        var input = e.target;
        if (input.dataset.act !== 'set') { return; }

        var item = cart[Number(input.dataset.idx)];
        if (!item) { return; }

        var n = parseInt(input.value, 10);
        if (isNaN(n) || n < 1) { n = 1; }

        if (n > item.stok) {
            tampilkanPesan('Stok ' + item.nama + ' hanya ' + item.stok + '.');
            n = item.stok;
        }

        item.qty = n;
        renderKeranjang();
    });

    /* ---------- Total, uang bayar, dan kembalian ---------- */

    function hitungTotal() {
        var jumlah = 0;
        var total  = 0;
        cart.forEach(function (i) {
            jumlah += i.qty;
            total  += i.qty * i.harga;
        });
        return { jumlah: jumlah, total: total };
    }

    function perbaruiTotal() {
        var t = hitungTotal();
        totalItemEl.textContent = String(t.jumlah);
        totalEl.textContent = rupiah(t.total);

        var adaBayar = bayarEl.value !== '';
        var bayar    = adaBayar ? Number(bayarEl.value) : 0;
        var kembali  = bayar - t.total;

        if (!adaBayar) {
            kembalianEl.textContent = rupiah(0);
            pesanBayarEl.textContent = 'Masukkan uang yang diterima dari pelanggan.';
            pesanBayarEl.classList.remove('error');
        } else if (kembali < 0) {
            kembalianEl.textContent = rupiah(0);
            pesanBayarEl.textContent = 'Uang kurang ' + rupiah(-kembali) + '.';
            pesanBayarEl.classList.add('error');
        } else {
            kembalianEl.textContent = rupiah(kembali);
            pesanBayarEl.textContent = '';
            pesanBayarEl.classList.remove('error');
        }

        btnSimpan.disabled = !(cart.length > 0 && adaBayar && kembali >= 0);
    }

    bayarEl.addEventListener('input', perbaruiTotal);

    document.querySelectorAll('[data-nominal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var nilai = btn.dataset.nominal;
            bayarEl.value = nilai === 'pas' ? String(hitungTotal().total) : nilai;
            perbaruiTotal();
        });
    });

    btnKosong.addEventListener('click', function () {
        if (cart.length && !window.confirm('Kosongkan semua barang di keranjang?')) { return; }
        cart = [];
        bayarEl.value = '';
        renderKeranjang();
    });

    /* ---------- Pencarian: ketik lalu tunggu sebentar ---------- */

    cariEl.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(muatProduk, 250);
    });

    kategoriEl.addEventListener('change', muatProduk);

    /* ---------- Kirim transaksi ---------- */

    formEl.addEventListener('submit', function (e) {
        if (btnSimpan.disabled || cart.length === 0) {
            e.preventDefault();
            return;
        }

        formEl.querySelectorAll('.item-input').forEach(function (n) { n.remove(); });

        cart.forEach(function (item, i) {
            formEl.appendChild(buatHidden('items[' + i + '][product_id]', item.id, 'item-input'));
            formEl.appendChild(buatHidden('items[' + i + '][qty]', item.qty, 'item-input'));
        });

        btnSimpan.disabled = true;
        btnSimpan.textContent = 'Memproses...';
    });

    // Jika kembali dari halaman lain (tombol Back), tombol simpan dikembalikan normal
    window.addEventListener('pageshow', function () {
        btnSimpan.textContent = 'Selesaikan Transaksi';
        perbaruiTotal();
    });

    /* ---------- Pemulihan keranjang setelah error dari server ---------- */

    function pulihkanKeranjangLama() {
        var node = document.getElementById('old-checkout');
        if (!node) { return; }

        var data;
        try {
            data = JSON.parse(node.textContent);
        } catch (err) {
            return;
        }

        (data.items || []).forEach(function (it) {
            if (it.stok > 0) {
                cart.push({
                    id:     it.id,
                    kode:   it.kode,
                    nama:   it.nama,
                    satuan: it.satuan,
                    harga:  it.harga,
                    stok:   it.stok,
                    qty:    Math.min(it.qty, it.stok)
                });
            }
        });

        if (data.uang_bayar) {
            bayarEl.value = data.uang_bayar;
        }
    }

    /* ---------- Inisialisasi ---------- */

    pulihkanKeranjangLama();
    renderKeranjang();
    muatProduk();
})();