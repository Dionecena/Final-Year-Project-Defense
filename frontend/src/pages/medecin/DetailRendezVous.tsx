import { useEffect, useRef, useState } from 'react'
import { BadgeStatut, libelleRendezVous } from '../../components/Creneaux'
import { Alerte, Bouton } from '../../components/Formulaire'
import { api, messageErreur } from '../../lib/api'
import { MENTION_NON_DIAGNOSTIC, type Planning, type Preconsultation } from '../../types'

/**
 * Détail d'un rendez-vous du planning du médecin, avec la préconsultation si elle existe.
 * Son contenu n'est lisible que par le médecin du rendez-vous et le patient (RG10, contrôlé par le serveur).
 */
export default function DetailRendezVous({ rendezVous: r, onFermer }: { rendezVous: Planning['rendez_vous'][number]; onFermer: () => void }) {
  const [preconsultation, setPreconsultation] = useState<Preconsultation | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const titre = useRef<HTMLHeadingElement>(null)

  useEffect(() => {
    titre.current?.focus()
    setPreconsultation(null)
    setErreur(null)
    if (!r.a_preconsultation) return
    let actif = true
    api
      .get<{ data: Preconsultation }>(`/api/rendez-vous/${r.id}/preconsultation`)
      .then(({ data }) => actif && setPreconsultation(data.data))
      .catch((err) => actif && setErreur(messageErreur(err)))
    return () => {
      actif = false
    }
  }, [r.id, r.a_preconsultation])

  return (
    <section aria-labelledby="titre-detail-rdv" className="space-y-4 rounded-xl border border-primaire-600 bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 id="titre-detail-rdv" ref={titre} tabIndex={-1} className="text-lg font-bold text-gray-900 first-letter:uppercase focus:outline-none">
            {libelleRendezVous(r)}
          </h2>
          <p className="text-gray-700">
            Patient : <span className="font-medium text-gray-900">{r.patient.prenom} {r.patient.nom}</span>
          </p>
        </div>
        <BadgeStatut statut={r.statut} libelle={r.statut_libelle} />
      </div>

      <div className="space-y-3">
        <h3 className="font-semibold text-gray-900">Préconsultation</h3>
        {!r.a_preconsultation && <p className="text-sm text-gray-700">Le patient n'a pas rempli de préconsultation.</p>}
        {erreur && <Alerte>{erreur}</Alerte>}
        {r.a_preconsultation && !preconsultation && !erreur && (
          <p role="status" className="text-sm text-gray-600">
            Chargement…
          </p>
        )}
        {preconsultation && (
          <>
            <dl className="space-y-3 rounded-lg bg-gray-50 p-3">
              <div>
                <dt className="text-sm font-medium text-gray-700">Motif</dt>
                <dd className="text-gray-900">{preconsultation.motif}</dd>
              </div>
              <div>
                <dt className="text-sm font-medium text-gray-700">Symptômes</dt>
                {/* Texte brut saisi par le patient, rendu sans interprétation HTML. */}
                <dd className="whitespace-pre-line break-words text-gray-900">{preconsultation.symptomes}</dd>
              </div>
              {preconsultation.date_saisie && (
                <div>
                  <dt className="text-sm font-medium text-gray-700">Saisie le</dt>
                  <dd className="text-gray-900">{libelleRendezVous({ date: preconsultation.date_saisie.slice(0, 10), heure: preconsultation.date_saisie.slice(11) })}</dd>
                </div>
              )}
            </dl>
            {preconsultation.specialite_suggeree && (
              <p className="text-sm text-gray-700">
                Une orientation vers la spécialité <strong>{preconsultation.specialite_suggeree.libelle}</strong> avait été suggérée ; le
                patient a conservé son choix.
              </p>
            )}
            <p className="text-xs text-gray-600">{MENTION_NON_DIAGNOSTIC}</p>
          </>
        )}
      </div>

      <Bouton variante="secondaire" onClick={onFermer}>
        Fermer le détail
      </Bouton>
    </section>
  )
}
