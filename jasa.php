<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_modul('jasa');
$owner = $u['role'] === 'owner';
$pdo = db();
$jenisBoleh = jasa_boleh($u);

$jenis = input('jenis', $jenisBoleh[0] ?? 'motor');
if (!in_array($jenis, $jenisBoleh, true)) {
    $jenis = $jenisBoleh[0] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');
    $jenisPost = input('jenis');
    if (!in_array($jenisPost, $jenisBoleh, true)) {
        flash_set('error', 'Jenis jasa di luar wilayah kerja akun Anda.');
        redirect('jasa.php');
    }

    if ($aksi === 'simpan') {
        $pelanggan = input('pelanggan');
        $paket = input('paket');
        $biaya = input_int('biaya');
        $qty = input_float('qty', 0);
        $daftarPaket = paket_jasa()[$jenisPost] ?? [];

        if ($pelanggan === '') {
            flash_set('error', 'Nama pelanggan wajib diisi.');
            redirect('jasa.php?jenis=' . urlencode($jenisPost));
        }
        if (!in_array($paket, $daftarPaket, true)) {
            flash_set('error', 'Paket layanan tidak dikenal.');
            redirect('jasa.php?jenis=' . urlencode($jenisPost));
        }
        if ($biaya <= 0) {
            flash_set('error', 'Biaya wajib lebih dari 0.');
            redirect('jasa.php?jenis=' . urlencode($jenisPost));
        }
        if ($jenisPost === 'laundry' && $qty <= 0) {
            flash_set('error', 'Berat (kg) wajib diisi untuk laundry.');
            redirect('jasa.php?jenis=' . urlencode($jenisPost));
        }

        $waktu = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO jasa
            (jenis, tanggal, waktu, pelanggan, plat, paket, qty, biaya, petugas_id, petugas_nama, status, catatan, dibuat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$jenisPost, date('Y-m-d'), $waktu, $pelanggan, input('plat'), $paket,
                $jenisPost === 'laundry' ? $qty : 1, $biaya, (int) $u['id'], (string) $u['nama'],
                'dikerjakan', input('catatan'), $waktu]);

        flash_set('sukses', jasa_label($jenisPost) . ' untuk ' . h($pelanggan) . ' tersimpan (' . rupiah($biaya) . ').');
        redirect('jasa.php?jenis=' . urlencode($jenisPost));
    }

    if ($aksi === 'selesai') {
        $id = input_int('id');
        $st = $pdo->prepare('SELECT * FROM jasa WHERE id = ?');
        $st->execute([$id]);
        $j = $st->fetch();
        if ($j && ($owner || (int) $j['petugas_id'] === (int) $u['id'])) {
            $pdo->prepare('UPDATE jasa SET status = ? WHERE id = ?')->execute(['selesai', $id]);
            flash_set('sukses', 'Pekerjaan ditandai selesai.');
        }
        redirect('jasa.php?jenis=' . urlencode($jenisPost));
    }

    if ($aksi === 'hapus' && $owner) {
        $pdo->prepare('DELETE FROM jasa WHERE id = ?')->execute([input_int('id')]);
        flash_set('sukses', 'Data pekerjaan dihapus.');
        redirect('jasa.php?jenis=' . urlencode($jenisPost));
    }
}

$tanggal = input('tanggal', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

$rows = [];
$totalHari = 0;
$totalBulan = 0;
$nBulan = 0;
if ($jenis !== '') {
    $st = $pdo->prepare('SELECT * FROM jasa WHERE jenis = ? AND tanggal = ? ORDER BY id DESC');
    $st->execute([$jenis, $tanggal]);
    $rows = $st->fetchAll();
    foreach ($rows as $r) {
        $totalHari += (int) $r['biaya'];
    }
    $st = $pdo->prepare('SELECT COALESCE(SUM(biaya),0), COUNT(*) FROM jasa WHERE jenis = ? AND substr(tanggal,1,7) = ?');
    $st->execute([$jenis, date('Y-m')]);
    [$totalBulan, $nBulan] = $st->fetch(PDO::FETCH_NUM);
}

$tabs = '';
foreach ($jenisBoleh as $j) {
    $tabs .= '<a class="tab ' . ($j === $jenis ? 'aktif' : '') . '" href="jasa.php?jenis=' . h($j) . '">'
        . ikon(jasa_ikon($j)) . ' ' . h(jasa_label($j)) . '</a>';
}

halaman_awal('Service & Jasa', 'jasa.php', $tabs);
?>

<div class="grid grid-3">
  <div class="stat"><div class="stat-ikon emas"><?= ikon('wrench') ?></div>
    <div class="stat-label">Pendapatan <?= h(tanggal_pendek($tanggal)) ?></div>
    <div class="stat-nilai"><?= h(rupiah($totalHari)) ?></div>
    <div class="stat-sub"><?= h((string) count($rows)) ?> pekerjaan</div></div>
  <div class="stat"><div class="stat-ikon hijau"><?= ikon('chart') ?></div>
    <div class="stat-label">Bulan ini</div>
    <div class="stat-nilai"><?= h(rupiah((int) $totalBulan)) ?></div>
    <div class="stat-sub"><?= h((string) (int) $nBulan) ?> pekerjaan</div></div>
  <div class="stat"><div class="stat-ikon"><?= ikon('clock') ?></div>
    <div class="stat-label">Belum selesai</div>
    <div class="stat-nilai"><?= h((string) count(array_filter($rows, fn($r) => $r['status'] !== 'selesai'))) ?></div>
    <div class="stat-sub">pada tanggal ini</div></div>
</div>

<?php if ($jenis === ''): ?>
  <div class="card"><div class="kosong">Akun ini belum terhubung ke unit jasa. Hubungi owner.</div></div>
<?php else: ?>
<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head"><div><h2>Input Pekerjaan — <?= h(jasa_label($jenis)) ?></h2>
      <div class="sub">Petugas otomatis tercatat sesuai akun yang login.</div></div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
      <div class="form-grid">
        <div class="field"><label>Nama pelanggan</label>
          <input class="input" name="pelanggan" required placeholder="Contoh: Budi"></div>
        <?php if ($jenis !== 'laundry'): ?>
          <div class="field"><label>Plat kendaraan</label>
            <input class="input" name="plat" placeholder="B 1234 AB"></div>
        <?php else: ?>
          <div class="field"><label>Berat (kg)</label>
            <input class="input" name="qty" type="number" step="0.1" min="0.1" required placeholder="5"></div>
        <?php endif; ?>
        <div class="field"><label>Paket layanan</label>
          <select class="select" name="paket" required>
            <?php foreach (paket_jasa()[$jenis] as $p): ?>
              <option value="<?= h($p) ?>"><?= h($p) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Biaya (Rp)</label>
          <input class="input" name="biaya" type="number" min="1" required placeholder="35000"></div>
        <div class="field"><label>Catatan</label>
          <input class="input" name="catatan" placeholder="Keluhan / permintaan khusus"></div>
      </div>
      <div class="form-kaki">
        <button class="btn btn-emas" type="submit"><?= ikon('plus') ?> Simpan Pekerjaan</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>SOP Wajib</h2><div class="sub">Diambil dari menu SOP Management</div></div></div>
    <?php
    $st = $pdo->prepare('SELECT * FROM sop WHERE kunci = ? AND aktif = 1');
    $st->execute([$jenis]);
    $sop = $st->fetch() ?: null;
    $langkah = $sop ? array_values(array_filter(array_map('trim', explode("\n", (string) $sop['langkah'])))) : [];
    ?>
    <?php if (!$langkah): ?>
      <div class="kosong">SOP untuk layanan ini belum diisi owner.</div>
    <?php else: ?>
      <ul class="sop-list">
        <?php foreach ($langkah as $i => $l): ?>
          <li><span class="sop-no"><?= h((string) ($i + 1)) ?></span><span><?= h($l) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Pekerjaan <?= h(jasa_label($jenis)) ?></h2><div class="sub"><?= h(tanggal_id($tanggal, true)) ?></div></div>
    <form method="get" class="saring tanpa-cetak">
      <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
      <div class="field"><label>Tanggal</label>
        <input class="input" type="date" name="tanggal" value="<?= h($tanggal) ?>" data-auto-submit></div>
    </form>
  </div>
  <div class="table-wrap">
    <table class="tabel">
      <thead><tr><th>Jam</th><th>Pelanggan</th><?= $jenis !== 'laundry' ? '<th>Plat</th>' : '<th class="angka">Kg</th>' ?>
        <th>Paket</th><th class="angka">Biaya</th><th>Petugas</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="kosong">Belum ada pekerjaan pada tanggal ini.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= h(jam($r['waktu'])) ?></td>
          <td><b><?= h($r['pelanggan']) ?></b><?= (int) $r['contoh'] === 1 ? ' ' . badge('contoh', 'abu') : '' ?></td>
          <td><?= $jenis !== 'laundry' ? h($r['plat']) : h(angka($r['qty'], 1)) ?></td>
          <td><?= h($r['paket']) ?></td>
          <td class="angka"><b><?= h(rupiah($r['biaya'])) ?></b></td>
          <td><?= h($r['petugas_nama']) ?></td>
          <td><?= badge_jasa((string) $r['status']) ?></td>
          <td class="aksi-baris" style="white-space:nowrap">
            <?php if ($r['status'] !== 'selesai'): ?>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="selesai">
                <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-kecil btn-hijau" type="submit"><?= ikon('check') ?> Selesai</button>
              </form>
            <?php endif; ?>
            <?php if ($owner): ?>
              <form method="post" data-konfirmasi="Hapus data pekerjaan ini?">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-kecil btn-merah" type="submit"><?= ikon('trash') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php halaman_akhir(); ?>
