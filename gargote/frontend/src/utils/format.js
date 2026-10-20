/** 12500 -> « 12 500 Ar » */
export function ariary(montant) {
  return new Intl.NumberFormat('fr-FR').format(montant) + ' Ar'
}

/** « 2026-10-20 14:05:00 » -> « 20/10/2026 14:05 » */
export function dateHeure(texte) {
  const d = new Date(texte.replace(' ', 'T'))
  return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

/** Regroupe une liste de plats par catégorie : { Plat: [...], Soupe: [...] } */
export function parCategorie(plats) {
  const groupes = {}
  for (const plat of plats) {
    if (!groupes[plat.categorie]) groupes[plat.categorie] = []
    groupes[plat.categorie].push(plat)
  }
  return groupes
}
