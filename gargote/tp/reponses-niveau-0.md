# Réponses Niveau 0 — Prise en main

---

## 0.2 Lire le schéma relationnel

**1. Schéma des 4 tables**

J'ai lu le fichier `01_schema.sql` et voilà ce que j'ai compris :

```
categorie      (__id__, libelle)
plat           (__id__, nom, prix, #id_categorie)
commande       (__id__, date_commande, numero_table)
commande_ligne (__id__, #id_commande, #id_plat, quantite, prix_unitaire)
```

*(les clés primaires sont soulignées, les clés étrangères ont un `#` devant)*

---

**2. Pourquoi `prix_unitaire` dans `commande_ligne` alors que le prix est déjà dans `plat` ?**

Au début j'avais pas compris pourquoi dupliquer l'info. Puis j'ai réfléchi :
si Bao décide de changer le prix du Romazava de 7000 Ar à 9000 Ar demain, les anciennes commandes vont afficher le nouveau prix alors que le client avait payé l'ancien. C'est un bug.

Du coup on copie le prix **au moment où on commande** dans `prix_unitaire`. Comme ça même si le prix change dans `plat`, les commandes passées restent correctes.

Exemple concret : aujourd'hui le Romazava est à 7000 Ar, le client commande. Demain Bao passe à 9000 Ar. La commande d'aujourd'hui affiche toujours 7000 Ar. ✅

---

**3. Que se passe-t-il pour les lignes quand on supprime une commande ?**

Elles sont supprimées automatiquement. C'est la ligne suivante dans le schéma qui fait ça :

```sql
id_commande INTEGER NOT NULL REFERENCES commande(id) ON DELETE CASCADE
```

Le `ON DELETE CASCADE` dit à PostgreSQL : "si tu supprimes une commande, supprime aussi toutes ses lignes". J'ai testé et ça marche bien.

---

**4. Trois données absurdes que le schéma accepte**

J'ai essayé des trucs pour trouver des failles :

1. **Prix négatif** — j'ai inséré un plat à -500 Ar, ça passe sans problème. Aucun `CHECK` sur le prix.
2. **Numéro de table 0 ou négatif** — `numero_table` est juste `INTEGER NOT NULL`, donc -1 ou 0 sont acceptés alors qu'une table numéro -1 ça existe pas.
3. **Nom de plat vide** — `NOT NULL` interdit `NULL` mais pas une chaîne vide `''`. Donc on peut avoir un plat sans nom.

*(ces 3 points seront corrigés aux niveaux 1 et 2 apparemment)*

---

## 0.3 Interroger l'API à la main

J'ai utilisé `curl.exe` sous Windows (pas `curl` tout seul qui est en fait `Invoke-WebRequest` sous PowerShell et qui marche pas pareil).

**a) Liste des soupes → 200**
```bash
curl http://localhost:8080/api/plats?categorie=2
```
```json
[
  {"id":7,"nom":"Mi sao","prix":6500,"id_categorie":2,"categorie":"Soupe"},
  {"id":6,"nom":"Soupe chinoise","prix":6000,"id_categorie":2,"categorie":"Soupe"}
]
```

---

**b) Créer un plat valide → 201**
```bash
curl -X POST http://localhost:8080/api/plats \
     -H "Content-Type: application/json" \
     -d '{"nom":"Mofo gasy","prix":300,"id_categorie":1}'
```
```json
{"id":11,"nom":"Mofo gasy","prix":300,"id_categorie":1}
```

---

**c) Créer un plat invalide → 422**
```bash
curl -X POST http://localhost:8080/api/plats \
     -H "Content-Type: application/json" \
     -d '{"nom":"","prix":"cher"}'
```
```json
{
  "message": "Données invalides.",
  "errors": {
    "nom": "Le nom du plat est obligatoire.",
    "prix": "Le prix doit être un nombre entier (en Ariary)."
  }
}
```

---

**d) Supprimer un plat qui est dans une commande → 409**
```bash
curl -X DELETE http://localhost:8080/api/plats/2
```
```json
{"message":"Ce plat figure déjà dans des commandes, il ne peut pas être supprimé.","errors":{}}
```

---

**e) Une commande qui n'existe pas → 404**
```bash
curl http://localhost:8080/api/commandes/999
```
```json
{"message":"Commande 999 introuvable.","errors":{}}
```

---

**5. Requête b : pourquoi 201 et pas 200 ?**

200 c'est "OK" au sens large. 201 c'est "OK et en plus j'ai créé quelque chose de nouveau". Ici on a créé un nouveau plat en base avec un nouvel id, donc 201 est plus précis. C'est la convention REST.

---

**6. Requête d : qui bloque la suppression, PHP ou PostgreSQL ?**

Les deux en fait, mais c'est PostgreSQL qui détecte l'erreur en premier (contrainte de clé étrangère). PHP l'attrape ensuite dans le `catch` et renvoie un 409 propre au lieu de laisser crasher avec un 500.

La ligne qui fait la transformation est dans `Plats.php` :

```php
// backend/app/Controllers/Api/Plats.php, lignes 74-79
try {
    $this->plats->delete($id);
} catch (\Throwable $e) {
    // Le plat apparaît dans des commandes : la clé étrangère bloque la suppression.
    return $this->erreur('Ce plat figure déjà dans des commandes, il ne peut pas être supprimé.', 409);
}
```

Sans le `try/catch`, on aurait eu un 500 moche. Là c'est propre.

---

**7. Créer une commande : table 6, 2 Romazava et 1 Café**

J'ai cherché les ids dans la base : Romazava = id 4, Café = id 10.

```bash
curl -X POST http://localhost:8080/api/commandes \
     -H "Content-Type: application/json" \
     -d '{
       "numero_table": 6,
       "lignes": [
         {"id_plat": 4, "quantite": 2},
         {"id_plat": 10, "quantite": 1}
       ]
     }'
```

Réponse (201) :
```json
{
  "id": 3,
  "date_commande": "2026-10-20 10:43:43",
  "numero_table": 6,
  "lignes": [
    {"id":5,"id_plat":4,"plat":"Romazava","quantite":2,"prix_unitaire":7000,"sous_total":14000},
    {"id":6,"id_plat":10,"plat":"Café","quantite":1,"prix_unitaire":1000,"sous_total":1000}
  ],
  "total": 15000
}
```

Le total est bien 2×7000 + 1×1000 = 15 000 Ar. 

---

## 0.4 Suivre une requête de bout en bout

J'ai dû lire pas mal de fichiers pour remplir ce tableau. Le plus dur c'était de trouver où se fait exactement la validation.

| # | Étape | Fichier | Fonction / ligne |
|---|-------|---------|-----------------|
| 1 | Le formulaire est soumis | `components/PlatForm.vue` | `enregistrer()` ligne 33 |
| 2 | La requête HTTP est construite | `src/api.js` | `requete()` ligne 17 |
| 3 | Vite transmet `/api/...` au port 8080 | `vite.config.js` | configuration `proxy` |
| 4 | CodeIgniter choisit le contrôleur | `app/Config/Routes.php` | `$routes->post('plats', 'Plats::create')` |
| 5 | Le contrôleur lit le JSON | `Controllers/Api/Plats.php` | `donneesJson()` ligne 40 (hérité de `ApiController`) |
| 6 | Les règles de validation sont vérifiées | `Models/PlatModel.php` | `$validationRules` lignes 27-31, appelé automatiquement par `insert()` |
| 7 | La ligne est insérée | `Models/PlatModel.php` | `insert()` de CodeIgniter (ligne 42 du contrôleur) |
| 8 | Le formulaire prévient l'écran parent | `components/PlatForm.vue` | `emit('enregistre', resultat)` ligne 41 |
| 9 | L'écran recharge la liste | `views/CarteView.vue` | `surEnregistrement()` → `charger()` lignes 23-27 |

---

**8. Où est le code métier de la création d'une commande ? Pourquoi pas dans le contrôleur ?**

Le code métier est dans `app/Services/CommandeService.php`, méthode `creer()`.

J'avoue que j'avais du mal à voir pourquoi ne pas le mettre directement dans `Commandes.php`. Mais en relisant le README j'ai compris : le contrôleur doit **seulement** gérer le HTTP (lire la requête, choisir le code de réponse). Les règles "est-ce qu'il y a au moins un plat ?" ou "est-ce que le plat existe vraiment ?" c'est du métier, ça doit pas être mélangé avec le code HTTP. Comme ça si demain on veut créer une commande depuis un autre endroit (une CLI par exemple), on réutilise le service sans réécrire les règles.

---

## 0.5 Repérer les notions de Vue

| Notion | À quoi ça sert | Mon exemple |
|--------|----------------|-------------|
| `ref()` | variable réactive, l'écran se met à jour quand elle change | `CarteView.vue` l.7 : `const plats = ref([])` — quand on charge les plats, l'ardoise s'actualise |
| `computed()` | valeur calculée automatiquement depuis d'autres `ref` | `App.vue` l.15 : `vueCourante` recalcule quel composant afficher quand on change d'onglet |
| `v-for` | répéter un bout de HTML pour chaque élément d'une liste | `PlatList.vue` l.23 : `v-for="plat in liste"` — une ligne par plat |
| `v-if` / `v-else` | afficher un élément seulement si une condition est vraie | `PlatList.vue` l.18 : affiche "Aucun plat" si la liste est vide |
| `v-model` | liaison bidirectionnelle entre un champ et une variable | `PlatForm.vue` l.64 : `v-model.trim="form.nom"` — ce qu'on tape va dans `form.nom` et vice versa |
| `defineProps` | recevoir des données venant du composant parent | `PlatList.vue` l.6-8 : reçoit le tableau `plats` depuis `CarteView` |
| `defineEmits` / `emit` | envoyer un signal au composant parent | `PlatList.vue` l.11 + l.29 : `emit('supprimer', plat)` prévient `CarteView` qu'on veut supprimer |
| `onMounted` | exécuter du code quand le composant apparaît à l'écran | `CarteView.vue` l.40 : `onMounted(charger)` charge les plats dès qu'on ouvre l'onglet |

---

**9. Pourquoi `Panier.vue` émet `plus` et `moins` au lieu de modifier directement la quantité ?**

Parce que `Panier.vue` ne possède pas le tableau `panier`. C'est `CommandeView.vue` qui le possède.

En Vue le principe c'est : **seul celui qui possède la donnée a le droit de la modifier**. Donc `Panier.vue` dit juste "hé, l'utilisateur a cliqué sur + pour ce plat" via `emit('plus', ligne)`, et c'est `CommandeView` qui s'occupe vraiment de modifier `panier.value`. C'est un peu comme si le panier était juste un afficheur, il décide pas, il reporte.

---

**10. À quoi sert le `watch` dans `PlatForm.vue` ?**

Le `watch` surveille la prop `plat` (le plat qu'on est en train de modifier). Chaque fois qu'elle change, il recopie les valeurs dans `form`.

Sans lui : je clique "Modifier" sur le Romazava, le formulaire affiche le Romazava. Ensuite je clique "Modifier" sur le Café — le formulaire affiche encore le Romazava (les anciens champs n'ont pas été mis à jour). Avec le `watch`, dès que `plat` change, `form` est mis à jour. L'option `{ immediate: true }` fait que ça s'exécute aussi au premier affichage.

---

## 0.6 Premières modifications

**a) Nom de la gargote** — j'ai modifié `frontend/src/App.vue` ligne 22 :
```
Avant : Chez Bao, Analakely
Après : Chez Bao, Analakely — Le meilleur du Zoma
```

**b) Catégorie *Goûter*** — ajoutée directement en SQL, sans toucher au code :
```sql
INSERT INTO categorie (libelle) VALUES ('Gouter') ON CONFLICT (libelle) DO NOTHING;
```
J'ai rechargé l'interface et la catégorie est bien apparue dans la liste déroulante du formulaire. ✅

---

**11. Pourquoi la catégorie apparaît sans que j'aie écrit de code ?**

Parce que `PlatForm.vue` charge les catégories depuis `/api/categories` à chaque fois que l'onglet "La carte" s'ouvre (`onMounted`). La route appelle `CategorieModel::findAll()` qui lit directement la table en base. Aucune liste de catégories n'est écrite en dur dans le code : tout ce qu'on ajoute en SQL ressort automatiquement dans l'interface.

---

## Checklist finale

- [x] Application qui tourne (API :8080 + interface :5173)
- [x] 11 questions répondues avec le tableau 0.4 complet
- [x] Nom de la gargote modifié dans `App.vue`
- [x] Catégorie *Goûter* ajoutée en base
- [x] Commit `niveau 0 : prise en main`
