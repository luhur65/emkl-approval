<?php

namespace App\Services;

/**
 * Pipeline jqGrid sisi server: pencarian, pengurutan, paging, dan pembanding
 * nilai. Dipakai modul-modul yang datanya diambil sekaligus (Stored Procedure
 * atau SELECT satu hari) lalu disaring di PHP, bukan lewat WHERE/ORDER BY SQL.
 *
 * Dibuat sebagai trait, bukan kelas induk: modul yang memakainya tidak punya
 * hubungan "adalah sebuah" dengan pipeline ini, dan satu service bisa melayani
 * LEBIH DARI SATU grid (mis. ApprovalPhargaService: grid approval & grid cetak
 * ulang). Karena itu barisnya dioper masuk sebagai argumen -- pipeline tidak
 * pernah memanggil balik ke pemakainya untuk bertanya "data yang mana".
 *
 * Dipakai ApprovalPhargaService, ApprovalTopService, ApprovalPoService, &
 * ApprovalTripService.
 */
trait GridPipeline
{
    /**
     * Baris tampilan -> respons standar jqGrid (page, total, records, rows).
     * Pencarian dijalankan lebih dulu supaya jumlah halaman mengikuti hasil
     * saringan, bukan jumlah data mentah.
     */
    protected function buildGrid(array $rows, array $params): \stdClass
    {
        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['rows'] ?? 50);
        $sidx  = $params['sidx'] ?? '';
        $sord  = $params['sord'] ?? 'asc';

        $rows = $this->filterRows($rows, $params);

        if ($sidx !== '') {
            $rows = $this->sortRows($rows, $sidx, $sord);
        }

        $count       = count($rows);
        $total_pages = $count > 0 ? (int)ceil($count / $limit) : 0;

        if ($page > $total_pages && $total_pages > 0) {
            $page = $total_pages;
        }

        $start = max(0, ($limit * $page) - $limit);

        $responce          = new \stdClass();
        $responce->page    = $page;
        $responce->total   = $total_pages;
        $responce->records = $count;
        $responce->rows    = array_values(array_slice($rows, $start, $limit));

        return $responce;
    }

    /**
     * Kunci seluruh baris yang lolos saringan -- untuk fitur "pilih semua",
     * yang tidak boleh terbatas pada baris yang kebetulan sudah dimuat lazy
     * loading. Dikembalikan sebagai string agar cocok dibandingkan dengan value
     * checkbox di sisi klien (atribut HTML selalu string).
     */
    protected function keysOf(array $rows, array $params, string $field): array
    {
        $keys = array_column($this->filterRows($rows, $params), $field);

        return array_values(array_unique(array_map('strval', $keys)));
    }

    /**
     * Menerapkan filter pencarian jqGrid. Toolbar per-kolom (groupOp AND)
     * dan pencarian global (groupOp OR ke semua kolom) sama-sama dikirim
     * lewat parameter `filters` berformat standar jqGrid, jadi cukup satu
     * jalur pemrosesan di sisi server.
     */
    protected function filterRows(array $rows, array $params): array
    {
        $isSearch   = filter_var($params['_search'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $filtersRaw = $params['filters'] ?? '';

        if (!$isSearch || $filtersRaw === '') {
            return $rows;
        }

        $filters = json_decode((string)$filtersRaw, true);
        $rules   = $filters['rules'] ?? [];

        if (empty($rules)) {
            return $rows;
        }

        $groupOp = strtoupper($filters['groupOp'] ?? 'AND');

        return array_values(array_filter($rows, function ($row) use ($rules, $groupOp) {
            foreach ($rules as $rule) {
                $match = $this->matchesRule($row, $rule);

                if ($groupOp === 'OR' && $match) {
                    return true;
                }
                if ($groupOp === 'AND' && !$match) {
                    return false;
                }
            }

            return $groupOp === 'AND';
        }));
    }

    /**
     * Mengecek satu baris terhadap satu rule pencarian (field, operator, data)
     * mengikuti operator standar jqGrid (cn, eq, bw, gt, dst).
     */
    protected function matchesRule(array $row, array $rule): bool
    {
        $field  = $rule['field'] ?? '';
        $op     = $rule['op'] ?? 'cn';
        $needle = trim((string)($rule['data'] ?? ''));

        if ($needle === '' || !array_key_exists($field, $row)) {
            return true;
        }

        $value = (string)$row[$field];

        // Operator perbandingan diserahkan ke compareForSort(), yang membaca
        // angka (termasuk yang berpemisah ribuan) sbg angka dan tanggal sbg
        // tanggal -- jadi "> 1,000,000" berarti sama dengan "> 1000000".
        switch ($op) {
            case 'lt': return $this->compareForSort($value, $needle) < 0;
            case 'le': return $this->compareForSort($value, $needle) <= 0;
            case 'gt': return $this->compareForSort($value, $needle) > 0;
            case 'ge': return $this->compareForSort($value, $needle) >= 0;
        }

        // Sisanya operator teks. Bentuk negatifnya (nc/ne/bn/en) dihitung
        // sebagai kebalikan dari pasangan positifnya, BUKAN dicocokkan sendiri:
        // matchesLoose() mencoba beberapa bentuk nilai, dan "tidak mengandung"
        // harus berarti tidak cocok pada SEMUA bentuk itu.
        $positif = [
            'cn' => 'cn', 'nc' => 'cn',
            'eq' => 'eq', 'ne' => 'eq',
            'bw' => 'bw', 'bn' => 'bw',
            'ew' => 'ew', 'en' => 'ew',
        ][$op] ?? 'cn';

        $cocok = $this->matchesLoose($positif, $value, $needle);

        return in_array($op, ['nc', 'ne', 'bn', 'en'], true) ? !$cocok : $cocok;
    }

    /**
     * Mencocokkan nilai sel dengan kata kunci, dicoba dalam dua bentuk.
     *
     * 1. Apa adanya -- supaya user bisa mengetik PERSIS seperti yang terlihat
     *    di grid, mis. "2,7" pada sel "2,736,213,726.00".
     * 2. Nilai tanpa pemisah ribuan -- supaya kebiasaan mengetik angka polos
     *    ("2736") tetap menemukan sel yang sama.
     *
     * Yang dibuang pemisahnya hanya NILAI-nya, tidak pernah kata kuncinya.
     * Begitu user mengetik koma, ia sedang menyalin bentuk yang terlihat di
     * layar; membuang koma itu justru MELEBARKAN pencarian -- "2," akan ikut
     * menjaring "250.00" -- persis kebalikan dari yang ia maksud.
     */
    protected function matchesLoose(string $op, string $value, string $needle): bool
    {
        if ($this->matchesText($op, $value, $needle)) {
            return true;
        }

        if (str_contains($needle, ',')) {
            return false;
        }

        $valuePolos = str_replace(',', '', $value);

        return $valuePolos !== $value && $this->matchesText($op, $valuePolos, $needle);
    }

    protected function matchesText(string $op, string $value, string $needle): bool
    {
        $v = mb_strtolower($value);
        $n = mb_strtolower($needle);

        switch ($op) {
            case 'eq': return $v === $n;
            case 'bw': return str_starts_with($v, $n);
            case 'ew': return str_ends_with($v, $n);
            case 'cn':
            default:   return str_contains($v, $n);
        }
    }

    /**
     * Mengurutkan baris berdasarkan kolom (sidx) & arah (sord) dari jqGrid.
     */
    protected function sortRows(array $rows, string $sidx, string $sord): array
    {
        if (!isset($rows[0]) || !array_key_exists($sidx, $rows[0])) {
            return $rows;
        }

        $direction = strtolower($sord) === 'desc' ? -1 : 1;

        usort($rows, function ($a, $b) use ($sidx, $direction) {
            return $this->compareForSort($a[$sidx], $b[$sidx]) * $direction;
        });

        return $rows;
    }

    /**
     * Bandingkan dua nilai secara "cerdas": angka sebagai angka, tanggal
     * (format d-m-Y, dengan atau tanpa jam) sebagai tanggal, selebihnya
     * sebagai teks tanpa memperhatikan huruf besar/kecil.
     */
    protected function compareForSort($a, $b): int
    {
        $angkaA = $this->toNumber($a);
        $angkaB = $this->toNumber($b);

        if ($angkaA !== null && $angkaB !== null) {
            return $angkaA <=> $angkaB;
        }

        $isDateFormat = static fn ($v) => preg_match('/^\d{2}-\d{2}-\d{4}( \d{2}:\d{2}(:\d{2})?)?$/', (string)$v) === 1;

        if ($isDateFormat($a) && $isDateFormat($b)) {
            return strtotime((string)$a) <=> strtotime((string)$b);
        }

        return strcasecmp((string)$a, (string)$b);
    }

    /**
     * Nilai -> float, termasuk bentuk tampilan kolom uang ("2,736,213,726.00").
     * Mengembalikan null bila bukan angka, sehingga pemanggilnya tahu harus
     * jatuh ke perbandingan tanggal/teks.
     *
     * Pola ribuannya sengaja ketat (kelompok 3 digit): tanpa itu, teks biasa
     * yang kebetulan memuat koma -- mis. nama shipper "PT. ABC, TBK" -- ikut
     * dianggap angka dan urutan kolom teks jadi kacau.
     */
    protected function toNumber($nilai): ?float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        $teks = trim((string) $nilai);

        if ($teks === '') {
            return null;
        }

        if (is_numeric($teks)) {
            return (float) $teks;
        }

        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $teks) === 1) {
            return (float) str_replace(',', '', $teks);
        }

        return null;
    }

    /**
     * Tanggal dari DB (string, sudah ReturnDatesAsStrings) -> format tampilan
     * d-m-Y / d-m-Y H:i:s.
     *
     * Nilai kosong dibiarkan kosong, dan 1900-01-01 diperlakukan sama: itu
     * default kolom NOT NULL seperti TPenawaranHarga.FTglApp, yang artinya
     * "belum pernah diproses" -- menampilkannya apa adanya jelas menyesatkan.
     */
    protected function formatTanggal($nilai, bool $denganJam = false): string
    {
        $nilai = trim((string) $nilai);

        if ($nilai === '' || str_starts_with($nilai, '1900-01-01')) {
            return '';
        }

        $waktu = strtotime($nilai);

        if ($waktu === false) {
            return $nilai;
        }

        return date($denganJam ? 'd-m-Y H:i:s' : 'd-m-Y', $waktu);
    }

    /**
     * Angka -> bentuk tampilan kolom uang ("2,736,213,726.00").
     *
     * Format kolom uang HARUS ditentukan di server saja. Begitu klien ikut
     * memformat (mis. formatter 'number' di colModel), teks yang dilihat user
     * berbeda dari teks yang dicocokkan pipeline ini, dan pencarian atas angka
     * berkoma berhenti bekerja.
     */
    protected function formatUang($nilai): string
    {
        return number_format(is_numeric($nilai) ? (float) $nilai : 0, 2, '.', ',');
    }
}
