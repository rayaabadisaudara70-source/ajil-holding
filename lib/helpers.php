<?php
declare(strict_types=1);

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function angka($n, int $desimal = 0): string
{
    return number_format((float) $n, $desimal, ',', '.');
}

$GLOBALS['__hari'] = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
];
$GLOBALS['__bulan'] = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

function tanggal_id(?string $ymd, bool $dengan_hari = false): string
{
    if (!$ymd) {
        return '-';
    }
    $ts = strtotime(substr((string) $ymd, 0, 10));
    if ($ts === false) {
        return '-';
    }
    $hari = $dengan_hari ? $GLOBALS['__hari'][date('l', $ts)] . ', ' : '';
    return $hari . date('j', $ts) . ' ' . $GLOBALS['__bulan'][(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function tanggal_pendek(?string $ymd): string
{
    if (!$ymd) {
        return '-';
    }
    $ts = strtotime(substr((string) $ymd, 0, 10));
    return $ts ? date('d/m/Y', $ts) : '-';
}

function jam(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime((string) $dt);
    return $ts ? date('H:i', $ts) : '-';
}

function waktu_lengkap(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime((string) $dt);
    return $ts ? date('d/m/Y H:i', $ts) . ' WIB' : '-';
}

function bulan_label(string $periode): string
{
    $p = explode('-', $periode);
    $n = (int) ($p[1] ?? 0);
    return ($GLOBALS['__bulan'][$n] ?? '-') . ' ' . ($p[0] ?? '');
}

function redirect(string $tujuan): void
{
    header('Location: ' . $tujuan);
    exit;
}

function input(string $kunci, string $default = ''): string
{
    $v = $_POST[$kunci] ?? $_GET[$kunci] ?? $default;
    if (is_array($v)) {
        return $default;
    }
    return trim((string) $v);
}

function input_int(string $kunci, int $default = 0): int
{
    $v = str_replace(['.', ',', ' ', 'Rp'], '', input($kunci, (string) $default));
    return $v === '' ? $default : (int) $v;
}

function input_float(string $kunci, float $default = 0.0): float
{
    $v = str_replace([' ', 'Rp'], '', input($kunci, (string) $default));
    $v = str_replace(',', '.', $v);
    return $v === '' ? $default : (float) $v;
}

function checkbox(string $kunci): bool
{
    return isset($_POST[$kunci]) && (string) $_POST[$kunci] !== '0';
}

function flash_set(string $tipe, string $pesan): void
{
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function flash_ambil(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $t = $_POST['_csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t);
}

function csrf_cek(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/** Batas ukuran unggahan (byte). */
function batas_upload(string $jenis): int
{
    return $jenis === 'video' ? 20 * 1024 * 1024 : 5 * 1024 * 1024;
}

function ekstensi_boleh(string $jenis): array
{
    return $jenis === 'video'
        ? ['mp4', 'webm', 'mov', 'm4v']
        : ['jpg', 'jpeg', 'png', 'webp', 'gif'];
}

/** Pesan galat $_FILES berdasarkan kode error PHP. */
function galat_upload(int $kode, string $jenis): string
{
    $label = $jenis === 'video' ? 'video (maks 20 MB)' : 'foto (maks 5 MB)';
    $map = [
        UPLOAD_ERR_INI_SIZE   => 'File terlalu besar untuk server ini. Unggah ' . $label . '.',
        UPLOAD_ERR_FORM_SIZE  => 'File terlalu besar. Unggah ' . $label . '.',
        UPLOAD_ERR_PARTIAL    => 'Unggahan terputus. Coba lagi.',
        UPLOAD_ERR_NO_FILE    => 'Belum ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server tidak punya folder sementara. Hubungi admin.',
        UPLOAD_ERR_CANT_WRITE => 'Server gagal menyimpan file. Coba lagi.',
        UPLOAD_ERR_EXTENSION  => 'Unggahan dihentikan oleh ekstensi PHP.',
    ];
    return $map[$kode] ?? 'Gagal mengunggah file.';
}

/**
 * Simpan file unggahan. Produksi: lewat proxy media platform (permanen, CDN).
 * Sebelum app dipublikasikan (token belum ada) file disimpan di folder uploads/ lokal.
 *
 * @return array{ok:bool,url?:string,error?:string}
 */
function simpan_media(string $field, string $jenis = 'gambar'): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return ['ok' => false, 'error' => 'Belum ada file yang dipilih.'];
    }
    $f = $_FILES[$field];
    if (is_array($f['name'])) {
        return ['ok' => false, 'error' => 'Hanya boleh satu file per unggahan.'];
    }
    $kode = (int) $f['error'];
    if ($kode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => galat_upload($kode, $jenis === 'video' ? 'video' : 'foto')];
    }
    $ukuran = (int) $f['size'];
    if ($ukuran <= 0) {
        return ['ok' => false, 'error' => 'File kosong.'];
    }
    if ($ukuran > batas_upload($jenis === 'video' ? 'video' : 'foto')) {
        return ['ok' => false, 'error' => $jenis === 'video'
            ? 'Video terlalu besar (maks 20 MB). Rekam lebih singkat.'
            : 'Foto terlalu besar (maks 5 MB). Kompres dulu.'];
    }
    $nama  = (string) $f['name'];
    $ext   = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
    $boleh = ekstensi_boleh($jenis === 'video' ? 'video' : 'foto');
    if (!in_array($ext, $boleh, true)) {
        return ['ok' => false, 'error' => 'Format .' . $ext . ' tidak didukung. Boleh: ' . implode(', ', $boleh) . '.'];
    }
    $tmp = (string) $f['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'File sementara tidak ditemukan.'];
    }

    $tokenFile = dirname(__DIR__) . '/.vibecoder-media-token';
    if (is_file($tokenFile)) {
        $namaAman = preg_replace('/[^A-Za-z0-9._-]/', '-', pathinfo($nama, PATHINFO_FILENAME)) ?: 'file';
        return upload_media($tmp, $namaAman . '.' . $ext);
    }

    // Fallback pengembangan (belum dipublikasikan): simpan lokal di folder app.
    $dir = dirname(__DIR__) . '/uploads/' . date('Ym');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Folder uploads tidak bisa dibuat.'];
    }
    $baru = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!@move_uploaded_file($tmp, $dir . '/' . $baru)) {
        return ['ok' => false, 'error' => 'Gagal menyimpan file ke server.'];
    }
    return ['ok' => true, 'url' => 'uploads/' . date('Ym') . '/' . $baru];
}

/** Upload ke proxy media platform (server-side saja, token tidak pernah ke browser). */
function upload_media(string $tmpPath, string $filename): array
{
    $tokenFile = dirname(__DIR__) . '/.vibecoder-media-token';
    if (!is_file($tokenFile)) {
        return ['ok' => false, 'error' => 'Penyimpanan media belum aktif.'];
    }
    $token = trim((string) file_get_contents($tokenFile));
    if ($token === '') {
        return ['ok' => false, 'error' => 'Token penyimpanan media kosong.'];
    }
    $body = file_get_contents($tmpPath);
    if ($body === false) {
        return ['ok' => false, 'error' => 'Gagal membaca file unggahan.'];
    }

    $url = 'http://127.0.0.1:4310/api/app-media/upload?filename=' . rawurlencode($filename);
    $headers = ['X-App-Media-Token: ' . $token, 'Content-Type: application/octet-stream'];
    $raw = null;
    $code = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 90,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'error' => 'Gagal menghubungi penyimpanan media: ' . $err];
        }
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'timeout'       => 90,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($raw === false) {
            return ['ok' => false, 'error' => 'Gagal menghubungi penyimpanan media.'];
        }
    }

    $json = json_decode((string) $raw, true);
    if ($code >= 400 || !is_array($json)) {
        $pesan = is_array($json) && isset($json['error']) ? (string) $json['error'] : 'Upload ditolak (kode ' . $code . ').';
        if ($code === 403) {
            $pesan = 'Kuota penyimpanan upload sudah penuh. Hapus file lama atau upgrade paket penyimpanan.';
        }
        return ['ok' => false, 'error' => $pesan];
    }
    if (empty($json['ok']) || empty($json['url'])) {
        return ['ok' => false, 'error' => 'Respons penyimpanan media tidak dikenali.'];
    }
    return ['ok' => true, 'url' => (string) $json['url']];
}

/** URL aman untuk dipakai di atribut src/href (relatif maupun http/https). */
function aman_url(string $url): string
{
    if ($url === '') {
        return '';
    }
    return preg_match('#^(https?://|uploads/|assets/)#i', $url) === 1 ? $url : '';
}

function badge(string $teks, string $warna = 'emas'): string
{
    return '<span class="badge badge-' . h($warna) . '">' . h($teks) . '</span>';
}
