<script setup>
import { computed } from 'vue'
import { ariary } from '../utils/format'

// lignes : [ { plat: {...}, quantite: 2 }, ... ]
const props = defineProps({
  lignes: { type: Array, required: true },
})
const emit = defineEmits(['plus', 'moins'])

const total = computed(() =>
  props.lignes.reduce((somme, l) => somme + l.plat.prix * l.quantite, 0),
)
</script>

<template>
  <div class="panier">
    <p v-if="lignes.length === 0" class="vide">Touchez un plat de la carte pour l’ajouter.</p>

    <ul v-else class="panier-liste">
      <li v-for="ligne in lignes" :key="ligne.plat.id" class="panier-ligne">
        <span class="panier-nom">{{ ligne.plat.nom }}</span>
        <span class="quantite">
          <button type="button" class="pastille" :aria-label="`Retirer un ${ligne.plat.nom}`" @click="emit('moins', ligne)">−</button>
          <strong>{{ ligne.quantite }}</strong>
          <button type="button" class="pastille" :aria-label="`Ajouter un ${ligne.plat.nom}`" @click="emit('plus', ligne)">+</button>
        </span>
        <span class="panier-montant">{{ ariary(ligne.plat.prix * ligne.quantite) }}</span>
      </li>
    </ul>

    <p class="panier-total">
      <span>Total</span>
      <strong>{{ ariary(total) }}</strong>
    </p>
  </div>
</template>
