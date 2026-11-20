<?php
declare(strict_types=1);

function ikon(string $nama): string
{
    static $p = [
        'grid'    => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
        'camera'  => '<path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.4"/>',
        'image'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="M4 17l5-4 4 3 3-2 4 3"/>',
        'cart'    => '<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h3l2.6 11.4h12L22 7H6"/>',
        'box'     => '<path d="M12 3l9 4.5v9L12 21 3 16.5v-9z"/><path d="M3 7.5l9 4.5 9-4.5M12 12v9"/>',
        'wrench'  => '<path d="M15 3a5 5 0 0 0-4.5 7L4 16.5V20h3.5L14 13.5A5 5 0 1 0 15 3z"/>',
        'hammer'  => '<path d="M14 3l7 7-3 3-7-7z"/><path d="M11 9l-7 7 3 3 7-7"/>',
        'book'    => '<path d="M4 4h7a3 3 0 0 1 3 3v13a2.5 2.5 0 0 0-2.5-2.5H4z"/><path d="M20 4h-3a3 3 0 0 0-3 3v13a2.5 2.5 0 0 1 2.5-2.5H20z"/>',
        'wallet'  => '<path d="M3 7h15a3 3 0 0 1 3 3v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 7V6a2 2 0 0 1 2-2h11"/><circle cx="17" cy="13" r="1.2"/>',
        'users'   => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M17.5 20a6 6 0 0 0-2-4.4"/>',
        'gear'    => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'logout'  => '<path d="M15 3h5v18h-5"/><path d="M10 17l-5-5 5-5M5 12h10"/>',
        'list'    => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/>',
        'check'   => '<path d="M20 6L9 17l-5-5"/>',
        'x'       => '<path d="M6 6l12 12M18 6L6 18"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'bike'    => '<circle cx="6" cy="17" r="3.2"/><circle cx="18" cy="17" r="3.2"/><path d="M9 17l3-8h4l2 5M9 9h4"/>',
        'car'     => '<path d="M5 16h14M4 16l1.5-5.5A2 2 0 0 1 7.4 9h9.2a2 2 0 0 1 1.9 1.5L20 16v4h-3v-2H7v2H4z"/>',
        'shirt'   => '<path d="M8 3l4 2 4-2 5 4-3 3v9H6v-9L3 7z"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'pin'     => '<path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'download' => '<path d="M12 3v12M7 11l5 5 5-5"/><path d="M4 21h16"/>',
        'edit'    => '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M14 4l4 4"/>',
        'shield'  => '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/>',
        'send'    => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'rupiah'  => '<circle cx="12" cy="12" r="9"/><path d="M9 7h4a3 3 0 0 1 0 6H9zM9 13h6M9 17h6"/>',
        'print'   => '<path d="M6 9V3h12v6"/><path d="M6 18H4v-6h16v6h-2"/><path d="M6 14h12v7H6z"/>',
    ];
    $isi = $p[$nama] ?? $p['grid'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true">' . $isi . '</svg>';
}

function logo_usaha(string $kelas = 'brand-logo', int $ukuran = 40): string
{
    return '<div class="' . h($kelas) . '"><img src="assets/logo.svg" width="' . $ukuran . '" height="' . $ukuran
        . '" alt="AJIL.JR Mini Holding"></div>';
}

/** Struktur menu per peran. */
function menu_app(): array
{
    $u = user_login();
    if ($u === null) {
        return [];
    }
    $owner = $u['role'] === 'owner';

    $utama = [
        ['index.php', 'Dashboard', 'grid'],
        ['absensi.php', 'Absensi', 'camera'],
        ['dokumentasi.php', 'Dokumentasi Live', 'image'],
        ['sop.php', 'SOP Management', 'book'],
    ];

    $pelaporan = [];
    if (modul_boleh($u, 'jual')) {
        $pelaporan[] = ['penjualan.php', $owner ? 'Penjualan' : 'Update Harian', 'cart'];
        $pelaporan[] = ['barang.php', 'BIG DATA Barang', 'box'];
    }
    if (modul_boleh($u, 'jasa')) {
        $pelaporan[] = ['jasa.php', 'Service & Jasa', 'wrench'];
    }
    if (modul_boleh($u, 'proyek')) {
        $pelaporan[] = ['property.php', 'Proyek Property', 'hammer'];
    }

    $grup = [['Utama', $utama]];
    if ($pelaporan) {
        $grup[] = ['Pelaporan Harian', $pelaporan];
    }
    if ($owner) {
        $grup[] = ['Owner', [
            ['gaji.php', 'Gaji & Rekap', 'wallet'],
            ['pengguna.php', 'Kelola Akun', 'users'],
            ['pengaturan.php', 'Pengaturan', 'gear'],
        ]];
    }
    $grup[] = ['Akun', [['akun.php', 'Akun Saya', 'user']]];

    return $grup;
}

function halaman_awal(string $judul, string $aktif = '', string $aksi_header = ''): void
{
    $u = user_login();
    $namaUsaha = pengaturan('nama_usaha', 'AJIL.JR MINI HOLDING');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($judul) ?> &middot; <?= h($namaUsaha) ?></title>
<meta name="theme-color" content="#0A1F44">
<link rel="icon" href="assets/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= (int) @filemtime(__DIR__ . '/../assets/app.css') ?>">
</head>
<body>
<div class="app">
  <aside class="sisi" id="sisi">
    <div class="merek">
      <?= logo_usaha('merek-logo') ?>
      <div class="merek-teks">
        <div class="merek-nama">AJIL.JR</div>
        <div class="merek-sub">MINI HOLDING</div>
      </div>
    </div>
    <nav class="nav">
      <?php foreach (menu_app() as [$namaGrup, $item]): ?>
        <div class="nav-grup"><?= h($namaGrup) ?></div>
        <?php foreach ($item as [$href, $label, $ic]): ?>
          <a class="nav-item <?= $aktif === $href ? 'aktif' : '' ?>" href="<?= h($href) ?>">
            <?= ikon($ic) ?><span><?= h($label) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sisi-kaki">
      <div class="user-chip">
        <div class="avatar"><?= h(strtoupper(substr((string) ($u['nama'] ?? '?'), 0, 1))) ?></div>
        <div class="user-info">
          <div class="user-nama"><?= h($u['nama'] ?? '') ?></div>
          <div class="user-role"><?= h(role_label((string) ($u['role'] ?? ''))) ?><?= ($u['usaha_nama'] ?? '') !== '' ? ' &middot; ' . h($u['usaha_nama']) : '' ?></div>
        </div>
      </div>
      <a class="nav-item keluar" href="logout.php"><?= ikon('logout') ?><span>Keluar</span></a>
    </div>
  </aside>

  <main class="utama">
    <header class="topbar">
      <button class="btn-ikon menu-toggle" id="menuToggle" aria-label="Buka menu"><?= ikon('list') ?></button>
      <h1 class="judul-halaman"><?= h($judul) ?></h1>
      <div class="topbar-aksi"><?= $aksi_header ?></div>
    </header>
    <div class="konten">
      <?php foreach (flash_ambil() as $f): ?>
        <div class="alert alert-<?= h($f['tipe']) ?>"><?= h($f['pesan']) ?></div>
      <?php endforeach; ?>
    <?php
}

function halaman_akhir(): void
{
    ?>
      <footer class="kaki-app">
        <?= h(pengaturan('nama_usaha', 'AJIL.JR MINI HOLDING')) ?> &middot;
        <?= h(pengaturan('yayasan', 'Koperasi Jasa Usaha Raya Abadi Saudara')) ?> &middot;
        <?= h(pengaturan('wilayah', 'Kota Depok')) ?>
      </footer>
    </div>
  </main>
</div>
<div class="tirai" id="tirai"></div>
<script src="assets/app.js?v=<?= (int) @filemtime(__DIR__ . '/../assets/app.js') ?>"></script>
</body>
</html>
<?php
}
