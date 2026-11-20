/* AJIL.JR MINI HOLDING — interaksi UI (tanpa dependency) */
(function () {
  'use strict';

  function siap(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  siap(function () {
    /* ---------- menu samping (mobile) ---------- */
    var tombol = document.getElementById('menuToggle');
    var tirai = document.getElementById('tirai');
    if (tombol) {
      tombol.addEventListener('click', function (e) {
        e.stopPropagation();
        document.body.classList.toggle('menu-buka');
      });
    }
    if (tirai) {
      tirai.addEventListener('click', function () {
        document.body.classList.remove('menu-buka');
      });
    }
    document.addEventListener('click', function (e) {
      if (!document.body.classList.contains('menu-buka')) return;
      var sisi = document.getElementById('sisi');
      if (sisi && sisi.contains(e.target)) return;
      if (tombol && tombol.contains(e.target)) return;
      document.body.classList.remove('menu-buka');
    });

    /* ---------- konfirmasi aksi ---------- */
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      var pesan = form.getAttribute('data-konfirmasi');
      if (pesan && !window.confirm(pesan)) {
        e.preventDefault();
        return;
      }
      var ketik = form.getAttribute('data-konfirmasi-ketik');
      if (ketik) {
        var jawab = window.prompt(pesan || 'Ketik "' + ketik + '" untuk melanjutkan.');
        if ((jawab || '').trim().toUpperCase() !== ketik.toUpperCase()) {
          e.preventDefault();
          window.alert('Dibatalkan — ketik ' + ketik + ' dengan benar.');
          return;
        }
      }
      var wajibFile = form.getAttribute('data-wajib-file');
      if (wajibFile) {
        var inputFile = form.querySelector('input[type=file]');
        if (inputFile && (!inputFile.files || inputFile.files.length === 0)) {
          e.preventDefault();
          window.alert(wajibFile);
          return;
        }
      }
      var t = form.querySelector('button[type=submit], .btn-emas, .btn-navy, .btn-hijau');
      if (t) {
        window.setTimeout(function () {
          t.setAttribute('disabled', 'disabled');
        }, 10);
      }
    });

    /* ---------- isi otomatis harga barang ---------- */
    var daftarBarang = document.querySelector('[data-barang-json]');
    if (daftarBarang) {
      var data = [];
      try {
        data = JSON.parse(daftarBarang.getAttribute('data-barang-json')) || [];
      } catch (err) {
        data = [];
      }
      var inputNama = document.getElementById('barang_nama');
      var inputHarga = document.getElementById('barang_harga');
      var labelSatuan = document.getElementById('barang_satuan');
      function cari(nama) {
        nama = (nama || '').trim().toLowerCase();
        for (var i = 0; i < data.length; i++) {
          if (String(data[i].nama).toLowerCase() === nama) return data[i];
        }
        return null;
      }
      if (inputNama) {
        inputNama.addEventListener('change', function () {
          var b = cari(inputNama.value);
          if (b) {
            if (inputHarga && (!inputHarga.value || inputHarga.value === '0')) inputHarga.value = b.harga;
            if (labelSatuan) labelSatuan.textContent = b.satuan || 'pcs';
          } else if (labelSatuan) {
            labelSatuan.textContent = 'pcs';
          }
        });
      }
    }

    /* ---------- pratinjau nama file ---------- */
    Array.prototype.forEach.call(document.querySelectorAll('input[type=file]'), function (inp) {
      inp.addEventListener('change', function () {
        var nama = inp.getAttribute('data-label');
        if (!nama) return;
        var el = document.getElementById(nama);
        if (!el) return;
        if (inp.files && inp.files[0]) {
          var f = inp.files[0];
          el.textContent = f.name + ' (' + (f.size / 1024 / 1024).toFixed(2) + ' MB)';
        } else {
          el.textContent = '';
        }
      });
    });

    /* ---------- lokasi GPS (opsional) ---------- */
    Array.prototype.forEach.call(document.querySelectorAll('form[data-geo]'), function (form) {
      var lat = form.querySelector('input[name=lat]');
      var lng = form.querySelector('input[name=lng]');
      var ket = form.querySelector('[data-geo-info]');
      if (!lat || !lng || !navigator.geolocation) {
        if (ket) ket.textContent = 'Lokasi GPS tidak tersedia di perangkat ini.';
        return;
      }
      navigator.geolocation.getCurrentPosition(function (pos) {
        lat.value = pos.coords.latitude.toFixed(6);
        lng.value = pos.coords.longitude.toFixed(6);
        if (ket) ket.textContent = 'Lokasi terdeteksi: ' + lat.value + ', ' + lng.value;
      }, function () {
        if (ket) ket.textContent = 'Lokasi GPS tidak diizinkan — absensi tetap bisa disimpan tanpa koordinat.';
      }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
    });

    /* ---------- pencarian cepat dalam tabel ---------- */
    Array.prototype.forEach.call(document.querySelectorAll('[data-cari]'), function (inp) {
      inp.addEventListener('input', function () {
        var target = document.querySelector(inp.getAttribute('data-cari'));
        if (!target) return;
        var q = inp.value.trim().toLowerCase();
        Array.prototype.forEach.call(target.querySelectorAll('tbody tr'), function (tr) {
          tr.style.display = (!q || tr.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
        });
      });
    });

    /* ---------- filter otomatis ---------- */
    Array.prototype.forEach.call(document.querySelectorAll('[data-auto-submit]'), function (el) {
      el.addEventListener('change', function () {
        if (el.form) el.form.submit();
      });
    });
  });
})();
