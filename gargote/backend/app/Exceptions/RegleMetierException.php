<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception levée quand une règle métier n'est pas respectée
 * (ex. : commande vide, plat inexistant...).
 *
 * Le contrôleur la transforme en réponse HTTP 422 :
 *   { "message": "...", "errors": { "champ": "détail" } }
 */
class RegleMetierException extends RuntimeException
{
    private array $errors;

    public function __construct(string $message, array $errors = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
