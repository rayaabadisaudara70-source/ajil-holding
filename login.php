<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

if (user_login() !== null) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $sisa = login_terkunci();
    if ($sisa > 0) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . $sisa . ' menit.';
    } else {
        $hasil = login_coba(input('username'), (string) ($_POST['password'] ?? ''));
        if ($hasil['ok']) {
            redirect('index.php');
        }
        $error = (string) ($hasil['pesan'] ?? 'Gagal masuk.');
    }
}

$namaUsaha = pengaturan('nama_usaha', 'AJIL.JR MINI HOLDING');
$yayasan   = pengaturan('yayasan', 'Koperasi Jasa Usaha Raya Abadi Saudara');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk &middot; <?= h($namaUsaha) ?></title>
<meta name="theme-color" content="#0A1F44">
<link rel="icon" href="assets/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= (int) @filemtime(__DIR__ . '/assets/app.css') ?>">
</head>
<body>
<div class="masuk">
  <section class="masuk-kiri">
    <div class="masuk-merek">
      <img src="assets/logo.svg" alt="Logo AJIL.JR Mini Holding">
      <div>
        <div class="t1">AJIL.JR MINI HOLDING</div>
        <div class="t2">DEPOK &middot; INDONESIA</div>
      </div>
    </div>
    <h1 class="masuk-judul">SOP Absensi &amp;<br>Pelaporan <span>Management</span></h1>
    <p class="masuk-sub">
      Satu pintu untuk absensi harian, dokumentasi lapangan, penjualan, service, dan laporan
      seluruh unit usaha. Semua data tersimpan di server, jadi owner dan karyawan melihat
      data yang sama dari perangkat masing-masing.
    </p>
    <div class="masuk-poin">
      <div><b>7 UNIT USAHA</b>Plastik, Sembako, Motor, Carwash, Laundry, Property, Kost/Ruko</div>
      <div><b>ABSENSI + FOTO</b>Check-in &amp; check-out dengan foto bukti dan lokasi GPS</div>
      <div><b>DOKUMENTASI LIVE</b>Foto &amp; video pekerjaan langsung masuk galeri bersama</div>
      <div><b>REKAP &amp; GAJI</b>Hitung kehadiran, potongan, dan bonus secara otomatis</div>
    </div>
  </section>

  <section class="masuk-kanan">
    <div class="masuk-kartu">
      <h2>Masuk Sistem</h2>
      <p class="sub">Gunakan akun yang diberikan owner/pengurus.</p>
      <?php if ($error !== ''): ?>
        <div class="alert alert-error" style="margin-bottom:14px"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field">
          <label for="username">Username</label>
          <input class="input" id="username" name="username" value="<?= h(input('username')) ?>" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" required>
        </div>
        <button class="btn btn-emas btn-blok btn-besar" type="submit">Masuk</button>
      </form>
      <p class="masuk-kaki">
        Lupa password? Hubungi owner untuk dilakukan reset dari menu <b>Kelola Akun</b>.
        Salah 3 kali berturut-turut = akun terkunci 5 menit.
      </p>
      <p class="masuk-owner"><?= h($yayasan) ?> &middot; <?= h(pengaturan('wilayah', 'Kota Depok')) ?></p>
    </div>
  </section>
</div>
</body>
</html>
