<?php

namespace App\Services;

use App\Models\PengajuanTripModel;

/**
 * Sisi ENTRI modul Trip: mandor mengajukan sekian trip untuk satu tanggal,
 * dan bisa menghapus pengajuannya sendiri selama belum di-approve.
 *
 * Bandingkan dengan ApprovalTripService yang mengurus sisi APPROVAL atas tabel
 * yang sama. Keduanya sengaja dipisah: yang boleh mengisi (mandor) dan yang
 * boleh menyetujui (kantor) adalah orang berbeda, jadi aturannya pun berbeda.
 */
class PengajuanTripService
{
    use GridPipeline;

    /**
     * Batas atas jumlah trip per pengajuan.
     *
     * CI3 tidak membatasi sama sekali: nilai dari form langsung dipakai sebagai
     * batas perulangan INSERT, sehingga satu ketikan keliru (mis. menempel
     * "300000") menulis ratusan ribu baris rincian dan praktis mengunci tabel.
     * Angka 100 dipilih longgar -- data nyata terbanyak sejauh ini 30 trip
     * sehari -- tapi cukup untuk menahan kesalahan ketik.
     */
    private const MAKS_JLH_TRIP = 100;

    protected PengajuanTripModel $model;

    public function __construct()
    {
        $this->model = new PengajuanTripModel();
    }

    // ------------------------------------------------------------------
    // Grid
    // ------------------------------------------------------------------

    public function getGridList(array $params): \stdClass
    {
        $tgl = $this->tanggalDari($params['tgl'] ?? '');

        return $this->buildGrid($this->mapRows($this->model->getData($tgl)), $params);
    }

    /**
     * Daftar mandor aktif untuk dropdown form. Dipakai controller saat
     * merender halaman, bukan lewat AJAX -- isinya jarang berubah dan
     * jumlahnya sedikit (7 baris pada data Surabaya).
     */
    public function daftarMandor(): array
    {
        return $this->model->daftarMandor();
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /**
     * Menyimpan satu pengajuan trip.
     *
     * Seluruh masukan divalidasi di sini, tidak seperti CI3 yang mengoper
     * $_POST langsung ke INSERT. Yang paling penting: kode mandor dicocokkan
     * ke daftar mandor AKTIF. Tanpa itu, siapa pun yang bisa membuka halaman
     * ini dapat mengirim kode mandor apa saja -- termasuk yang sudah tidak
     * aktif -- lewat request buatan sendiri, karena dropdown di HTML sama
     * sekali tidak mengikat sisi server.
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

        $jlhMentah = trim((string) ($input['jlh'] ?? ''));

        // ctype_digit, bukan is_numeric: "4.5" dan "1e3" lolos is_numeric lalu
        // dipotong diam-diam oleh (int) menjadi angka yang tidak diketik user.
        if ($jlhMentah === '' || !ctype_digit($jlhMentah)) {
            return $this->hasil('Jumlah trip harus berupa angka bulat.');
        }

        $jlh = (int) $jlhMentah;

        if ($jlh < 1 || $jlh > self::MAKS_JLH_TRIP) {
            return $this->hasil('Jumlah trip harus antara 1 dan ' . self::MAKS_JLH_TRIP . '.');
        }

        $mandor = trim((string) ($input['mandor'] ?? ''));

        if ($mandor === '') {
            return $this->hasil('Mandor belum dipilih.');
        }

        if (!$this->mandorAktif($mandor)) {
            return $this->hasil('Mandor tidak dikenal atau sudah tidak aktif.');
        }

        $fid = $this->model->simpan($tgl, $jlh, $mandor, $userId);

        if ($fid === null) {
            $pesan = $this->model->pesanError();

            return $this->hasil($pesan !== '' ? $pesan : 'Penyimpanan gagal.');
        }

        return [
            'error' => '',
            'msg'   => 'Pengajuan tersimpan: ' . $jlh . ' trip untuk mandor ' . $mandor . '.',
            'fid'   => $fid,
        ];
    }

    /**
     * Menghapus satu pengajuan.
     *
     * Hasil 0 baris TIDAK diperlakukan sebagai kegagalan teknis: penyebab
     * lazimnya barisnya keburu di-approve orang lain, atau sudah dihapus di
     * tab lain. Pesannya menyebut keduanya supaya user tahu harus memuat ulang
     * daftarnya, bukan mengulang klik.
     */
    public function hapus(string $fid, ?string $userId): array
    {
        if ($userId === null || trim($userId) === '') {
            return $this->hasil('Sesi Anda telah berakhir. Silakan login ulang.');
        }

        $fid = trim($fid);

        if ($fid === '' || !ctype_digit($fid)) {
            return $this->hasil('Nomor pengajuan tidak valid.');
        }

        $terhapus = $this->model->hapus($fid);

        if ($terhapus < 1) {
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
     * Memetakan baris mentah ke kolom grid. Kolomnya mengikuti <thead> view
     * CI3 (approval/trip/index.php): Tanggal, Jumlah Trip, Mandor, Status,
     * User -- ditambah FID sebagai kunci baris & penanda boleh-hapus.
     */
    private function mapRows(array $list): array
    {
        $rows = [];

        foreach ($list as $d) {
            $id = (string) ($d['FID'] ?? '');

            // JOIN ke MMandor bertipe LEFT, jadi FNMandor bisa NULL untuk kode
            // yang tidak ada di master -- tanpa penjagaan ini barisnya tampil
            // sebagai "005 ()".
            $kode = trim((string) ($d['FMandor'] ?? ''));
            $nama = trim((string) ($d['FNMandor'] ?? ''));

            $sudahApp = (int) ($d['FIsApp'] ?? 0) === 1;

            $rows[] = [
                'id'        => $id,
                'IdTarget'  => $id,
                'FID'       => $id,
                'FTgl'      => $this->formatTanggal($d['FTgl'] ?? ''),
                'FJlhTrip'  => $d['FJlhTrip'] ?? '',
                'FMandor'   => $nama !== '' ? $kode . ' (' . $nama . ')' : $kode,
                'FStatus'   => $sudahApp ? 'APPROVED' : 'BELUM APPROVED',
                'FUserID'   => $d['FUserID'] ?? '',
                'FTglInput' => $this->formatTanggal($d['FTglInput'] ?? '', true),
                // Dipakai view untuk memutuskan tombol Hapus ditampilkan atau
                // tidak. Sisi server tetap memeriksanya sendiri saat menghapus;
                // ini semata supaya user tidak menekan tombol yang pasti gagal.
                'BolehHapus' => $sudahApp ? '0' : '1',
            ];
        }

        return $rows;
    }

    /**
     * Kode mandor -> apakah ada di daftar mandor aktif. Daftarnya pendek dan
     * sudah diambil sekali, jadi pencocokannya di PHP saja -- tidak perlu
     * query tambahan hanya untuk memastikan satu nilai.
     */
    private function mandorAktif(string $kode): bool
    {
        foreach ($this->model->daftarMandor() as $m) {
            if (strcasecmp(trim((string) $m['FKMandor']), $kode) === 0) {
                return true;
            }
        }

        return false;
    }

    private function tanggalValid(string $tgl): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $tgl);

        return $d !== false && $d->format('Y-m-d') === $tgl;
    }

    /** Tanggal dari request, jatuh ke hari ini bila kosong / tidak valid. */
    private function tanggalDari($nilai): string
    {
        $tgl = trim((string) $nilai);

        return $this->tanggalValid($tgl) ? $tgl : date('Y-m-d');
    }

    /** Balasan seragam saat proses ditolak sebelum menyentuh database. */
    private function hasil(string $pesan): array
    {
        return ['error' => $pesan, 'msg' => ''];
    }
}
