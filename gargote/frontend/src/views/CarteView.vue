<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import PlatList from '../components/PlatList.vue'
import PlatForm from '../components/PlatForm.vue'

const plats = ref([])
const categories = ref([])
const platSelectionne = ref(null) // plat en cours de modification
const message = ref(null)         // { type: 'succes' | 'erreur', texte }

async function charger() {
  try {
    ;[plats.value, categories.value] = await Promise.all([
      api.plats.lister(),
      api.categories.lister(),
    ])
  } catch (e) {
    message.value = { type: 'erreur', texte: 'Impossible de charger la carte : ' + e.message }
  }
}

async function surEnregistrement(plat) {
  message.value = { type: 'succes', texte: `« ${plat.nom} » est enregistré.` }
  platSelectionne.value = null
  await charger()
}

async function supprimer(plat) {
  if (!confirm(`Supprimer « ${plat.nom} » de la carte ?`)) return
  try {
    await api.plats.supprimer(plat.id)
    message.value = { type: 'succes', texte: `« ${plat.nom} » est supprimé.` }
    await charger()
  } catch (e) {
    message.value = { type: 'erreur', texte: e.message }
  }
}

onMounted(charger)
</script>

<template>
  <p v-if="message" class="alerte" :class="`alerte-${message.type}`">{{ message.texte }}</p>

  <div class="grille">
    <PlatList :plats="plats" @modifier="platSelectionne = $event" @supprimer="supprimer" />

    <PlatForm
      :plat="platSelectionne"
      :categories="categories"
      @enregistre="surEnregistrement"
      @annule="platSelectionne = null"
    />
  </div>
</template>
