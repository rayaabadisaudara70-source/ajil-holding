<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_login();
$owner = $u['role'] === 'owner';
$pdo = db();

/* ---------------- aksi ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'absen') {
        $tipe = input('tipe') === 'pulang' ? 'pulang' : 'masuk';
        $tanggal = date('Y-m-d');
        $catatan = input('catatan');

        $cek = $pdo->prepare('SELECT id FROM absensi WHERE pengguna_id = ? AND tanggal = ? AND tipe = ?');
        $cek->execute([(int) $u['id'], $tanggal, $tipe]);
        if ($cek->fetchColumn()) {
            flash_set('error', 'Absensi ' . $tipe . ' hari ini sudah tercatat. Tidak bisa dicatat dua kali.');
            redirect('absensi.php');
        }
        if ($tipe === 'pulang') {
            $cekMasuk = $pdo->prepare('SELECT id FROM absensi WHERE pengguna_id = ? AND tanggal = ? AND tipe = ?');
            $cekMasuk->execute([(int) $u['id'], $tanggal, 'masuk']);
            if (!$cekMasuk->fetchColumn()) {
                flash_set('error', 'Belum ada check-in hari ini. Lakukan check-in dulu sebelum check-out.');
                redirect('absensi.php');
            }
        }

        $media = simpan_media('foto', 'gambar');
        if (!$media['ok']) {
            flash_set('error', 'Absensi gagal disimpan: ' . (string) ($media['error'] ?? 'foto wajib diunggah.'));
            redirect('absensi.php');
        }

        $waktu = date('Y-m-d H:i:s');
        $telat = 0;
        if ($tipe === 'masuk') {
            $batas = strtotime($tanggal . ' ' . pengaturan('jam_masuk', '08:00'))
                + ((int) pengaturan('toleransi_menit', '10')) * 60;
            $telat = max(0, (int) floor((time() - $batas) / 60));
        }
        $st = $pdo->prepare('INSERT INTO absensi
            (tanggal, pengguna_id, nama, usaha_nama, tipe, waktu, foto, lat, lng, catatan, telat_menit, dibuat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$tanggal, (int) $u['id'], $u['nama'], (string) $u['usaha_nama'], $tipe, $waktu,
            (string) $media['url'], input('lat'), input('lng'), $catatan, $telat, $waktu]);

        flash_set('sukses', 'Absensi ' . $tipe . ' tercatat pukul ' . date('H:i') . ' WIB'
            . ($telat > 0 ? ' (terlambat ' . $telat . ' menit).' : '.') . ' Jangan lupa kirim foto kegiatan ke Dokumentasi Live.');
        redirect('absensi.php');
    }

    if ($aksi === 'hapus' && $owner) {
        $id = input_int('id');
        $pdo->prepare('DELETE FROM absensi WHERE id = ?')->execute([$id]);
        flash_set('sukses', 'Catatan absensi dihapus.');
        redirect('absensi.php?tanggal=' . h(input('tanggal', date('Y-m-d'))));
    }
}

/* ---------------- ekspor CSV (owner) ---------------- */
if ($owner && input('ekspor') === 'csv') {
    $tanggal = input('tanggal', date('Y-m-d'));
    $st = $pdo->prepare('SELECT * FROM absensi WHERE tanggal = ? ORDER BY waktu');
    $st->execute([$tanggal]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="absensi-' . $tanggal . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tanggal', 'Nama', 'Unit usaha', 'Tipe', 'Jam', 'Terlambat (menit)', 'Latitude', 'Longitude', 'Catatan', 'Foto']);
    foreach ($st->fetchAll() as $r) {
        fputcsv($out, [$r['tanggal'], $r['nama'], $r['usaha_nama'], $r['tipe'], jam($r['waktu']),
            $r['telat_menit'], $r['lat'], $r['lng'], $r['catatan'], $r['foto']]);
    }
    fclose($out);
    exit;
}

/* ---------------- filter ---------------- */
$tanggal = input('tanggal', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}
$filterUsaha = $owner ? input('usaha') : '';

$sql = 'SELECT * FROM absensi WHERE tanggal = ?';
$args = [$tanggal];
if (!$owner) {
    $sql .= ' AND pengguna_id = ?';
    $args[] = (int) $u['id'];
} elseif ($filterUsaha !== '') {
    $sql .= ' AND usaha_nama = ?';
    $args[] = $filterUsaha;
}
$sql .= ' ORDER BY waktu';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$hariIni = date('Y-m-d');
$absenHariIni = [];
foreach ($rows as $r) {
    if ($r['tanggal'] === $hariIni) {
        $absenHariIni[(string) $r['tipe']] = $r;
    }
}
$punyaMasuk = isset($absenHariIni['masuk']);
$punyaPulang = isset($absenHariIni['pulang']);
$bolehAbsenHariIni = $tanggal === $hariIni;

$aksiHeader = $owner
    ? '<a class="btn btn-garis" href="absensi.php?tanggal=' . h($tanggal) . '&ekspor=csv">' . ikon('download') . ' Ekspor CSV</a>'
    : '';

halaman_awal('Absensi Harian', 'absensi.php', $aksiHeader);
?>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head">
      <div><h2>Absensi + Foto Bukti</h2>
        <div class="sub">Foto wajib. Lokasi GPS dicatat bila diizinkan perangkat.</div></div>
      <?= $punyaMasuk ? badge('Sudah check-in ' . jam($absenHariIni['masuk']['waktu']), 'hijau') : badge('Belum check-in', 'kuning') ?>
    </div>

    <?php if (!$bolehAbsenHariIni): ?>
      <div class="alert alert-info">Absensi hanya bisa dicatat untuk hari ini. Pilih tanggal hari ini pada filter riwayat.</div>
    <?php else: ?>
      <div class="grid grid-2">
        <form method="post" enctype="multipart/form-data" data-geo data-wajib-file="Foto bukti wajib dipilih untuk absensi.">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="absen">
          <input type="hidden" name="tipe" value="masuk">
          <input type="hidden" name="lat" value="">
          <input type="hidden" name="lng" value="">
          <div class="field">
            <label>Foto check-in (selfie / lokasi kerja)</label>
            <input class="file-input" type="file" name="foto" accept="image/*" capture="environment" required>
            <span class="hint" data-geo-info>Menunggu izin lokasi…</span>
          </div>
          <button class="btn btn-hijau btn-blok" type="submit" <?= $punyaMasuk ? 'disabled' : '' ?>>
            <?= ikon('check') ?> <?= $punyaMasuk ? 'Check-in sudah tercatat' : 'Check-In Sekarang' ?>
          </button>
        </form>

        <form method="post" enctype="multipart/form-data" data-geo data-wajib-file="Foto bukti wajib dipilih untuk absensi.">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="absen">
          <input type="hidden" name="tipe" value="pulang">
          <input type="hidden" name="lat" value="">
          <input type="hidden" name="lng" value="">
          <div class="field">
            <label>Foto check-out (hasil kerja)</label>
            <input class="file-input" type="file" name="foto" accept="image/*" capture="environment" required>
            <span class="hint">Lokasi tercatat otomatis bila GPS aktif.</span>
          </div>
          <button class="btn btn-navy btn-blok" type="submit" <?= (!$punyaMasuk || $punyaPulang) ? 'disabled' : '' ?>>
            <?= ikon('logout') ?> <?= $punyaPulang ? 'Check-out sudah tercatat' : 'Check-Out Sekarang' ?>
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Kebijakan Absensi</h2><div class="sub">Berlaku seluruh unit</div></div></div>
    <ul class="sop-list">
      <li><span class="sop-no">1</span><span>Jam masuk <b><?= h(pengaturan('jam_masuk', '08:00')) ?></b> WIB, toleransi <b><?= h(pengaturan('toleransi_menit', '10')) ?> menit</b>.</span></li>
      <li><span class="sop-no">2</span><span>Check-in dan check-out <b>wajib foto</b>; foto tersimpan sebagai bukti.</span></li>
      <li><span class="sop-no">3</span><span>Satu check-in dan satu check-out per hari — tidak bisa dobel.</span></li>
      <li><span class="sop-no">4</span><span>Check-out hanya bisa setelah check-in hari itu tercatat.</span></li>
      <li><span class="sop-no">5</span><span>Tidak hadir tanpa kabar dihitung alpa: potongan <b><?= h(rupiah((int) pengaturan('potongan_per_hari', '50000'))) ?>/hari</b>.</span></li>
      <li><span class="sop-no">6</span><span>Hadir penuh sesuai hari kerja mendapat bonus <b><?= h(rupiah((int) pengaturan('bonus_penuh', '200000'))) ?></b>.</span></li>
    </ul>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Riwayat Absensi</h2><div class="sub"><?= h(tanggal_id($tanggal, true)) ?> &middot; <?= h((string) count($rows)) ?> catatan</div></div>
    <form method="get" class="saring tanpa-cetak">
      <div class="field"><label>Tanggal</label>
        <input class="input" type="date" name="tanggal" value="<?= h($tanggal) ?>" data-auto-submit></div>
      <?php if ($owner): ?>
        <div class="field"><label>Unit usaha</label>
          <select class="select" name="usaha" data-auto-submit>
            <option value="">Semua unit</option>
            <?php foreach (usaha_nama_semua() as $un): ?>
              <option value="<?= h($un) ?>" <?= $filterUsaha === $un ? 'selected' : '' ?>><?= h($un) ?></option>
            <?php endforeach; ?>
          </select></div>
      <?php endif; ?>
    </form>
  </div>
  <div class="table-wrap">
    <table class="tabel">
      <thead><tr><th>Jam</th><th>Nama</th><th>Unit usaha</th><th>Tipe</th><th>Terlambat</th><th>Lokasi</th><th>Foto</th><th>Catatan</th><?= $owner ? '<th></th>' : '' ?></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $owner ? 9 : 8 ?>" class="kosong">Belum ada catatan absensi pada tanggal ini.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><b><?= h(jam($r['waktu'])) ?></b></td>
          <td><?= h($r['nama']) ?><?= (int) $r['contoh'] === 1 ? ' ' . badge('contoh', 'abu') : '' ?></td>
          <td><?= h($r['usaha_nama']) ?></td>
          <td><?= $r['tipe'] === 'masuk' ? badge('Masuk', 'hijau') : badge('Pulang', 'abu') ?></td>
          <td><?= (int) $r['telat_menit'] > 0 ? badge($r['telat_menit'] . ' menit', 'merah') : '-' ?></td>
          <td>
            <?php if ($r['lat'] !== '' && $r['lng'] !== ''): ?>
              <a class="badge badge-emas" target="_blank" rel="noopener"
                 href="https://www.google.com/maps?q=<?= h($r['lat']) ?>,<?= h($r['lng']) ?>"><?= ikon('pin') ?> Peta</a>
            <?php else: ?><span class="stat-sub">-</span><?php endif; ?>
          </td>
          <td>
            <?php if (aman_url((string) $r['foto']) !== ''): ?>
              <a href="<?= h(aman_url((string) $r['foto'])) ?>" target="_blank" rel="noopener">Lihat foto</a>
            <?php else: ?><span class="stat-sub">-</span><?php endif; ?>
          </td>
          <td><?= h($r['catatan']) !== '' ? h($r['catatan']) : '<span class="stat-sub">-</span>' ?></td>
          <?php if ($owner): ?>
            <td>
              <form method="post" data-konfirmasi="Hapus catatan absensi ini?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
                <button class="btn btn-kecil btn-merah" type="submit"><?= ikon('trash') ?></button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php halaman_akhir(); ?>
