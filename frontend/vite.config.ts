import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import type { IncomingMessage } from 'node:http'

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
        configure: (proxy) => {
          // Die Live-API setzt Session-Cookies mit "Secure" (HTTPS-Produktion).
          // Der Browser spricht hier aber nur mit http://localhost - ein Secure-Cookie
          // wuerde dort stillschweigend verworfen. Fuer den Dev-Proxy entfernen wir
          // das Secure-Attribut, der Rest (HttpOnly, SameSite) bleibt erhalten.
          proxy.on('proxyRes', (proxyRes: IncomingMessage) => {
            const setCookie = proxyRes.headers['set-cookie']
            if (setCookie) {
              proxyRes.headers['set-cookie'] = setCookie.map((cookie) =>
                cookie.replace(/;\s*Secure/gi, ''),
              )
            }
          })
        },
      },
    },
  },
}))
