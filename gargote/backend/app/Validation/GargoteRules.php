<?php

namespace App\Validation;

/**
 * Règles de validation propres à la gargote.
 *
 * Une règle = une méthode publique qui renvoie true (valide) ou false.
 * Le paramètre $erreur (facultatif) permet de fixer le message d'erreur.
 * Elles sont déclarées dans app/Config/Validation.php ($ruleSets)
 * et s'utilisent comme les règles de CodeIgniter :
 *
 *     'telephone' => 'required|telephone_mg'
 */
class GargoteRules
{
    /**
     * Numéro de mobile malgache : 10 chiffres commençant par 032, 033, 034, 037 ou 038.
     * Les espaces sont tolérés : « 034 12 345 67 » est accepté.
     */
    public function telephone_mg($valeur, ?string &$erreur = null): bool
    {
        $chiffres = preg_replace('/\D/', '', (string) $valeur);

        if (preg_match('/^03[23478]\d{7}$/', $chiffres) === 1) {
            return true;
        }

        $erreur = 'Numéro malgache attendu : 10 chiffres commençant par 032, 033, 034, 037 ou 038.';

        return false;
    }
}
