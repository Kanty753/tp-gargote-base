import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    // Toute requête vers /api est transmise au serveur CodeIgniter.
    // Le navigateur croit parler au même serveur : pas de problème de CORS.
    proxy: {
      '/api': 'http://localhost:8080',
    },
  },
})
