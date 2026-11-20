<?php
declare(strict_types=1);

/** Daftar jenis dokumentasi live (sama dengan mockup). */
function jenis_dokumentasi(): array
{
    return [
        'Absensi Masuk',
        'Absensi Pulang',
        'Progres Pekerjaan',
        'Penjualan / Pelayanan',
        'Sebelum - Sesudah',
        'Kendala Lapangan',
    ];
}

function kategori_barang(): array
{
    return ['Plastik', 'Sembako', 'Kemasan', 'Umum', 'Lainnya'];
}

/** Toko/outlet per usaha (usaha dengan modul 'jual'). */
function toko_per_usaha(): array
{
    return [
        'Ajil Jaya Plastik' => ['Ajil Jaya Plastik Cab. GDC', 'Ajil Jaya Plastik Cab. BBM'],
        'Toko Sembako Ajil' => ['Toko Sembako Ajil'],
    ];
}

/** Semua toko (owner) — [nama_toko => nama_usaha]. */
function toko_semua(): array
{
    $out = [];
    foreach (toko_per_usaha() as $usaha => $list) {
        foreach ($list as $t) {
            $out[$t] = $usaha;
        }
    }
    return $out;
}

function toko_untuk(string $usaha_nama): array
{
    return toko_per_usaha()[$usaha_nama] ?? [];
}

/** Paket layanan per jenis jasa. */
function paket_jasa(): array
{
    return [
        'motor'   => ['Service Ringan', 'Service Berat', 'Ganti Oli', 'Tune Up', 'Ganti Ban', 'Rem', 'Kelistrikan'],
        'carwash' => ['Cuci Motor Biasa', 'Cuci Motor Premium', 'Cuci Mobil Biasa', 'Cuci Mobil Premium', 'Cuci + Wax', 'Cuci + Vacuum'],
        'laundry' => ['Kiloan Reguler', 'Kiloan Express', 'Satuan Selimut', 'Satuan Bed Cover', 'Satuan Jas', 'Cuci Sepatu'],
    ];
}

function jasa_label(string $jenis): string
{
    return ['motor' => 'Service Motor', 'carwash' => 'Carwash', 'laundry' => 'Laundry'][$jenis] ?? ucfirst($jenis);
}

function jasa_ikon(string $jenis): string
{
    return ['motor' => 'bike', 'carwash' => 'car', 'laundry' => 'shirt'][$jenis] ?? 'wrench';
}

/** Satuan qty per jenis jasa (laundry pakai kg, lainnya hitung unit). */
function jasa_satuan(string $jenis): string
{
    return $jenis === 'laundry' ? 'kg' : 'unit';
}

/** Status pekerjaan jasa. */
function status_jasa(): array
{
    return ['dikerjakan' => 'Dikerjakan', 'selesai' => 'Selesai'];
}

function badge_jasa(string $status): string
{
    return $status === 'selesai' ? badge('Selesai', 'hijau') : badge('Dikerjakan', 'kuning');
}

/** Daftar usaha (cache). */
function usaha_semua(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT * FROM usaha WHERE aktif = 1 ORDER BY urutan, nama')->fetchAll();
    }
    return $cache;
}

function usaha_nama_semua(): array
{
    return array_map(fn($u) => (string) $u['nama'], usaha_semua());
}

function usaha_row(string $nama): ?array
{
    foreach (usaha_semua() as $u) {
        if ($u['nama'] === $nama) {
            return $u;
        }
    }
    return null;
}

function usaha_modul(string $nama): string
{
    $u = usaha_row($nama);
    return $u ? (string) $u['modul'] : 'absensi';
}

/** Modul yang boleh dibuka pengguna. Owner semua. */
function modul_boleh(array $u, string $modul): bool
{
    if (($u['role'] ?? '') === 'owner') {
        return true;
    }
    $usaha = (string) ($u['usaha_nama'] ?? '');
    if ($modul === 'absensi' || $modul === 'dokumentasi' || $modul === 'sop' || $modul === 'akun' || $modul === 'dashboard') {
        return true;
    }
    $m = usaha_modul($usaha);
    if ($modul === 'jual' || $modul === 'barang') {
        return $m === 'jual';
    }
    if ($modul === 'jasa') {
        return $m === 'jasa';
    }
    if ($modul === 'proyek') {
        return $m === 'proyek';
    }
    return false;
}

/** Jenis jasa yang boleh dibuka pengguna (karyawan hanya usahanya). */
function jasa_boleh(array $u): array
{
    if (($u['role'] ?? '') === 'owner') {
        return array_keys(paket_jasa());
    }
    $row = usaha_row((string) ($u['usaha_nama'] ?? ''));
    if ($row && $row['modul'] === 'jasa' && ($row['jasa_jenis'] ?? '') !== '') {
        return [(string) $row['jasa_jenis']];
    }
    return [];
}

/** SOP bawaan (dipakai seed awal & tombol "kembalikan bawaan"). */
function sop_bawaan(): array
{
    return [
        ['service', 'Ajil Jaya Motor — Service Motor', 'Ajil Jaya Motor',
            "Terima motor & foto kondisi awal\nCek oli, rem, rantai, ban, lampu, klakson, aki\nKonfirmasi biaya sebelum bongkar\nKerjakan sesuai paket & standar\nTest ride & cek ulang\nBersihkan, foto hasil, jelaskan perawatan\nGaransi service 7 hari"],
        ['carwash', 'Ajil Carwash', 'Ajil Carwash',
            "Sambut & catat baret, foto awal\nBilas awal buang debu/pasir\nCuci body atas ke bawah sampo khusus\nCuci velg, kolong, ban terpisah\nBilas sampai tidak ada busa\nKeringkan lap microfiber\nVacuum / semir ban, foto hasil\nQC cek ulang sebelum serahkan"],
        ['laundry', 'Cemerlang Laundry', 'Cemerlang Laundry',
            "Terima & timbang di depan pelanggan, cek kantong & noda\nPisahkan putih/berwarna/halus\nCuci sesuai jenis kain\nJemur / dryer sesuai standar\nSetrika rapi, cek pcs\nPacking + label, foto hasil\nSerahkan cek bersama pelanggan"],
        ['sembako', 'Toko Sembako Ajil', 'Toko Sembako Ajil',
            "Buka toko, cek kebersihan & stok kritis\nCek kadaluarsa, FIFO lama dulu\nLayani ramah, hitung kembalian di depan pelanggan\nCatat penjualan + bon\nCek stok sore, order besok\nRekap kas harian + foto laporan\nTutup toko, amankan kas"],
        ['plastik', 'Ajil Jaya Plastik — Cab. GDC & BBM', 'Ajil Jaya Plastik',
            "Buka toko, cek kebersihan & stok display\nHitung fisik stok awal, cocokkan dengan BIG DATA\nLayani pembeli, hitung total di depan pembeli\nCatat penjualan di menu Penjualan (stok otomatis berkurang)\nFoto bukti pelayanan / nota ke Dokumentasi Live\nRekap kas & setor ke owner\nTutup toko, amankan uang & kunci"],
        ['property', 'Property Development Konstruksi', 'Property Development Konstruksi',
            "Cek gambar kerja & RAB\nBriefing pagi, keselamatan kerja\nCek material masuk cocokkan nota\nDokumentasi foto pagi/siang/sore\nQC ukuran & struktur\nLaporan sore ke Owner\nKeamanan lokasi, kunci gudang\nSerah terima tahap"],
    ];
}
