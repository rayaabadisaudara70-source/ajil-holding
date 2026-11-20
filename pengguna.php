<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_owner();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'simpan') {
        $id = input_int('id');
        $nama = input('nama');
        $username = strtolower(input('username'));
        $role = input('role') === 'owner' ? 'owner' : 'karyawan';
        $usahaNama = $role === 'owner' ? '' : input('usaha_nama');
        $password = (string) ($_POST['password'] ?? '');

        if ($nama === '' || $username === '') {
            flash_set('error', 'Nama dan username wajib diisi.');
            redirect('pengguna.php');
        }
        if (!preg_match('/^[a-z0-9._]{3,24}$/', $username)) {
            flash_set('error', 'Username hanya huruf kecil/angka/titik, 3–24 karakter.');
            redirect('pengguna.php');
        }
        if ($role === 'karyawan' && !in_array($usahaNama, usaha_nama_semua(), true)) {
            flash_set('error', 'Unit usaha wajib dipilih untuk akun karyawan.');
            redirect('pengguna.php');
        }
        if ($id === 0 && strlen($password) < 6) {
            flash_set('error', 'Password akun baru minimal 6 karakter.');
            redirect('pengguna.php');
        }
        if ($password !== '' && strlen($password) < 6) {
            flash_set('error', 'Password minimal 6 karakter.');
            redirect('pengguna.php');
        }

        $cek = $pdo->prepare('SELECT id FROM pengguna WHERE lower(username) = ? AND id <> ?');
        $cek->execute([$username, $id]);
        if ($cek->fetchColumn()) {
            flash_set('error', 'Username "' . h($username) . '" sudah dipakai akun lain.');
            redirect('pengguna.php');
        }

        $usahaId = null;
        if ($usahaNama !== '') {
            $row = usaha_row($usahaNama);
            $usahaId = $row ? (int) $row['id'] : null;
        }
        $data = [$nama, $username, $role, $usahaId, $usahaNama, input('jabatan'), input('hp'),
            input_int('pokok'), input_int('tunjangan_makan'), input_int('tunjangan_transport')];

        if ($id > 0) {
            $sql = 'UPDATE pengguna SET nama = ?, username = ?, role = ?, usaha_id = ?, usaha_nama = ?, jabatan = ?,
                hp = ?, pokok = ?, tunjangan_makan = ?, tunjangan_transport = ?';
            if ($password !== '') {
                $sql .= ', password = ?';
                $data[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = ?';
            $data[] = $id;
            $pdo->prepare($sql)->execute($data);
            flash_set('sukses', 'Akun ' . h($nama) . ' diperbarui.');
        } else {
            $data[] = password_hash($password, PASSWORD_DEFAULT);
            $data[] = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO pengguna
                (nama, username, role, usaha_id, usaha_nama, jabatan, hp, pokok, tunjangan_makan, tunjangan_transport,
                 password, dibuat)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($data);
            flash_set('sukses', 'Akun baru dibuat. Berikan username & password ke yang bersangkutan, lalu minta segera menggantinya di menu Akun Saya.');
        }
        redirect('pengguna.php');
    }

    if ($aksi === 'aktif') {
        $id = input_int('id');
        if ($id === (int) $u['id']) {
            flash_set('error', 'Tidak bisa menonaktifkan akun Anda sendiri.');
            redirect('pengguna.php');
        }
        $pdo->prepare('UPDATE pengguna SET aktif = 1 - aktif WHERE id = ?')->execute([$id]);
        flash_set('sukses', 'Status akun diperbarui.');
        redirect('pengguna.php');
    }

    if ($aksi === 'reset') {
        $id = input_int('id');
        $baru = input('password_baru');
        if (strlen($baru) < 6) {
            flash_set('error', 'Password baru minimal 6 karakter.');
            redirect('pengguna.php');
        }
        $pdo->prepare('UPDATE pengguna SET password = ?, gagal = 0, terkunci_sampai = NULL WHERE id = ?')
            ->execute([password_hash($baru, PASSWORD_DEFAULT), $id]);
        flash_set('sukses', 'Password direset. Sampaikan password baru langsung ke yang bersangkutan.');
        redirect('pengguna.php');
    }

    if ($aksi === 'hapus') {
        $id = input_int('id');
        if ($id === (int) $u['id']) {
            flash_set('error', 'Tidak bisa menghapus akun Anda sendiri.');
            redirect('pengguna.php');
        }
        $st = $pdo->prepare('SELECT * FROM pengguna WHERE id = ?');
        $st->execute([$id]);
        $target = $st->fetch();
        if (!$target) {
            redirect('pengguna.php');
        }
        if ($target['role'] === 'owner') {
            $jmlOwner = (int) $pdo->query("SELECT COUNT(*) FROM pengguna WHERE role = 'owner' AND aktif = 1")->fetchColumn();
            if ($jmlOwner <= 1) {
                flash_set('error', 'Akun owner terakhir tidak boleh dihapus.');
                redirect('pengguna.php');
            }
        }
        $punyaData = (int) $pdo->query('SELECT
            (SELECT COUNT(*) FROM absensi WHERE pengguna_id = ' . $id . ') +
            (SELECT COUNT(*) FROM dokumentasi WHERE pengguna_id = ' . $id . ') +
            (SELECT COUNT(*) FROM penjualan WHERE kasir_id = ' . $id . ') +
            (SELECT COUNT(*) FROM jasa WHERE petugas_id = ' . $id . ') +
            (SELECT COUNT(*) FROM proyek WHERE mandor_id = ' . $id . ')')->fetchColumn();
        if ($punyaData > 0) {
            flash_set('error', 'Akun ' . h((string) $target['nama']) . ' punya ' . $punyaData
                . ' catatan aktivitas. Nonaktifkan saja agar riwayat & rekap gaji tetap utuh.');
            redirect('pengguna.php');
        }
        $pdo->prepare('DELETE FROM pengguna WHERE id = ?')->execute([$id]);
        flash_set('sukses', 'Akun dihapus.');
        redirect('pengguna.php');
    }
}

$edit = null;
$editId = input_int('edit');
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM pengguna WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$daftar = $pdo->query('SELECT * FROM pengguna ORDER BY role, usaha_nama, nama')->fetchAll();

halaman_awal('Kelola Akun', 'pengguna.php', '<span class="chip">' . h((string) count($daftar)) . ' akun</span>');
?>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head">
      <div><h2><?= $edit ? 'Ubah Akun' : 'Tambah Akun Baru' ?></h2>
        <div class="sub">Gaji pokok &amp; tunjangan dipakai menu Gaji &amp; Rekap.</div></div>
      <?php if ($edit): ?><a class="btn btn-kecil btn-garis" href="pengguna.php">Batal</a><?php endif; ?>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <div class="form-grid">
        <div class="field"><label>Nama lengkap</label>
          <input class="input" name="nama" required value="<?= h($edit['nama'] ?? '') ?>" placeholder="Contoh: Rani - Cab. GDC"></div>
        <div class="field"><label>Username (untuk login)</label>
          <input class="input" name="username" required value="<?= h($edit['username'] ?? '') ?>" placeholder="rani"></div>
        <div class="field"><label>Peran</label>
          <select class="select" name="role" data-auto-submit>
            <option value="karyawan" <?= (($edit['role'] ?? 'karyawan') === 'karyawan') ? 'selected' : '' ?>>Karyawan</option>
            <option value="owner" <?= (($edit['role'] ?? '') === 'owner') ? 'selected' : '' ?>>Owner</option>
          </select></div>
        <div class="field"><label>Unit usaha</label>
          <select class="select" name="usaha_nama">
            <option value="">— (owner: semua unit) —</option>
            <?php foreach (usaha_nama_semua() as $un): ?>
              <option value="<?= h($un) ?>" <?= (($edit['usaha_nama'] ?? '') === $un) ? 'selected' : '' ?>><?= h($un) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Jabatan</label>
          <input class="input" name="jabatan" value="<?= h($edit['jabatan'] ?? '') ?>" placeholder="Kasir / Mekanik / Mandor"></div>
        <div class="field"><label>Nomor HP</label>
          <input class="input" name="hp" value="<?= h($edit['hp'] ?? '') ?>" placeholder="08xx"></div>
        <div class="field"><label>Gaji pokok (Rp)</label>
          <input class="input" name="pokok" type="number" min="0" value="<?= h((string) ($edit['pokok'] ?? '0')) ?>"></div>
        <div class="field"><label>Tunjangan makan (Rp)</label>
          <input class="input" name="tunjangan_makan" type="number" min="0" value="<?= h((string) ($edit['tunjangan_makan'] ?? '0')) ?>"></div>
        <div class="field"><label>Tunjangan transport (Rp)</label>
          <input class="input" name="tunjangan_transport" type="number" min="0" value="<?= h((string) ($edit['tunjangan_transport'] ?? '0')) ?>"></div>
        <div class="field"><label><?= $edit ? 'Password baru (kosongkan bila tetap)' : 'Password awal' ?></label>
          <input class="input" name="password" type="text" minlength="6"
                 placeholder="<?= $edit ? 'Isi hanya bila ingin mengganti' : 'Minimal 6 karakter' ?>" <?= $edit ? '' : 'required' ?>></div>
      </div>
      <div class="form-kaki">
        <button class="btn btn-emas" type="submit"><?= ikon($edit ? 'check' : 'plus') ?> <?= $edit ? 'Simpan Perubahan' : 'Buat Akun' ?></button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Reset Password</h2><div class="sub">Untuk akun yang lupa password</div></div></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="reset">
      <div class="field"><label>Akun</label>
        <select class="select" name="id" required>
          <?php foreach ($daftar as $p): ?>
            <option value="<?= (int) $p['id'] ?>"><?= h($p['nama']) ?> (<?= h($p['username']) ?>)</option>
          <?php endforeach; ?>
        </select></div>
      <div class="field" style="margin-top:10px"><label>Password baru</label>
        <input class="input" name="password_baru" required minlength="6" placeholder="Minimal 6 karakter"></div>
      <div class="form-kaki">
        <button class="btn btn-navy" type="submit"><?= ikon('shield') ?> Reset Password</button>
      </div>
    </form>
    <p class="stat-sub" style="margin-top:14px">
      Password tidak pernah ditampilkan di aplikasi. Bila lupa, gunakan reset ini lalu sampaikan langsung ke pemilik akun.
    </p>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h2>Daftar Akun</h2><div class="sub">Owner &amp; karyawan seluruh unit</div></div></div>
  <div class="table-wrap">
    <table class="tabel">
      <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Unit usaha</th><th>Jabatan</th>
        <th class="angka">Pokok</th><th class="angka">Tunjangan</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($daftar as $p): ?>
        <tr>
          <td><b><?= h($p['nama']) ?></b>
            <?php if (password_bawaan($p)): ?> <?= badge('password bawaan', 'merah') ?><?php endif; ?>
            <?php if ((string) $p['hp'] !== ''): ?><br><span class="stat-sub"><?= h($p['hp']) ?></span><?php endif; ?>
          </td>
          <td><?= h($p['username']) ?></td>
          <td><?= $p['role'] === 'owner' ? badge('Owner', 'emas') : badge('Karyawan', 'abu') ?></td>
          <td><?= h($p['usaha_nama'] !== '' ? $p['usaha_nama'] : 'Semua unit') ?></td>
          <td><?= h($p['jabatan']) ?></td>
          <td class="angka"><?= h(rupiah($p['pokok'])) ?></td>
          <td class="angka"><?= h(rupiah((int) $p['tunjangan_makan'] + (int) $p['tunjangan_transport'])) ?></td>
          <td><?= (int) $p['aktif'] === 1 ? badge('Aktif', 'hijau') : badge('Nonaktif', 'merah') ?></td>
          <td class="aksi-baris" style="white-space:nowrap">
            <a class="btn btn-kecil btn-garis" href="pengguna.php?edit=<?= (int) $p['id'] ?>"><?= ikon('edit') ?></a>
            <form method="post" data-konfirmasi="Ubah status aktif akun ini?">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="aktif">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-kecil btn-garis" type="submit"><?= (int) $p['aktif'] === 1 ? ikon('x') : ikon('check') ?></button>
            </form>
            <form method="post" data-konfirmasi="Hapus akun ini permanen?">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-kecil btn-merah" type="submit"><?= ikon('trash') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php halaman_akhir(); ?>
