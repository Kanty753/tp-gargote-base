<?php

namespace App\Controllers\Api;

use App\Models\PlatModel;

class Plats extends ApiController
{
    private PlatModel $plats;

    public function __construct()
    {
        $this->plats = model(PlatModel::class);
    }

    /**
     * GET /api/plats
     * GET /api/plats?categorie=2   (filtre facultatif)
     */
    public function index()
    {
        $idCategorie = $this->request->getGet('categorie');

        return $this->respond($this->plats->listerAvecCategorie(
            $idCategorie !== null ? (int) $idCategorie : null
        ));
    }

    /** GET /api/plats/{id} */
    public function show($id = null)
    {
        $plat = $this->plats->find($id);

        return $plat ? $this->respond($plat) : $this->erreur("Plat $id introuvable.", 404);
    }

    /** POST /api/plats */
    public function create()
    {
        $donnees = $this->donneesJson();

        $id = $this->plats->insert($donnees);
        if ($id === false) {
            return $this->erreurValidation($this->plats->errors());
        }

        return $this->respondCreated($this->plats->find($id));
    }

    /** PUT /api/plats/{id} */
    public function update($id = null)
    {
        if (! $this->plats->find($id)) {
            return $this->erreur("Plat $id introuvable.", 404);
        }

        $donnees       = $this->donneesJson();
        $donnees['id'] = (int) $id; // permet la règle is_unique[plat.nom,id,{id}]

        if (! $this->plats->update($id, $donnees)) {
            return $this->erreurValidation($this->plats->errors());
        }

        return $this->respond($this->plats->find($id));
    }

    /** DELETE /api/plats/{id} */
    public function delete($id = null)
    {
        if (! $this->plats->find($id)) {
            return $this->erreur("Plat $id introuvable.", 404);
        }

        try {
            $this->plats->delete($id);
        } catch (\Throwable $e) {
            // Le plat apparaît dans des commandes : la clé étrangère bloque la suppression.
            return $this->erreur('Ce plat figure déjà dans des commandes, il ne peut pas être supprimé.', 409);
        }

        return $this->respondDeleted(['id' => (int) $id]);
    }
}
