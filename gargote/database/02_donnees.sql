-- =============================================================
--  Gargote — données de démonstration (NIVEAU 0)
--  À exécuter après 01_schema.sql
-- =============================================================

INSERT INTO categorie (libelle) VALUES
    ('Plat'), ('Soupe'), ('Boisson');

INSERT INTO plat (nom, prix, id_categorie) VALUES
    ('Vary amin''anana',           2000, 1),
    ('Ravitoto sy henakisoa',      8000, 1),
    ('Akoho sy voanio',            9000, 1),
    ('Romazava',                   7000, 1),
    ('Henakisoa sy tsaramaso',     8000, 1),
    ('Soupe chinoise',             6000, 2),
    ('Mi sao',                     6500, 2),
    ('Ranon''ampango',              500, 3),
    ('Jus de fruit',               2500, 3),
    ('Café',                       1000, 3);

INSERT INTO commande (date_commande, numero_table) VALUES
    (now() - interval '2 hours', 3),
    (now() - interval '1 hour',  1);

INSERT INTO commande_ligne (id_commande, id_plat, quantite, prix_unitaire) VALUES
    (1, 2, 2, 8000),
    (1, 8, 2,  500),
    (2, 6, 1, 6000),
    (2, 10, 1, 1000);
