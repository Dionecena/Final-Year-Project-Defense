import axios from 'axios'

/**
 * Client HTTP de l'API Laravel. Sanctum en mode SPA : authentification par cookie de
 * session (même origine), jeton CSRF lu dans le cookie XSRF-TOKEN. Aucun jeton n'est
 * stocké dans le navigateur.
 */
export const api = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** À appeler avant /login, /logout et l'inscription pour obtenir le cookie CSRF. */
export function initialiserCsrf() {
  return api.get('/sanctum/csrf-cookie')
}

/** Message lisible à partir d'une erreur Axios (validation 422, 409, 403…). */
export function messageErreur(erreur: unknown, parDefaut = 'Une erreur est survenue. Veuillez réessayer.'): string {
  if (axios.isAxiosError(erreur)) {
    const message = (erreur.response?.data as { message?: string } | undefined)?.message
    if (message) return message
    if (!erreur.response) return 'Le serveur est injoignable. Vérifiez votre connexion.'
  }
  return parDefaut
}
