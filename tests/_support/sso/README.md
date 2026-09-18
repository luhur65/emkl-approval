# Kunci khusus test SSO

Tiga berkas PEM di folder ini **hanya untuk test** (`tests/unit/Sso*Test.php`).
Keduanya dibuat lokal dengan `openssl_pkey_new()` (RSA 2048 bit) dan tidak punya
hubungan apa pun dengan kunci SSO sungguhan:

| Berkas | Peran |
| --- | --- |
| `test-only-private.pem` | Menandatangani tiket "sah" di dalam test |
| `test-only-public.pem`  | Pasangannya; dipasang ke `Config\Sso::$ticketPublicKey` di test |
| `test-only-other-private.pem` | Kunci LAIN, untuk memastikan tiket dari penerbit lain ditolak |

Private key sungguhan hanya ada di auth-sso-api. Yang boleh ada di `.env`
aplikasi ini hanya **public** key-nya (`sso.ticketPublicKey`), dan test
`SsoTicketTest::testPublicKeyDiEnvBisaDibacaOpensslDanBerukuranMinimal2048Bit`
memeriksa nilai itu masih terbaca OpenSSL.

Kalau perlu dibuat ulang (di Windows, `openssl_pkey_new()` butuh path
`openssl.cnf` lewat opsi `config`):

```php
$cfg = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'config' => '<dir php>/extras/ssl/openssl.cnf'];
$res = openssl_pkey_new($cfg);
openssl_pkey_export($res, $pem, null, ['config' => $cfg['config']]);
file_put_contents('test-only-private.pem', $pem);
file_put_contents('test-only-public.pem', openssl_pkey_get_details($res)['key']);
```
