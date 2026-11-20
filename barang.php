<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_modul('barang');
$owner = $u['role'] === 'owner';
$pdo = db();
$tokoBoleh = $owner ? array_keys(toko_semua()) : toko_untuk((string) $u['usaha_nama']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'simpan') {
        $id = input_int('id');
        $nama = input('nama');
        $toko = input('toko');
        $harga = input_int('harga');
        $modal = input_int('modal');
        $stok = input_float('stok', 0);

        if ($nama === '') {
            flash_set('error', 'Nama barang wajib diisi.');
            redirect('barang.php');
        }
        if (!in_array($toko, $tokoBoleh, true)) {
            flash_set('error', 'Toko di luar wilayah kerja akun Anda.');
            redirect('barang.php');
        }
        if ($harga <= 0) {
            flash_set('error', 'Harga jual wajib lebih dari 0.');
            redirect('barang.php');
        }

        $foto = '';
        if (!empty($_FILES['foto']['name'])) {
            $hasil = simpan_media('foto', 'gambar');
            if (!$hasil['ok']) {
                flash_set('error', 'Foto barang gagal diunggah: ' . (string) ($hasil['error'] ?? ''));
                redirect('barang.php');
            }
            $foto = (string) $hasil['url'];
        }

        $waktu = date('Y-m-d H:i:s');
        if ($id > 0) {
            $st = $pdo->prepare('SELECT * FROM barang WHERE id = ?');
            $st->execute([$id]);
            $lama = $st->fetch();
            if (!$lama || (!$owner && !in_array((string) $lama['toko'], $tokoBoleh, true))) {
                flash_set('error', 'Barang tidak ditemukan / bukan wilayah kerja Anda.');
                redirect('barang.php');
            }
            $pdo->prepare('UPDATE barang SET nama = ?, kategori = ?, satuan = ?, harga = ?, modal = ?, stok = ?,
                toko = ?, foto = ?, oleh = ? WHERE id = ?')
                ->execute([$nama, input('kategori', 'Umum'), input('satuan', 'pcs'), $harga, $modal, $stok,
                    $toko, $foto !== '' ? $foto : (string) $lama['foto'], (string) $u['nama'], $id]);
            flash_set('sukses', 'Data barang diperbarui.');
        } else {
            $pdo->prepare('INSERT INTO barang (nama, kategori, satuan, harga, modal, stok, toko, foto, oleh, dibuat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$nama, input('kategori', 'Umum'), input('satuan', 'pcs'), $harga, $modal, $stok,
                    $toko, $foto, (string) $u['nama'], $waktu]);
            flash_set('sukses', 'Barang baru masuk BIG DATA.');
        }
        redirect('barang.php');
    }

    if ($aksi === 'aktif') {
        $id = input_int('id');
        $st = $pdo->prepare('SELECT * FROM barang WHERE id = ?');
        $st->execute([$id]);
        $b = $st->fetch();
        if ($b && ($owner || in_array((string) $b['toko'], $tokoBoleh, true))) {
            $pdo->prepare('UPDATE barang SET aktif = 1 - aktif WHERE id = ?')->execute([$id]);
            flash_set('sukses', 'Status barang diperbarui.');
        }
        redirect('barang.php');
    }

    if ($aksi === 'hapus' && $owner) {
        $pdo->prepare('DELETE FROM barang WHERE id = ?')->execute([input_int('id')]);
        flash_set('sukses', 'Barang dihapus dari BIG DATA.');
        redirect('barang.php');
    }
}

$edit = null;
$editId = input_int('edit');
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM barang WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
    if ($edit && !$owner && !in_array((string) $edit['toko'], $tokoBoleh, true)) {
        $edit = null;
    }
}

$sql = 'SELECT * FROM barang WHERE 1 = 1';
$args = [];
if ($tokoBoleh) {
    $sql .= ' AND toko IN (' . in_list($tokoBoleh) . ')';
    $args = $tokoBoleh;
} else {
    $sql .= ' AND 0 = 1';
}
$sql .= ' ORDER BY aktif DESC, nama';
$st = $pdo->prepare($sql);
$st->execute($args);
$daftar = $st->fetchAll();

$nilaiStok = 0;
$stokKritis = 0;
foreach ($daftar as $b) {
    $nilaiStok += (int) round((float) $b['stok'] * (int) $b['harga']);
    if ((float) $b['stok'] <= 10) {
        $stokKritis++;
    }
}

halaman_awal('BIG DATA Barang', 'barang.php', '<span class="chip">' . h((string) count($daftar)) . ' barang terdata</span>');
?>

<div class="grid grid-3">
  <div class="stat"><div class="stat-ikon emas"><?= ikon('box') ?></div>
    <div class="stat-label">Total barang</div><div class="stat-nilai"><?= h((string) count($daftar)) ?></div>
    <div class="stat-sub">termasuk nonaktif</div></div>
  <div class="stat"><div class="stat-ikon hijau"><?= ikon('rupiah') ?></div>
    <div class="stat-label">Nilai stok (harga jual)</div><div class="stat-nilai"><?= h(rupiah($nilaiStok)) ?></div>
    <div class="stat-sub">estimasi bila terjual habis</div></div>
  <div class="stat"><div class="stat-ikon"><?= ikon('chart') ?></div>
    <div class="stat-label">Stok kritis (≤ 10)</div><div class="stat-nilai"><?= h((string) $stokKritis) ?></div>
    <div class="stat-sub">perlu segera diisi ulang</div></div>
</div>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head">
      <div><h2><?= $edit ? 'Ubah Barang' : 'Tambah Barang ke BIG DATA' ?></h2>
        <div class="sub">Foto barang membantu karyawan lain mengenali produk.</div></div>
      <?php if ($edit): ?><a class="btn btn-kecil btn-garis" href="barang.php">Batal ubah</a><?php endif; ?>
    </div>
    <?php if (!$tokoBoleh): ?>
      <div class="alert alert-error">Akun ini belum punya toko. Hubungi owner.</div>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <div class="form-grid">
        <div class="field"><label>Nama barang</label>
          <input class="input" name="nama" required value="<?= h($edit['nama'] ?? '') ?>" placeholder="Contoh: Kantong Plastik"></div>
        <div class="field"><label>Toko</label>
          <select class="select" name="toko" required>
            <?php foreach ($tokoBoleh as $t): ?>
              <option value="<?= h($t) ?>" <?= (($edit['toko'] ?? '') === $t) ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Kategori</label>
          <select class="select" name="kategori">
            <?php foreach (kategori_barang() as $k): ?>
              <option value="<?= h($k) ?>" <?= (($edit['kategori'] ?? '') === $k) ? 'selected' : '' ?>><?= h($k) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Satuan</label>
          <input class="input" name="satuan" value="<?= h($edit['satuan'] ?? 'pcs') ?>" placeholder="pcs / pack / kg"></div>
        <div class="field"><label>Harga jual (Rp)</label>
          <input class="input" name="harga" type="number" min="1" required value="<?= h((string) ($edit['harga'] ?? '')) ?>"></div>
        <div class="field"><label>Harga modal (Rp)</label>
          <input class="input" name="modal" type="number" min="0" value="<?= h((string) ($edit['modal'] ?? '0')) ?>"></div>
        <div class="field"><label>Stok</label>
          <input class="input" name="stok" type="number" step="0.01" min="0" value="<?= h((string) ($edit['stok'] ?? '0')) ?>"></div>
        <div class="field"><label>Foto barang (opsional)</label>
          <input class="file-input" type="file" name="foto" accept="image/*"></div>
      </div>
      <div class="form-kaki">
        <button class="btn btn-emas" type="submit"><?= ikon($edit ? 'check' : 'plus') ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah ke BIG DATA' ?></button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Cari Barang</h2><div class="sub">Ketik untuk menyaring tabel</div></div></div>
    <div class="field">
      <input class="input" placeholder="Cari nama / kategori / toko…" data-cari="#tabelBarang">
    </div>
    <p class="stat-sub" style="margin-top:12px">
      <?= $owner ? 'Owner melihat barang seluruh toko.' : 'Akun Anda melihat barang ' . h((string) $u['usaha_nama']) . ' saja.' ?>
    </p>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Daftar BIG DATA</h2><div class="sub"><?= h((string) count($daftar)) ?> barang</div></div></div>
  <div class="table-wrap">
    <table class="tabel" id="tabelBarang">
      <thead><tr><th>Barang</th><th>Kategori</th><th>Satuan</th><th class="angka">Harga jual</th><th class="angka">Modal</th><th class="angka">Stok</th><th>Toko</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$daftar): ?>
        <tr><td colspan="9" class="kosong">Belum ada barang. Tambahkan lewat formulir di atas.</td></tr>
      <?php else: foreach ($daftar as $b): ?>
        <tr>
          <td>
            <b><?= h($b['nama']) ?></b>
            <?php if ((int) $b['contoh'] === 1): ?> <?= badge('contoh', 'abu') ?><?php endif; ?>
            <?php if ((string) $b['foto'] !== '' && aman_url((string) $b['foto']) !== ''): ?>
              <br><a href="<?= h(aman_url((string) $b['foto'])) ?>" target="_blank" rel="noopener" class="stat-sub">Lihat foto</a>
            <?php endif; ?>
          </td>
          <td><?= h($b['kategori']) ?></td>
          <td><?= h($b['satuan']) ?></td>
          <td class="angka"><?= h(rupiah($b['harga'])) ?></td>
          <td class="angka"><?= h(rupiah($b['modal'])) ?></td>
          <td class="angka"><b><?= h(angka($b['stok'], (float) $b['stok'] == (int) $b['stok'] ? 0 : 2)) ?></b></td>
          <td><?= h($b['toko']) ?></td>
          <td><?= (int) $b['aktif'] === 1 ? badge('Aktif', 'hijau') : badge('Nonaktif', 'abu') ?></td>
          <td class="aksi-baris" style="white-space:nowrap">
            <a class="btn btn-kecil btn-garis" href="barang.php?edit=<?= (int) $b['id'] ?>"><?= ikon('edit') ?></a>
            <form method="post" data-konfirmasi="Ubah status aktif barang ini?">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="aktif">
              <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="btn btn-kecil btn-garis" type="submit"><?= (int) $b['aktif'] === 1 ? ikon('x') : ikon('check') ?></button>
            </form>
            <?php if ($owner): ?>
              <form method="post" data-konfirmasi="Hapus barang ini dari BIG DATA?">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
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

<?php halaman_akhir(); ?>
