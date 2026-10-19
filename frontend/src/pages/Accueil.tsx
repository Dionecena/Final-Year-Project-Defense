import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { ACCUEIL_ROLE } from '../types'

/**
 * Page d'accueil publique (version du socle). La mise en page inspirée du modèle fourni
 * sera réalisée lors des finitions (module 10).
 */
export default function Accueil() {
  const { utilisateur } = useAuth()
  const cible = utilisateur?.role === 'patient' ? '/patient/prise-de-rendez-vous' : utilisateur ? ACCUEIL_ROLE[utilisateur.role] : '/connexion'

  return (
    <div className="space-y-8">
      <section className="rounded-2xl bg-gradient-to-br from-primaire-50 via-white to-medical-50 px-5 py-10 sm:px-10">
        <h1 className="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
          Prenez rendez-vous en ligne à la Clinique Croix Bleue
        </h1>
        <p className="mt-4 max-w-2xl text-gray-700">
          Consultez les créneaux réellement disponibles, réservez avec une confirmation immédiate et, si vous le souhaitez,
          remplissez une préconsultation pour préparer votre visite.
        </p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link to={cible} className="rounded-lg bg-primaire-600 px-5 py-3 font-semibold text-white hover:bg-primaire-700">
            Prendre rendez-vous
          </Link>
          <Link to="/specialites" className="rounded-lg border border-gray-300 bg-white px-5 py-3 font-semibold text-gray-900 hover:bg-gray-50">
            Voir les spécialités
          </Link>
        </div>
      </section>

      {/* RG13 : la plateforme ne remplace pas l'accueil direct. */}
      <section aria-labelledby="titre-accueil-direct" className="rounded-xl border-l-4 border-medical-700 bg-white p-5 shadow-sm">
        <h2 id="titre-accueil-direct" className="text-lg font-bold text-gray-900">
          Sans rendez-vous, c'est toujours possible
        </h2>
        <p className="mt-1 text-gray-700">
          La prise de rendez-vous en ligne est un service complémentaire. Vous pouvez toujours vous présenter directement à
          l'accueil de la clinique, sans rendez-vous.
        </p>
      </section>
    </div>
  )
}
