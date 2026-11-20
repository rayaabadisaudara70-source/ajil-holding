<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'profil') {
        $nama = input('nama');
        $hp = input('hp');
        if ($nama === '') {
            flash_set('error', 'Nama tidak boleh kosong.');
            redirect('akun.php');
        }
        $pdo->prepare('UPDATE pengguna SET nama = ?, hp = ? WHERE id = ?')
            ->execute([$nama, $hp, (int) $u['id']]);
        flash_set('sukses', 'Profil diperbarui.');
        redirect('akun.php');
    }

    if ($aksi === 'password') {
        $lama = (string) ($_POST['password_lama'] ?? '');
        $baru = (string) ($_POST['password_baru'] ?? '');
        $ulang = (string) ($_POST['password_ulang'] ?? '');
        if (!password_verify($lama, (string) $u['password'])) {
            flash_set('error', 'Password saat ini salah.');
            redirect('akun.php');
        }
        if (strlen($baru) < 6) {
            flash_set('error', 'Password baru minimal 6 karakter.');
            redirect('akun.php');
        }
        if ($baru !== $ulang) {
            flash_set('error', 'Konfirmasi password baru tidak sama.');
            redirect('akun.php');
        }
        if ($baru === $lama) {
            flash_set('error', 'Password baru harus berbeda dari password lama.');
            redirect('akun.php');
        }
        $pdo->prepare('UPDATE pengguna SET password = ?, gagal = 0, terkunci_sampai = NULL WHERE id = ?')
            ->execute([password_hash($baru, PASSWORD_DEFAULT), (int) $u['id']]);
        flash_set('sukses', 'Password berhasil diganti. Gunakan password baru pada login berikutnya.');
        redirect('akun.php');
    }
}

$bulanIni = date('Y-m');
$hadir = (int) $pdo->query('SELECT COUNT(DISTINCT tanggal) FROM absensi WHERE pengguna_id = ' . (int) $u['id']
    . " AND tipe = 'masuk' AND substr(tanggal,1,7) = " . $pdo->quote($bulanIni) . ' AND contoh = 0')->fetchColumn();

halaman_awal('Akun Saya', 'akun.php');
?>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head"><div><h2>Profil</h2><div class="sub">Nama tampil di absensi, dokumentasi, dan laporan</div></div></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="profil">
      <div class="form-grid">
        <div class="field"><label>Nama lengkap</label>
          <input class="input" name="nama" value="<?= h($u['nama']) ?>" required></div>
        <div class="field"><label>Nomor HP</label>
          <input class="input" name="hp" value="<?= h($u['hp']) ?>" placeholder="08xx"></div>
        <div class="field"><label>Username (tidak bisa diubah sendiri)</label>
          <input class="input" value="<?= h($u['username']) ?>" disabled></div>
        <div class="field"><label>Unit usaha</label>
          <input class="input" value="<?= h($u['usaha_nama'] !== '' ? $u['usaha_nama'] : 'Semua unit (owner)') ?>" disabled></div>
      </div>
      <div class="form-kaki"><button class="btn btn-emas" type="submit"><?= ikon('check') ?> Simpan Profil</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Ringkasan Akun</h2><div class="sub"><?= h(bulan_label($bulanIni)) ?></div></div></div>
    <div class="grid" style="gap:14px">
      <div><div class="stat-label">Peran</div><div class="stat-nilai" style="font-size:17px"><?= h(role_label((string) $u['role'])) ?></div></div>
      <div><div class="stat-label">Jabatan</div><div class="stat-nilai" style="font-size:17px"><?= h($u['jabatan'] !== '' ? $u['jabatan'] : '-') ?></div></div>
      <div><div class="stat-label">Hadir bulan ini</div><div class="stat-nilai" style="font-size:17px"><?= h((string) $hadir) ?> hari</div></div>
      <div><div class="stat-label">Status akun</div><div><?= (int) $u['aktif'] === 1 ? badge('Aktif', 'hijau') : badge('Nonaktif', 'merah') ?>
        <?= password_bawaan($u) ? badge('password bawaan', 'merah') : badge('password sudah diganti', 'hijau') ?></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Ganti Password</h2>
    <div class="sub">Wajib diganti bila akun masih memakai password bawaan.</div></div></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="password">
    <div class="form-grid">
      <div class="field"><label>Password saat ini</label>
        <input class="input" type="password" name="password_lama" required autocomplete="current-password"></div>
      <div class="field"><label>Password baru (min. 6 karakter)</label>
        <input class="input" type="password" name="password_baru" required minlength="6" autocomplete="new-password"></div>
      <div class="field"><label>Ulangi password baru</label>
        <input class="input" type="password" name="password_ulang" required minlength="6" autocomplete="new-password"></div>
    </div>
    <div class="form-kaki"><button class="btn btn-navy" type="submit"><?= ikon('shield') ?> Ganti Password</button></div>
  </form>
</div>

<?php halaman_akhir(); ?>
