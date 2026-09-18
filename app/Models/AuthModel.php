<?php

namespace App\Models;

use CodeIgniter\Model;

class AuthModel extends Model
{
    protected $table = 'FUserList';
    // PK sebenarnya di SQL Server adalah FID (int, PK__FUserLis__...), BUKAN
    // FUserID. FUserID hanya identitas login (varchar, tanpa unique index).
    // Dipakai find() pada jalur SSO: klaim `sub` tiket berisi FID baris yang
    // dipilih auth-sso-api dari tabel ini.
    protected $primaryKey = 'FID';
    protected $returnType = 'array';
    
    /**
     * Memvalidasi user login berdasarkan UserID dan Password.
     * Logika mengikuti CI3 Legacy (di mana FKode disamakan dengan md5 string password).
     */
    public function validateUser($userId, $password)
    {
        return $this->where('FUserID', $userId)
                    ->where('FKode', $password)
                    ->first();
    }

    /**
     * Baris FUserList dengan FID tertentu, atau null bila tidak ada. Dipakai
     * SsoAuth untuk menukar klaim `sub` (= FID) menjadi baris user.
     */
    public function findByFid(int $fid): ?array
    {
        $row = $this->find($fid);

        return is_array($row) ? $row : null;
    }

    /**
     * Baris-baris FUserList yang kolom `$column`-nya bernilai `$value`, paling
     * banyak `$limit`. Dua baris cukup untuk membedakan "tidak ada", "satu",
     * dan "lebih dari satu" — pemanggil yang memutuskan ambigu atau tidak.
     *
     * Nama kolom berasal dari konfigurasi (sso.matchColumn), bukan dari input
     * pengguna; keberadaannya diperiksa dulu lewat hasColumn().
     */
    public function findByColumn(string $column, string|int $value, int $limit = 2): array
    {
        return $this->where($column, $value)
                    ->findAll($limit);
    }

    /** Apakah kolom `$column` benar-benar ada di FUserList pada database yang dipakai? */
    public function hasColumn(string $column): bool
    {
        return $this->db->fieldExists($column, $this->table);
    }
}
