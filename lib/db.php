<?php
declare(strict_types=1);

/**
 * Koneksi SQLite + bootstrap skema (idempotent, jalan tiap request).
 * Seluruh bootstrap dibungkus SATU transaksi supaya tidak bayar fsync per statement.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Path DB bisa di-override lewat env (dipakai skrip uji agar tidak menyentuh data produksi).
    $file = getenv('APP_DB_PATH') ?: dirname(__DIR__) . '/data.sqlite';
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');

    db_bootstrap($pdo);

    return $pdo;
}

function db_bootstrap(PDO $pdo): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS usaha (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL UNIQUE,
            modul TEXT NOT NULL DEFAULT 'absensi',
            jasa_jenis TEXT NOT NULL DEFAULT '',
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pengguna (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'karyawan',
            usaha_id INTEGER NULL,
            usaha_nama TEXT NOT NULL DEFAULT '',
            jabatan TEXT NOT NULL DEFAULT '',
            hp TEXT NOT NULL DEFAULT '',
            pokok INTEGER NOT NULL DEFAULT 0,
            tunjangan_makan INTEGER NOT NULL DEFAULT 0,
            tunjangan_transport INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            gagal INTEGER NOT NULL DEFAULT 0,
            terkunci_sampai TEXT NULL,
            dibuat TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS absensi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT NOT NULL,
            pengguna_id INTEGER NOT NULL,
            nama TEXT NOT NULL DEFAULT '',
            usaha_nama TEXT NOT NULL DEFAULT '',
            tipe TEXT NOT NULL DEFAULT 'masuk',
            waktu TEXT NOT NULL,
            foto TEXT NOT NULL DEFAULT '',
            lat TEXT NOT NULL DEFAULT '',
            lng TEXT NOT NULL DEFAULT '',
            catatan TEXT NOT NULL DEFAULT '',
            telat_menit INTEGER NOT NULL DEFAULT 0,
            contoh INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT NOT NULL,
            UNIQUE(pengguna_id, tanggal, tipe)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS dokumentasi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT NOT NULL,
            waktu TEXT NOT NULL,
            pengguna_id INTEGER NULL,
            nama TEXT NOT NULL DEFAULT '',
            usaha_nama TEXT NOT NULL DEFAULT '',
            jenis TEXT NOT NULL DEFAULT 'Progres Pekerjaan',
            judul TEXT NOT NULL DEFAULT '',
            keterangan TEXT NOT NULL DEFAULT '',
            media TEXT NOT NULL DEFAULT 'gambar',
            url TEXT NOT NULL DEFAULT '',
            contoh INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS barang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            kategori TEXT NOT NULL DEFAULT 'Umum',
            satuan TEXT NOT NULL DEFAULT 'pcs',
            harga INTEGER NOT NULL DEFAULT 0,
            modal INTEGER NOT NULL DEFAULT 0,
            stok REAL NOT NULL DEFAULT 0,
            toko TEXT NOT NULL DEFAULT '',
            foto TEXT NOT NULL DEFAULT '',
            aktif INTEGER NOT NULL DEFAULT 1,
            contoh INTEGER NOT NULL DEFAULT 0,
            oleh TEXT NOT NULL DEFAULT '',
            dibuat TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS penjualan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT NOT NULL,
            waktu TEXT NOT NULL,
            toko TEXT NOT NULL DEFAULT '',
            barang_id INTEGER NULL,
            barang_nama TEXT NOT NULL DEFAULT '',
            qty REAL NOT NULL DEFAULT 0,
            harga INTEGER NOT NULL DEFAULT 0,
            total INTEGER NOT NULL DEFAULT 0,
            kasir_id INTEGER NULL,
            kasir_nama TEXT NOT NULL DEFAULT '',
            catatan TEXT NOT NULL DEFAULT '',
            contoh INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS jasa (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            jenis TEXT NOT NULL DEFAULT 'motor',
            tanggal TEXT NOT NULL,
            waktu TEXT NOT NULL,
            pelanggan TEXT NOT NULL DEFAULT '',
            plat TEXT NOT NULL DEFAULT '',
            paket TEXT NOT NULL DEFAULT '',
            qty REAL NOT NULL DEFAULT 0,
            biaya INTEGER NOT NULL DEFAULT 0,
            petugas_id INTEGER NULL,
            petugas_nama TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'dikerjakan',
            catatan TEXT NOT NULL DEFAULT '',
            contoh INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS proyek (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT NOT NULL,
            waktu TEXT NOT NULL,
            nama TEXT NOT NULL DEFAULT '',
            lokasi TEXT NOT NULL DEFAULT '',
            mandor_id INTEGER NULL,
            mandor_nama TEXT NOT NULL DEFAULT '',
            progres INTEGER NOT NULL DEFAULT 0,
            biaya INTEGER NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT '',
            contoh INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT NOT NULL
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sop (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kunci TEXT NOT NULL UNIQUE,
            judul TEXT NOT NULL,
            usaha_nama TEXT NOT NULL DEFAULT '',
            langkah TEXT NOT NULL DEFAULT '',
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            diperbarui TEXT NOT NULL DEFAULT ''
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT ''
        )");

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_absensi_tgl ON absensi (tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_absensi_pengguna ON absensi (pengguna_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_penjualan_tgl ON penjualan (tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_jasa_tgl ON jasa (tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_dok_tgl ON dokumentasi (tanggal)');

        db_seed($pdo);

        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

/** Seed awal — semua idempotent, aman dipanggil tiap request. */
function db_seed(PDO $pdo): void
{
    $usaha = [
        ['Ajil Jaya Plastik', 'jual', '', 1],
        ['Toko Sembako Ajil', 'jual', '', 2],
        ['Ajil Jaya Motor', 'jasa', 'motor', 3],
        ['Ajil Carwash', 'jasa', 'carwash', 4],
        ['Cemerlang Laundry', 'jasa', 'laundry', 5],
        ['Property Development Konstruksi', 'proyek', '', 6],
        ['Kost / Ruko / Hotel - Absensi Saja', 'absensi', '', 7],
    ];
    $st = $pdo->prepare('INSERT OR IGNORE INTO usaha (nama, modul, jasa_jenis, urutan) VALUES (?, ?, ?, ?)');
    foreach ($usaha as $u) {
        $st->execute($u);
    }

    $setelan = [
        'nama_usaha'            => 'AJIL.JR MINI HOLDING',
        'tagline'               => 'SOP Absensi & Pelaporan Management',
        'yayasan'               => 'Koperasi Jasa Usaha Raya Abadi Saudara',
        'wilayah'               => 'Kota Depok',
        'telepon'               => '',
        'alamat'                => '',
        'jam_masuk'             => '08:00',
        'toleransi_menit'       => '10',
        'potongan_per_hari'     => '50000',
        'bonus_penuh'           => '200000',
        'hari_kerja'            => '1,2,3,4,5,6',
        'footer_laporan'        => 'Dokumen internal AJIL.JR Mini Holding — tidak untuk disebarluaskan.',
        'seed_contoh'           => '',
    ];
    $st = $pdo->prepare('INSERT OR IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)');
    foreach ($setelan as $k => $v) {
        $st->execute([$k, $v]);
    }

    $jml = (int) $pdo->query('SELECT COUNT(*) FROM pengguna')->fetchColumn();
    if ($jml === 0) {
        $daftar = [
            ['Owner', 'owner', 'owner', '', '', 0, 0, 0],
            ['Tekat - Mandor Property', 'tekat', 'karyawan', 'Property Development Konstruksi', 'Mandor', 3500000, 450000, 400000],
            ['Selamet - Tukang', 'selamet', 'karyawan', 'Property Development Konstruksi', 'Tukang', 2500000, 400000, 300000],
            ['Ila - Cab. BBM', 'ila', 'karyawan', 'Ajil Jaya Plastik', 'Kasir BBM', 2000000, 300000, 200000],
            ['Rani - Cab. GDC', 'rani', 'karyawan', 'Ajil Jaya Plastik', 'Kasir GDC', 2000000, 300000, 200000],
            ['Abeng', 'abeng', 'karyawan', 'Ajil Jaya Motor', 'Mekanik', 2800000, 350000, 300000],
            ['Budi - Carwash', 'budi', 'karyawan', 'Ajil Carwash', 'Petugas Carwash', 1900000, 300000, 200000],
            ['Siti - Laundry', 'siti', 'karyawan', 'Cemerlang Laundry', 'Petugas Laundry', 1900000, 300000, 200000],
            ['Dewi - Sembako', 'dewi', 'karyawan', 'Toko Sembako Ajil', 'Kasir Sembako', 1900000, 300000, 200000],
        ];
        $hash = password_hash('ajiljr1992', PASSWORD_DEFAULT);
        $now  = date('Y-m-d H:i:s');
        $ins  = $pdo->prepare('INSERT INTO pengguna
            (nama, username, password, role, usaha_nama, jabatan, pokok, tunjangan_makan, tunjangan_transport, dibuat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($daftar as $d) {
            [$nama, $user, $role, $uNama, $jabatan, $pokok, $makan, $transport] = $d;
            $ins->execute([$nama, $user, $hash, $role, $uNama, $jabatan, $pokok, $makan, $transport, $now]);
        }
        // tautkan usaha_id
        $pdo->exec("UPDATE pengguna SET usaha_id = (SELECT id FROM usaha WHERE usaha.nama = pengguna.usaha_nama)
                    WHERE usaha_nama <> ''");
    }

    $jmlSop = (int) $pdo->query('SELECT COUNT(*) FROM sop')->fetchColumn();
    if ($jmlSop === 0) {
        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare('INSERT INTO sop (kunci, judul, usaha_nama, langkah, urutan, diperbarui) VALUES (?, ?, ?, ?, ?, ?)');
        $urut = 1;
        foreach (sop_bawaan() as $s) {
            $ins->execute([$s[0], $s[1], $s[2], $s[3], $urut++, $now]);
        }
    }

    // Data contoh hanya SEKALI (ditandai seed_contoh + dijaga NOT EXISTS) —
    // tahan terhadap beberapa request pertama yang datang bersamaan.
    if (pengaturan('seed_contoh') === '') {
        pengaturan_set('seed_contoh', date('Y-m-d'));
        $waktu = date('Y-m-d H:i:s');
        $now = date('Y-m-d');

        if ((int) $pdo->query('SELECT COUNT(*) FROM dokumentasi WHERE contoh = 1')->fetchColumn() === 0) {
            $pdo->prepare('INSERT INTO dokumentasi (tanggal, waktu, pengguna_id, nama, usaha_nama, jenis, judul, keterangan, media, url, contoh, dibuat)
                VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 1, ?)')
                ->execute([$now, $waktu, 'Panitia', 'AJIL.JR MINI HOLDING', 'Absensi Masuk',
                    'Contoh — contoh tampilan Dokumentasi Live',
                    'Ini contoh kartu. Hapus semua data contoh lewat menu Pengaturan setelah aplikasi dipakai.',
                    'gambar', 'assets/contoh-dokumentasi.svg', $waktu]);
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM proyek WHERE contoh = 1')->fetchColumn() === 0) {
            $pdo->prepare('INSERT INTO proyek (tanggal, waktu, nama, lokasi, mandor_id, mandor_nama, progres, biaya, keterangan, contoh, dibuat)
                VALUES (?, ?, ?, ?, NULL, ?, 0, 0, ?, 1, ?)')
                ->execute([$now, $waktu, 'Contoh — Pembangunan Kost 10 Pintu', 'Kota Depok', 'Tekat - Mandor Property',
                    'Baris contoh: laporan progres diisi mandor setiap hari.', $waktu]);
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM barang WHERE contoh = 1')->fetchColumn() === 0) {
            $barang = [
                ['Kantong Plastik', 'Plastik', 'pak', 15000, 9000, 120, 'Ajil Jaya Plastik Cab. GDC'],
                ['Cup Plastik', 'Plastik', 'pack', 25000, 16000, 80, 'Ajil Jaya Plastik Cab. BBM'],
                ['Sedotan', 'Plastik', 'pack', 12000, 7000, 200, 'Ajil Jaya Plastik Cab. GDC'],
                ['Beras 5kg', 'Sembako', 'karung', 65000, 58000, 40, 'Toko Sembako Ajil'],
                ['Minyak Goreng 1L', 'Sembako', 'botol', 18000, 15500, 60, 'Toko Sembako Ajil'],
                ['Telur 1kg', 'Sembako', 'kg', 28000, 24000, 30, 'Toko Sembako Ajil'],
            ];
            $ins = $pdo->prepare('INSERT INTO barang (nama, kategori, satuan, harga, modal, stok, toko, contoh, oleh, dibuat)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
            foreach ($barang as $b) {
                $ins->execute([$b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], 'Owner', $waktu]);
            }
        }
    }
}

function pengaturan_semua(): array
{
    if (!isset($GLOBALS['__pengaturan']) || !is_array($GLOBALS['__pengaturan'])) {
        $GLOBALS['__pengaturan'] = [];
        foreach (db()->query('SELECT kunci, nilai FROM pengaturan') as $r) {
            $GLOBALS['__pengaturan'][$r['kunci']] = $r['nilai'];
        }
    }
    return $GLOBALS['__pengaturan'];
}

function pengaturan(string $kunci, string $default = ''): string
{
    $all = pengaturan_semua();
    return array_key_exists($kunci, $all) ? (string) $all[$kunci] : $default;
}

function pengaturan_set(string $kunci, string $nilai): void
{
    $st = db()->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
        ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai');
    $st->execute([$kunci, $nilai]);
    unset($GLOBALS['__pengaturan']);
    $GLOBALS['__pengaturan'] = null;
}
