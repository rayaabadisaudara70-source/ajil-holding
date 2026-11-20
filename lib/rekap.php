<?php
declare(strict_types=1);

/** Daftar bulan pilihan (12 bulan terakhir, terbaru di depan). */
function periode_pilihan(int $jumlah = 14): array
{
    $out = [];
    for ($i = 0; $i < $jumlah; $i++) {
        $out[] = date('Y-m', strtotime('first day of -' . $i . ' month'));
    }
    return $out;
}

function hari_kerja_list(): array
{
    $raw = pengaturan('hari_kerja', '1,2,3,4,5,6');
    $out = [];
    foreach (explode(',', $raw) as $x) {
        $n = (int) trim($x);
        if ($n >= 1 && $n <= 7) {
            $out[] = $n;
        }
    }
    return $out ?: [1, 2, 3, 4, 5, 6];
}

/** Jumlah hari kerja dalam periode YYYY-MM sesuai pengaturan hari kerja. */
function hari_kerja_bulan(string $periode): int
{
    $ts = strtotime($periode . '-01');
    if ($ts === false) {
        return 0;
    }
    $hari = (int) date('t', $ts);
    $boleh = hari_kerja_list();
    $n = 0;
    for ($d = 1; $d <= $hari; $d++) {
        if (in_array((int) date('N', strtotime($periode . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT))), $boleh, true)) {
            $n++;
        }
    }
    return $n;
}

/** Rentang Senin–Minggu untuk sebuah tanggal (default hari ini). */
function rentang_minggu(?string $tanggal = null): array
{
    $ts = $tanggal ? strtotime($tanggal) : time();
    if ($ts === false) {
        $ts = time();
    }
    $n = (int) date('N', $ts);
    $senin = strtotime('-' . ($n - 1) . ' day', $ts);
    $minggu = strtotime('+' . (7 - $n) . ' day', $ts);
    return [date('Y-m-d', $senin), date('Y-m-d', $minggu)];
}

/**
 * Hitung gaji per karyawan untuk periode YYYY-MM.
 * Rumus: pokok + tunjangan makan + tunjangan transport − potongan tidak hadir + bonus hadir penuh.
 */
function gaji_hitung(string $periode): array
{
    $pdo = db();
    $hariKerja = hari_kerja_bulan($periode);
    $potonganHari = (int) pengaturan('potongan_per_hari', '50000');
    $bonusPenuh = (int) pengaturan('bonus_penuh', '200000');

    $st = $pdo->prepare('SELECT * FROM pengguna WHERE role = ? AND aktif = 1 ORDER BY usaha_nama, nama');
    $st->execute(['karyawan']);
    $rows = [];
    foreach ($st->fetchAll() as $k) {
        $hadir = (int) $pdo->query('SELECT COUNT(DISTINCT tanggal) FROM absensi
            WHERE pengguna_id = ' . (int) $k['id'] . " AND tipe = 'masuk' AND substr(tanggal,1,7) = " . $pdo->quote($periode) . ' AND contoh = 0')->fetchColumn();
        $hariAlpa = max(0, $hariKerja - $hadir);
        $potongan = $hariAlpa * $potonganHari;
        $bonus = ($hariKerja > 0 && $hadir >= $hariKerja) ? $bonusPenuh : 0;
        $tunjangan = (int) $k['tunjangan_makan'] + (int) $k['tunjangan_transport'];
        $pokok = (int) $k['pokok'];
        $total = $pokok + $tunjangan - $potongan + $bonus;

        $dok = (int) $pdo->query('SELECT COUNT(*) FROM dokumentasi WHERE pengguna_id = ' . (int) $k['id']
            . ' AND substr(tanggal,1,7) = ' . $pdo->quote($periode) . ' AND contoh = 0')->fetchColumn();
        $jual = (float) $pdo->query('SELECT COALESCE(SUM(total),0) FROM penjualan WHERE kasir_id = ' . (int) $k['id']
            . ' AND substr(tanggal,1,7) = ' . $pdo->quote($periode) . ' AND contoh = 0')->fetchColumn();

        $rows[] = [
            'id'          => (int) $k['id'],
            'nama'        => (string) $k['nama'],
            'usaha_nama'  => (string) $k['usaha_nama'],
            'jabatan'     => (string) $k['jabatan'],
            'pokok'       => $pokok,
            'tunjangan'   => $tunjangan,
            'hadir'       => $hadir,
            'hari_kerja'  => $hariKerja,
            'hari_alpa'   => $hariAlpa,
            'potongan'    => $potongan,
            'bonus'       => $bonus,
            'total'       => $total,
            'dokumentasi' => $dok,
            'penjualan'   => (int) $jual,
        ];
    }

    $total = 0;
    foreach ($rows as $r) {
        $total += $r['total'];
    }
    return ['periode' => $periode, 'hari_kerja' => $hariKerja, 'rows' => $rows, 'total' => $total];
}

/** Rekap aktivitas per karyawan untuk rentang tanggal tertentu. */
function rekap_aktivitas(string $mulai, string $selesai): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM pengguna WHERE role = ? AND aktif = 1 ORDER BY usaha_nama, nama');
    $st->execute(['karyawan']);
    $rows = [];
    foreach ($st->fetchAll() as $k) {
        $id = (int) $k['id'];
        $hadir = (int) $pdo->query('SELECT COUNT(DISTINCT tanggal) FROM absensi WHERE pengguna_id = ' . $id
            . " AND tipe='masuk' AND tanggal BETWEEN " . $pdo->quote($mulai) . ' AND ' . $pdo->quote($selesai)
            . ' AND contoh = 0')->fetchColumn();
        $dok = (int) $pdo->query('SELECT COUNT(*) FROM dokumentasi WHERE pengguna_id = ' . $id
            . ' AND tanggal BETWEEN ' . $pdo->quote($mulai) . ' AND ' . $pdo->quote($selesai) . ' AND contoh = 0')->fetchColumn();
        $jualN = (int) $pdo->query('SELECT COUNT(*) FROM penjualan WHERE kasir_id = ' . $id
            . ' AND tanggal BETWEEN ' . $pdo->quote($mulai) . ' AND ' . $pdo->quote($selesai) . ' AND contoh = 0')->fetchColumn();
        $jualRp = (int) $pdo->query('SELECT COALESCE(SUM(total),0) FROM penjualan WHERE kasir_id = ' . $id
            . ' AND tanggal BETWEEN ' . $pdo->quote($mulai) . ' AND ' . $pdo->quote($selesai) . ' AND contoh = 0')->fetchColumn();
        $jasaN = (int) $pdo->query('SELECT COUNT(*) FROM jasa WHERE petugas_id = ' . $id
            . ' AND tanggal BETWEEN ' . $pdo->quote($mulai) . ' AND ' . $pdo->quote($selesai) . ' AND contoh = 0')->fetchColumn();
        $rows[] = [
            'nama' => (string) $k['nama'],
            'usaha_nama' => (string) $k['usaha_nama'],
            'jabatan' => (string) $k['jabatan'],
            'hadir' => $hadir,
            'dokumentasi' => $dok,
            'jual_n' => $jualN,
            'jual_rp' => $jualRp,
            'jasa_n' => $jasaN,
        ];
    }
    return $rows;
}

function in_list(array $nilai): string
{
    return implode(',', array_fill(0, max(1, count($nilai)), '?'));
}

/** Ringkasan angka satu hari (bisa difilter per usaha). */
function ringkasan_hari(string $tanggal, string $usaha = ''): array
{
    $pdo = db();
    $args = [$tanggal];

    $filterUsaha = $usaha !== '' ? ' AND usaha_nama = ?' : '';
    if ($usaha !== '') {
        $args[] = $usaha;
    }
    $st = $pdo->prepare("SELECT
        SUM(CASE WHEN tipe = 'masuk' THEN 1 ELSE 0 END) AS masuk,
        SUM(CASE WHEN tipe = 'pulang' THEN 1 ELSE 0 END) AS pulang
        FROM absensi WHERE tanggal = ?" . $filterUsaha);
    $st->execute($args);
    $a = $st->fetch() ?: ['masuk' => 0, 'pulang' => 0];

    $argsDok = [$tanggal];
    if ($usaha !== '') {
        $argsDok[] = $usaha;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM dokumentasi WHERE tanggal = ?' . $filterUsaha);
    $st->execute($argsDok);
    $dok = (int) $st->fetchColumn();

    $toko = $usaha !== '' ? toko_untuk($usaha) : array_keys(toko_semua());
    $jualRp = 0;
    $jualN = 0;
    if ($toko) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(total),0) AS rp, COUNT(*) AS n FROM penjualan WHERE tanggal = ? AND toko IN (' . in_list($toko) . ')');
        $st->execute(array_merge([$tanggal], $toko));
        $r = $st->fetch() ?: ['rp' => 0, 'n' => 0];
        $jualRp = (int) $r['rp'];
        $jualN = (int) $r['n'];
    }

    $jenisJasa = $usaha !== '' ? [(string) (usaha_row($usaha)['jasa_jenis'] ?? '')] : array_keys(paket_jasa());
    $jenisJasa = array_values(array_filter($jenisJasa, fn($x) => $x !== ''));
    $jasaRp = 0;
    $jasaN = 0;
    if ($jenisJasa) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(biaya),0) AS rp, COUNT(*) AS n FROM jasa WHERE tanggal = ? AND jenis IN (' . in_list($jenisJasa) . ')');
        $st->execute(array_merge([$tanggal], $jenisJasa));
        $r = $st->fetch() ?: ['rp' => 0, 'n' => 0];
        $jasaRp = (int) $r['rp'];
        $jasaN = (int) $r['n'];
    }

    $stokKritis = 0;
    if ($toko) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM barang WHERE aktif = 1 AND stok <= 10 AND toko IN (' . in_list($toko) . ')');
        $st->execute($toko);
        $stokKritis = (int) $st->fetchColumn();
    }

    return [
        'masuk' => (int) $a['masuk'],
        'pulang' => (int) $a['pulang'],
        'dokumentasi' => $dok,
        'jual_rp' => $jualRp,
        'jual_n' => $jualN,
        'jasa_rp' => $jasaRp,
        'jasa_n' => $jasaN,
        'stok_kritis' => $stokKritis,
    ];
}
