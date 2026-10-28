import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ command, mode }) => {
  // API Laravel en développement (php artisan serve).
  const API = loadEnv(mode, process.cwd(), '').VITE_API_PROXY || 'http://127.0.0.1:8000'

  return {
    plugins: [react(), tailwindcss()],

    // En démonstration, Laravel sert la SPA compilée depuis backend/public/spa.
    base: command === 'build' ? '/spa/' : '/',

    server: {
      port: 5173,
      strictPort: true,
      // Autorise le domaine du tunnel ngrok (même origine pour la SPA et l'API).
      allowedHosts: ['.ngrok-free.app', '.ngrok-free.dev', '.ngrok.app', '.ngrok.io'],
      // Une seule origine : Vite relaie l'API, Sanctum et l'authentification vers Laravel.
      // changeOrigin reste à false pour que Laravel voie l'hôte réel (cookies, Sanctum).
      proxy: {
        '/api': { target: API },
        '/sanctum': { target: API },
        '/login': { target: API },
        '/logout': { target: API },
        // Fichiers de l'application installable, servis par Laravel (backend/public).
        '/manifest.json': { target: API },
        '/icones': { target: API },
      },
    },

    build: {
      outDir: '../backend/public/spa',
      emptyOutDir: true,
    },
  }
})
