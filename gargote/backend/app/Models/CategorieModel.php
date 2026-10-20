<?php

namespace App\Models;

use CodeIgniter\Model;

class CategorieModel extends Model
{
    protected $table         = 'categorie';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['libelle'];
    protected array $casts   = ['id' => 'int'];
}
