<?php

namespace App\Models;

use CodeIgniter\Model;

class AuthModel extends Model
{
    protected $table = 'FUserList';
    protected $primaryKey = 'FUserID';
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
}
