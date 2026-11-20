<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_owner();
$pdo = db();

$tab = input('tab', 'gaji') === 'minggu' ? 'minggu' : 'gaji';
$periode = input('periode', date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
    $periode = date('Y-m');
}
$minggu = input('minggu', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $minggu)) {
    $minggu = date('Y-m-d');
}
[$mulai, $selesai] = rentang_minggu($minggu);

$gaji = gaji_hitung($periode);
$rekap = rekap_aktivitas($mulai, $selesai);

/* ---------- ekspor CSV ---------- */
if (input('ekspor') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    if ($tab === 'gaji') {
        header('Content-Disposition: attachment; filename="gaji-' . $periode . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Periode', 'Nama', 'Unit usaha', 'Jabatan', 'Pokok', 'Tunjangan', 'Hari kerja',
            'Hadir', 'Tanpa kabar', 'Potongan', 'Bonus', 'Total diterima']);
        foreach ($gaji['rows'] as $r) {
            fputcsv($out, [$periode, $r['nama'], $r['usaha_nama'], $r['jabatan'], $r['pokok'], $r['tunjangan'],
                $r['hari_kerja'], $r['hadir'], $r['hari_alpa'], $r['potongan'], $r['bonus'], $r['total']]);
        }
        fputcsv($out, ['', 'TOTAL', '', '', '', '', '', '', '', '', '', $gaji['total']]);
    } else {
        header('Content-Disposition: attachment; filename="rekap-' . $mulai . '_' . $selesai . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Nama', 'Unit usaha', 'Jabatan', 'Hadir (hari)', 'Dokumentasi', 'Transaksi penjualan', 'Nilai penjualan', 'Pekerjaan jasa']);
        foreach ($rekap as $r) {
            fputcsv($out, [$r['nama'], $r['usaha_nama'], $r['jabatan'], $r['hadir'], $r['dokumentasi'],
                $r['jual_n'], $r['jual_rp'], $r['jasa_n']]);
        }
    }
    fclose($out);
    exit;
}

$tabs = '<div class="tabs">'
    . '<a class="tab ' . ($tab === 'gaji' ? 'aktif' : '') . '" href="gaji.php?tab=gaji&periode=' . h($periode) . '">' . ikon('wallet') . ' Gaji Bulanan</a>'
    . '<a class="tab ' . ($tab === 'minggu' ? 'aktif' : '') . '" href="gaji.php?tab=minggu&minggu=' . h($minggu) . '">' . ikon('chart') . ' Rekap Mingguan</a>'
    . '</div>';

$aksiHeader = '<a class="btn btn-garis" target="_blank" href="gaji.php?tab=' . h($tab) . '&periode=' . h($periode)
    . '&minggu=' . h($minggu) . '&ekspor=csv">' . ikon('download') . ' Ekspor CSV</a>'
    . '<button class="btn btn-navy" type="button" onclick="window.print()">' . ikon('print') . ' Cetak</button>';

halaman_awal('Gaji & Rekap', 'gaji.php', $aksiHeader);
echo $tabs;

if ($tab === 'gaji'):
    ?>
    <div class="card">
      <div class="card-head">
        <div><h2>Kalkulasi Gaji — <?= h(bulan_label($periode)) ?></h2>
          <div class="sub">Hari kerja bulan ini: <?= h((string) $gaji['hari_kerja']) ?> hari
            (Senin–<?= h($GLOBALS['__hari'][['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][max(hari_kerja_list())] ?? 'Sabtu']) ?> sesuai pengaturan)</div></div>
        <form method="get" class="saring tanpa-cetak">
          <input type="hidden" name="tab" value="gaji">
          <div class="field"><label>Periode</label>
            <select class="select" name="periode" data-auto-submit>
              <?php foreach (periode_pilihan() as $p): ?>
                <option value="<?= h($p) ?>" <?= $p === $periode ? 'selected' : '' ?>><?= h(bulan_label($p)) ?></option>
              <?php endforeach; ?>
            </select></div>
        </form>
      </div>
      <div class="alert alert-info" style="margin-bottom:16px">
        Rumus: <b>pokok + tunjangan makan + tunjangan transport − potongan tidak hadir + bonus hadir penuh</b>.
        Potongan <?= h(rupiah((int) pengaturan('potongan_per_hari', '50000'))) ?> per hari tidak hadir,
        bonus <?= h(rupiah((int) pengaturan('bonus_penuh', '200000'))) ?> bila hadir penuh.
        Angka bisa diubah di menu <b>Pengaturan</b>.
      </div>
      <div class="table-wrap">
        <table class="tabel">
          <thead><tr><th>Nama</th><th>Unit usaha</th><th>Jabatan</th><th class="angka">Pokok</th><th class="angka">Tunjangan</th>
            <th class="angka">Hadir</th><th class="angka">Tanpa kabar</th><th class="angka">Potongan</th><th class="angka">Bonus</th><th class="angka">Diterima</th></tr></thead>
          <tbody>
          <?php if (!$gaji['rows']): ?>
            <tr><td colspan="10" class="kosong">Belum ada karyawan aktif.</td></tr>
          <?php else: foreach ($gaji['rows'] as $r): ?>
            <tr>
              <td><b><?= h($r['nama']) ?></b></td>
              <td><?= h($r['usaha_nama']) ?></td>
              <td><?= h($r['jabatan']) ?></td>
              <td class="angka"><?= h(rupiah($r['pokok'])) ?></td>
              <td class="angka"><?= h(rupiah($r['tunjangan'])) ?></td>
              <td class="angka"><?= h((string) $r['hadir']) ?> / <?= h((string) $r['hari_kerja']) ?></td>
              <td class="angka"><?= $r['hari_alpa'] > 0 ? badge((string) $r['hari_alpa'] . ' hari', 'merah') : '-' ?></td>
              <td class="angka"><?= $r['potongan'] > 0 ? h(rupiah($r['potongan'])) : '-' ?></td>
              <td class="angka"><?= $r['bonus'] > 0 ? h(rupiah($r['bonus'])) : '-' ?></td>
              <td class="angka"><b><?= h(rupiah($r['total'])) ?></b></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr><th colspan="9" style="text-align:right">Total gaji <?= h(bulan_label($periode)) ?></th>
              <th class="angka" style="text-align:right"><?= h(rupiah($gaji['total'])) ?></th></tr>
          </tfoot>
        </table>
      </div>
    </div>
    <?php
else:
    ?>
    <div class="card">
      <div class="card-head">
        <div><h2>Rekap Mingguan</h2>
          <div class="sub"><?= h(tanggal_pendek($mulai)) ?> – <?= h(tanggal_pendek($selesai)) ?> (Senin–Minggu)</div></div>
        <form method="get" class="saring tanpa-cetak">
          <input type="hidden" name="tab" value="minggu">
          <div class="field"><label>Ambil minggu dari tanggal</label>
            <input class="input" type="date" name="minggu" value="<?= h($minggu) ?>" data-auto-submit></div>
        </form>
      </div>
      <div class="table-wrap">
        <table class="tabel">
          <thead><tr><th>Nama</th><th>Unit usaha</th><th class="angka">Hadir</th><th class="angka">Dokumentasi</th>
            <th class="angka">Transaksi jual</th><th class="angka">Nilai penjualan</th><th class="angka">Pekerjaan jasa</th></tr></thead>
          <tbody>
          <?php if (!$rekap): ?>
            <tr><td colspan="7" class="kosong">Belum ada karyawan aktif.</td></tr>
          <?php else: foreach ($rekap as $r): ?>
            <tr>
              <td><b><?= h($r['nama']) ?></b><br><span class="stat-sub"><?= h($r['jabatan']) ?></span></td>
              <td><?= h($r['usaha_nama']) ?></td>
              <td class="angka"><?= h((string) $r['hadir']) ?></td>
              <td class="angka"><?= h((string) $r['dokumentasi']) ?></td>
              <td class="angka"><?= h((string) $r['jual_n']) ?></td>
              <td class="angka"><?= h(rupiah($r['jual_rp'])) ?></td>
              <td class="angka"><?= h((string) $r['jasa_n']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php
    $st = $pdo->prepare('SELECT * FROM absensi WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC, waktu DESC LIMIT 40');
    $st->execute([$mulai, $selesai]);
    $absenMinggu = $st->fetchAll();
    ?>
    <div class="card">
      <div class="card-head"><div><h2>Absensi Minggu Ini</h2><div class="sub"><?= h((string) count($absenMinggu)) ?> catatan terakhir</div></div></div>
      <div class="table-wrap">
        <table class="tabel">
          <thead><tr><th>Tanggal</th><th>Nama</th><th>Unit usaha</th><th>Tipe</th><th>Jam</th><th>Telat</th></tr></thead>
          <tbody>
          <?php if (!$absenMinggu): ?>
            <tr><td colspan="6" class="kosong">Belum ada absensi pada minggu ini.</td></tr>
          <?php else: foreach ($absenMinggu as $r): ?>
            <tr>
              <td><?= h(tanggal_pendek($r['tanggal'])) ?></td>
              <td><?= h($r['nama']) ?></td>
              <td><?= h($r['usaha_nama']) ?></td>
              <td><?= $r['tipe'] === 'masuk' ? badge('Masuk', 'hijau') : badge('Pulang', 'abu') ?></td>
              <td><?= h(jam($r['waktu'])) ?></td>
              <td><?= (int) $r['telat_menit'] > 0 ? badge($r['telat_menit'] . ' mnt', 'merah') : '-' ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
endif;

halaman_akhir();
