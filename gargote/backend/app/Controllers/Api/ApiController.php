<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Exceptions\RegleMetierException;
use CodeIgniter\API\ResponseTrait;

/**
 * Classe mère de tous les contrôleurs de l'API.
 * Elle regroupe les petites fonctions utilisées partout.
 */
abstract class ApiController extends BaseController
{
    use ResponseTrait;

    protected $format = 'json';

    /** Lit le corps JSON de la requête et le renvoie sous forme de tableau. */
    protected function donneesJson(): array
    {
        $donnees = $this->request->getJSON(true);

        return is_array($donnees) ? $donnees : [];
    }

    /**
     * Réponse d'erreur standard. Toutes les erreurs de l'API ont la même forme :
     *   { "message": "...", "errors": { "champ": "détail", ... } }
     */
    protected function erreur(string $message, int $code, array $errors = [])
    {
        return $this->respond(['message' => $message, 'errors' => (object) $errors], $code);
    }

    /** Réponse standard quand la validation échoue (HTTP 422). */
    protected function erreurValidation(array $errors, string $message = 'Données invalides.')
    {
        return $this->erreur($message, 422, $errors);
    }

    /** Réponse standard quand une règle métier est violée. */
    protected function erreurMetier(RegleMetierException $e)
    {
        return $this->erreur($e->getMessage(), $e->getCode(), $e->getErrors());
    }
}
