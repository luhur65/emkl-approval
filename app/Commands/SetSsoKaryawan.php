<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Sso as SsoConfig;
use Throwable;

/**
 * Memetakan satu user FUserList ke id master karyawan HR (kolom
 * sso.matchColumn, bawaannya FIDKaryawan) — prasyarat login SSO.
 *
 * Aplikasi ini tidak punya halaman manajemen user, jadi pemetaannya lewat
 * perintah ini. Nilai yang dipetakan adalah `users.karyawan_id` akun SSO orang
 * itu (= `karyawan.id` di database HR); auth-sso-api mencocokkan nilai yang
 * sama ke FUserList sebelum menerbitkan tiket.
 *
 *   php spark sso:map citra 332          tampilkan rencana, minta konfirmasi
 *   php spark sso:map citra 332 --yes    tanpa konfirmasi (non-interaktif)
 *   php spark sso:map citra --clear      kosongkan pemetaan (NULL)
 *
 * Satu karyawan boleh menempel pada LEBIH DARI SATU user (satu orang dengan
 * beberapa akun) — dashboard SSO yang meminta user memilih akunnya — tapi ini
 * ditampilkan sebagai peringatan supaya pemetaan ke orang yang salah ketahuan
 * selagi masih di layar.
 */
class SetSsoKaryawan extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'sso:map';
    protected $description = 'Memetakan FUserList.<sso.matchColumn> seorang user ke karyawan_id akun SSO-nya.';
    protected $usage       = 'sso:map <FUserID> <karyawan_id> [--yes] | sso:map <FUserID> --clear [--yes]';
    protected $arguments   = [
        'FUserID'     => 'Identitas login user di FUserList',
        'karyawan_id' => 'users.karyawan_id akun SSO orang itu (angka > 0)',
    ];
    protected $options = [
        '--yes'   => 'Langsung tulis tanpa konfirmasi',
        '--clear' => 'Kosongkan pemetaan (set NULL) alih-alih mengisi',
    ];

    public function run(array $params)
    {
        $sso    = config(SsoConfig::class);
        $column = $sso->matchColumn;
        $userId = trim((string) ($params[0] ?? ''));
        $clear  = CLI::getOption('clear') !== null;
        $nilai  = trim((string) ($params[1] ?? ''));

        if ($userId === '' || (! $clear && $nilai === '')) {
            CLI::error('Pemakaian: ' . $this->usage);

            return EXIT_ERROR;
        }

        if (! $clear && (! ctype_digit($nilai) || (int) $nilai <= 0)) {
            CLI::error('karyawan_id harus bilangan bulat > 0 (di master karyawan, 0 berarti "tidak punya karyawan").');

            return EXIT_ERROR;
        }

        if ($column === '') {
            CLI::error('sso.matchColumn kosong di konfigurasi.');

            return EXIT_ERROR;
        }

        try {
            $db = \Config\Database::connect();
        } catch (Throwable $e) {
            CLI::error('Tidak bisa menyambung ke database default: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        if (! $db->fieldExists($column, 'FUserList')) {
            CLI::error('Kolom FUserList.' . $column . ' tidak ada di ' . $db->getDatabase() . ' — periksa sso.matchColumn.');

            return EXIT_ERROR;
        }

        $rows = $db->table('FUserList')
            ->select('FID, FUserID, FNamaUser, ' . $column)
            ->where('FUserID', $userId)
            ->limit(2)
            ->get()
            ->getResultArray();

        if (count($rows) !== 1) {
            CLI::error(count($rows) === 0
                ? 'Tidak ada baris FUserList dengan FUserID "' . $userId . '".'
                : 'Ada lebih dari satu baris FUserList dengan FUserID "' . $userId . '" — bereskan dulu di database.');

            return EXIT_ERROR;
        }

        $row  = $rows[0];
        $lama = $row[$column];
        $baru = $clear ? null : (int) $nilai;

        CLI::write('Database : ' . $db->hostname . (empty($db->port) || preg_match('/[,:]/', (string) $db->hostname) ? '' : ':' . $db->port) . ' / ' . $db->getDatabase(), 'yellow');
        CLI::write(sprintf('User     : FID=%s | FUserID=%s | FNamaUser=%s', $row['FID'], $row['FUserID'], $row['FNamaUser'] ?? '-'));
        CLI::write(sprintf(
            'Perubahan: %s  %s  ->  %s',
            $column,
            $lama === null || (int) $lama === 0 ? '(belum dipetakan)' : (string) $lama,
            $baru === null ? 'NULL' : (string) $baru
        ));

        if ($baru !== null && (int) $lama === $baru) {
            CLI::write('Tidak ada yang berubah — nilainya sudah sama.', 'green');

            return EXIT_SUCCESS;
        }

        if ($baru !== null) {
            $lain = $db->table('FUserList')
                ->select('FID, FUserID')
                ->where($column, $baru)
                ->where('FID !=', $row['FID'])
                ->get()
                ->getResultArray();

            if ($lain !== []) {
                CLI::write('PERHATIAN: karyawan_id ' . $baru . ' sudah dipakai user lain: '
                    . implode(', ', array_map(static fn ($r) => $r['FUserID'] . ' (FID ' . $r['FID'] . ')', $lain)), 'yellow');
                CLI::write('Kalau itu memang orang yang sama dengan beberapa akun, dashboard SSO akan', 'yellow');
                CLI::write('meminta ia memilih akun. Kalau bukan, hentikan — ini pemetaan ke orang yang salah.', 'yellow');
            }
        }

        if (CLI::getOption('yes') === null) {
            if (CLI::prompt('Tulis perubahan ini?', ['y', 'n']) !== 'y') {
                CLI::write('Dibatalkan.');

                return EXIT_SUCCESS;
            }
        }

        $db->transBegin();

        try {
            $db->table('FUserList')
                ->where('FID', $row['FID'])
                ->update([$column => $baru]);

            $affected = $db->affectedRows();

            if ($affected !== 1) {
                $db->transRollback();
                CLI::error('UPDATE menyentuh ' . $affected . ' baris (seharusnya 1) — dibatalkan.');

                return EXIT_ERROR;
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            CLI::error('Gagal menulis: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        $sesudah = $db->table('FUserList')->select($column)->where('FID', $row['FID'])->get()->getRowArray();

        CLI::write('Tersimpan. ' . $column . ' sekarang = ' . var_export($sesudah[$column] ?? null, true), 'green');
        CLI::write('Periksa dengan: php spark sso:match ' . ($baru === null ? $userId : $baru), 'yellow');

        return EXIT_SUCCESS;
    }
}
