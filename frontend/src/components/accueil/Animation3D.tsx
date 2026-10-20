import { useEffect, useRef, useState } from 'react'
import type { Scene3D } from './scenes3d'

const REQUETE_MOUVEMENT_REDUIT = '(prefers-reduced-motion: reduce)'
const REQUETE_PETIT_ECRAN = '(max-width: 767px)'

/** Suit une requête média (mise à jour si l'écran pivote ou si la préférence change). */
function useRequeteMedia(requete: string) {
  const [correspond, setCorrespond] = useState(() => typeof window !== 'undefined' && window.matchMedia(requete).matches)

  useEffect(() => {
    const media = window.matchMedia(requete)
    const maj = () => setCorrespond(media.matches)
    media.addEventListener('change', maj)
    return () => media.removeEventListener('change', maj)
  }, [requete])

  return correspond
}

/**
 * Canevas décoratif (aria-hidden), sur tous les écrans. Sur smartphone, la scène est allégée
 * (option compact). three.js n'est pas téléchargé si l'utilisateur a demandé de réduire les
 * animations : la page reste alors entièrement lisible, sans canevas.
 */
export default function Animation3D({ type, className = '' }: { type: Scene3D; className?: string }) {
  const canvas = useRef<HTMLCanvasElement>(null)
  const mouvementReduit = useRequeteMedia(REQUETE_MOUVEMENT_REDUIT)
  const compact = useRequeteMedia(REQUETE_PETIT_ECRAN)

  useEffect(() => {
    if (mouvementReduit || !canvas.current) return
    let arreter: (() => void) | undefined
    let annule = false

    import('./scenes3d')
      .then(({ demarrerScene }) => {
        if (!annule && canvas.current) arreter = demarrerScene(type, canvas.current, { compact })
      })
      .catch(() => {
        // WebGL indisponible : l'animation est simplement absente.
      })

    return () => {
      annule = true
      arreter?.()
    }
  }, [mouvementReduit, compact, type])

  if (mouvementReduit) return null

  return <canvas ref={canvas} aria-hidden="true" className={`block h-full w-full ${className}`} />
}
