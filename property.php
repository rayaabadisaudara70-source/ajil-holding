<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_modul('proyek');
$owner = $u['role'] === 'owner';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'simpan') {
        $nama = input('nama');
        $progres = input_int('progres');
        $biaya = input_int('biaya');
        if ($nama === '') {
            flash_set('error', 'Nama proyek wajib diisi.');
            redirect('property.php');
        }
        if ($progres < 0 || $progres > 100) {
            flash_set('error', 'Progres harus antara 0 dan 100 persen.');
            redirect('property.php');
        }
        $waktu = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO proyek (tanggal, waktu, nama, lokasi, mandor_id, mandor_nama, progres, biaya, keterangan, dibuat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([date('Y-m-d'), $waktu, $nama, input('lokasi'), (int) $u['id'], (string) $u['nama'],
                $progres, $biaya, input('keterangan'), $waktu]);
        flash_set('sukses', 'Laporan progres proyek tersimpan (' . $progres . '%).');
        redirect('property.php');
    }

    if ($aksi === 'hapus' && $owner) {
        $pdo->prepare('DELETE FROM proyek WHERE id = ?')->execute([input_int('id')]);
        flash_set('sukses', 'Laporan proyek dihapus.');
        redirect('property.php');
    }
}

$sql = 'SELECT * FROM proyek';
$args = [];
if (!$owner) {
    $sql .= ' WHERE mandor_id = ?';
    $args[] = (int) $u['id'];
}
$sql .= ' ORDER BY id DESC LIMIT 120';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

// ringkasan per proyek (laporan terbaru tiap nama)
$ringkas = [];
foreach ($rows as $r) {
    if (!isset($ringkas[$r['nama']])) {
        $ringkas[$r['nama']] = $r;
    }
}
$totalBiaya = 0;
foreach ($rows as $r) {
    $totalBiaya += (int) $r['biaya'];
}

halaman_awal('Proyek Property', 'property.php', '<span class="chip">' . h((string) count($ringkas)) . ' proyek</span>');
?>

<div class="grid grid-3">
  <div class="stat"><div class="stat-ikon emas"><?= ikon('hammer') ?></div>
    <div class="stat-label">Proyek terdata</div><div class="stat-nilai"><?= h((string) count($ringkas)) ?></div>
    <div class="stat-sub"><?= $owner ? 'seluruh mandor' : 'laporan Anda' ?></div></div>
  <div class="stat"><div class="stat-ikon hijau"><?= ikon('rupiah') ?></div>
    <div class="stat-label">Total biaya dilaporkan</div><div class="stat-nilai"><?= h(rupiah($totalBiaya)) ?></div>
    <div class="stat-sub">akumulasi laporan</div></div>
  <div class="stat"><div class="stat-ikon"><?= ikon('chart') ?></div>
    <div class="stat-label">Progres tertinggi</div>
    <div class="stat-nilai"><?= $ringkas ? h((string) max(array_map(fn($r) => (int) $r['progres'], $ringkas))) . '%' : '-' ?></div>
    <div class="stat-sub">dari laporan terakhir</div></div>
</div>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head"><div><h2>Laporan Mandor</h2>
      <div class="sub">Kirim progres harian agar owner tahu kondisi lapangan.</div></div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <div class="form-grid">
        <div class="field"><label>Nama proyek</label>
          <input class="input" name="nama" required placeholder="Pembangunan Kost 10 Pintu"></div>
        <div class="field"><label>Lokasi</label>
          <input class="input" name="lokasi" placeholder="Kota Depok"></div>
        <div class="field"><label>Progres (%)</label>
          <input class="input" name="progres" type="number" min="0" max="100" required placeholder="65"></div>
        <div class="field"><label>Biaya pengadaan (Rp)</label>
          <input class="input" name="biaya" type="number" min="0" placeholder="12500000"></div>
        <div class="field" style="grid-column:1/-1"><label>Keterangan / kendala</label>
          <textarea class="textarea" name="keterangan" placeholder="Material masuk, pekerjaan lantai 2, kendala cuaca…"></textarea></div>
      </div>
      <div class="form-kaki">
        <button class="btn btn-emas" type="submit"><?= ikon('send') ?> Kirim Laporan</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Ringkasan Proyek</h2><div class="sub">Laporan terakhir per proyek</div></div></div>
    <?php if (!$ringkas): ?>
      <div class="kosong">Belum ada laporan.</div>
    <?php else: foreach ($ringkas as $nm => $r): ?>
      <div style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;gap:8px;font-weight:700;font-size:13.5px">
          <span><?= h($nm) ?></span><span><?= h((string) $r['progres']) ?>%</span>
        </div>
        <div class="progres" style="margin-top:6px"><span style="width:<?= (int) $r['progres'] ?>%"></span></div>
        <div class="progres-teks">
          <span><?= h($r['mandor_nama']) ?></span><span><?= h(tanggal_pendek($r['tanggal'])) ?></span>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Riwayat Laporan</h2><div class="sub"><?= h((string) count($rows)) ?> laporan</div></div></div>
  <div class="table-wrap">
    <table class="tabel">
      <thead><tr><th>Tanggal</th><th>Proyek</th><th>Lokasi</th><th>Mandor</th><th class="angka">Progres</th><th class="angka">Biaya</th><th>Keterangan</th><?= $owner ? '<th></th>' : '' ?></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $owner ? 8 : 7 ?>" class="kosong">Belum ada laporan proyek.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= h(tanggal_pendek($r['tanggal'])) ?><br><span class="stat-sub"><?= h(jam($r['waktu'])) ?></span></td>
          <td><b><?= h($r['nama']) ?></b><?= (int) $r['contoh'] === 1 ? ' ' . badge('contoh', 'abu') : '' ?></td>
          <td><?= h($r['lokasi']) ?></td>
          <td><?= h($r['mandor_nama']) ?></td>
          <td class="angka"><b><?= h((string) $r['progres']) ?>%</b></td>
          <td class="angka"><?= h(rupiah($r['biaya'])) ?></td>
          <td><?= (string) $r['keterangan'] !== '' ? h($r['keterangan']) : '<span class="stat-sub">-</span>' ?></td>
          <?php if ($owner): ?>
            <td>
              <form method="post" data-konfirmasi="Hapus laporan proyek ini?">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
