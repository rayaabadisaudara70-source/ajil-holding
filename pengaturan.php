<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/init.php';

$u = wajib_owner();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = input('aksi');

    if ($aksi === 'identitas') {
        pengaturan_set('nama_usaha', input('nama_usaha', 'AJIL.JR MINI HOLDING'));
        pengaturan_set('tagline', input('tagline'));
        pengaturan_set('yayasan', input('yayasan'));
        pengaturan_set('wilayah', input('wilayah'));
        pengaturan_set('telepon', input('telepon'));
        pengaturan_set('alamat', input('alamat'));
        flash_set('sukses', 'Identitas usaha disimpan.');
        redirect('pengaturan.php');
    }

    if ($aksi === 'aturan') {
        $hari = [];
        foreach ((array) ($_POST['hari'] ?? []) as $h) {
            $n = (int) $h;
            if ($n >= 1 && $n <= 7) {
                $hari[] = $n;
            }
        }
        sort($hari);
        if (!$hari) {
            flash_set('error', 'Pilih minimal satu hari kerja.');
            redirect('pengaturan.php');
        }
        pengaturan_set('jam_masuk', input('jam_masuk', '08:00'));
        pengaturan_set('toleransi_menit', (string) max(0, input_int('toleransi_menit')));
        pengaturan_set('potongan_per_hari', (string) max(0, input_int('potongan_per_hari')));
        pengaturan_set('bonus_penuh', (string) max(0, input_int('bonus_penuh')));
        pengaturan_set('hari_kerja', implode(',', $hari));
        pengaturan_set('footer_laporan', input('footer_laporan'));
        flash_set('sukses', 'Aturan absensi & gaji disimpan.');
        redirect('pengaturan.php');
    }

    if ($aksi === 'hapus_contoh') {
        $jml = 0;
        foreach (['absensi', 'dokumentasi', 'penjualan', 'jasa', 'proyek', 'barang'] as $tabel) {
            $jml += (int) $pdo->exec('DELETE FROM ' . $tabel . ' WHERE contoh = 1');
        }
        flash_set('sukses', 'Semua data contoh dihapus (' . $jml . ' baris). Data asli tidak tersentuh.');
        redirect('pengaturan.php');
    }
}

$jam = ['00:00', '06:00', '07:00', '07:30', '08:00', '08:30', '09:00', '10:00'];
$jamMasuk = pengaturan('jam_masuk', '08:00');
if (!in_array($jamMasuk, $jam, true)) {
    $jam[] = $jamMasuk;
    sort($jam);
}
$hariKerja = hari_kerja_list();
$namaHari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

halaman_awal('Pengaturan', 'pengaturan.php');
?>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head"><div><h2>Identitas Usaha</h2><div class="sub">Tampil di login, sidebar, dan cetakan</div></div></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="identitas">
      <div class="form-grid">
        <div class="field"><label>Nama usaha / holding</label>
          <input class="input" name="nama_usaha" value="<?= h(pengaturan('nama_usaha')) ?>" required></div>
        <div class="field"><label>Tagline</label>
          <input class="input" name="tagline" value="<?= h(pengaturan('tagline')) ?>"></div>
        <div class="field"><label>Badan / koperasi</label>
          <input class="input" name="yayasan" value="<?= h(pengaturan('yayasan')) ?>"></div>
        <div class="field"><label>Wilayah</label>
          <input class="input" name="wilayah" value="<?= h(pengaturan('wilayah')) ?>"></div>
        <div class="field"><label>Telepon / WA</label>
          <input class="input" name="telepon" value="<?= h(pengaturan('telepon')) ?>"></div>
        <div class="field"><label>Alamat</label>
          <input class="input" name="alamat" value="<?= h(pengaturan('alamat')) ?>"></div>
      </div>
      <div class="form-kaki"><button class="btn btn-emas" type="submit"><?= ikon('check') ?> Simpan Identitas</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Aturan Absensi &amp; Gaji</h2><div class="sub">Dipakai untuk telat, potongan, dan bonus</div></div></div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="aturan">
      <div class="form-grid">
        <div class="field"><label>Jam masuk</label>
          <select class="select" name="jam_masuk">
            <?php foreach ($jam as $j): ?>
              <option value="<?= h($j) ?>" <?= $jamMasuk === $j ? 'selected' : '' ?>><?= h($j) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Toleransi keterlambatan (menit)</label>
          <input class="input" name="toleransi_menit" type="number" min="0" value="<?= h(pengaturan('toleransi_menit', '10')) ?>"></div>
        <div class="field"><label>Potongan per hari tidak hadir (Rp)</label>
          <input class="input" name="potongan_per_hari" type="number" min="0" value="<?= h(pengaturan('potongan_per_hari', '50000')) ?>"></div>
        <div class="field"><label>Bonus hadir penuh (Rp)</label>
          <input class="input" name="bonus_penuh" type="number" min="0" value="<?= h(pengaturan('bonus_penuh', '200000')) ?>"></div>
      </div>
      <div class="field" style="margin-top:12px">
        <label>Hari kerja</label>
        <div class="aksi-baris">
          <?php foreach ($namaHari as $n => $label): ?>
            <label class="chip" style="cursor:pointer">
              <input type="checkbox" name="hari[]" value="<?= (int) $n ?>" <?= in_array($n, $hariKerja, true) ? 'checked' : '' ?>>
              <?= h($label) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="hint">Jumlah hari kerja per bulan dihitung otomatis dari pilihan ini.</span>
      </div>
      <div class="field" style="margin-top:12px"><label>Catatan kaki laporan / cetakan</label>
        <input class="input" name="footer_laporan" value="<?= h(pengaturan('footer_laporan')) ?>"></div>
      <div class="form-kaki"><button class="btn btn-emas" type="submit"><?= ikon('check') ?> Simpan Aturan</button></div>
    </form>
  </div>
</div>

<div class="grid grid-2-1">
  <div class="card">
    <div class="card-head"><div><h2>Unit Usaha</h2><div class="sub">Modul kerja tiap unit menentukan menu karyawan</div></div></div>
    <div class="table-wrap">
      <table class="tabel">
        <thead><tr><th>Unit usaha</th><th>Modul kerja karyawan</th><th>Karyawan</th></tr></thead>
        <tbody>
        <?php foreach (usaha_semua() as $b):
            $modulLabel = ['jual' => 'Penjualan & BIG DATA', 'jasa' => 'Service & Jasa', 'proyek' => 'Proyek Property', 'absensi' => 'Absensi saja'];
            $st = $pdo->prepare('SELECT COUNT(*) FROM pengguna WHERE role = ? AND aktif = 1 AND usaha_nama = ?');
            $st->execute(['karyawan', $b['nama']]);
            $jml = (int) $st->fetchColumn();
            ?>
          <tr>
            <td><b><?= h($b['nama']) ?></b></td>
            <td><?= h($modulLabel[$b['modul']] ?? $b['modul']) ?><?= $b['jasa_jenis'] !== '' ? ' (' . h(jasa_label($b['jasa_jenis'])) . ')' : '' ?></td>
            <td><?= h((string) $jml) ?> orang</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="stat-sub" style="margin-top:12px">
      Ingin menambah unit usaha baru atau mengubah modulnya? Sampaikan ke pengembang — daftar unit
      disimpan di database agar tidak perlu mengubah kode.
    </p>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Data Contoh</h2><div class="sub">Bersihkan sebelum dipakai produksi</div></div></div>
    <?php
    $jmlContoh = 0;
    foreach (['absensi', 'dokumentasi', 'penjualan', 'jasa', 'proyek', 'barang'] as $tabel) {
        $jmlContoh += (int) $pdo->query('SELECT COUNT(*) FROM ' . $tabel . ' WHERE contoh = 1')->fetchColumn();
    }
    ?>
    <p style="font-size:13.5px;line-height:1.55;margin:0 0 14px">
      Saat ini ada <b><?= h((string) $jmlContoh) ?></b> baris data contoh (bertanda “contoh”).
      Data contoh hanya dibuat sekali saat aplikasi pertama dijalankan dan tidak akan muncul lagi setelah dihapus.
    </p>
    <form method="post" data-konfirmasi="Hapus semua data contoh? Data asli tidak ikut terhapus.">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus_contoh">
      <button class="btn btn-merah btn-blok" type="submit"><?= ikon('trash') ?> Hapus Semua Data Contoh</button>
    </form>
  </div>
</div>

<?php halaman_akhir(); ?>
