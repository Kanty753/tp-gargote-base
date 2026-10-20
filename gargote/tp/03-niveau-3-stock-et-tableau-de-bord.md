# Niveau 3 — Stock, recettes et tableau de bord

> **Durée :** 5 h
> **Rendu :** `database/niveau3_schema.sql`, `database/niveau3_donnees.sql`, le code modifié, un commit par tâche
> **Prérequis :** niveau 2 terminé

## Ce que demande Bao

> « Le soir, je ne sais jamais combien il me reste de henakisoa ou de ravitoto. Je veux que le stock baisse tout seul quand on commande, et qu’on m’empêche de vendre ce que je n’ai plus.
> Quand je fais le marché, je veux enregistrer ce que j’ai acheté. Et je veux savoir d’où vient chaque kilo : achat, vente, perte.
> À la fin du mois, je veux voir combien j’ai encaissé, quels plats marchent, quel serveur travaille le plus, et si les clients paient plutôt en espèces ou en mobile money.
> Enfin, quand un client demande l’addition, j’aimerais lui imprimer un vrai ticket. »

## Ce que vous allez apprendre

- Modéliser une association porteuse de données (une recette) et un journal de mouvements.
- Discuter d’une redondance volontaire et la contrôler.
- Créer des **vues SQL** et des requêtes d’agrégation (`GROUP BY`, `MIN`, `SUM`, fonctions de date).
- Générer un grand volume de données de test avec `generate_series`.
- Écrire du code métier qui touche plusieurs tables dans **une seule transaction**.
- Construire des composants Vue génériques, réutilisés avec des données différentes.
- Utiliser le CSS pour des graphiques simples, l’impression et les petits écrans.

---

## A. Base de données

### 3.1 Script de migration

Créez `database/niveau3_schema.sql`.

```
ingredient     (id, nom, unite, stock, seuil_alerte)
recette        (#id_plat, #id_ingredient, quantite)
mouvement_stock(id, #id_ingredient, date_mouvement, quantite, motif, #id_commande)
```

| Table | Contraintes |
|---|---|
| `ingredient` | `nom` unique ; `unite` parmi `kg`, `l`, `piece` ; `stock` ≥ 0 ; `seuil_alerte` ≥ 0. Les quantités sont des `NUMERIC(10,3)` (on achète 1,5 kg de viande). |
| `recette` | quantité d’un ingrédient pour **une portion** du plat, strictement positive. |
| `mouvement_stock` | `quantite` non nulle : **positive** pour une entrée, **négative** pour une sortie ; `motif` parmi `approvisionnement`, `commande`, `annulation`, `perte` ; `id_commande` facultatif. |

1. Quelle est la clé primaire de `recette` ? Justifiez.
2. Écrivez une contrainte `CHECK` qui impose : *`id_commande` est renseigné si et seulement si le motif est `commande` ou `annulation`*.
3. `ingredient.stock` est **redondant** : on pourrait le recalculer par `SUM(mouvement_stock.quantite)`. Donnez un avantage et un risque à le stocker quand même. Écrivez la requête qui liste les ingrédients dont le stock **ne correspond pas** à la somme de leurs mouvements (elle doit renvoyer zéro ligne en fin de TP).

### 3.2 Deux vues SQL

**a) `v_portions_possibles (id_plat, portions)`** : pour chaque plat **qui a une recette**, le nombre de portions que le stock actuel permet de servir. C’est l’ingrédient le plus limitant qui décide :

```
portions = MIN( FLOOR(stock de l'ingrédient / quantité dans la recette) )
```

Exemple : le *ravitoto sy henakisoa* demande 0,15 kg de ravitoto et 0,12 kg de henakisoa. Avec 3 kg de ravitoto et 0,5 kg de henakisoa, on peut en servir `MIN(20, 4) = 4`.

**b) `v_ventes_jour (jour, nb_commandes, chiffre_affaires)`** : pour chaque jour, le nombre de commandes **payées** et le montant encaissé.

4. Un plat sans recette (le café, par exemple, si vous ne lui en donnez pas) n’apparaît pas dans `v_portions_possibles`. Comment l’API devra-t-elle l’interpréter ?

### 3.3 Données de test

Créez `database/niveau3_donnees.sql` :

- au moins **10 ingrédients** (vary, henakisoa, ravitoto, voanio, akoho, tsaramaso, anana, mi, sakamalao, sira…) avec un seuil d’alerte ;
- une **recette pour chaque plat** de la catégorie *Plat* et *Soupe* ;
- le stock initial est créé **par des mouvements** `approvisionnement`, et la colonne `stock` est mise à jour en conséquence (votre requête de la question 3 doit renvoyer zéro ligne) ;
- au moins **2 ingrédients sous leur seuil d’alerte**, et **1 plat** dont les portions possibles sont à 0 ;
- **200 commandes payées** environ, réparties sur les **30 derniers jours**, entre 11 h et 14 h, avec des serveurs, des tables et des modes de paiement variés. Elles servent au tableau de bord ; on considère qu’elles sont antérieures à la mise en place du stock (pas de mouvements pour elles).

Écrire 200 commandes à la main est hors de question : utilisez `generate_series`.

```sql
-- 30 jours x 7 commandes, à une heure aléatoire entre 11 h et 14 h
SELECT jour + TIME '11:00' + random() * INTERVAL '3 hours' AS date_commande
FROM generate_series(CURRENT_DATE - 30, CURRENT_DATE - 1, INTERVAL '1 day') AS jour,
     generate_series(1, 7) AS n;
```

> **Coups de pouce.**
> - Pour tirer un élément au hasard dans une liste : `(ARRAY['especes','mvola','orange_money','airtel_money'])[1 + floor(random() * 4)::int]`.
> - Piège : `(SELECT id FROM serveur ORDER BY random() LIMIT 1)` placé dans un `SELECT … FROM generate_series(…)` n’est évalué **qu’une seule fois** : toutes les commandes auront le même serveur. Vérifiez votre résultat avec un `GROUP BY id_serveur`, et cherchez comment rendre la sous-requête *corrélée* (indice : `LATERAL`).
> - Pour les lignes, partez des commandes qui n’en ont pas encore : `WHERE NOT EXISTS (SELECT 1 FROM commande_ligne l WHERE l.id_commande = c.id)`.

5. Une fois les données chargées, vérifiez-les : nombre de commandes par jour, par serveur, par mode de paiement. Collez ces trois requêtes et leurs résultats.

---

## B. API et code métier

### 3.4 Le service de stock

Créez `app/Services/StockService.php`. Il est le **seul** endroit du code qui modifie `ingredient.stock`, et il le fait **toujours** en écrivant un mouvement en même temps.

| Méthode | Rôle |
|---|---|
| `besoins(array $lignes): array` | à partir des lignes d’une commande, calcule la quantité **totale** nécessaire de chaque ingrédient. Deux plats peuvent utiliser le même ingrédient : il faut additionner. |
| `verifier(array $besoins): void` | lève une `RegleMetierException` (409) si un ingrédient manque, avec le détail de **chaque** manque |
| `sortir(array $besoins, int $idCommande): void` | mouvements `commande` (quantités négatives) + mise à jour du stock |
| `reintegrer(int $idCommande): void` | remet en stock ce que la commande avait consommé (mouvements `annulation`) |
| `approvisionner(int $idIngredient, float $quantite): void` | mouvement `approvisionnement` + mise à jour du stock |

Exemple de réponse attendue quand le stock ne suffit pas :

```json
{
  "message": "Stock insuffisant pour cette commande.",
  "errors": {
    "henakisoa": "Il faut 0,360 kg, il en reste 0,200 kg.",
    "ravitoto":  "Il faut 0,450 kg, il en reste 0,300 kg."
  }
}
```

6. Une commande contient 2 *ravitoto sy henakisoa* et 1 *henakisoa sy tsaramaso*. Calculez à la main le besoin en henakisoa avec vos recettes. Votre méthode `besoins()` donne-t-elle le même résultat ?

### 3.5 Brancher le stock sur les commandes

- `CommandeService::creer()` : vérifie le stock **avant** la transaction ; dans la transaction, enregistre la commande, ses lignes **et** les sorties de stock. Si une seule de ces écritures échoue, rien ne doit rester en base.
- `CommandeService::changerStatut()` : le passage à `annulee` réintègre le stock.
- La règle R5 du niveau 2 (*plat épuisé*) s’étend : un plat dont les portions possibles sont à 0 est refusé de la même façon.

7. Deux serveurs envoient au même instant une commande qui utilise les **deux derniers** kilos de henakisoa. Les deux vérifications passent, les deux commandes s’enregistrent. Que se passe-t-il ensuite en base ? Quelle contrainte du schéma vous protège ?
8. **Bonus :** lisez la documentation de `SELECT … FOR UPDATE` et expliquez comment l’utiliser pour que la seconde commande soit refusée proprement.

### 3.6 Les routes du stock

| Méthode | URL | Rôle | Codes |
|---|---|---|---|
| GET | `/api/ingredients` | tous les ingrédients, avec un booléen `en_alerte` (stock ≤ seuil) | 200 |
| POST | `/api/ingredients/{id}/approvisionnement` | corps `{"quantite": 2.5}` | 200, 404, 422 |
| GET | `/api/ingredients/{id}/mouvements` | historique des mouvements, du plus récent au plus ancien | 200, 404 |
| GET | `/api/plats` | chaque plat a désormais un champ `portions` (`null` = pas de recette, donc pas de limite) | 200 |

Validation de l’approvisionnement : quantité **numérique**, strictement positive, 500 au plus. Attention : `2,5` (virgule) n’est pas un nombre pour PHP ; que renvoie votre API ?

### 3.7 Les statistiques

`GET /api/statistiques?du=2026-10-01&au=2026-10-20` renvoie, pour les **commandes payées** de la période :

```json
{
  "chiffre_affaires": 1845000,
  "nb_commandes": 172,
  "panier_moyen": 10727,
  "par_jour":    [ { "jour": "2026-10-01", "chiffre_affaires": 61500 } ],
  "top_plats":   [ { "plat": "Ravitoto sy henakisoa", "quantite": 96, "chiffre_affaires": 768000 } ],
  "par_serveur": [ { "serveur": "Tiana", "nb_commandes": 61, "chiffre_affaires": 650000 } ],
  "par_mode":    [ { "mode": "mvola", "nb_commandes": 58, "chiffre_affaires": 610000 } ]
}
```

- `top_plats` : les 5 plats qui rapportent le plus.
- `par_jour` : **tous** les jours de la période, y compris ceux sans vente (à 0). *(Indice : `generate_series` et `LEFT JOIN`.)*
- Les requêtes vont dans un `StatistiqueModel` ; le contrôleur ne contient aucun SQL.

| Cas | Code |
|---|---|
| `du` ou `au` absent | les 30 derniers jours par défaut |
| date mal formée (`2026-13-45`, `hier`) | 422 |
| `du` postérieur à `au` | 422 |
| période de plus de 366 jours | 422 |

9. Le panier moyen se calcule-t-il en SQL ou en PHP ? Défendez votre choix.

---

## C. Interface Vue

### 3.8 L’écran Stock

Nouvel onglet **Stock** (`views/StockView.vue`) :

- la liste des ingrédients avec stock, unité et seuil ; les ingrédients en alerte sont **en tête** et signalés ;
- sur chaque ligne, un bouton **Approvisionner** ouvre un petit formulaire (quantité) ; les erreurs 422 s’affichent dessous ;
- un clic sur un ingrédient affiche ses derniers mouvements (date, motif, quantité signée, n° de commande s’il y en a).

### 3.9 Les portions sur la carte

- Dans `PlatList.vue` et `CommandeView.vue`, un plat dont il reste **5 portions ou moins** affiche *« Plus que 3 »*.
- Un plat à 0 portion se comporte comme un plat épuisé.
- Si l’API refuse une commande pour stock insuffisant, le détail de **chaque** ingrédient manquant s’affiche.

### 3.10 Le tableau de bord

Nouvel onglet **Tableau de bord** (`views/TableauDeBordView.vue`) avec un choix de période : *Aujourd’hui*, *7 jours*, *30 jours*, *Personnalisée* (deux champs date).

Il est construit avec **deux composants génériques**, qui ne savent rien de la gargote :

| Composant | Props | Utilisé pour |
|---|---|---|
| `Indicateur.vue` | `libelle`, `valeur`, `detail` (facultatif) | chiffre d’affaires, nombre de commandes, panier moyen |
| `Histogramme.vue` | `donnees` : `[{ libelle, valeur }]`, `format` : une fonction qui met en forme la valeur | CA par jour, CA par serveur, CA par mode de paiement |

Les barres sont de simples `<div>` dont la largeur est proportionnelle à la valeur, **sans bibliothèque de graphiques** :

```vue
<div class="barre" :style="{ width: (100 * d.valeur / max) + '%' }"></div>
```

Les 5 meilleurs plats s’affichent dans un tableau.

10. `Histogramme.vue` reçoit une **fonction** en prop (`format`). Pourquoi ne pas formater les montants directement dans le composant ?

### 3.11 Le plan de salle

Nouveau composant `PlanSalle.vue`, en tête de l’écran **Commandes** : une grille de `nombre_tables` cases. Chaque case montre le numéro de la table et son état (*libre*, *en cours*, *servie*), avec la couleur du statut. Un clic sur une table occupée affiche le détail de sa commande ouverte.

Vous choisissez comment obtenir les données : appeler la liste existante avec des filtres, ou ajouter une route dédiée. Justifiez votre choix en une phrase dans vos réponses.

### 3.12 Le ticket de caisse

Dans le détail d’une commande **payée**, un bouton **Imprimer le ticket** appelle `window.print()`.

---

## D. CSS

### 3.13 L’histogramme

Stylez `.histogramme` : une ligne par donnée (libellé à gauche, barre au centre, valeur à droite), alignées en grille ; la barre utilise une variable de couleur. Les libellés longs ne doivent pas casser l’alignement.

### 3.14 Le ticket imprimé

Dans une section `@media print` :

- **seul** le ticket s’imprime : en-tête, onglets, liste et boutons sont masqués ;
- largeur de **80 mm** (le format des imprimantes de caisse), police à chasse fixe, texte noir sur fond blanc, aucune ombre ni bordure arrondie ;
- en haut : le nom de la gargote ; en bas : *« Misaotra tompoko ! »*, la date, le mode de paiement.

Testez avec l’aperçu avant impression du navigateur (ou « Enregistrer en PDF »).

### 3.15 Prendre une commande au téléphone

Les serveurs prennent les commandes sur leur téléphone. Ouvrez les outils de développement, mode **appareil mobile**, largeur **375 px**, écran *Prendre une commande* :

- rien ne doit déborder horizontalement ;
- les boutons de table et les boutons `+` / `−` font au moins **44 px** de côté (taille minimale confortable au doigt) ;
- le total et le bouton **Envoyer en cuisine** restent visibles en bas de l’écran pendant qu’on fait défiler la carte (`position: sticky`).

Joignez deux captures (375 px et 1 200 px).

---

## Validation du niveau 3

- [ ] Les scripts des niveaux 0 à 3 s’enchaînent sans erreur (`./reset.sh 3`).
- [ ] La requête de contrôle de la question 3 renvoie zéro ligne, **y compris après** avoir passé, annulé et approvisionné depuis l’interface.
- [ ] Une commande impossible faute de stock est refusée avec le détail des manques ; rien n’est écrit en base.
- [ ] Le tableau de bord affiche les bons chiffres : vérifiez le chiffre d’affaires d’un jour par une requête SQL directe.
- [ ] `Indicateur.vue` et `Histogramme.vue` sont chacun utilisés au moins trois fois.
- [ ] Le ticket s’imprime seul, au format 80 mm.
- [ ] L’écran de commande est utilisable à 375 px.
- [ ] Réponses aux questions 1 à 10 dans `tp/reponses-niveau-3.md`.

---

## Pour aller plus loin

Pour les plus rapides, au choix :

- **Vue Router** : remplacez les onglets par de vraies URL (`/carte`, `/commandes/12`…), pour pouvoir partager le lien d’une commande.
- **Tests automatiques** : écrivez des tests PHPUnit pour `CommandeService` (transitions interdites, stock insuffisant, table occupée). CodeIgniter fournit une base de test dans `backend/tests/`.
- **Connexion** : chaque serveur se connecte avec son téléphone et un code à 4 chiffres ; la commande prend automatiquement son `id_serveur`. Bao seul accède au stock et au tableau de bord.
- **Export** : `GET /api/statistiques/export?du=…&au=…` renvoie un fichier CSV que Bao ouvre dans un tableur.
- **Pertes** : un formulaire pour déclarer une perte (riz moisi, viande avariée) avec un commentaire ; le tableau de bord affiche la valeur des pertes du mois.
