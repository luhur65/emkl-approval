<?php

namespace App\Controllers;

use App\Models\HomeModel;

class Home extends BaseController
{
    public function index()
    {
        $homeModel = new HomeModel();
        $jumlah = $homeModel->getJumlahBelumAppTop();

        $data['jumlah'] = $jumlah;

        return $this->render('home/index', $data);
    }
}
