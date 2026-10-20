import { useEffect, useRef, useState, type ReactNode } from 'react'

/**
 * Apparition en fondu au défilement (effet « reveal » du modèle). Le contenu reste visible sans
 * JavaScript et avec la préférence « mouvement réduit » (classe motion-safe de Tailwind).
 */
export default function Revelation({ children, delai = 0, className = '' }: { children: ReactNode; delai?: number; className?: string }) {
  const ref = useRef<HTMLDivElement>(null)
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const element = ref.current
    if (!element || typeof IntersectionObserver === 'undefined') {
      setVisible(true)
      return
    }
    const observateur = new IntersectionObserver(
      ([entree]) => {
        if (entree.isIntersecting) {
          setVisible(true)
          observateur.disconnect()
        }
      },
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
    )
    observateur.observe(element)
    return () => observateur.disconnect()
  }, [])

  return (
    <div
      ref={ref}
      style={{ transitionDelay: `${delai}ms` }}
      className={`motion-safe:transition-all motion-safe:duration-700 motion-safe:ease-out ${
        visible ? 'opacity-100 translate-y-0' : 'motion-safe:translate-y-6 motion-safe:opacity-0'
      } ${className}`}
    >
      {children}
    </div>
  )
}
