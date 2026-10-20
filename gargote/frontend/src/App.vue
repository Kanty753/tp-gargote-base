<script setup>
import { computed, ref } from 'vue'
import CarteView from './views/CarteView.vue'
import CommandeView from './views/CommandeView.vue'
import HistoriqueView from './views/HistoriqueView.vue'

// Pour ajouter un écran : créer le composant dans views/ puis l'ajouter ici.
const onglets = [
  { id: 'carte', libelle: 'La carte', composant: CarteView },
  { id: 'commande', libelle: 'Prendre une commande', composant: CommandeView },
  { id: 'historique', libelle: 'Commandes', composant: HistoriqueView },
]

const ongletActif = ref('carte')
const vueCourante = computed(() => onglets.find((o) => o.id === ongletActif.value).composant)
</script>

<template>
  <header class="entete">
    <div class="enseigne">
      <span class="enseigne-nom">Gargote</span>
      <span class="enseigne-lieu">Chez Bao, Analakely</span>
    </div>

    <nav class="onglets">
      <button
        v-for="onglet in onglets"
        :key="onglet.id"
        class="onglet"
        :class="{ actif: onglet.id === ongletActif }"
        @click="ongletActif = onglet.id"
      >
        {{ onglet.libelle }}
      </button>
    </nav>
  </header>

  <main class="contenu">
    <component :is="vueCourante" />
  </main>
</template>
