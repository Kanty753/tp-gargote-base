<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { ariary, dateHeure } from '../utils/format'
import CommandeDetail from '../components/CommandeDetail.vue'

const commandes = ref([])
const detail = ref(null)

onMounted(async () => {
  commandes.value = await api.commandes.lister()
})

async function afficher(id) {
  detail.value = await api.commandes.detail(id)
}
</script>

<template>
  <div class="grille">
    <div class="panneau">
      <h2>Commandes</h2>
      <p v-if="commandes.length === 0" class="vide">Aucune commande enregistrée.</p>

      <table v-else class="tableau tableau-cliquable">
        <thead>
          <tr><th>N°</th><th>Heure</th><th class="nombre">Table</th><th class="nombre">Plats</th><th class="nombre">Total</th></tr>
        </thead>
        <tbody>
          <tr
            v-for="c in commandes"
            :key="c.id"
            :class="{ selectionnee: detail && detail.id === c.id }"
            tabindex="0"
            @click="afficher(c.id)"
            @keydown.enter="afficher(c.id)"
          >
            <td>{{ c.id }}</td>
            <td>{{ dateHeure(c.date_commande) }}</td>
            <td class="nombre">{{ c.numero_table }}</td>
            <td class="nombre">{{ c.nb_lignes }}</td>
            <td class="nombre">{{ ariary(c.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <CommandeDetail v-if="detail" :commande="detail" />
    <p v-else class="panneau vide">Choisissez une commande pour voir son détail.</p>
  </div>
</template>
