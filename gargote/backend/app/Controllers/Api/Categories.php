<?php

namespace App\Controllers\Api;

use App\Models\CategorieModel;

class Categories extends ApiController
{
    /** GET /api/categories */
    public function index()
    {
        $categories = model(CategorieModel::class)->orderBy('libelle')->findAll();

        return $this->respond($categories);
    }
}
