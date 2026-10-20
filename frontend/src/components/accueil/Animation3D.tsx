import { useEffect, useRef, useState } from 'react'
import type { Scene3D } from './scenes3d'

const REQUETE_GRAND_ECRAN = '(min-width: 768px)'
const REQUETE_MOUVEMENT_REDUIT = '(prefers-reduced-motion: reduce)'

/** Vrai sur grand écran, sauf si l'utilisateur a demandé de réduire les animations. */
function useAnimationsAutorisees() {
  const calculer = () =>
    typeof window !== 'undefined' &&
    window.matchMedia(REQUETE_GRAND_ECRAN).matches &&
    !window.matchMedia(REQUETE_MOUVEMENT_REDUIT).matches
  const [autorisees, setAutorisees] = useState(calculer)

  useEffect(() => {
    const requetes = [window.matchMedia(REQUETE_GRAND_ECRAN), window.matchMedia(REQUETE_MOUVEMENT_REDUIT)]
    const maj = () => setAutorisees(calculer())
    requetes.forEach((r) => r.addEventListener('change', maj))
    return () => requetes.forEach((r) => r.removeEventListener('change', maj))
  }, [])

  return autorisees
}

/**
 * Canevas décoratif (aria-hidden). three.js n'est téléchargé que si l'animation est autorisée ;
 * sinon rien n'est affiché et la page reste entièrement lisible.
 */
export default function Animation3D({ type, className = '' }: { type: Scene3D; className?: string }) {
  const canvas = useRef<HTMLCanvasElement>(null)
  const autorisees = useAnimationsAutorisees()

  useEffect(() => {
    if (!autorisees || !canvas.current) return
    let arreter: (() => void) | undefined
    let annule = false

    import('./scenes3d')
      .then(({ demarrerScene }) => {
        if (!annule && canvas.current) arreter = demarrerScene(type, canvas.current)
      })
      .catch(() => {
        // WebGL indisponible : l'animation est simplement absente.
      })

    return () => {
      annule = true
      arreter?.()
    }
  }, [autorisees, type])

  if (!autorisees) return null

  return <canvas ref={canvas} aria-hidden="true" className={`block h-full w-full ${className}`} />
}
