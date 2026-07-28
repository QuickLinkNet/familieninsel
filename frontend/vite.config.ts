import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

const apiPath = '/apps/familieninsel/api'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  plugins: [react()],
  // Die App wird produktiv unter /apps/familieninsel/ ausgeliefert.
  // Im Dev-Server bleibt der Root-Pfad "/", damit lokal keine Sub-Path-Probleme entstehen.
  base: command === 'build' ? '/apps/familieninsel/' : '/',
  server: {
    // Kein lokales PHP-Backend: Vite proxyt API-Aufrufe serverseitig an die live
    // deployte API weiter. Der Browser sieht dadurch nur "localhost" (same-origin,
    // kein CORS noetig) - die Daten kommen trotzdem von der echten Live-API.
    proxy: {
      [apiPath]: {
        target: 'https://www.red-it.org',
        changeOrigin: true,
        secure: true,
      },
    },
  },
}))
