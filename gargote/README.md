# Gargote — TP fil rouge : base de données, API REST et Vue.js

Bao tient une gargote à Analakely. Il veut une petite application pour afficher sa carte, prendre les commandes en salle et suivre ce qu’il encaisse.

Le code fourni **fonctionne déjà** : on peut gérer la carte, passer une commande et consulter l’historique. Votre travail consiste à le faire évoluer, niveau par niveau, comme on le fait dans une vraie équipe : on ne repart jamais de zéro, on lit le code existant, on respecte son organisation et on l’étend.

| Niveau | Fichier | Thème | Durée indicative |
|---|---|---|---|
| 0 | [`tp/00-prise-en-main.md`](tp/00-prise-en-main.md) | Installer, lancer, comprendre le code | 1 h 30 |
| 1 | [`tp/01-niveau-1-la-carte.md`](tp/01-niveau-1-la-carte.md) | Enrichir la carte | 3 h |
| 2 | [`tp/02-niveau-2-le-service.md`](tp/02-niveau-2-le-service.md) | Serveurs, statuts et paiement | 4 h |
| 3 | [`tp/03-niveau-3-stock-et-tableau-de-bord.md`](tp/03-niveau-3-stock-et-tableau-de-bord.md) | Stock, recettes et chiffres | 5 h |

Chaque niveau touche **toutes les couches** de l’application : schéma relationnel, données de test, code métier, validations, composants Vue et CSS.

---

## 1. Prérequis

| Outil | Version | Vérifier |
|---|---|---|
| PostgreSQL | 13 ou plus | `psql --version` |
| PHP | 8.1 ou plus, extensions `pgsql`, `intl`, `mbstring` | `php -v` puis `php -m` |
| Node.js | 18 ou plus | `node -v` |
| Un client HTTP | `curl`, Thunder Client (VS Code) ou Postman | |

Le dossier `backend/vendor` est fourni : **pas besoin de Composer ni d’Internet** pour lancer l’API. Seul `npm install` (frontend) demande une connexion la première fois.

## 2. Installation

### 2.1 La base de données

```bash
# En tant qu'administrateur PostgreSQL
sudo -u postgres psql -c "CREATE USER gargote WITH PASSWORD 'gargote';"
sudo -u postgres psql -c "CREATE DATABASE gargote OWNER gargote;"

# Création des tables et des données de démonstration
cd database
psql -h localhost -U gargote -d gargote -f 01_schema.sql -f 02_donnees.sql
```

Sous Windows, ouvrez **SQL Shell (psql)** ou pgAdmin et exécutez les mêmes commandes SQL.
Avec Docker : `docker compose up -d` à la racine du projet fait tout ceci automatiquement.

### 2.2 L’API (CodeIgniter 4)

```bash
cd backend
php spark serve          # l'API écoute sur http://localhost:8080
```

Vérification : <http://localhost:8080/api/plats> doit afficher la liste des plats en JSON.
Les paramètres de connexion sont dans `backend/.env`.

### 2.3 L’interface (Vue 3 + Vite)

Dans un **second terminal** :

```bash
cd frontend
npm install              # une seule fois
npm run dev              # l'interface s'ouvre sur http://localhost:5173
```

Les deux serveurs doivent tourner en même temps.

## 3. Organisation du code

```
gargote/
├── database/
│   ├── 01_schema.sql              création des tables
│   ├── 02_donnees.sql             données de démonstration
│   └── reset.sh                   recrée la base : ./reset.sh 2 = niveaux 0 à 2
│
├── backend/                       API REST — CodeIgniter 4
│   ├── .env                       connexion à PostgreSQL
│   └── app/
│       ├── Config/Routes.php      la liste des URL de l'API
│       ├── Controllers/Api/       reçoivent la requête, renvoient du JSON
│       │   ├── ApiController.php  classe mère (format des erreurs)
│       │   ├── Categories.php
│       │   ├── Plats.php
│       │   └── Commandes.php
│       ├── Models/                accès aux tables + règles de validation
│       ├── Services/              CODE MÉTIER (règles de gestion)
│       │   └── CommandeService.php
│       ├── Validation/
│       │   └── GargoteRules.php   règles de validation maison
│       └── Exceptions/
│           └── RegleMetierException.php
│
└── frontend/                      interface — Vue 3
    ├── vite.config.js             redirige /api vers le port 8080
    └── src/
        ├── api.js                 TOUS les appels à l'API passent par ici
        ├── style.css              l'unique feuille de style
        ├── App.vue                en-tête + onglets
        ├── utils/format.js        formatage des montants et des dates
        ├── views/                 un fichier par écran (onglet)
        └── components/            morceaux d'interface réutilisables
```

### Qui fait quoi ?

| Couche | Rôle | On y met… | On n’y met pas… |
|---|---|---|---|
| Base de données | Garantir que les données restent cohérentes | clés, `NOT NULL`, `CHECK`, `UNIQUE` | de la logique d’affichage |
| Modèle | Lire et écrire une table | requêtes, champs autorisés, validation des champs | des règles qui concernent plusieurs tables |
| Service | Appliquer les règles de gestion | « une table ne peut avoir qu’une commande ouverte » | du code HTTP |
| Contrôleur | Traduire HTTP ↔ PHP | lecture de la requête, choix du code de réponse | des requêtes SQL, des règles métier |
| `api.js` | Parler à l’API | une fonction par route | de l’affichage |
| Composant Vue | Afficher et réagir | état local, événements | des `fetch()` directs |

## 4. L’API

Toutes les réponses sont en JSON. Toutes les erreurs ont la même forme :

```json
{ "message": "Données invalides.", "errors": { "prix": "Le prix est obligatoire." } }
```

| Méthode | URL | Rôle | Codes |
|---|---|---|---|
| GET | `/api/categories` | liste des catégories | 200 |
| GET | `/api/plats` | liste des plats (`?categorie=2` pour filtrer) | 200 |
| GET | `/api/plats/{id}` | un plat | 200, 404 |
| POST | `/api/plats` | créer un plat | 201, 422 |
| PUT | `/api/plats/{id}` | modifier un plat | 200, 404, 422 |
| DELETE | `/api/plats/{id}` | supprimer un plat | 200, 404, 409 |
| GET | `/api/commandes` | liste des commandes avec leur total | 200 |
| GET | `/api/commandes/{id}` | une commande et ses lignes | 200, 404 |
| POST | `/api/commandes` | créer une commande | 201, 422 |

### Les codes HTTP utilisés

| Code | Sens | Exemple |
|---|---|---|
| 200 | OK | lecture, modification |
| 201 | Créé | nouveau plat |
| 404 | Introuvable | `/api/plats/999` |
| 409 | Conflit avec l’état actuel | supprimer un plat déjà commandé |
| 422 | Données invalides | prix vide, commande sans plat |
| 500 | Erreur du serveur | **ne devrait jamais arriver** : c’est un bug à corriger |

## 5. Règles de travail

1. **Un commit par tâche**, avec un message clair : `niveau 1 – 1.4 : validation du prix`.
2. Chaque niveau produit **deux scripts SQL** dans `database/` : `niveauN_schema.sql` (modifications de structure) et `niveauN_donnees.sql` (données de test). Ils doivent s’exécuter **à la suite** des scripts précédents, sans erreur : `./reset.sh 2` recrée la base et enchaîne les niveaux 1 et 2. Sous Windows, exécutez les fichiers un par un dans le même ordre avec psql ou pgAdmin.
3. On ne modifie pas `01_schema.sql` ni `02_donnees.sql` : on écrit des scripts de **migration** (`ALTER TABLE…`), comme sur une base en production.
4. Une erreur 500 n’est jamais acceptable. Toute donnée invalide doit produire un 422 ou un 409 avec un message compréhensible par Bao.
5. Testez chaque route avec `curl` ou Thunder Client **avant** de l’utiliser dans Vue.

## 6. Problèmes fréquents

| Symptôme | Cause probable |
|---|---|
| `invalid value for parameter "client_encoding": "utf8mb4"` | ligne `database.default.charset = utf8` absente du `.env` |
| `Unable to connect to the database` | PostgreSQL arrêté, ou mot de passe différent dans `.env` |
| `could not find driver` | extension `pgsql` non activée dans `php.ini` |
| L’interface affiche « Le serveur ne répond pas » | `php spark serve` n’est pas lancé |
| Un champ ajouté en base n’est jamais enregistré | il manque dans `$allowedFields` du modèle |
| Une colonne booléenne arrive en `"t"` / `"f"` | il manque le `$casts` correspondant dans le modèle |
| `{"nom": "Le champ nom doit contenir une valeur unique."}` alors qu’on modifie le plat lui-même | la règle `is_unique` n’ignore pas l’enregistrement en cours : `is_unique[plat.nom,id,{id}]` |
