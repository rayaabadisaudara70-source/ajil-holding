<?php
declare(strict_types=1);

function user_login(): ?array
{
    static $u = null;
    if (empty($_SESSION['uid'])) {
        return null;
    }
    if ($u === null) {
        $st = db()->prepare('SELECT * FROM pengguna WHERE id = ? AND aktif = 1');
        $st->execute([(int) $_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if ($u === null) {
            unset($_SESSION['uid']);
        }
    }
    return $u ?: null;
}

function wajib_login(): array
{
    $u = user_login();
    if ($u === null) {
        redirect('login.php');
    }
    return $u;
}

function is_owner(): bool
{
    $u = user_login();
    return $u !== null && $u['role'] === 'owner';
}

function wajib_owner(): array
{
    $u = wajib_login();
    if ($u['role'] !== 'owner') {
        flash_set('error', 'Halaman ini khusus Owner.');
        redirect('index.php');
    }
    return $u;
}

/** Tolak bila peran pengguna tidak berhak membuka modul. */
function wajib_modul(string $modul): array
{
    $u = wajib_login();
    if (!modul_boleh($u, $modul)) {
        flash_set('error', 'Modul ini tidak termasuk wilayah kerja akun Anda.');
        redirect('index.php');
    }
    return $u;
}

function role_label(string $role): string
{
    return $role === 'owner' ? 'Owner' : 'Karyawan';
}

/** True bila akun masih memakai password bawaan (untuk pengingat di dashboard). */
function password_bawaan(array $u): bool
{
    return password_verify('ajiljr1992', (string) $u['password']);
}

/**
 * Coba login. Kunci 5 menit setelah 3 kali salah (per akun).
 * @return array{ok:bool,pesan?:string}
 */
function login_coba(string $username, string $password): array
{
    $pdo = db();
    $username = strtolower(trim($username));
    $st = $pdo->prepare('SELECT * FROM pengguna WHERE lower(username) = ? AND aktif = 1');
    $st->execute([$username]);
    $u = $st->fetch();
    $now = date('Y-m-d H:i:s');

    if ($u) {
        if (!empty($u['terkunci_sampai']) && (string) $u['terkunci_sampai'] > $now) {
            $sisa = max(1, (int) ceil((strtotime((string) $u['terkunci_sampai']) - time()) / 60));
            return ['ok' => false, 'pesan' => 'Akun terkunci sementara. Coba lagi dalam ' . $sisa . ' menit.'];
        }
        if (password_verify($password, (string) $u['password'])) {
            $pdo->prepare('UPDATE pengguna SET gagal = 0, terkunci_sampai = NULL WHERE id = ?')->execute([(int) $u['id']]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            unset($_SESSION['login_gagal'], $_SESSION['login_kunci']);
            return ['ok' => true];
        }
        $gagal = (int) $u['gagal'] + 1;
        $kunci = null;
        if ($gagal >= 3) {
            $kunci = date('Y-m-d H:i:s', time() + 300);
            $gagal = 0;
        }
        $pdo->prepare('UPDATE pengguna SET gagal = ?, terkunci_sampai = ? WHERE id = ?')
            ->execute([$gagal, $kunci, (int) $u['id']]);
        return ['ok' => false, 'pesan' => $kunci !== null
            ? 'Salah 3 kali — akun dikunci 5 menit.'
            : 'Username atau password salah (' . $gagal . '/3).'];
    }

    // Username tidak dikenal — hitung di sesi agar tidak bisa ditebak tanpa batas.
    $gagal = (int) ($_SESSION['login_gagal'] ?? 0) + 1;
    $_SESSION['login_gagal'] = $gagal;
    if ($gagal >= 3) {
        $_SESSION['login_gagal'] = 0;
        $_SESSION['login_kunci'] = time() + 300;
        return ['ok' => false, 'pesan' => 'Salah 3 kali — coba lagi 5 menit lagi.'];
    }
    return ['ok' => false, 'pesan' => 'Username atau password salah (' . $gagal . '/3).'];
}

function login_terkunci(): int
{
    $sampai = (int) ($_SESSION['login_kunci'] ?? 0);
    return $sampai > time() ? (int) ceil(($sampai - time()) / 60) : 0;
}
