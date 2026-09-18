<?php

namespace App\Libraries;

use Config\Sso as SsoConfig;

/**
 * Verifikator tiket SSO — JWT bertanda tangan RS256 dari auth-sso-api.
 *
 * Tiket membawa identitas di dalam dirinya sendiri dan dibuktikan oleh tanda
 * tangan asimetris, jadi verifikasi dilakukan LOKAL: tidak ada panggilan balik
 * ke server SSO, tidak ada tulisan ke database. Disalin dari
 * sys-modern (app/Libraries/SsoTicket.php) tanpa perubahan logika.
 *
 * Implementasi sengaja memakai ext-openssl langsung, tanpa menambah dependency
 * Composer: seluruh yang dibutuhkan (openssl_verify + base64url + json_decode)
 * sudah tersedia di PHP 8.2 yang dipakai proyek ini.
 *
 * Yang WAJIB diperiksa dan alasannya:
 *  - `alg` dipatok ke RS256. Tanpa ini, tiket palsu ber-`alg: none` atau HS256
 *    (ditandatangani memakai public key sebagai secret) akan lolos — kelas
 *    serangan algorithm confusion.
 *  - `iss` harus auth-sso, `aud` harus kode aplikasi (cabang) ini. Tanpa `aud`,
 *    tiket yang sah untuk cabang lain bisa diputar ulang di sini.
 *  - `exp` wajib ada. Tiket hidup ~60 detik; tanpa exp ia berlaku selamanya.
 *  - `jti` wajib ada. Sifat sekali-pakai ditegakkan pemanggil lewat
 *    SsoNonceStore — di sini hanya dipastikan nonce-nya benar-benar dikirim.
 */
class SsoTicket
{
    /** Satu-satunya algoritma yang diterima. */
    private const ALGORITHM = 'RS256';

    /** Panjang kunci minimum yang dianggap layak. */
    private const MIN_KEY_BITS = 2048;

    private SsoConfig $config;

    public function __construct(?SsoConfig $config = null)
    {
        $this->config = $config ?? config(SsoConfig::class);
    }

    /**
     * Memeriksa tiket dan mengembalikan klaimnya.
     *
     * @return array<string, mixed> Klaim tiket: sub, jti, karyawanId, sid, dst.
     *
     * @throws SsoTicketException kalau tiket tidak sah dengan alasan apa pun.
     */
    public function verify(string $ticket): array
    {
        $parts = explode('.', $ticket);

        if (count($parts) !== 3) {
            throw new SsoTicketException('Format tiket bukan JWT tiga bagian.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeSegment($encodedHeader, 'header');

        // Patokan algoritma dilakukan SEBELUM menyentuh tanda tangan: header
        // adalah bagian tiket yang dikendalikan penyerang, jadi ia tidak boleh
        // ikut menentukan cara verifikasi.
        if (($header['alg'] ?? null) !== self::ALGORITHM) {
            throw new SsoTicketException(sprintf(
                'Algoritma tiket "%s" ditolak, hanya %s yang diterima.',
                is_string($header['alg'] ?? null) ? $header['alg'] : '-',
                self::ALGORITHM
            ));
        }

        if (isset($header['typ']) && strtoupper((string) $header['typ']) !== 'JWT') {
            throw new SsoTicketException('Header typ tiket bukan JWT.');
        }

        $signature = $this->base64UrlDecode($encodedSignature);

        if ($signature === false || $signature === '') {
            throw new SsoTicketException('Tanda tangan tiket tidak bisa didekode.');
        }

        $verified = openssl_verify(
            $encodedHeader . '.' . $encodedPayload,
            $signature,
            $this->publicKey(),
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw new SsoTicketException('Tanda tangan tiket tidak cocok dengan public key SSO.');
        }

        $claims = $this->decodeSegment($encodedPayload, 'payload');

        $this->assertTimeClaims($claims);
        $this->assertIssuer($claims);
        $this->assertAudience($claims);
        $this->assertIdentityClaims($claims);

        return $claims;
    }

    /**
     * Public key SSO sebagai objek kunci OpenSSL.
     *
     * @return \OpenSSLAsymmetricKey
     */
    private function publicKey()
    {
        $key = openssl_pkey_get_public($this->publicKeyPem());

        if ($key === false) {
            throw new SsoTicketException('sso.ticketPublicKey bukan public key yang bisa dibaca OpenSSL.');
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new SsoTicketException('sso.ticketPublicKey bukan kunci RSA.');
        }

        if ((int) ($details['bits'] ?? 0) < self::MIN_KEY_BITS) {
            throw new SsoTicketException(sprintf(
                'Public key SSO hanya %d bit, minimal %d bit.',
                (int) ($details['bits'] ?? 0),
                self::MIN_KEY_BITS
            ));
        }

        return $key;
    }

    /**
     * Menormalkan nilai .env menjadi PEM.
     *
     * auth-sso-api dan sys-modern menyimpan kunci sebagai badan base64 satu
     * baris dengan newline ditulis sebagai dua karakter backslash-n. Nilai yang
     * sama diterima apa adanya di sini — termasuk kalau seseorang menempelkan
     * PEM lengkap — supaya satu kunci bisa disalin antar aplikasi tanpa diedit.
     */
    private function publicKeyPem(): string
    {
        $raw = trim($this->config->ticketPublicKey);

        if ($raw === '') {
            throw new SsoTicketException('sso.ticketPublicKey belum diisi di .env.');
        }

        $raw = str_replace(['\r\n', '\n', '\r'], "\n", $raw);

        if (str_contains($raw, '-----BEGIN')) {
            return $raw;
        }

        $body = preg_replace('/\s+/', '', $raw) ?? '';

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSegment(string $segment, string $label): array
    {
        $json = $this->base64UrlDecode($segment);

        if ($json === false) {
            throw new SsoTicketException(sprintf('Bagian %s tiket bukan base64url yang sah.', $label));
        }

        $decoded = json_decode($json, true, 8);

        if (! is_array($decoded)) {
            throw new SsoTicketException(sprintf('Bagian %s tiket bukan objek JSON.', $label));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertTimeClaims(array $claims): void
    {
        $now    = time();
        $leeway = max(0, $this->config->leeway);

        // `exp` wajib: tiket ini adalah kredensial pembawa, umur hidupnya yang
        // sangat pendek adalah sebagian besar keamanannya.
        if (! isset($claims['exp']) || ! is_numeric($claims['exp'])) {
            throw new SsoTicketException('Tiket tanpa klaim exp.');
        }

        if ((int) $claims['exp'] + $leeway <= $now) {
            throw new SsoTicketException('Tiket sudah kedaluwarsa.');
        }

        if (isset($claims['nbf']) && is_numeric($claims['nbf']) && (int) $claims['nbf'] - $leeway > $now) {
            throw new SsoTicketException('Tiket belum berlaku (nbf di masa depan).');
        }

        if (isset($claims['iat']) && is_numeric($claims['iat']) && (int) $claims['iat'] - $leeway > $now) {
            throw new SsoTicketException('Tiket diterbitkan di masa depan (iat).');
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertIssuer(array $claims): void
    {
        $expected = $this->config->issuer;

        if ($expected === '') {
            throw new SsoTicketException('sso.issuer belum diisi di .env.');
        }

        if (! isset($claims['iss']) || ! is_string($claims['iss']) || ! hash_equals($expected, $claims['iss'])) {
            throw new SsoTicketException('Klaim iss tiket bukan ' . $expected . '.');
        }
    }

    /**
     * `aud` boleh string atau daftar string menurut RFC 7519; auth-sso-api
     * mengirim string, tapi keduanya diterima supaya verifikasi tidak patah
     * kalau suatu saat satu tiket dipakai untuk lebih dari satu audience.
     *
     * @param array<string, mixed> $claims
     */
    private function assertAudience(array $claims): void
    {
        $expected = $this->config->appCode;

        if ($expected === '') {
            throw new SsoTicketException('sso.appCode belum diisi di .env.');
        }

        $audience = $claims['aud'] ?? null;
        $list     = is_array($audience) ? $audience : [$audience];

        foreach ($list as $candidate) {
            if (is_string($candidate) && hash_equals($expected, $candidate)) {
                return;
            }
        }

        throw new SsoTicketException('Klaim aud tiket bukan ' . $expected . ' — tiket ini milik aplikasi/cabang lain.');
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertIdentityClaims(array $claims): void
    {
        // `jti` dibakar pemanggil supaya tiket hanya bisa ditukar sekali; tanpa
        // klaim ini tidak ada yang bisa dibakar dan replay jadi bebas.
        if (! isset($claims['jti']) || ! is_string($claims['jti']) || trim($claims['jti']) === '') {
            throw new SsoTicketException('Tiket tanpa klaim jti.');
        }

        if (! isset($claims['sub']) || (! is_string($claims['sub']) && ! is_int($claims['sub']))) {
            throw new SsoTicketException('Tiket tanpa klaim sub.');
        }

        if (trim((string) $claims['sub']) === '') {
            throw new SsoTicketException('Klaim sub tiket kosong.');
        }
    }

    /**
     * @return string|false
     */
    private function base64UrlDecode(string $data)
    {
        $remainder = strlen($data) % 4;

        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
