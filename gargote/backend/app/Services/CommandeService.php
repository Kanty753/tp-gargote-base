<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\CommandeLigneModel;
use App\Models\CommandeModel;
use App\Models\PlatModel;

/**
 * Le CODE MÉTIER des commandes vit ici, pas dans le contrôleur.
 *
 * Le contrôleur reçoit la requête HTTP et renvoie la réponse ;
 * le service décide si l'opération est permise et l'exécute.
 * Chaque nouvelle règle métier = une vérification dans ce fichier
 * qui lève une RegleMetierException si elle n'est pas respectée.
 */
class CommandeService
{
    /**
     * Crée une commande et ses lignes. Renvoie l'id de la commande.
     *
     * @throws RegleMetierException
     */
    public function creer(array $donnees): int
    {
        $numeroTable = $donnees['numero_table'] ?? null;
        $lignes      = $donnees['lignes'] ?? [];

        // --- Règles métier -------------------------------------------------
        if (! is_numeric($numeroTable)) {
            throw new RegleMetierException('Numéro de table manquant.', [
                'numero_table' => 'Indiquez le numéro de la table.',
            ]);
        }

        if (! is_array($lignes) || $lignes === []) {
            throw new RegleMetierException('La commande est vide.', [
                'lignes' => 'Ajoutez au moins un plat.',
            ]);
        }

        $plats = model(PlatModel::class);

        // On prépare les lignes en récupérant le prix ACTUEL de chaque plat.
        $lignesPretes = [];
        foreach ($lignes as $i => $ligne) {
            $plat = $plats->find($ligne['id_plat'] ?? 0);
            if ($plat === null) {
                throw new RegleMetierException('Plat inconnu.', [
                    "lignes.$i.id_plat" => 'Ce plat n’existe pas.',
                ]);
            }

            $lignesPretes[] = [
                'id_plat'       => (int) $plat['id'],
                'quantite'      => (int) ($ligne['quantite'] ?? 1),
                'prix_unitaire' => (int) $plat['prix'],
            ];
        }

        // --- Enregistrement : tout ou rien (transaction) -------------------
        $db = db_connect();
        $db->transStart();

        $idCommande = model(CommandeModel::class)->insert([
            'numero_table' => (int) $numeroTable,
        ]);

        $lignesModel = model(CommandeLigneModel::class);
        foreach ($lignesPretes as $ligne) {
            $ligne['id_commande'] = $idCommande;
            $lignesModel->insert($ligne);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RegleMetierException('Erreur lors de l’enregistrement de la commande.', [], 500);
        }

        return (int) $idCommande;
    }
}
