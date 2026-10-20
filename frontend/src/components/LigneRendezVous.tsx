import { useState } from 'react'
import { api, messageErreur } from '../lib/api'
import type { RendezVous } from '../types'
import { BadgeStatut, libelleRendezVous, ModificationRendezVous, nomMedecin } from './Creneaux'
import { Alerte, Bouton } from './Formulaire'

/**
 * Rendez-vous vu par la secrétaire, avec changement de créneau et annulation sans délai.
 * Pour un rendez-vous « à reprogrammer », le changement de créneau s'intitule « Reprogrammer »
 * (même route PATCH : le rendez-vous repasse à « confirmé »).
 */
export default function LigneRendezVous({ rendezVous: r, onAction }: { rendezVous: RendezVous; onAction: (message: string) => void }) {
  const [mode, setMode] = useState<'aucun' | 'modification' | 'annulation'>('aucun')
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const patient = r.patient ? `${r.patient.prenom} ${r.patient.nom}` : ''
  const aReprogrammer = r.statut === 'a_reprogrammer'

  async function annuler() {
    setEnvoi(true)
    setErreur(null)
    try {
      await api.post(`/api/rendez-vous/${r.id}/annulation`)
      onAction(`Le rendez-vous de ${patient} du ${libelleRendezVous(r)} est annulé.`)
    } catch (err) {
      setErreur(messageErreur(err))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <li className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="font-semibold text-gray-900 first-letter:uppercase">{libelleRendezVous(r)}</p>
          <p className="text-sm text-gray-700">
            {r.specialite.libelle} – {nomMedecin(r.medecin)}
          </p>
          {r.patient && (
            <p className="text-sm text-gray-700">
              Patient : <span className="font-medium text-gray-900">{patient}</span>
              {r.patient.telephone && <> · {r.patient.telephone}</>}
            </p>
          )}
          {/* RG10 : la secrétaire voit seulement si une préconsultation existe. */}
          {r.a_preconsultation !== undefined && (
            <p className="text-sm text-gray-700">Préconsultation : {r.a_preconsultation ? 'remplie' : 'non remplie'}</p>
          )}
        </div>
        <BadgeStatut statut={r.statut} libelle={r.statut_libelle} />
      </div>

      {erreur && (
        <div className="mt-3">
          <Alerte>{erreur}</Alerte>
        </div>
      )}

      {mode === 'aucun' && (r.modifiable || r.annulable) && (
        <div className="mt-3 flex flex-wrap gap-2">
          {r.modifiable && (
            <Bouton variante={aReprogrammer ? 'primaire' : 'secondaire'} onClick={() => setMode('modification')}>
              {aReprogrammer ? 'Reprogrammer' : 'Modifier'}
            </Bouton>
          )}
          {r.annulable && (
            <Bouton variante="secondaire" onClick={() => setMode('annulation')}>
              Annuler
            </Bouton>
          )}
        </div>
      )}
      {mode === 'annulation' && (
        <div role="group" aria-label="Confirmation de l'annulation" className="mt-3 space-y-2 rounded-lg border border-red-200 bg-red-50 p-3">
          <p className="text-sm text-red-900">Confirmez-vous l'annulation du rendez-vous de {patient} ?</p>
          <div className="flex flex-wrap gap-2">
            <Bouton variante="danger" onClick={annuler} disabled={envoi}>
              {envoi ? 'Annulation…' : "Confirmer l'annulation"}
            </Bouton>
            <Bouton variante="secondaire" onClick={() => setMode('aucun')} disabled={envoi}>
              Garder le rendez-vous
            </Bouton>
          </div>
        </div>
      )}
      {mode === 'modification' && (
        <ModificationRendezVous
          rendezVous={r}
          onFermer={() => setMode('aucun')}
          onModifie={(nouveau) => {
            setMode('aucun')
            onAction(
              `Le rendez-vous de ${patient} est ${aReprogrammer ? 'reprogrammé' : 'déplacé'} au ${libelleRendezVous(nouveau)} avec ${nomMedecin(nouveau.medecin)}.`,
            )
          }}
        />
      )}
    </li>
  )
}
