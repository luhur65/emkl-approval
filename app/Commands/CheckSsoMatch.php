<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Sso as SsoConfig;
use Throwable;

/**
 * Diagnosa pemetaan identitas SSO -> baris `FUserList`.
 *
 * Menjawab satu pertanyaan yang dari browser mustahil dibedakan: saat login SSO
 * ditolak dengan "Akun Anda belum terhubung ke EMKL Approval", apakah barisnya
 * memang belum dipetakan, dipetakan ke lebih dari satu baris, atau aplikasi ini
 * sebenarnya sedang menatap database/kolom yang berbeda dari yang Anda periksa
 * lewat SSMS.
 *
 * Perintah ini memakai konfigurasi dan koneksi yang sama persis dengan
 * App\Controllers\SsoAuth.
 *
 *   php spark sso:match 332        (nilai numerik = FIDKaryawan / klaim karyawanId)
 *   php spark sso:match citra      (nilai non-numerik = FUserID, tampilkan pemetaannya)
 *   php spark sso:match            (tanpa nilai: hanya tampilkan konfigurasi)
 */
class CheckSsoMatch extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'sso:match';
    protected $description = 'Memeriksa konfigurasi pencocokan SSO dan menguji satu nilai terhadap FUserList.';
    protected $usage       = 'sso:match [nilai]';
    protected $arguments   = ['nilai' => 'FIDKaryawan (angka) yang diuji, atau FUserID untuk melihat pemetaan satu user'];

    public function run(array $params)
    {
        $sso = config(SsoConfig::class);

        CLI::write('Konfigurasi SSO', 'yellow');
        CLI::write(str_repeat('-', 60));
        CLI::write('  sso.enabled     = ' . ($sso->enabled ? 'true' : CLI::color('false', 'red')));
        CLI::write('  sso.appCode     = ' . ($sso->appCode === '' ? CLI::color('(KOSONG)', 'red') : $sso->appCode));
        CLI::write('  sso.matchClaim  = ' . ($sso->matchClaim === '' ? CLI::color('(KOSONG)', 'red') : $sso->matchClaim));
        CLI::write('  sso.matchColumn = ' . ($sso->matchColumn === '' ? CLI::color('(KOSONG)', 'red') : $sso->matchColumn));

        try {
            $db = \Config\Database::connect();
        } catch (Throwable $e) {
            CLI::error('Tidak bisa menyambung ke database default: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::newLine();
        CLI::write('Database yang benar-benar dipakai aplikasi ini', 'yellow');
        CLI::write(str_repeat('-', 60));
        CLI::write('  hostname = ' . $db->hostname . (empty($db->port) || preg_match('/[,:]/', (string) $db->hostname) ? '' : ':' . $db->port));
        CLI::write('  database = ' . $db->getDatabase());

        $column = $sso->matchColumn;

        if ($column === '') {
            CLI::error('sso.matchColumn kosong — setiap login SSO akan ditolak.');

            return EXIT_ERROR;
        }

        if (! $db->fieldExists($column, 'FUserList')) {
            CLI::newLine();
            CLI::error('Kolom FUserList.' . $column . ' TIDAK ADA di database ini.');
            CLI::write('Setiap login SSO akan ditolak. Periksa sso.matchColumn, atau', 'yellow');
            CLI::write('apakah database.default menunjuk server yang Anda kira.', 'yellow');

            return EXIT_ERROR;
        }

        CLI::write('  FUserList.' . $column . ' ada : ' . CLI::color('ya', 'green'));

        // Gambaran umum: berapa baris yang sudah dipetakan, dan berapa di antara
        // pemegang menu approval (syarat checkMenu()) yang sudah dipetakan —
        // hanya mereka yang benar-benar butuh SSO.
        $total  = $db->table('FUserList')->countAllResults();
        $terisi = $db->table('FUserList')
            ->where('ISNULL(' . $column . ', 0) > 0', null, false)
            ->countAllResults();

        CLI::write('  baris FUserList dengan ' . $column . ' terisi : ' . $terisi . ' dari ' . $total);

        $approver = $db->table('FUserList u')
            ->join('fusermenu m', "m.FMenuId = u.FUserID AND m.FMenuShowOrder = '191014'")
            ->select('u.FUserID')
            ->distinct()
            ->countAllResults();
        $approverTerisi = $db->table('FUserList u')
            ->join('fusermenu m', "m.FMenuId = u.FUserID AND m.FMenuShowOrder = '191014'")
            ->where('ISNULL(u.' . $column . ', 0) > 0', null, false)
            ->select('u.FUserID')
            ->distinct()
            ->countAllResults();

        CLI::write('  user bermenu approval yang sudah dipetakan : ' . $approverTerisi . ' dari ' . $approver);

        $nilai = trim((string) ($params[0] ?? ''));

        if ($nilai === '') {
            CLI::newLine();
            CLI::write('Beri satu nilai untuk mengujinya, contoh: php spark sso:match 332', 'yellow');

            return EXIT_SUCCESS;
        }

        CLI::newLine();

        if (! ctype_digit($nilai)) {
            return $this->tampilkanUser($db, $column, $nilai);
        }

        CLI::write('Uji klaim karyawanId = ' . $nilai, 'yellow');
        CLI::write(str_repeat('-', 60));

        // Penjaga yang sama dengan SsoAuth: nilai non-positif bukan identitas.
        if ((int) $nilai <= 0) {
            CLI::write('  ' . CLI::color('ditolak', 'red') . ' — nilai <= 0 bukan identitas yang sah.');

            return EXIT_ERROR;
        }

        $rows = $db->table('FUserList')
            ->select('FID, FUserID, FNamaUser, ' . $column)
            ->where($column, (int) $nilai)
            ->limit(3)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            CLI::write(sprintf(
                '   FID=%s | FUserID=%s | FNamaUser=%s | %s=%s | menu approval: %s',
                $row['FID'],
                $row['FUserID'],
                $row['FNamaUser'] ?? '-',
                $column,
                $row[$column] ?? '-',
                $this->punyaMenuApproval($db, (string) $row['FUserID']) ? CLI::color('ya', 'green') : CLI::color('tidak', 'red')
            ));
        }

        CLI::newLine();
        $jml = count($rows);

        if ($jml === 1) {
            CLI::write('HASIL: tepat 1 baris — auth-sso-api akan menerbitkan tiket dengan sub=FID ' . $rows[0]['FID'] . ', dan login SSO akan BERHASIL.', 'green');

            return EXIT_SUCCESS;
        }

        if ($jml === 0) {
            CLI::error('HASIL: 0 baris — auth-sso-api menolak "Akun Anda belum terdaftar" sebelum tiket terbit.');
            CLI::write('Petakan lewat: php spark sso:map <FUserID> ' . $nilai, 'yellow');
        } else {
            CLI::write('HASIL: ' . $jml . ' baris — dashboard SSO akan meminta user memilih salah satu akun.', 'yellow');
        }

        return EXIT_SUCCESS;
    }

    /**
     * Nilai non-numerik dibaca sebagai FUserID: tampilkan pemetaan user itu.
     *
     * @param \CodeIgniter\Database\BaseConnection $db
     */
    private function tampilkanUser($db, string $column, string $userId): int
    {
        CLI::write('Pemetaan FUserID = ' . $userId, 'yellow');
        CLI::write(str_repeat('-', 60));

        $rows = $db->table('FUserList')
            ->select('FID, FUserID, FNamaUser, ' . $column)
            ->where('FUserID', $userId)
            ->limit(3)
            ->get()
            ->getResultArray();

        if ($rows === []) {
            CLI::error('Tidak ada baris FUserList dengan FUserID itu.');

            return EXIT_ERROR;
        }

        foreach ($rows as $row) {
            $terisi = (int) ($row[$column] ?? 0) > 0;

            CLI::write(sprintf(
                '   FID=%s | FUserID=%s | FNamaUser=%s | %s=%s | menu approval: %s',
                $row['FID'],
                $row['FUserID'],
                $row['FNamaUser'] ?? '-',
                $column,
                $terisi ? CLI::color((string) $row[$column], 'green') : CLI::color('(belum dipetakan)', 'red'),
                $this->punyaMenuApproval($db, (string) $row['FUserID']) ? CLI::color('ya', 'green') : CLI::color('tidak', 'red')
            ));
        }

        CLI::newLine();
        CLI::write('Untuk memetakan: php spark sso:map ' . $userId . ' <karyawan_id akun SSO>', 'yellow');

        return EXIT_SUCCESS;
    }

    /**
     * @param \CodeIgniter\Database\BaseConnection $db
     */
    private function punyaMenuApproval($db, string $userId): bool
    {
        // Syarat yang sama dengan helper checkMenu() di sidebar.
        return $db->table('fusermenu')
            ->where('FMenuShowOrder', '191014')
            ->where('FMenuId', $userId)
            ->countAllResults() > 0;
    }
}
