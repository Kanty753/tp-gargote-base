<script setup>
import { computed, ref, watch } from 'vue'
import { api, ApiError } from '../api'

const props = defineProps({
  plat: { type: Object, default: null }, // null = nouveau plat
  categories: { type: Array, required: true },
})
const emit = defineEmits(['enregistre', 'annule'])

const formulaireVide = () => ({ nom: '', prix: '', id_categorie: '' })

const form = ref(formulaireVide())
const erreurs = ref({})
const message = ref('')
const enCours = ref(false)

const modeEdition = computed(() => props.plat !== null)

// Quand le parent choisit un autre plat à modifier, on recopie ses valeurs.
watch(
  () => props.plat,
  (plat) => {
    form.value = plat
      ? { nom: plat.nom, prix: plat.prix, id_categorie: plat.id_categorie }
      : formulaireVide()
    erreurs.value = {}
    message.value = ''
  },
  { immediate: true },
)

async function enregistrer() {
  enCours.value = true
  erreurs.value = {}
  message.value = ''
  try {
    const resultat = modeEdition.value
      ? await api.plats.modifier(props.plat.id, form.value)
      : await api.plats.creer(form.value)
    emit('enregistre', resultat)
    if (!modeEdition.value) form.value = formulaireVide()
  } catch (e) {
    if (e instanceof ApiError) {
      message.value = e.message
      erreurs.value = e.errors
    } else {
      message.value = 'Le serveur ne répond pas. Vérifiez que l’API est lancée.'
    }
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <form class="panneau formulaire" @submit.prevent="enregistrer">
    <h2>{{ modeEdition ? `Modifier « ${plat.nom} »` : 'Ajouter un plat' }}</h2>

    <p v-if="message" class="alerte alerte-erreur">{{ message }}</p>

    <label class="champ">
      <span>Nom du plat</span>
      <input v-model.trim="form.nom" type="text" placeholder="Ex. : Mofo gasy" />
      <small v-if="erreurs.nom" class="erreur">{{ erreurs.nom }}</small>
    </label>

    <label class="champ">
      <span>Prix (Ar)</span>
      <input v-model.number="form.prix" type="number" placeholder="Ex. : 500" />
      <small v-if="erreurs.prix" class="erreur">{{ erreurs.prix }}</small>
    </label>

    <label class="champ">
      <span>Catégorie</span>
      <select v-model.number="form.id_categorie">
        <option value="" disabled>Choisir…</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.libelle }}</option>
      </select>
      <small v-if="erreurs.id_categorie" class="erreur">{{ erreurs.id_categorie }}</small>
    </label>

    <div class="boutons">
      <button type="submit" class="bouton" :disabled="enCours">
        {{ modeEdition ? 'Enregistrer les modifications' : 'Ajouter le plat' }}
      </button>
      <button v-if="modeEdition" type="button" class="bouton bouton-discret" @click="emit('annule')">
        Annuler
      </button>
    </div>
  </form>
</template>
