<?php

namespace App\Controllers\Api;

use App\Exceptions\RegleMetierException;
use App\Models\CommandeModel;
use App\Services\CommandeService;

class Commandes extends ApiController
{
    /** GET /api/commandes */
    public function index()
    {
        return $this->respond(model(CommandeModel::class)->listerAvecTotal());
    }

    /** GET /api/commandes/{id} — la commande et ses lignes */
    public function show($id = null)
    {
        $commande = model(CommandeModel::class)->detail((int) $id);

        return $commande ? $this->respond($commande) : $this->erreur("Commande $id introuvable.", 404);
    }

    /**
     * POST /api/commandes
     * Corps attendu :
     * {
     *   "numero_table": 4,
     *   "lignes": [ { "id_plat": 2, "quantite": 1 }, ... ]
     * }
     */
    public function create()
    {
        try {
            $id = (new CommandeService())->creer($this->donneesJson());
        } catch (RegleMetierException $e) {
            return $this->erreurMetier($e);
        }

        return $this->respondCreated(model(CommandeModel::class)->detail($id));
    }
}
