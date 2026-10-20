<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, ApiError } from '../api'
import { ariary, parCategorie } from '../utils/format'
import Panier from '../components/Panier.vue'

const plats = ref([])
const panier = ref([]) // [ { plat, quantite } ]
const numeroTable = ref('')
const message = ref(null)
const erreurs = ref({})

const groupes = computed(() => parCategorie(plats.value))

onMounted(async () => {
  plats.value = await api.plats.lister()
})

function ajouter(plat) {
  const ligne = panier.value.find((l) => l.plat.id === plat.id)
  if (ligne) ligne.quantite++
  else panier.value.push({ plat, quantite: 1 })
}

function retirer(ligne) {
  ligne.quantite--
  if (ligne.quantite === 0) {
    panier.value = panier.value.filter((l) => l !== ligne)
  }
}

async function envoyer() {
  message.value = null
  erreurs.value = {}
  try {
    const commande = await api.commandes.creer({
      numero_table: numeroTable.value,
      lignes: panier.value.map((l) => ({ id_plat: l.plat.id, quantite: l.quantite })),
    })
    message.value = { type: 'succes', texte: `Commande n° ${commande.id} envoyée en cuisine (table ${commande.numero_table}).` }
    panier.value = []
    numeroTable.value = ''
  } catch (e) {
    message.value = { type: 'erreur', texte: e.message }
    if (e instanceof ApiError) erreurs.value = e.errors
  }
}
</script>

<template>
  <p v-if="message" class="alerte" :class="`alerte-${message.type}`">{{ message.texte }}</p>

  <div class="grille">
    <div class="ardoise">
      <section v-for="(liste, categorie) in groupes" :key="categorie" class="ardoise-groupe">
        <h3 class="ardoise-titre">{{ categorie }}</h3>
        <ul class="ardoise-liste">
          <li v-for="plat in liste" :key="plat.id">
            <button class="ardoise-ligne ardoise-choix" @click="ajouter(plat)">
              <span class="plat-nom">{{ plat.nom }}</span>
              <span class="plat-pointilles" aria-hidden="true"></span>
              <span class="plat-prix">{{ ariary(plat.prix) }}</span>
            </button>
          </li>
        </ul>
      </section>
    </div>

    <form class="panneau" @submit.prevent="envoyer">
      <h2>Commande en cours</h2>

      <label class="champ champ-court">
        <span>Table n°</span>
        <input v-model.number="numeroTable" type="number" min="1" />
        <small v-if="erreurs.numero_table" class="erreur">{{ erreurs.numero_table }}</small>
      </label>

      <Panier :lignes="panier" @plus="ajouter($event.plat)" @moins="retirer" />
      <small v-if="erreurs.lignes" class="erreur">{{ erreurs.lignes }}</small>

      <div class="boutons">
        <button type="submit" class="bouton">Envoyer en cuisine</button>
      </div>
    </form>
  </div>
</template>
