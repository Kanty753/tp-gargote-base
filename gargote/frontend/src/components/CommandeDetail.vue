<script setup>
import { ariary, dateHeure } from '../utils/format'

defineProps({
  commande: { type: Object, required: true },
})
</script>

<template>
  <article class="panneau ticket">
    <h2>Commande n° {{ commande.id }}</h2>
    <p class="ticket-info">Table {{ commande.numero_table }}, {{ dateHeure(commande.date_commande) }}</p>

    <table class="tableau">
      <thead>
        <tr><th>Plat</th><th class="nombre">Qté</th><th class="nombre">Prix</th><th class="nombre">Sous-total</th></tr>
      </thead>
      <tbody>
        <tr v-for="l in commande.lignes" :key="l.id">
          <td>{{ l.plat }}</td>
          <td class="nombre">{{ l.quantite }}</td>
          <td class="nombre">{{ ariary(l.prix_unitaire) }}</td>
          <td class="nombre">{{ ariary(l.sous_total) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr><td colspan="3">Total</td><td class="nombre">{{ ariary(commande.total) }}</td></tr>
      </tfoot>
    </table>
  </article>
</template>
