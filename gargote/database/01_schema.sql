-- =============================================================
--  Gargote — schéma de base (version fournie, NIVEAU 0)
--  SGBD : PostgreSQL
--  Exécution : psql -U gargote -d gargote -f 01_schema.sql
-- =============================================================

DROP TABLE IF EXISTS commande_ligne CASCADE;
DROP TABLE IF EXISTS commande       CASCADE;
DROP TABLE IF EXISTS plat           CASCADE;
DROP TABLE IF EXISTS categorie      CASCADE;

-- Catégories du menu : Plat, Soupe, Boisson...
CREATE TABLE categorie (
    id       SERIAL PRIMARY KEY,
    libelle  VARCHAR(50) NOT NULL UNIQUE
);

-- Plats proposés à la carte (prix en Ariary)
CREATE TABLE plat (
    id            SERIAL PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    prix          INTEGER      NOT NULL,
    id_categorie  INTEGER      NOT NULL REFERENCES categorie(id)
);

-- Une commande = une table de la gargote à un instant donné
CREATE TABLE commande (
    id             SERIAL PRIMARY KEY,
    date_commande  TIMESTAMP(0) NOT NULL DEFAULT now(),
    numero_table   INTEGER   NOT NULL
);

-- Les plats commandés.
-- prix_unitaire est COPIÉ depuis plat.prix au moment de la commande :
-- si le prix du plat change demain, les anciennes commandes restent justes.
CREATE TABLE commande_ligne (
    id             SERIAL PRIMARY KEY,
    id_commande    INTEGER NOT NULL REFERENCES commande(id) ON DELETE CASCADE,
    id_plat        INTEGER NOT NULL REFERENCES plat(id),
    quantite       INTEGER NOT NULL,
    prix_unitaire  INTEGER NOT NULL
);
