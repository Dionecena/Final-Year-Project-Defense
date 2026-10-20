import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { Alerte } from '../components/Formulaire'
import { api, messageErreur } from '../lib/api'
import type { Specialite } from '../types'

/** Page publique : liste des spécialités avec leurs jours de présence (RG01 : sans connexion). */
export default function Specialites() {
  const { utilisateur } = useAuth()
  const [specialites, setSpecialites] = useState<Specialite[] | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    api
      .get<{ data: Specialite[] }>('/api/specialites')
      .then(({ data }) => setSpecialites(data.data))
      .catch((err) => setErreur(messageErreur(err)))
  }, [])

  const cible = utilisateur?.role === 'patient' ? '/patient/prise-de-rendez-vous' : utilisateur ? null : '/connexion'

  return (
    <section aria-labelledby="titre-specialites" className="space-y-5">
      <div>
        <h1 id="titre-specialites" className="text-2xl font-bold text-gray-900">
          Spécialités
        </h1>
        <p className="mt-1 text-gray-700">Spécialités assurées par la clinique et jours de présence des médecins.</p>
      </div>

      {erreur && <Alerte>{erreur}</Alerte>}

      {!specialites && !erreur ? (
        <p role="status" className="text-gray-600">
          Chargement…
        </p>
      ) : (
        specialites && (
          <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {specialites.map((s) => (
              <li key={s.id} className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 className="font-semibold text-gray-900">{s.libelle}</h2>
                <p className="mt-1 text-sm text-gray-700">{s.description ?? 'Jours de présence non communiqués.'}</p>
                {utilisateur?.role === 'patient' && (
                  <Link
                    to={`/patient/prise-de-rendez-vous?specialite=${s.id}`}
                    className="mt-2 inline-block text-sm font-semibold text-primaire-700 underline hover:text-primaire-800"
                  >
                    Prendre rendez-vous<span className="sr-only"> en {s.libelle}</span>
                  </Link>
                )}
              </li>
            ))}
          </ul>
        )
      )}

      <p className="text-sm text-gray-700">
        Vous pouvez toujours vous présenter directement à l'accueil de la clinique, sans rendez-vous.
        {cible && (
          <>
            {' '}
            <Link to={cible} className="font-semibold text-primaire-700 underline hover:text-primaire-800">
              Prendre rendez-vous en ligne
            </Link>
          </>
        )}
      </p>
    </section>
  )
}
