<?php

namespace App\Models;

use CodeIgniter\Model;

class CommandeLigneModel extends Model
{
    protected $table         = 'commande_ligne';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_commande', 'id_plat', 'quantite', 'prix_unitaire'];
}
