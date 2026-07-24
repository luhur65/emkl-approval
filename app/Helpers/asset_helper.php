<?php

if (! function_exists('asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param string $path
     * @return string
     */
    function asset(string $path): string
    {
        $filePathOnly = parse_url($path, PHP_URL_PATH);
        $cleanPath    = ltrim($filePathOnly, '/');

        // Cek 1: di luar public/ dulu (FCPATH langsung)
        $pathOutside = FCPATH . $cleanPath;

        // Cek 2: kalau tidak ada, cari di dalam public/
        $pathPublic  = FCPATH . 'public/' . $cleanPath;

        if (file_exists($pathOutside)) {
            $version   = filemtime($pathOutside);
            $separator = (strpos($path, '?') !== false) ? '&' : '?';
            return base_url($path) . $separator . 'v=' . $version;
        }

        if (file_exists($pathPublic)) {
            $version   = filemtime($pathPublic);
            $separator = (strpos($path, '?') !== false) ? '&' : '?';
            return base_url('public/' . $cleanPath) . $separator . 'v=' . $version;
        }

        // Tidak ketemu di mana pun, fallback ke public/
        return base_url('public/' . $cleanPath);
    }
}

