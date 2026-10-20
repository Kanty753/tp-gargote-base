#!/bin/sh
# Recrée la base de zéro.
#   ./reset.sh      -> schéma et données de base (niveau 0)
#   ./reset.sh 2    -> niveau 0, puis les scripts des niveaux 1 et 2
#
# Le mot de passe peut être fourni par la variable PGPASSWORD.
set -e
cd "$(dirname "$0")"
NIVEAU=${1:-0}
PSQL="psql -h localhost -U gargote -d gargote -q -v ON_ERROR_STOP=1"

# Supprime TOUTES les tables de l'utilisateur gargote, y compris celles
# créées par vos migrations (serveur, ingredient...).
$PSQL -c "DROP OWNED BY gargote;"

$PSQL -f 01_schema.sql -f 02_donnees.sql
echo "Niveau 0 chargé."

i=1
while [ "$i" -le "$NIVEAU" ]; do
  $PSQL -f "niveau${i}_schema.sql" -f "niveau${i}_donnees.sql"
  echo "Niveau $i chargé."
  i=$((i + 1))
done
