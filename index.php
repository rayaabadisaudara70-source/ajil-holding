<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_login();
$owner = $u['role'] === 'owner';
$pdo = db();
$hariIni = date('Y-m-d');
$bulanIni = date('Y-m');

$aksiHeader = '<a class="btn btn-emas" href="absensi.php">' . ikon('camera') . ' Absen Sekarang</a>'
    . '<a class="btn btn-garis" href="dokumentasi.php">' . ikon('image') . ' Dokumentasi Live</a>';

halaman_awal('Dashboard', 'index.php', $aksiHeader);

/* ---------- peringatan password bawaan ---------- */
if (password_bawaan($u)) {
    echo '<div class="alert alert-info">Akun <b>' . h($u['nama']) . '</b> masih memakai password bawaan. ';
    echo 'Segera ganti lewat menu <a href="akun.php"><b>Akun Saya</b></a> agar tidak dipakai orang lain.</div>';
}

if ($owner):
    $ringkas = ringkasan_hari($hariIni);
    $jmlKaryawan = (int) $pdo->query("SELECT COUNT(*) FROM pengguna WHERE role = 'karyawan' AND aktif = 1")->fetchColumn();
    $jmlUsaha = count(usaha_semua());
    $hadirHariIni = $ringkas['masuk'];
    $belumAbsen = max(0, $jmlKaryawan - $hadirHariIni);
    $totalJualBulan = (int) $pdo->query('SELECT COALESCE(SUM(total),0) FROM penjualan WHERE substr(tanggal,1,7) = '
        . $pdo->quote($bulanIni))->fetchColumn();
    $totalJasaBulan = (int) $pdo->query('SELECT COALESCE(SUM(biaya),0) FROM jasa WHERE substr(tanggal,1,7) = '
        . $pdo->quote($bulanIni))->fetchColumn();
    $proyekAktif = (int) $pdo->query('SELECT COUNT(DISTINCT nama) FROM proyek WHERE progres < 100')->fetchColumn();
    ?>
    <div class="card">
      <div class="card-head">
        <div>
          <h2>Halo, <?= h($u['nama']) ?> 👋</h2>
          <div class="sub"><?= h(tanggal_id($hariIni, true)) ?> &middot; ringkasan seluruh unit usaha</div>
        </div>
        <div class="aksi-baris">
          <span class="chip"><?= h((string) $jmlUsaha) ?> unit usaha</span>
          <span class="chip"><?= h((string) $jmlKaryawan) ?> karyawan aktif</span>
        </div>
      </div>
      <div class="grid grid-4">
        <div class="stat"><div class="stat-ikon emas"><?= ikon('camera') ?></div>
          <div class="stat-label">Absensi masuk hari ini</div>
          <div class="stat-nilai"><?= h((string) $hadirHariIni) ?> / <?= h((string) $jmlKaryawan) ?></div>
          <div class="stat-sub"><?= $belumAbsen > 0 ? h((string) $belumAbsen) . ' karyawan belum absen' : 'Semua sudah absen' ?></div>
        </div>
        <div class="stat"><div class="stat-ikon hijau"><?= ikon('cart') ?></div>
          <div class="stat-label">Penjualan hari ini</div>
          <div class="stat-nilai"><?= h(rupiah($ringkas['jual_rp'])) ?></div>
          <div class="stat-sub"><?= h((string) $ringkas['jual_n']) ?> transaksi</div>
        </div>
        <div class="stat"><div class="stat-ikon biru"><?= ikon('wrench') ?></div>
          <div class="stat-label">Jasa hari ini</div>
          <div class="stat-nilai"><?= h(rupiah($ringkas['jasa_rp'])) ?></div>
          <div class="stat-sub"><?= h((string) $ringkas['jasa_n']) ?> pekerjaan</div>
        </div>
        <div class="stat"><div class="stat-ikon"><?= ikon('image') ?></div>
          <div class="stat-label">Dokumentasi hari ini</div>
          <div class="stat-nilai"><?= h((string) $ringkas['dokumentasi']) ?></div>
          <div class="stat-sub"><?= h((string) $ringkas['stok_kritis']) ?> barang stok kritis</div>
        </div>
      </div>
    </div>

    <div class="grid grid-2-1">
      <div class="card">
        <div class="card-head">
          <div><h2>Absensi Hari Ini</h2><div class="sub">Check-in &amp; check-out seluruh unit</div></div>
          <a class="btn btn-kecil btn-garis" href="absensi.php">Lihat semua</a>
        </div>
        <div class="table-wrap">
          <table class="tabel">
            <thead><tr><th>Nama</th><th>Unit usaha</th><th>Tipe</th><th>Jam</th><th>Lokasi</th></tr></thead>
            <tbody>
            <?php
            $rows = $pdo->query('SELECT * FROM absensi WHERE tanggal = ' . $pdo->quote($hariIni)
                . ' ORDER BY waktu DESC LIMIT 8')->fetchAll();
            if (!$rows): ?>
              <tr><td colspan="5" class="kosong">Belum ada absensi hari ini.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><b><?= h($r['nama']) ?></b></td>
                <td><?= h($r['usaha_nama']) ?></td>
                <td><?= $r['tipe'] === 'masuk' ? badge('Masuk', 'hijau') : badge('Pulang', 'abu') ?></td>
                <td><?= h(jam($r['waktu'])) ?></td>
                <td><?= $r['lat'] !== '' ? badge('GPS', 'emas') : '<span class="stat-sub">-</span>' ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><div><h2>Angka Bulan Ini</h2><div class="sub"><?= h(bulan_label($bulanIni)) ?></div></div></div>
        <div class="grid" style="gap:14px">
          <div><div class="stat-label">Penjualan (Plastik &amp; Sembako)</div><div class="stat-nilai"><?= h(rupiah($totalJualBulan)) ?></div></div>
          <div><div class="stat-label">Pendapatan jasa (Motor, Carwash, Laundry)</div><div class="stat-nilai"><?= h(rupiah($totalJasaBulan)) ?></div></div>
          <div><div class="stat-label">Proyek property berjalan</div><div class="stat-nilai"><?= h((string) $proyekAktif) ?></div></div>
          <div class="aksi-baris">
            <a class="btn btn-kecil btn-garis" href="gaji.php"><?= ikon('wallet') ?> Gaji &amp; Rekap</a>
            <a class="btn btn-kecil btn-garis" href="pengguna.php"><?= ikon('users') ?> Kelola Akun</a>
          </div>
        </div>
      </div>
    </div>

    <?php
    $dok = $pdo->query('SELECT * FROM dokumentasi ORDER BY id DESC LIMIT 3')->fetchAll();
    if ($dok): ?>
      <div class="card">
        <div class="card-head">
          <div><h2>Dokumentasi Live Terbaru</h2><div class="sub">Foto &amp; video dari lapangan</div></div>
          <a class="btn btn-kecil btn-garis" href="dokumentasi.php">Buka galeri</a>
        </div>
        <div class="feed">
          <?php foreach ($dok as $d): ?>
            <div class="dok">
              <div class="dok-media">
                <span class="dok-pita">DOKUMENTASI AJIL.JR</span>
                <span class="dok-jenis"><?= h($d['jenis']) ?></span>
                <?php if (aman_url((string) $d['url']) !== ''): ?>
                  <?php if ($d['media'] === 'video'): ?>
                    <video controls preload="metadata" src="<?= h(aman_url((string) $d['url'])) ?>"></video>
                  <?php else: ?>
                    <img src="<?= h(aman_url((string) $d['url'])) ?>" alt="<?= h($d['judul']) ?>" loading="lazy">
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <div class="dok-cap">
                <div class="dok-judul"><?= h($d['judul']) ?></div>
                <div class="dok-meta"><?= h($d['nama']) ?> &middot; <?= h($d['usaha_nama']) ?><br><?= h(waktu_lengkap($d['waktu'])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

<?php else:
    /* ---------- Dashboard karyawan ---------- */
    $usaha = (string) $u['usaha_nama'];
    $masukHariIni = $pdo->prepare('SELECT * FROM absensi WHERE pengguna_id = ? AND tanggal = ? AND tipe = ?');
    $masukHariIni->execute([(int) $u['id'], $hariIni, 'masuk']);
    $absenMasuk = $masukHariIni->fetch() ?: null;
    $pulangHariIni = $pdo->prepare('SELECT * FROM absensi WHERE pengguna_id = ? AND tanggal = ? AND tipe = ?');
    $pulangHariIni->execute([(int) $u['id'], $hariIni, 'pulang']);
    $absenPulang = $pulangHariIni->fetch() ?: null;

    $hadirBulan = (int) $pdo->query('SELECT COUNT(DISTINCT tanggal) FROM absensi WHERE pengguna_id = '
        . (int) $u['id'] . " AND tipe = 'masuk' AND substr(tanggal,1,7) = " . $pdo->quote($bulanIni)
        . ' AND contoh = 0')->fetchColumn();
    $dokBulan = (int) $pdo->query('SELECT COUNT(*) FROM dokumentasi WHERE pengguna_id = ' . (int) $u['id']
        . ' AND substr(tanggal,1,7) = ' . $pdo->quote($bulanIni) . ' AND contoh = 0')->fetchColumn();
    $jualBulan = (int) $pdo->query('SELECT COALESCE(SUM(total),0) FROM penjualan WHERE kasir_id = ' . (int) $u['id']
        . ' AND substr(tanggal,1,7) = ' . $pdo->quote($bulanIni) . ' AND contoh = 0')->fetchColumn();
    $jasaBulan = (int) $pdo->query('SELECT COALESCE(SUM(biaya),0) FROM jasa WHERE petugas_id = ' . (int) $u['id']
        . ' AND substr(tanggal,1,7) = ' . $pdo->quote($bulanIni) . ' AND contoh = 0')->fetchColumn();
    ?>
    <div class="absen-hero">
      <div class="label">Kartu Absensi Hari Ini</div>
      <div class="nama"><?= h($u['nama']) ?></div>
      <div class="meta"><?= h($usaha !== '' ? $usaha : 'Semua unit') ?> &middot; <?= h((string) $u['jabatan']) ?> &middot; <?= h(tanggal_id($hariIni, true)) ?></div>
      <div class="absen-status">
        <div class="absen-kotak">
          <div class="t">Check-in</div>
          <div class="v"><?= $absenMasuk ? h(jam($absenMasuk['waktu'])) : 'Belum' ?></div>
        </div>
        <div class="absen-kotak">
          <div class="t">Check-out</div>
          <div class="v"><?= $absenPulang ? h(jam($absenPulang['waktu'])) : 'Belum' ?></div>
        </div>
        <div class="absen-kotak">
          <div class="t">Hadir bulan ini</div>
          <div class="v"><?= h((string) $hadirBulan) ?> hari</div>
        </div>
        <div class="absen-kotak">
          <div class="t">Jam masuk kantor</div>
          <div class="v"><?= h(pengaturan('jam_masuk', '08:00')) ?></div>
        </div>
      </div>
    </div>

    <div class="grid grid-4">
      <div class="stat"><div class="stat-ikon emas"><?= ikon('image') ?></div>
        <div class="stat-label">Dokumentasi bulan ini</div>
        <div class="stat-nilai"><?= h((string) $dokBulan) ?></div>
        <div class="stat-sub">foto &amp; video terkirim</div>
      </div>
      <?php if (modul_boleh($u, 'jual')): ?>
        <div class="stat"><div class="stat-ikon hijau"><?= ikon('cart') ?></div>
          <div class="stat-label">Penjualan saya</div>
          <div class="stat-nilai"><?= h(rupiah($jualBulan)) ?></div>
          <div class="stat-sub">bulan ini</div>
        </div>
      <?php endif; ?>
      <?php if (modul_boleh($u, 'jasa')): ?>
        <div class="stat"><div class="stat-ikon biru"><?= ikon('wrench') ?></div>
          <div class="stat-label">Jasa saya</div>
          <div class="stat-nilai"><?= h(rupiah($jasaBulan)) ?></div>
          <div class="stat-sub">bulan ini</div>
        </div>
      <?php endif; ?>
      <?php if (modul_boleh($u, 'proyek')): ?>
        <?php
        $pr = $pdo->prepare('SELECT * FROM proyek WHERE mandor_id = ? ORDER BY id DESC LIMIT 1');
        $pr->execute([(int) $u['id']]);
        $proyek = $pr->fetch() ?: null;
        ?>
        <div class="stat"><div class="stat-ikon"><?= ikon('hammer') ?></div>
          <div class="stat-label">Progres proyek terakhir</div>
          <div class="stat-nilai"><?= $proyek ? h((string) $proyek['progres']) . '%' : '-' ?></div>
          <div class="stat-sub"><?= $proyek ? h((string) $proyek['nama']) : 'Belum ada laporan' ?></div>
        </div>
      <?php endif; ?>
      <div class="stat"><div class="stat-ikon"><?= ikon('clock') ?></div>
        <div class="stat-label">Tanggal hari ini</div>
        <div class="stat-nilai" style="font-size:18px"><?= h(date('d/m/Y')) ?></div>
        <div class="stat-sub"><?= h($GLOBALS['__hari'][date('l')]) ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Aksi Cepat</h2><div class="sub">Semua yang biasa dipakai harian</div></div></div>
      <div class="aksi-baris">
        <a class="btn btn-emas" href="absensi.php"><?= ikon('camera') ?> Absensi &amp; Foto</a>
        <a class="btn btn-navy" href="dokumentasi.php"><?= ikon('image') ?> Dokumentasi Live</a>
        <?php if (modul_boleh($u, 'jual')): ?>
          <a class="btn btn-garis" href="penjualan.php"><?= ikon('cart') ?> Update Penjualan</a>
          <a class="btn btn-garis" href="barang.php"><?= ikon('box') ?> BIG DATA Barang</a>
        <?php endif; ?>
        <?php if (modul_boleh($u, 'jasa')): ?>
          <a class="btn btn-garis" href="jasa.php"><?= ikon('wrench') ?> Input Pekerjaan</a>
        <?php endif; ?>
        <?php if (modul_boleh($u, 'proyek')): ?>
          <a class="btn btn-garis" href="property.php"><?= ikon('hammer') ?> Laporan Proyek</a>
        <?php endif; ?>
        <a class="btn btn-garis" href="sop.php"><?= ikon('book') ?> SOP Unit Saya</a>
      </div>
    </div>

    <?php
    $sopRow = null;
    $row = usaha_row($usaha);
    if ($row) {
        $st = $pdo->prepare('SELECT * FROM sop WHERE usaha_nama = ? AND aktif = 1 ORDER BY urutan LIMIT 1');
        $st->execute([$usaha]);
        $sopRow = $st->fetch() ?: null;
    }
    if ($sopRow):
        $langkah = array_values(array_filter(array_map('trim', explode("\n", (string) $sopRow['langkah']))));
        ?>
      <div class="card">
        <div class="card-head">
          <div><h2>SOP Singkat — <?= h($sopRow['judul']) ?></h2><div class="sub">Wajib diikuti setiap hari kerja</div></div>
          <a class="btn btn-kecil btn-garis" href="sop.php">SOP lengkap</a>
        </div>
        <ul class="sop-list">
          <?php foreach ($langkah as $i => $l): ?>
            <li><span class="sop-no"><?= h((string) ($i + 1)) ?></span><span><?= h($l) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
<?php endif;

halaman_akhir();
