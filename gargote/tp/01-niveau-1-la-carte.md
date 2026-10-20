# Niveau 1 — La carte s’enrichit

> **Durée :** 3 h
> **Rendu :** `database/niveau1_schema.sql`, `database/niveau1_donnees.sql`, le code modifié, un commit par tâche

## Ce que demande Bao

> « Les clients me demandent tout le temps ce qu’il y a dans le *ravitoto*. Je veux une petite description sous chaque plat.
> Quand il n’y a plus de *mi sao*, je veux le marquer *épuisé* sans le supprimer : demain il reviendra.
> Et chaque matin je choisis un plat du jour. Je veux le voir en haut de la carte, et me souvenir de ce que j’ai proposé les jours d’avant.
> Ah, et la semaine dernière quelqu’un a enregistré un plat à 0 Ar. Ça ne doit plus arriver. »

## Ce que vous allez apprendre

- Faire évoluer un schéma existant avec `ALTER TABLE` (migration).
- Choisir entre une colonne et une table pour représenter une information.
- Écrire des données de test qui ne dépendent pas des identifiants.
- Déclarer des règles de validation dans un modèle CodeIgniter et écrire une règle maison.
- Placer une règle métier dans un service.
- Créer un composant Vue avec props et événements, et le réutiliser dans deux écrans.
- Organiser un CSS autour de variables.

---

## A. Base de données

### 1.1 Script de migration

Créez `database/niveau1_schema.sql`. Il modifie le schéma **existant** (pas de `DROP TABLE`).

| # | Modification | Contrainte |
|---|---|---|
| a | `plat.description` | texte de 255 caractères au plus, facultatif |
| b | `plat.disponible` | booléen, obligatoire, vrai par défaut |
| c | `plat.prix` | strictement positif (`CHECK`) |
| d | `plat.nom` | unique |
| e | nouvelle table `plat_du_jour` | voir ci-dessous |

**Le plat du jour.** On veut savoir quel plat était proposé chaque jour, et il ne peut y en avoir qu’un par jour.

```
plat_du_jour (date_jour, #id_plat)
```

1. Écrivez la table. Quelle colonne choisissez-vous comme clé primaire ? En quoi ce choix **garantit-il** la règle « un seul plat du jour par jour », sans aucune ligne de PHP ?
2. Un camarade propose plutôt d’ajouter une colonne `est_plat_du_jour BOOLEAN` dans `plat`. Donnez **deux** informations ou garanties que l’on perdrait avec sa solution.

> **Coup de pouce.** `ALTER TABLE plat ADD COLUMN …`, `ALTER TABLE plat ADD CONSTRAINT ck_plat_prix CHECK (…)`. Nommez vos contraintes : le message d’erreur de PostgreSQL sera plus lisible.

**Vérification :** exécutez le script deux fois de suite sur une base neuve (`reset.sh` puis votre script). La seconde exécution échoue-t-elle ? Est-ce normal ? *(Bonus : rendez le script ré-exécutable avec `IF NOT EXISTS`.)*

### 1.2 Données de test

Créez `database/niveau1_donnees.sql` :

- la catégorie *Goûter* (attention, elle existe peut-être déjà depuis le niveau 0 : utilisez `ON CONFLICT (libelle) DO NOTHING`) ;
- au moins **5 plats** de goûter avec une description : *mofo gasy, mokary, koba, godrogodro, menakely…* ;
- une description pour au moins **5 plats existants** (`UPDATE`) ;
- **2 plats épuisés** ;
- un plat du jour pour **aujourd’hui et les 6 jours précédents**.

**Règle importante :** n’écrivez **aucun identifiant en dur**. Le script doit fonctionner même si les `id` sont différents sur la machine de votre voisin.

```sql
-- Non : on suppose que Goûter a l'id 4
INSERT INTO plat (nom, prix, id_categorie) VALUES ('Koba', 1000, 4);

-- Oui : on retrouve l'id à partir du libellé
INSERT INTO plat (nom, prix, id_categorie, description)
SELECT 'Koba', 1000, id, 'Pâte d''arachide, banane et farine de riz, cuite en feuille de bananier'
FROM categorie WHERE libelle = 'Goûter';
```

Pour les dates, `CURRENT_DATE - 3` donne la date d’il y a trois jours.

3. Essayez d’insérer un plat à 0 Ar et un plat dont le nom existe déjà. Copiez les messages d’erreur de PostgreSQL dans vos réponses.

---

## B. API et code métier

### 1.3 Le modèle `PlatModel`

Un champ ajouté en base n’est pas automatiquement géré par CodeIgniter.

- Ajoutez `description` et `disponible` à `$allowedFields`.
- Ajoutez `disponible` à `$casts` avec le type `boolean`. Sans cela, PostgreSQL renvoie `"t"` ou `"f"`, et en JavaScript la chaîne `"f"` est… vraie.

**Test :** `curl http://localhost:8080/api/plats/1` doit renvoyer `"disponible": true` (sans guillemets autour de `true`).

### 1.4 Filtrer les plats disponibles

`GET /api/plats?disponible=1` ne renvoie que les plats disponibles. Le filtre `?categorie=` doit continuer de fonctionner, **seul ou combiné** : `?categorie=1&disponible=1`.

> **Coup de pouce.** Ajoutez un paramètre à `listerAvecCategorie()` ; le contrôleur ne fait que lire `$this->request->getGet('disponible')`.

### 1.5 Le plat du jour

Deux nouvelles routes :

| Méthode | URL | Rôle | Codes |
|---|---|---|---|
| GET | `/api/plat-du-jour` | le plat du jour d’aujourd’hui, ou `null` s’il n’est pas encore choisi | 200 |
| PUT | `/api/plat-du-jour` | corps `{"id_plat": 4}` : choisit (ou remplace) le plat du jour d’aujourd’hui | 200, 409, 422 |
| GET | `/api/plat-du-jour/historique` | les 7 derniers jours, du plus récent au plus ancien | 200 |

Organisation attendue :

```
Controllers/Api/PlatDuJour.php      lit la requête, renvoie la réponse
Services/PlatDuJourService.php      les règles
Models/PlatDuJourModel.php          la table plat_du_jour
```

Règles métier, dans le service :

| Règle | Si elle n’est pas respectée |
|---|---|
| R1 — le plat existe | 422 |
| R2 — un plat **épuisé** ne peut pas devenir plat du jour | 409, message : *« Le mi sao est épuisé : il ne peut pas être plat du jour. »* |
| R3 — choisir un plat du jour alors qu’il en existe déjà un aujourd’hui **le remplace** | pas une erreur |

> **Coup de pouce pour R3.** PostgreSQL sait faire « insérer, ou mettre à jour si la clé existe déjà » en une requête :
> ```sql
> INSERT INTO plat_du_jour (date_jour, id_plat) VALUES (CURRENT_DATE, ?)
> ON CONFLICT (date_jour) DO UPDATE SET id_plat = EXCLUDED.id_plat;
> ```
> Exécutez-la avec `$this->db->query($sql, [$idPlat])` dans le modèle.

4. Pourquoi R2 renvoie-t-elle 409 et pas 422 ? *(Relisez le tableau des codes dans le README.)*
5. Que doit-il se passer si Bao marque comme épuisé le plat qui est **déjà** plat du jour ? Proposez un comportement et justifiez-le. Vous n’êtes pas obligé de le coder.

---

## C. Validations

### 1.6 Les règles du modèle

Complétez `$validationRules` dans `PlatModel` :

| Champ | Règle |
|---|---|
| `nom` | obligatoire, 2 à 100 caractères, **unique** |
| `prix` | obligatoire, entier **strictement positif**, 100 000 Ar au plus |
| `id_categorie` | obligatoire, doit **exister** dans `categorie` |
| `description` | facultative, 255 caractères au plus |
| `disponible` | facultatif, doit être un booléen |

> **Coups de pouce.**
> - Les règles de CodeIgniter sont listées dans la [documentation](https://codeigniter.com/user_guide/libraries/validation.html#available-rules). Cherchez `is_natural_no_zero`, `less_than_equal_to`, `is_not_unique`, `permit_empty`.
> - Pour l’unicité, `is_unique[plat.nom]` refuse aussi de **modifier** un plat sans changer son nom (il se trouve lui-même !). Écrivez `is_unique[plat.nom,id,{id}]` et ajoutez la règle `'id' => 'permit_empty|is_natural_no_zero'`. Le contrôleur `Plats::update()` ajoute déjà l’`id` aux données pour vous.
> - Il n’existe pas de règle « booléen » dans CodeIgniter. Ouvrez `app/Validation/GargoteRules.php` : une règle maison `telephone_mg` y sert d’exemple. Écrivez sur le même modèle une règle `est_booleen` qui accepte `true`, `false`, `0`, `1`, `"0"` et `"1"`.

Les messages par défaut sont en français, mais ils parlent de « champ id_categorie ». Personnalisez au moins les messages de `nom`, `prix` et `id_categorie` pour qu’ils soient compréhensibles par Bao.

### 1.7 Jeu de tests

Avant d’écrire le moindre composant Vue, vérifiez **chaque ligne** de ce tableau avec `curl`, **dans l’ordre**, et collez les réponses dans vos réponses :

| Requête | Code attendu |
|---|---|
| `POST /api/plats` `{"nom":"Café","prix":1000,"id_categorie":3}` (le nom existe déjà) | 422, erreur sur `nom` |
| `PUT /api/plats/4` `{"nom":"Romazava","prix":7500}` (on garde son propre nom) | 200 |
| `POST /api/plats` `{"nom":"Thé","prix":0,"id_categorie":3}` | 422, erreur sur `prix` |
| `POST /api/plats` `{"nom":"Thé","prix":500,"id_categorie":99}` | 422, erreur sur `id_categorie` (**pas 500**) |
| `PUT /api/plats/7` `{"disponible":"oui"}` | 422 |
| `PUT /api/plats/7` `{"disponible":false}` | 200 |
| `PUT /api/plat-du-jour` `{"id_plat":7}` (le *mi sao* est épuisé) | 409 |
| `GET /api/plats?categorie=2&disponible=1` | 200, sans le *mi sao* |

6. Avant votre règle `is_not_unique`, la quatrième requête renvoyait 500. Pourquoi ? Qui détectait l’erreur, et pourquoi est-ce un problème de laisser PostgreSQL la détecter seul ?

---

## D. Interface Vue

Ajoutez d’abord les nouvelles fonctions dans `src/api.js` (`api.platDuJour.lire()`, `api.platDuJour.definir(idPlat)`, `api.platDuJour.historique()`). Aucun composant ne doit appeler `fetch()` directement.

### 1.8 Formulaire de plat

Dans `PlatForm.vue` :

- un champ **Description** (`<textarea>`) ;
- une case à cocher **Disponible** (cochée par défaut pour un nouveau plat) ;
- les erreurs de ces deux champs s’affichent sous le champ, comme pour les autres.

### 1.9 L’ardoise

Dans `PlatList.vue` :

- la description apparaît sous le nom du plat, en plus petit ;
- un plat épuisé est grisé, son nom barré, avec un badge **Épuisé** ;
- un nouveau bouton **Épuisé** / **Remettre** bascule la disponibilité. Le composant **émet** un événement `basculer` ; c’est `CarteView.vue` qui appelle l’API puis recharge la liste.

Dans `CommandeView.vue` : un plat épuisé reste visible mais on ne peut pas l’ajouter au panier (`:disabled`).

7. Si l’on désactive le bouton dans l’interface, un plat épuisé peut-il quand même être commandé ? Prouvez-le avec une requête `curl`. *(Vous corrigerez ce trou au niveau 2.)*

### 1.10 Le composant `PlatDuJour.vue`

Créez `src/components/PlatDuJour.vue` et utilisez-le dans **deux** écrans :

| Écran | Prop `modifiable` | Comportement |
|---|---|---|
| `CarteView` | `true` | affiche le plat du jour ; une liste déroulante des plats **disponibles** et un bouton **Choisir** permettent de le changer |
| `CommandeView` | `false` | affiche le plat du jour ; un clic dessus émet `choisir` et le plat s’ajoute au panier |

S’il n’y a pas encore de plat du jour, le composant l’indique et, en mode modifiable, invite à en choisir un. Si l’API répond 409, le message s’affiche dans le composant.

```vue
<!-- Utilisation attendue -->
<PlatDuJour :modifiable="true" :plats="plats" />
<PlatDuJour :modifiable="false" @choisir="ajouter" />
```

**Bonus :** un lien **Voir les 7 derniers jours** déplie l’historique sous le plat du jour.

---

## E. CSS

### 1.11 Styles des nouveautés

Dans `style.css`, en ajoutant une section numérotée comme les autres :

- `.badge` (forme générale) et `.badge-epuise` (couleur) ;
- `.plat-epuise` : ligne grisée, nom barré ;
- `.plat-description` : plus petit, légèrement transparent, sur sa propre ligne ;
- la bannière du plat du jour : elle doit se distinguer de l’ardoise au premier coup d’œil. Le style est libre, à condition d’utiliser les variables.

### 1.12 Chasser les couleurs en dur

Le CSS fourni contient encore **des couleurs écrites directement** en dehors de `:root` (le cadre en bois de l’ardoise, le fond des messages, la ligne sélectionnée…).

- Trouvez-les toutes (`grep -n "#" src/style.css` vous aidera).
- Remplacez chacune par une variable déclarée dans `:root`, avec un nom qui dit **à quoi elle sert** (`--cadre`, `--fond-succes`…), pas de quoi elle a l’air (`--marron`).

**Preuve :** changez uniquement les valeurs dans `:root` pour donner à la gargote une autre identité (par exemple une ardoise bleu nuit et un accent vert). Faites deux captures d’écran, avant et après, sans avoir touché à une seule autre ligne du CSS.

---

## Validation du niveau 1

- [ ] `reset.sh`, puis `niveau1_schema.sql`, puis `niveau1_donnees.sql` s’exécutent sans erreur.
- [ ] Les 8 requêtes du tableau 1.7 donnent le code attendu. Aucune erreur 500.
- [ ] La carte affiche descriptions, plats épuisés et plat du jour ; on peut basculer un plat en épuisé et choisir le plat du jour depuis l’interface.
- [ ] `PlatDuJour.vue` est utilisé dans deux écrans, avec deux comportements différents.
- [ ] Plus aucune couleur en dur hors de `:root` ; deux captures d’écran montrent deux thèmes.
- [ ] Réponses aux questions 1 à 7 dans `tp/reponses-niveau-1.md`.
