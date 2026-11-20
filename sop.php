<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_login();
$owner = $u['role'] === 'owner';
$pdo = db();
$usahaSaya = (string) $u['usaha_nama'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    if (!$owner) {
        flash_set('error', 'Hanya owner yang boleh mengubah SOP.');
        redirect('sop.php');
    }
    $aksi = input('aksi');
    if ($aksi === 'simpan') {
        $id = input_int('id');
        $judul = input('judul');
        $langkah = trim((string) ($_POST['langkah'] ?? ''));
        if ($judul === '' || $langkah === '') {
            flash_set('error', 'Judul dan langkah SOP tidak boleh kosong.');
            redirect('sop.php');
        }
        $pdo->prepare('UPDATE sop SET judul = ?, langkah = ?, diperbarui = ? WHERE id = ?')
            ->execute([$judul, $langkah, date('Y-m-d H:i:s'), $id]);
        flash_set('sukses', 'SOP diperbarui.');
        redirect('sop.php');
    }
    if ($aksi === 'bawaan') {
        $now = date('Y-m-d H:i:s');
        $upd = $pdo->prepare('UPDATE sop SET judul = ?, usaha_nama = ?, langkah = ?, diperbarui = ? WHERE kunci = ?');
        foreach (sop_bawaan() as $s) {
            $upd->execute([$s[1], $s[2], $s[3], $now, $s[0]]);
        }
        flash_set('sukses', 'Semua SOP dikembalikan ke teks bawaan.');
        redirect('sop.php');
    }
}

$daftar = $pdo->query('SELECT * FROM sop WHERE aktif = 1 ORDER BY urutan, id')->fetchAll();

halaman_awal('SOP Management', 'sop.php', $owner
    ? '<button class="btn btn-garis" type="button" onclick="window.print()">' . ikon('print') . ' Cetak SOP</button>'
    : '<span class="chip">' . h($usahaSaya !== '' ? $usahaSaya : 'Semua unit') . '</span>');
?>

<div class="card">
  <div class="card-head">
    <div><h2>Prosedur Tetap Seluruh Unit Usaha</h2>
      <div class="sub">Setiap pekerjaan mengikuti langkah di bawah ini — wajib dibaca sebelum mulai kerja.</div></div>
    <?php if ($owner): ?>
      <form method="post" data-konfirmasi="Kembalikan semua SOP ke teks bawaan? Perubahan Anda akan hilang.">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="bawaan">
        <button class="btn btn-kecil btn-garis" type="submit"><?= ikon('shield') ?> Kembalikan Bawaan</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="alert alert-info">
    SOP unit Anda ditandai emas. Bila ada langkah yang tidak sesuai kondisi lapangan, ajukan perubahan ke owner —
    owner bisa mengubahnya langsung dari halaman ini tanpa mengubah kode program.
  </div>
</div>

<div class="grid grid-2">
<?php foreach ($daftar as $s):
    $langkah = array_values(array_filter(array_map('trim', explode("\n", (string) $s['langkah']))));
    $milikSaya = $s['usaha_nama'] === $usahaSaya || $owner;
    ?>
  <div class="sop-kartu">
    <h3>
      <?= ikon('book') ?>
      <span><?= h($s['judul']) ?></span>
      <?php if ($s['usaha_nama'] === $usahaSaya && !$owner): ?><?= badge('Unit Anda', 'emas') ?><?php endif; ?>
    </h3>
    <div class="stat-sub" style="margin:6px 0 14px">
      <?= h($s['usaha_nama']) ?> &middot; <?= h((string) count($langkah)) ?> langkah
      <?= (string) $s['diperbarui'] !== '' ? ' · diperbarui ' . h(tanggal_pendek($s['diperbarui'])) : '' ?>
    </div>
    <ul class="sop-list">
      <?php foreach ($langkah as $i => $l): ?>
        <li><span class="sop-no"><?= h((string) ($i + 1)) ?></span><span><?= h($l) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <?php if ($owner && $milikSaya): ?>
      <details style="margin-top:14px">
        <summary class="btn btn-kecil btn-garis" style="display:inline-flex;cursor:pointer"><?= ikon('edit') ?> Ubah SOP ini</summary>
        <form method="post" style="margin-top:12px">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="simpan">
          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <div class="field"><label>Judul SOP</label>
            <input class="input" name="judul" value="<?= h($s['judul']) ?>" required></div>
          <div class="field" style="margin-top:10px">
            <label>Langkah (satu langkah per baris)</label>
            <textarea class="textarea" name="langkah" rows="8" required><?= h($s['langkah']) ?></textarea>
          </div>
          <div class="form-kaki">
            <button class="btn btn-emas" type="submit"><?= ikon('check') ?> Simpan SOP</button>
          </div>
        </form>
      </details>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<?php halaman_akhir(); ?>
