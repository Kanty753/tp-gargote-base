/**
 * Toutes les communications avec l'API passent par ce fichier.
 * Un composant n'appelle JAMAIS fetch() directement : il appelle api.xxx().
 */

const BASE = '/api'

/** Erreur renvoyée par l'API : message général + erreurs par champ. */
export class ApiError extends Error {
  constructor(status, corps) {
    super(corps?.message ?? `Erreur ${status}`)
    this.status = status
    this.errors = corps?.errors ?? {}
  }
}

async function requete(methode, chemin, donnees) {
  const options = {
    method: methode,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  }
  if (donnees !== undefined) {
    options.body = JSON.stringify(donnees)
  }

  const reponse = await fetch(BASE + chemin, options)
  const corps = await reponse.json().catch(() => null)

  if (!reponse.ok) {
    throw new ApiError(reponse.status, corps)
  }
  return corps
}

export const api = {
  categories: {
    lister: () => requete('GET', '/categories'),
  },

  plats: {
    lister: (idCategorie) =>
      requete('GET', idCategorie ? `/plats?categorie=${idCategorie}` : '/plats'),
    creer: (plat) => requete('POST', '/plats', plat),
    modifier: (id, plat) => requete('PUT', `/plats/${id}`, plat),
    supprimer: (id) => requete('DELETE', `/plats/${id}`),
  },

  commandes: {
    lister: () => requete('GET', '/commandes'),
    detail: (id) => requete('GET', `/commandes/${id}`),
    creer: (commande) => requete('POST', '/commandes', commande),
  },
}
