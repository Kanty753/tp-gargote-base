<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return $this->response->setJSON([
            'application' => 'Gargote API',
            'documentation' => 'Voir README.md — toutes les routes commencent par /api',
        ]);
    }
}
