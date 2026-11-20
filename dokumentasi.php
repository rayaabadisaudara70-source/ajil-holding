<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_login();
$owner = $u['role'] === 'owner';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'simpan') {
        $jenis = input('jenis', 'Progres Pekerjaan');
        if (!in_array($jenis, jenis_dokumentasi(), true)) {
            $jenis = 'Progres Pekerjaan';
        }
        $keterangan = input('keterangan');
        $namaFile = (string) ($_FILES['media']['name'] ?? '');
        $ext = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
        $tipeMedia = in_array($ext, ['mp4', 'webm', 'mov', 'm4v'], true) ? 'video' : 'gambar';

        $hasil = simpan_media('media', $tipeMedia);
        if (!$hasil['ok']) {
            flash_set('error', 'Dokumentasi gagal dikirim: ' . (string) ($hasil['error'] ?? 'file tidak valid.'));
            redirect('dokumentasi.php');
        }

        $judul = input('judul');
        if ($judul === '') {
            $judul = $jenis . ' - ' . (string) $u['nama'];
        }
        $waktu = date('Y-m-d H:i:s');
        $st = $pdo->prepare('INSERT INTO dokumentasi
            (tanggal, waktu, pengguna_id, nama, usaha_nama, jenis, judul, keterangan, media, url, dibuat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([date('Y-m-d'), $waktu, (int) $u['id'], (string) $u['nama'], (string) $u['usaha_nama'],
            $jenis, $judul, $keterangan, $tipeMedia === 'video' ? 'video' : 'gambar', (string) $hasil['url'], $waktu]);

        flash_set('sukses', 'Dokumentasi Live terkirim. Terima kasih — bukti pekerjaan sudah tersimpan di server.');
        redirect('dokumentasi.php');
    }

    if ($aksi === 'hapus') {
        $id = input_int('id');
        $st = $pdo->prepare('SELECT * FROM dokumentasi WHERE id = ?');
        $st->execute([$id]);
        $d = $st->fetch();
        if ($d && ($owner || (int) $d['pengguna_id'] === (int) $u['id'])) {
            $pdo->prepare('DELETE FROM dokumentasi WHERE id = ?')->execute([$id]);
            flash_set('sukses', 'Dokumentasi dihapus.');
        } else {
            flash_set('error', 'Anda tidak berhak menghapus dokumentasi ini.');
        }
        redirect('dokumentasi.php');
    }
}

$filterJenis = input('jenis');
$filterUsaha = $owner ? input('usaha') : '';

$sql = 'SELECT * FROM dokumentasi WHERE 1 = 1';
$args = [];
if (!$owner) {
    $sql .= ' AND (pengguna_id = ? OR pengguna_id IS NULL)';
    $args[] = (int) $u['id'];
} elseif ($filterUsaha !== '') {
    $sql .= ' AND usaha_nama = ?';
    $args[] = $filterUsaha;
}
if ($filterJenis !== '') {
    $sql .= ' AND jenis = ?';
    $args[] = $filterJenis;
}
$sql .= ' ORDER BY id DESC LIMIT 60';
$st = $pdo->prepare($sql);
$st->execute($args);
$daftar = $st->fetchAll();

$jmlSemua = (int) $pdo->query('SELECT COUNT(*) FROM dokumentasi')->fetchColumn();

halaman_awal('Dokumentasi Live', 'dokumentasi.php', '<span class="chip">' . h((string) $jmlSemua) . ' dokumentasi tersimpan</span>');
?>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head">
      <div><h2>Kirim Foto / Video Kegiatan</h2>
        <div class="sub">Foto maks 5 MB, video maks 20 MB (mp4/webm/mov).</div></div>
    </div>
    <form method="post" enctype="multipart/form-data" data-wajib-file="Pilih foto atau video dulu sebelum mengirim.">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <div class="form-grid">
        <div class="field">
          <label>Jenis dokumentasi</label>
          <select class="select" name="jenis">
            <?php foreach (jenis_dokumentasi() as $j): ?>
              <option value="<?= h($j) ?>"><?= h($j) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Judul (opsional)</label>
          <input class="input" name="judul" placeholder="Otomatis: jenis - nama Anda">
        </div>
      </div>
      <div class="field" style="margin-top:12px">
        <label>Foto / video</label>
        <input class="file-input" type="file" name="media" accept="image/*,video/*" data-label="labelFile" required>
        <span class="hint" id="labelFile"></span>
      </div>
      <div class="field" style="margin-top:12px">
        <label>Keterangan pekerjaan</label>
        <textarea class="textarea" name="keterangan" placeholder="Ceritakan pekerjaan / kondisi hari ini…"></textarea>
      </div>
      <div class="form-kaki">
        <button class="btn btn-emas" type="submit"><?= ikon('send') ?> Kirim ke Dokumentasi Live</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Filter Galeri</h2><div class="sub">Cari dokumentasi tertentu</div></div></div>
    <form method="get" class="form-grid">
      <div class="field">
        <label>Jenis</label>
        <select class="select" name="jenis" data-auto-submit>
          <option value="">Semua jenis</option>
          <?php foreach (jenis_dokumentasi() as $j): ?>
            <option value="<?= h($j) ?>" <?= $filterJenis === $j ? 'selected' : '' ?>><?= h($j) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($owner): ?>
        <div class="field">
          <label>Unit usaha</label>
          <select class="select" name="usaha" data-auto-submit>
            <option value="">Semua unit</option>
            <?php foreach (usaha_nama_semua() as $un): ?>
              <option value="<?= h($un) ?>" <?= $filterUsaha === $un ? 'selected' : '' ?>><?= h($un) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </form>
    <p class="stat-sub" style="margin-top:14px">
      <?= $owner
          ? 'Owner melihat dokumentasi seluruh unit usaha.'
          : 'Karyawan melihat dokumentasi miliknya sendiri (privasi antar rekan).' ?>
    </p>
  </div>
</div>

<?php if (!$daftar): ?>
  <div class="card"><div class="kosong">Belum ada dokumentasi pada filter ini. Kirim foto kegiatan pertama lewat formulir di atas.</div></div>
<?php else: ?>
  <div class="feed">
    <?php foreach ($daftar as $d): ?>
      <div class="dok">
        <div class="dok-media">
          <span class="dok-pita">DOKUMENTASI AJIL.JR</span>
          <span class="dok-jenis"><?= h($d['jenis']) ?></span>
          <?php if ($owner || (int) $d['pengguna_id'] === (int) $u['id']): ?>
            <form method="post" class="dok-hapus tanpa-cetak" data-konfirmasi="Hapus dokumentasi ini?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
              <button class="btn btn-kecil btn-merah" type="submit" title="Hapus"><?= ikon('trash') ?></button>
            </form>
          <?php endif; ?>
          <?php if (aman_url((string) $d['url']) !== ''): ?>
            <?php if ($d['media'] === 'video'): ?>
              <video controls preload="metadata" src="<?= h(aman_url((string) $d['url'])) ?>"></video>
            <?php else: ?>
              <a href="<?= h(aman_url((string) $d['url'])) ?>" target="_blank" rel="noopener">
                <img src="<?= h(aman_url((string) $d['url'])) ?>" alt="<?= h($d['judul']) ?>" loading="lazy">
              </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="dok-cap">
          <div class="dok-judul"><?= h($d['judul']) ?></div>
          <div class="dok-meta">
            <?= h($d['nama']) ?> &middot; <?= h($d['usaha_nama']) ?><br>
            <?= h(waktu_lengkap($d['waktu'])) ?><?= (int) $d['contoh'] === 1 ? ' &middot; ' . badge('contoh', 'abu') : '' ?>
          </div>
          <?php if ((string) $d['keterangan'] !== ''): ?>
            <p class="dok-ket"><?= h($d['keterangan']) ?></p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php halaman_akhir(); ?>
