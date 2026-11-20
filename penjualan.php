<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_modul('jual');
$owner = $u['role'] === 'owner';
$pdo = db();

$tokoBoleh = $owner ? array_keys(toko_semua()) : toko_untuk((string) $u['usaha_nama']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'jual') {
        $toko = input('toko');
        $namaBarang = input('barang_nama');
        $qty = input_float('qty', 0);
        $harga = input_int('harga');
        $catatan = input('catatan');

        if (!in_array($toko, $tokoBoleh, true)) {
            flash_set('error', 'Toko di luar wilayah kerja akun Anda.');
            redirect('penjualan.php');
        }
        if ($namaBarang === '') {
            flash_set('error', 'Nama barang wajib diisi.');
            redirect('penjualan.php');
        }
        if ($qty <= 0) {
            flash_set('error', 'Jumlah harus lebih dari 0.');
            redirect('penjualan.php');
        }
        if ($harga <= 0) {
            flash_set('error', 'Harga jual harus lebih dari 0.');
            redirect('penjualan.php');
        }

        $st = $pdo->prepare('SELECT * FROM barang WHERE lower(nama) = ? AND toko = ? AND aktif = 1');
        $st->execute([strtolower($namaBarang), $toko]);
        $barang = $st->fetch() ?: null;

        $total = (int) round($qty * $harga);
        $waktu = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO penjualan
                (tanggal, waktu, toko, barang_id, barang_nama, qty, harga, total, kasir_id, kasir_nama, catatan, dibuat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([date('Y-m-d'), $waktu, $toko, $barang ? (int) $barang['id'] : null, $namaBarang,
                $qty, $harga, $total, (int) $u['id'], (string) $u['nama'], $catatan, $waktu]);
            if ($barang) {
                $pdo->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?')->execute([$qty, (int) $barang['id']]);
                if ((int) $barang['harga'] !== $harga) {
                    $pdo->prepare('UPDATE barang SET harga = ? WHERE id = ?')->execute([$harga, (int) $barang['id']]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', 'Penjualan gagal disimpan: ' . $e->getMessage());
            redirect('penjualan.php');
        }

        flash_set('sukses', 'Penjualan ' . h($namaBarang) . ' x' . h((string) $qty) . ' = ' . rupiah($total)
            . ' tersimpan' . ($barang ? ' dan stok BIG DATA otomatis berkurang.' : '. (Barang belum ada di BIG DATA)'));
        redirect('penjualan.php');
    }

    if ($aksi === 'hapus' && $owner) {
        $id = input_int('id');
        $st = $pdo->prepare('SELECT * FROM penjualan WHERE id = ?');
        $st->execute([$id]);
        $p = $st->fetch();
        if ($p) {
            $pdo->beginTransaction();
            try {
                if (!empty($p['barang_id'])) {
                    $pdo->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?')
                        ->execute([(float) $p['qty'], (int) $p['barang_id']]);
                }
                $pdo->prepare('DELETE FROM penjualan WHERE id = ?')->execute([$id]);
                $pdo->commit();
                flash_set('sukses', 'Transaksi penjualan dihapus dan stok barang dikembalikan.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash_set('error', 'Gagal menghapus transaksi.');
            }
        }
        redirect('penjualan.php?tanggal=' . h(input('tanggal', date('Y-m-d'))));
    }
}

$tanggal = input('tanggal', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}
$filterToko = $owner ? input('toko') : '';

$sql = 'SELECT * FROM penjualan WHERE tanggal = ?';
$args = [$tanggal];
if ($tokoBoleh) {
    $sql .= ' AND toko IN (' . in_list($tokoBoleh) . ')';
    $args = array_merge($args, $tokoBoleh);
} else {
    $sql .= ' AND 0 = 1';
}
if ($filterToko !== '' && in_array($filterToko, $tokoBoleh, true)) {
    $sql .= ' AND toko = ?';
    $args[] = $filterToko;
}
$sql .= ' ORDER BY id DESC';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$totalHari = 0;
foreach ($rows as $r) {
    $totalHari += (int) $r['total'];
}

/* barang untuk datalist + autofill harga */
$sqlBarang = 'SELECT * FROM barang WHERE aktif = 1';
$argsBarang = [];
if ($tokoBoleh) {
    $sqlBarang .= ' AND toko IN (' . in_list($tokoBoleh) . ')';
    $argsBarang = $tokoBoleh;
} else {
    $sqlBarang .= ' AND 0 = 1';
}
$sqlBarang .= ' ORDER BY nama';
$st = $pdo->prepare($sqlBarang);
$st->execute($argsBarang);
$barang = $st->fetchAll();
$jsonBarang = json_encode(array_map(fn($b) => [
    'nama' => $b['nama'], 'harga' => (int) $b['harga'], 'satuan' => $b['satuan'],
], $barang), JSON_UNESCAPED_UNICODE);

$bulanIni = date('Y-m');
$sqlBulan = 'SELECT COALESCE(SUM(total),0), COUNT(*) FROM penjualan WHERE substr(tanggal,1,7) = ?';
$argBulan = [$bulanIni];
if ($tokoBoleh) {
    $sqlBulan .= ' AND toko IN (' . in_list($tokoBoleh) . ')';
    $argBulan = array_merge($argBulan, $tokoBoleh);
}
$st = $pdo->prepare($sqlBulan);
$st->execute($argBulan);
[$rpBulan, $nBulan] = $st->fetch(PDO::FETCH_NUM);

halaman_awal($owner ? 'Penjualan' : 'Update Harian — Penjualan', 'penjualan.php');
?>

<div class="grid grid-4">
  <div class="stat"><div class="stat-ikon emas"><?= ikon('cart') ?></div>
    <div class="stat-label">Penjualan <?= h(tanggal_pendek($tanggal)) ?></div>
    <div class="stat-nilai"><?= h(rupiah($totalHari)) ?></div>
    <div class="stat-sub"><?= h((string) count($rows)) ?> transaksi</div>
  </div>
  <div class="stat"><div class="stat-ikon hijau"><?= ikon('chart') ?></div>
    <div class="stat-label">Bulan ini</div>
    <div class="stat-nilai"><?= h(rupiah((int) $rpBulan)) ?></div>
    <div class="stat-sub"><?= h((string) (int) $nBulan) ?> transaksi</div>
  </div>
  <div class="stat"><div class="stat-ikon"><?= ikon('box') ?></div>
    <div class="stat-label">Barang terdata</div>
    <div class="stat-nilai"><?= h((string) count($barang)) ?></div>
    <div class="stat-sub">di BIG DATA</div>
  </div>
  <div class="stat"><div class="stat-ikon biru"><?= ikon('pin') ?></div>
    <div class="stat-label">Toko aktif</div>
    <div class="stat-nilai" style="font-size:16px"><?= h((string) count($tokoBoleh)) ?></div>
    <div class="stat-sub"><?= h($owner ? 'seluruh unit' : (string) $u['usaha_nama']) ?></div>
  </div>
</div>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head"><div><h2>Catat Penjualan</h2>
      <div class="sub">Stok di BIG DATA otomatis berkurang setelah disimpan.</div></div></div>
    <?php if (!$tokoBoleh): ?>
      <div class="alert alert-error">Akun ini belum punya toko. Hubungi owner.</div>
    <?php else: ?>
    <form method="post" data-barang-json='<?= h($jsonBarang) ?>'>
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="jual">
      <div class="form-grid">
        <div class="field">
          <label>Toko / outlet</label>
          <select class="select" name="toko" required>
            <?php foreach ($tokoBoleh as $t): ?>
              <option value="<?= h($t) ?>"><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Nama barang</label>
          <input class="input" id="barang_nama" name="barang_nama" list="daftarBarang" required
                 placeholder="Pilih dari BIG DATA atau tulis baru">
          <datalist id="daftarBarang">
            <?php foreach ($barang as $b): ?>
              <option value="<?= h($b['nama']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <span class="hint">Harga terisi otomatis bila barang ada di BIG DATA.</span>
        </div>
        <div class="field">
          <label>Jumlah (<span id="barang_satuan">pcs</span>)</label>
          <input class="input" id="barang_qty" name="qty" type="number" step="0.01" min="0.01" value="1" required>
        </div>
        <div class="field">
          <label>Harga jual (Rp)</label>
          <input class="input" id="barang_harga" name="harga" type="number" min="1" step="1" required>
        </div>
        <div class="field">
          <label>Catatan (opsional)</label>
          <input class="input" name="catatan" placeholder="Contoh: bon / langganan / promo">
        </div>
      </div>
      <div class="form-kaki">
        <button class="btn btn-emas" type="submit"><?= ikon('plus') ?> Simpan Penjualan</button>
        <a class="btn btn-garis" href="barang.php"><?= ikon('box') ?> Tambah barang baru</a>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Filter</h2><div class="sub">Riwayat penjualan</div></div></div>
    <form method="get" class="form-grid">
      <div class="field"><label>Tanggal</label>
        <input class="input" type="date" name="tanggal" value="<?= h($tanggal) ?>" data-auto-submit></div>
      <?php if ($owner): ?>
        <div class="field"><label>Toko</label>
          <select class="select" name="toko" data-auto-submit>
            <option value="">Semua toko</option>
            <?php foreach ($tokoBoleh as $t): ?>
              <option value="<?= h($t) ?>" <?= $filterToko === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select></div>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Rekap Penjualan</h2><div class="sub"><?= h(tanggal_id($tanggal, true)) ?> &middot; total <?= h(rupiah($totalHari)) ?></div></div>
  </div>
  <div class="table-wrap">
    <table class="tabel">
      <thead><tr><th>Jam</th><th>Toko</th><th>Barang</th><th class="angka">Qty</th><th class="angka">Harga</th><th class="angka">Total</th><th>Kasir</th><th>Catatan</th><?= $owner ? '<th></th>' : '' ?></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $owner ? 9 : 8 ?>" class="kosong">Belum ada penjualan pada tanggal ini.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= h(jam($r['waktu'])) ?></td>
          <td><?= h($r['toko']) ?></td>
          <td><b><?= h($r['barang_nama']) ?></b><?= (int) $r['contoh'] === 1 ? ' ' . badge('contoh', 'abu') : '' ?></td>
          <td class="angka"><?= h(angka($r['qty'], (float) $r['qty'] == (int) $r['qty'] ? 0 : 2)) ?></td>
          <td class="angka"><?= h(rupiah($r['harga'])) ?></td>
          <td class="angka"><b><?= h(rupiah($r['total'])) ?></b></td>
          <td><?= h($r['kasir_nama']) ?></td>
          <td><?= (string) $r['catatan'] !== '' ? h($r['catatan']) : '<span class="stat-sub">-</span>' ?></td>
          <?php if ($owner): ?>
            <td>
              <form method="post" data-konfirmasi="Hapus transaksi ini? Stok barang akan dikembalikan.">
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
