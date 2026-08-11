<?php

namespace App\Services;

use App\Models\PengajuanSupirSerapModel;

/**
 * Sisi ENTRI Pengajuan Supir Serap: mandor mendaftarkan kendaraan yang supir
 * tetapnya digantikan supir serap pada satu tanggal, berikut alasannya.
 *
 * Persetujuannya ditangani ApprovalPengajuanService di atas tabel yang sama
 * (TrApprovalAbsensi). Yang mengisi dan yang menyetujui orang berbeda, jadi
 * hak aksesnya pun dipisah -- lihat Controllers/Pengajuan.php.
 */
class PengajuanSupirSerapService
{
    use GridPipeline;

    /** Sesuai lebar kolom TrApprovalAbsensi.FKeterangan = varchar(500). */
    private const MAKS_KETERANGAN = 500;

    protected PengajuanSupirSerapModel $model;

    public function __construct()
    {
        $this->model = new PengajuanSupirSerapModel();
    }

    // ------------------------------------------------------------------
    // Grid & master
    // ------------------------------------------------------------------

    public function getGridList(array $params): \stdClass
    {
        $tgl = $this->tanggalDari($params['tgl'] ?? '');

        return $this->buildGrid($this->mapRows($this->model->getData($tgl)), $params);
    }

    public function daftarKendaraan(): array
    {
        return $this->model->daftarKendaraan();
    }

    public function daftarSupir(): array
    {
        return $this->model->daftarSupir();
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Menyimpan satu pengajuan supir serap.
     *
     * Perbedaan penting dari CI3: kolom FKSupir TIDAK diambil dari kiriman
     * form. Di CI3 nilainya berasal dari input readonly yang ikut dikirim POST,
     * padahal `readonly` hanya berlaku di browser -- request buatan sendiri
     * bebas mengisinya dengan apa pun, sehingga tersimpan pasangan
     * kendaraan-supir yang tidak sesuai master. Di sini supir tetapnya dibaca
     * ulang dari Gdg.FMilikSupir berdasarkan kendaraan yang dipilih.
     */
    public function simpan(array $input, ?string $userId): array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasil('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        $tgl = trim((string) ($input['tgl'] ?? ''));

        if (!$this->tanggalValid($tgl)) {
            return $this->hasil('Tanggal tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        // Form CI3 memasang max="hari ini" pada input tanggalnya. Itu cuma
        // penjagaan di browser; disalin ke sini supaya benar-benar berlaku.
        if ($tgl > date('Y-m-d')) {
            return $this->hasil('Tanggal pengajuan tidak boleh melewati hari ini.');
        }

        $gdg = trim((string) ($input['gdg'] ?? ''));

        if ($gdg === '') {
            return $this->hasil('No Polisi belum dipilih.');
        }

        // Sekaligus memvalidasi kendaraannya dan mengambil supir tetapnya.
        $supir = $this->model->supirPemilik($gdg);

        if ($supir === null) {
            return $this->hasil('Kendaraan tidak dikenal atau sudah tidak aktif.');
        }

        $supirSerap = trim((string) ($input['supir_serap'] ?? ''));

        if ($supirSerap === '') {
            return $this->hasil('Supir serap belum dipilih.');
        }

        if (!$this->model->supirSerapValid($supirSerap)) {
            return $this->hasil('Supir serap tidak dikenal atau sudah tidak aktif.');
        }

        // Catatan: supir serap yang sama dengan supir tetapnya TIDAK ditolak.
        // CI3 sempat menulis penjagaan itu di sisi klien lalu menonaktifkannya
        // kembali (barisnya masih ada sebagai komentar di view aslinya), jadi
        // melarangnya di sini berarti menambah aturan bisnis baru -- bukan
        // wewenang migrasi.

        $keterangan = trim((string) ($input['keterangan'] ?? ''));

        if ($keterangan === '') {
            return $this->hasil('Keterangan wajib diisi.');
        }

        if (mb_strlen($keterangan) > self::MAKS_KETERANGAN) {
            return $this->hasil('Keterangan maksimal ' . self::MAKS_KETERANGAN . ' karakter.');
        }

        $fid = $this->model->simpan($tgl, $gdg, $supir, $supirSerap, $keterangan, $userId);

        if ($fid === null) {
            $pesan = $this->model->pesanError();

            // pesanError kosong = bukan kegagalan teknis, melainkan penjagaan
            // duplikat di dalam INSERT yang menolak baris kembar.
            return $this->hasil(
                $pesan !== '' ? $pesan : 'Data yang sama sudah pernah diinput untuk tanggal ini.'
            );
        }

        return [
            'error' => '',
            'msg'   => 'Pengajuan tersimpan untuk kendaraan ' . $gdg . '.',
            'fid'   => $fid,
        ];
    }

    public function hapus(string $fid, ?string $userId): array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasil('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        $fid = trim($fid);

        if ($fid === '' || !ctype_digit($fid)) {
            return $this->hasil('Nomor pengajuan tidak valid.');
        }

        if ($this->model->hapus($fid) < 1) {
            $pesan = $this->model->pesanError();

            return $this->hasil(
                $pesan !== ''
                    ? $pesan
                    : 'Data tidak jadi dihapus -- kemungkinan sudah di-approve '
                      . 'atau sudah dihapus sebelumnya. Muat ulang daftarnya.'
            );
        }

        return ['error' => '', 'msg' => 'Pengajuan berhasil dihapus.'];
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * Kolomnya mengikuti <thead> view CI3 (approval/pengajuan/index.php):
     * Tanggal, No Polisi, Supir, Supir Serap, Approved, Keterangan, Tgl Input,
     * Tgl App -- ditambah FID sebagai kunci baris & penanda boleh-hapus.
     */
    private function mapRows(array $list): array
    {
        $rows = [];

        foreach ($list as $d) {
            $id       = (string) ($d['FID'] ?? '');
            $sudahApp = (int) ($d['FIsApp'] ?? 0) === 1;

            $rows[] = [
                'id'            => $id,
                'IdTarget'      => $id,
                'FID'           => $id,
                'FTgl'          => $this->formatTanggal($d['FTgl'] ?? ''),
                'FKGdg'         => $d['FKGdg'] ?? '',
                'FKSupir'       => $d['FKSupir'] ?? '',
                'FKSupir_Serap' => $d['FKSupir_Serap'] ?? '',
                'FStatus'       => $sudahApp ? 'APPROVED' : 'BELUM APPROVED',
                'FKeterangan'   => $d['FKeterangan'] ?? '',
                'FTglInput'     => $this->formatTanggal($d['FTglInput'] ?? '', true),
                'FTglApp'       => $this->formatTanggal($d['FTglApp'] ?? '', true),
                'BolehHapus'    => $sudahApp ? '0' : '1',
            ];
        }

        return $rows;
    }

    private function tanggalValid(string $tgl): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $tgl);

        return $d !== false && $d->format('Y-m-d') === $tgl;
    }

    private function tanggalDari($nilai): string
    {
        $tgl = trim((string) $nilai);

        return $this->tanggalValid($tgl) ? $tgl : date('Y-m-d');
    }

    private function hasil(string $pesan): array
    {
        return ['error' => $pesan, 'msg' => ''];
    }
}
