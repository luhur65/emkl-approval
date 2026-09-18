<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Dilempar SsoTicket saat sebuah tiket ditolak.
 *
 * Pesannya ditujukan untuk log, bukan untuk pengguna: isinya menyebut alasan
 * teknis penolakan (`aud` salah, tanda tangan tidak cocok, dst.). Controller
 * mencatatnya lalu menampilkan pesan umum, supaya penyerang tidak bisa memakai
 * respons sebagai alat bantu menebak bentuk tiket yang benar.
 */
class SsoTicketException extends RuntimeException
{
}
