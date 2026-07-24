<?php

namespace App\Controllers;

class Harilibur extends BaseController
{
    public function index()
    {
        // By default fetch for the current year
        $year = $this->request->getGet('year') ?? date('Y');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-hari-libur.vercel.app/api?year=" . $year);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // avoid SSL issues on local
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpcode == 200) {
            return $this->response->setJSON(json_decode($response));
        }
        
        return $this->response->setJSON([
            'status' => 'error',
            'code' => 500,
            'data' => []
        ]);
    }
}
