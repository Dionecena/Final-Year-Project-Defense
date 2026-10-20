import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { BadgeStatut, libelleRendezVous, nomMedecin } from '../../components/Creneaux'
import { Alerte } from '../../components/Formulaire'
import { aVenir, useMesRendezVous } from './MesRendezVous'

/** Tableau de bord du patient : prochain rendez-vous et accès rapides. */
export default function TableauDeBord() {
  const { utilisateur } = useAuth()
  const { rendezVous, erreur } = useMesRendezVous()
  const prochains = rendezVous ? aVenir(rendezVous) : []
  const prochain = prochains[0]

  return (
    <section aria-labelledby="titre-tableau-bord" className="space-y-5">
      <h1 id="titre-tableau-bord" className="text-2xl font-bold text-gray-900">
        Bonjour {utilisateur?.prenom} {utilisateur?.nom}
      </h1>

      {erreur && <Alerte>{erreur}</Alerte>}

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-gray-900">Prochain rendez-vous</h2>
          {!rendezVous && !erreur && (
            <p role="status" className="mt-2 text-gray-600">
              Chargement…
            </p>
          )}
          {rendezVous &&
            (prochain ? (
              <div className="mt-2 space-y-1">
                <p className="font-semibold text-gray-900 first-letter:uppercase">{libelleRendezVous(prochain)}</p>
                <p className="text-sm text-gray-700">
                  {prochain.specialite.libelle} – {nomMedecin(prochain.medecin)}
                </p>
                <BadgeStatut statut={prochain.statut} libelle={prochain.statut_libelle} />
                {prochains.length > 1 && (
                  <p className="text-sm text-gray-600">
                    {prochains.length - 1} autre{prochains.length > 2 ? 's' : ''} rendez-vous à venir.
                  </p>
                )}
              </div>
            ) : (
              <p className="mt-2 text-gray-600">Aucun rendez-vous à venir.</p>
            ))}
        </div>

        <div className="space-y-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-bold text-gray-900">Que souhaitez-vous faire ?</h2>
          <div className="flex flex-wrap gap-2">
            <Link to="/patient/prise-de-rendez-vous" className="rounded-lg bg-primaire-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primaire-700">
              Prendre un rendez-vous
            </Link>
            <Link to="/patient/mes-rendez-vous" className="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 hover:bg-gray-50">
              Mes rendez-vous
            </Link>
          </div>
        </div>
      </div>

      {/* RG13 */}
      <p className="text-sm text-gray-700">Vous pouvez toujours vous présenter directement à l'accueil de la clinique, sans rendez-vous.</p>
    </section>
  )
}
