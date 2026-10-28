/**
 * Application installable (PWA) : enregistrement du service worker (servi par Laravel depuis
 * backend/public/sw.js) et capture de l'invite d'installation du navigateur.
 *
 * L'événement `beforeinstallprompt` peut survenir avant la connexion : il est mémorisé ici,
 * et la fenêtre d'installation (réservée aux utilisateurs connectés) l'utilise plus tard.
 */

/** Invite d'installation de Chrome, Edge et Android (absente des types du DOM). */
export interface InviteInstallation extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

let invite: InviteInstallation | null = null
const abonnes = new Set<() => void>()
const prevenir = () => abonnes.forEach((f) => f())

export function demarrerPwa() {
  if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => {
        // Sans service worker, le site fonctionne normalement ; il n'est simplement pas installable.
      })
    })
  }
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    invite = e as InviteInstallation
    prevenir()
  })
  window.addEventListener('appinstalled', () => {
    invite = null
    prevenir()
  })
}

export const inviteInstallation = () => invite

export function oublierInvite() {
  invite = null
  prevenir()
}

export function suivreInvite(f: () => void) {
  abonnes.add(f)
  return () => {
    abonnes.delete(f)
  }
}

/** Vrai si le site est déjà ouvert comme application installée. */
export const estInstallee = () =>
  window.matchMedia('(display-mode: standalone)').matches || (navigator as Navigator & { standalone?: boolean }).standalone === true

/** iPhone et iPad : pas d'invite automatique, l'installation passe par le menu Partager de Safari. */
export const estIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
