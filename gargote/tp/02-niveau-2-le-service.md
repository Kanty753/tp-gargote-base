# Niveau 2 — Le service en salle

> **Durée :** 4 h
> **Rendu :** `database/niveau2_schema.sql`, `database/niveau2_donnees.sql`, le code modifié, un commit par tâche
> **Prérequis :** niveau 1 terminé

## Ce que demande Bao

> « J’ai embauché trois serveurs : Tiana, Haja et Fara. Je veux savoir qui a pris quelle commande.
> Une commande, ça vit : on la prend, on la sert, le client paie. Parfois il change d’avis avant qu’on serve, alors on l’annule.
> Les clients paient en espèces ou par mobile money, je dois savoir comment.
> Hier, deux serveurs ont ouvert chacun une commande pour la table 4 : on a servi deux fois le même client. Et quelqu’un a commandé un mi sao alors qu’il n’y en avait plus. »

## Ce que vous allez apprendre

- Ajouter une colonne obligatoire à une table qui contient déjà des données.
- Exprimer une règle de gestion par une contrainte `CHECK` et par un index unique partiel.
- Modéliser un **cycle de vie** (machine à états) et le faire respecter côté serveur.
- Distinguer « données invalides » (422) et « opération impossible dans l’état actuel » (409).
- Centraliser les paramètres de l’application dans un fichier de configuration.
- Faire décider le serveur, et laisser l’interface seulement **afficher** ce qui est permis.

---

## A. Base de données

### 2.1 Script de migration

Créez `database/niveau2_schema.sql`.

**a) Les serveurs.**

```
serveur (id, nom, telephone, actif)
```

`telephone` est obligatoire et unique ; `actif` vaut vrai par défaut (on ne supprime pas un serveur qui part : on le désactive, ses commandes restent).

**b) Le serveur d’une commande.** Chaque commande doit avoir un serveur : `commande.id_serveur` est **obligatoire**. Mais la table contient déjà des commandes, qui n’en ont pas.

1. Que répond PostgreSQL si vous ajoutez directement `id_serveur INTEGER NOT NULL REFERENCES serveur(id)` ? Pourquoi ?
2. Faites la migration en trois temps : ajouter la colonne **sans** `NOT NULL`, remplir les commandes existantes, puis `ALTER COLUMN … SET NOT NULL`. Pour l’étape intermédiaire, insérez un serveur *« Bao (historique) »* et attribuez-lui les anciennes commandes.

**c) Le cycle de vie d’une commande.**

| Colonne | Type | Contrainte |
|---|---|---|
| `statut` | texte | obligatoire, `en_cours` par défaut, une valeur parmi `en_cours`, `servie`, `payee`, `annulee` |
| `mode_paiement` | texte | facultatif, une valeur parmi `especes`, `mvola`, `orange_money`, `airtel_money` |
| `date_paiement` | `TIMESTAMP(0)` | facultatif |

**d) Une contrainte de cohérence.** Ajoutez cette contrainte et expliquez-la en français, en une phrase :

```sql
ALTER TABLE commande ADD CONSTRAINT ck_commande_paiement CHECK (
    (statut = 'payee') = (mode_paiement IS NOT NULL AND date_paiement IS NOT NULL)
);
```

**e) Les lignes de commande.** La quantité est strictement positive, et un même plat n’apparaît qu’**une fois** par commande : `UNIQUE (id_commande, id_plat)`.

**f) Une table, une commande ouverte.** Une commande est *ouverte* tant qu’elle est `en_cours` ou `servie`. Une table ne peut pas avoir deux commandes ouvertes en même temps. PostgreSQL peut le garantir avec un **index unique partiel** :

```sql
CREATE UNIQUE INDEX ux_commande_table_ouverte
    ON commande (numero_table)
    WHERE statut IN ('en_cours', 'servie');
```

3. Expliquez pourquoi un simple `UNIQUE (numero_table)` ne conviendrait pas.
4. Cette règle sera **aussi** vérifiée en PHP (règle R6 plus bas). Pourquoi la vérifier deux fois ? Que fait chacune des deux vérifications que l’autre ne fait pas ?

### 2.2 Données de test

Créez `database/niveau2_donnees.sql` :

- **3 serveurs actifs** (Tiana, Haja, Fara) et **1 inactif**, avec des numéros malgaches valides ;
- au moins **15 commandes** réparties sur **les 3 derniers jours**, avec les **4 statuts** représentés, chacune avec 1 à 4 lignes ;
- les commandes payées ont un mode et une date de paiement (variez les modes) ;
- toutes les contraintes doivent être respectées : notamment, **pas plus d’une commande ouverte par table**.

Le prix unitaire d’une ligne doit être le prix du plat : récupérez-le par une sous-requête plutôt que de le recopier à la main. Pour insérer une commande **et** ses lignes sans connaître l’`id` à l’avance, PostgreSQL permet d’enchaîner les deux :

```sql
WITH nouvelle AS (
    INSERT INTO commande (date_commande, numero_table, id_serveur, statut, mode_paiement, date_paiement)
    SELECT CURRENT_DATE - 1 + TIME '12:15', 3, id, 'payee', 'mvola', CURRENT_DATE - 1 + TIME '12:50'
    FROM serveur WHERE nom = 'Tiana'
    RETURNING id
)
INSERT INTO commande_ligne (id_commande, id_plat, quantite, prix_unitaire)
SELECT nouvelle.id, plat.id, 2, plat.prix
FROM nouvelle, plat
WHERE plat.nom IN ('Romazava', 'Café');
```

---

## B. Configuration

### 2.3 Les paramètres de la gargote

Le nombre de tables (12), la quantité maximale par ligne (20) et la liste des modes de paiement vont servir dans le service **et** dans l’interface. Ils ne doivent être écrits qu’**à un seul endroit**.

Créez `app/Config/Gargote.php` :

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Gargote extends BaseConfig
{
    public int $nombreTables = 12;
    public int $quantiteMax  = 20;

    /** code => libellé affiché */
    public array $modesPaiement = [
        'especes'      => 'Espèces',
        'mvola'        => 'MVola',
        'orange_money' => 'Orange Money',
        'airtel_money' => 'Airtel Money',
    ];
}
```

On le lit avec `config('Gargote')->nombreTables`.

Ajoutez la route `GET /api/parametres`, qui renvoie ces trois paramètres en JSON. L’interface les lira au lieu de les recopier.

5. Le nombre de tables pourrait aussi être une contrainte `CHECK (numero_table BETWEEN 1 AND 12)`. Quel inconvénient le jour où Bao ajoute deux tables en terrasse ? Où préférez-vous mettre cette règle ?

---

## C. API et code métier

### 2.4 Les serveurs

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/serveurs` | les serveurs **actifs** (`?tous=1` : tous) |
| POST | `/api/serveurs` | créer un serveur |
| PUT | `/api/serveurs/{id}` | modifier (dont désactiver) |

Validation dans `ServeurModel` : nom obligatoire (2 à 50 caractères), téléphone obligatoire, **unique**, au format malgache (la règle `telephone_mg` existe déjà dans `GargoteRules`), `actif` booléen (votre règle `est_booleen` du niveau 1).

### 2.5 Prendre une commande : les règles

`POST /api/commandes` reçoit désormais :

```json
{ "numero_table": 4, "id_serveur": 2, "lignes": [ { "id_plat": 2, "quantite": 1 } ] }
```

Complétez `CommandeService::creer()`. Chaque règle non respectée lève une `RegleMetierException` avec le bon code et un message que Bao comprend.

| Règle | Code | Exemple de message |
|---|---|---|
| R1 — le numéro de table est entre 1 et `nombreTables` | 422 | *« Il n’y a pas de table 15 (la gargote en compte 12). »* |
| R2 — le serveur existe **et** est actif | 422 | *« Ce serveur n’existe pas ou ne travaille plus ici. »* |
| R3 — chaque quantité est un entier entre 1 et `quantiteMax` | 422 | erreur sur `lignes.0.quantite` |
| R4 — si un même plat apparaît deux fois, les quantités sont **additionnées** en une seule ligne | — | pas une erreur |
| R5 — aucun plat n’est épuisé | 409 | *« Le mi sao est épuisé. »* |
| R6 — la table n’a pas déjà une commande ouverte | 409 | *« La table 4 a déjà une commande en cours (n° 12). »* |

6. Pourquoi R4 est-elle **nécessaire** depuis la tâche 2.1 e ? Que se passerait-il sans elle ?
7. R1 à R4 sont vérifiées **avant** le début de la transaction. Pourquoi est-ce préférable ?

### 2.6 Faire avancer une commande

Une commande suit ce cycle :

```
              servir              encaisser
  en_cours ──────────▶ servie ──────────────▶ payee
     │
     │ annuler
     ▼
  annulee
```

Toute autre transition est interdite : on ne « dé-sert » pas une commande, on ne rembourse pas une commande payée, on n’annule pas un plat déjà mangé.

**Nouvelle route :** `PATCH /api/commandes/{id}/statut`

```json
{ "statut": "payee", "mode_paiement": "orange_money" }
```

Écrivez `CommandeService::changerStatut(int $id, string $statut, ?string $modePaiement)`. Représentez les transitions permises par un **tableau**, pas par une cascade de `if` :

```php
private const TRANSITIONS = [
    'en_cours' => ['servie', 'annulee'],
    'servie'   => ['payee'],
    'payee'    => [],
    'annulee'  => [],
];
```

| Cas | Code |
|---|---|
| commande inexistante | 404 |
| `statut` absent ou inconnu (`"livree"`) | 422 |
| passage à `payee` sans `mode_paiement`, ou avec un mode inconnu | 422 |
| transition interdite (ex. `payee` → `en_cours`) | 409, *« Une commande payée ne peut plus changer de statut. »* |
| passage à `payee` valide | 200 ; `date_paiement` est remplie **par le serveur**, jamais par le client |

8. Pourquoi la date de paiement est-elle fixée par l’API et non envoyée par l’interface ?

### 2.7 Listes et détail enrichis

- `GET /api/commandes` accepte les filtres `?statut=en_cours` et `?date=2026-10-20`, seuls ou combinés. Chaque commande indique son statut et le **nom** du serveur.
- `GET /api/commandes/{id}` renvoie en plus le serveur, le statut, le mode et la date de paiement, et un champ **`transitions`** : la liste des statuts vers lesquels la commande peut passer maintenant (ex. `["servie","annulee"]`). Ce champ est calculé par le service, à partir de `TRANSITIONS`.

> **Coup de pouce.** La liste fait un `GROUP BY commande.id`. Pour afficher `serveur.nom`, joignez `serveur` et ajoutez `serveur.id` au `GROUP BY`.

9. Grâce au champ `transitions`, l’interface n’a pas besoin de connaître les règles du cycle de vie. Quel avantage le jour où Bao ajoute un statut `en_preparation` ?

### 2.8 Jeu de tests

Vérifiez chaque ligne avec `curl`, **dans l’ordre**, sur une base fraîchement rechargée (schéma + données des niveaux 0 à 2). Adaptez les `id` à vos données et notez-les.

| # | Requête | Code attendu |
|---|---|---|
| 1 | créer une commande table 15 | 422 |
| 2 | créer une commande avec le serveur inactif | 422 |
| 3 | créer une commande avec une quantité de 0 | 422 |
| 4 | créer une commande contenant un plat épuisé | 409 |
| 5 | créer une commande sur une table **libre**, avec deux fois le même plat | 201, une seule ligne, quantité additionnée |
| 6 | créer une seconde commande sur cette même table | 409 |
| 7 | passer la commande n° 5 à `payee` directement | 409 |
| 8 | la passer à `servie` | 200 |
| 9 | la passer à `payee` sans mode de paiement | 422 |
| 10 | la passer à `payee` avec `mvola` | 200, `date_paiement` remplie |
| 11 | recréer une commande sur cette table | 201 (la table est libérée) |
| 12 | `GET /api/commandes?statut=payee&date=` *(aujourd’hui)* | 200, contient la commande n° 5 |

10. Pour la requête 6, désactivez temporairement R6 dans le service et relancez-la. Quel code obtenez-vous, et qui a bloqué ? Rétablissez R6 ensuite.

---

## D. Interface Vue

Commencez par `api.js` : `api.parametres()`, `api.serveurs.lister()`, `api.commandes.lister(filtres)`, `api.commandes.changerStatut(id, statut, modePaiement)`.

### 2.9 Prendre une commande

Dans `CommandeView.vue` :

- une liste déroulante **Serveur** (serveurs actifs) ;
- le numéro de table devient une **liste de boutons** 1 à `nombre_tables` (lu depuis `/api/parametres`), plus pratique au doigt qu’un champ numérique ;
- le message 409 de l’API (table occupée, plat épuisé) s’affiche tel quel ;
- les erreurs de quantité (`lignes.0.quantite`…) s’affichent sur la bonne ligne du panier.

### 2.10 Le composant `StatutBadge.vue`

Une prop `statut` ; affiche le libellé lisible (*En cours, Servie, Payée, Annulée*) avec une classe CSS propre au statut. Utilisez-le dans la liste des commandes **et** dans le détail.

### 2.11 Le composant `ActionsStatut.vue`

Une prop `commande` (le détail, avec son champ `transitions`). Il affiche **un bouton par transition possible** et rien d’autre : *Servir*, *Encaisser*, *Annuler*.

- *Encaisser* demande d’abord le mode de paiement (liste lue dans `/api/parametres`), puis confirme.
- *Annuler* demande une confirmation.
- Après succès, le composant émet `change` ; l’écran parent recharge la liste et le détail.

Le composant ne contient **aucune** règle du cycle de vie : il ne fait qu’afficher `commande.transitions`.

### 2.12 L’écran des commandes

Dans `HistoriqueView.vue` :

- des filtres : un bouton par statut (*Toutes, En cours, Servies, Payées, Annulées*) et un champ date, **aujourd’hui** par défaut ;
- une colonne *Serveur* et une colonne *Statut* (avec `StatutBadge`) ;
- en bas de la liste : **total encaissé** sur la période affichée (somme des commandes payées), calculé avec `computed` ;
- la liste se recharge seule **toutes les 30 secondes**. Arrêtez le minuteur quand on quitte l’écran (`onUnmounted`), sinon les rechargements s’accumulent à chaque changement d’onglet.

11. Ouvrez l’onglet réseau des outils de développement, changez trois fois d’onglet, attendez une minute. Combien de requêtes `/api/commandes` voyez-vous avec, puis sans `onUnmounted` ?

---

## E. CSS

### 2.13 Les couleurs des statuts

Déclarez une variable par statut dans `:root` (`--statut-en-cours`…) et stylez les badges avec. Les quatre statuts doivent rester distinguables **sans la couleur** (une personne daltonienne doit pouvoir les lire) : le libellé est déjà là, vérifiez que le contraste texte/fond est suffisant.

### 2.14 Les commandes qui traînent

Une commande `en_cours` depuis **plus de 20 minutes** doit sauter aux yeux dans la liste (classe `en-retard`, calculée dans Vue à partir de `date_commande`).

- Choisissez un traitement visuel qui attire l’œil sans être agressif.
- Si vous utilisez une animation, désactivez-la pour les personnes qui l’ont demandé :

```css
@media (prefers-reduced-motion: reduce) {
  .en-retard { animation: none; }
}
```

---

## Validation du niveau 2

- [ ] Les scripts des niveaux 0, 1 et 2 s’enchaînent sans erreur sur une base vide.
- [ ] Les 12 requêtes de 2.8 donnent le code attendu. Aucune erreur 500.
- [ ] Le cycle de vie est représenté par un tableau `TRANSITIONS` ; le front n’en contient aucune copie.
- [ ] Les paramètres (tables, quantité max, modes de paiement) ne sont écrits qu’une fois, dans `Config/Gargote.php`.
- [ ] On peut prendre, servir, encaisser et annuler une commande depuis l’interface.
- [ ] Le rafraîchissement automatique s’arrête quand on quitte l’écran.
- [ ] Réponses aux questions 1 à 11 dans `tp/reponses-niveau-2.md`.
