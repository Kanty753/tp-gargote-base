<?php

namespace App\Models;

use CodeIgniter\Model;

class PlatModel extends Model
{
    protected $table      = 'plat';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    // PostgreSQL renvoie les nombres sous forme de texte : on les convertit.
    protected array $casts = [
        'id'           => 'int',
        'prix'         => 'int',
        'id_categorie' => 'int',
    ];

    // Seuls ces champs peuvent être écrits par insert() / update().
    // Un champ ajouté dans la table doit AUSSI être ajouté ici.
    protected $allowedFields = ['nom', 'prix', 'id_categorie'];

    // Règles de validation : vérifiées automatiquement avant insert() / update().
    // Liste des règles disponibles :
    // https://codeigniter.com/user_guide/libraries/validation.html#available-rules
    protected $validationRules = [
        'nom'          => 'required|max_length[100]',
        'prix'         => 'required|integer',
        'id_categorie' => 'required|integer',
    ];

    protected $validationMessages = [
        'nom' => [
            'required'   => 'Le nom du plat est obligatoire.',
            'max_length' => 'Le nom ne doit pas dépasser 100 caractères.',
        ],
        'prix' => [
            'required' => 'Le prix est obligatoire.',
            'integer'  => 'Le prix doit être un nombre entier (en Ariary).',
        ],
        'id_categorie' => [
            'required' => 'Choisissez une catégorie.',
        ],
    ];

    /**
     * Liste des plats avec le libellé de leur catégorie.
     * Si $idCategorie est fourni, ne garde que cette catégorie.
     */
    public function listerAvecCategorie(?int $idCategorie = null): array
    {
        $this->select('plat.*, categorie.libelle AS categorie')
            ->join('categorie', 'categorie.id = plat.id_categorie')
            ->orderBy('categorie.libelle')
            ->orderBy('plat.nom');

        if ($idCategorie !== null) {
            $this->where('plat.id_categorie', $idCategorie);
        }

        return $this->findAll();
    }
}
