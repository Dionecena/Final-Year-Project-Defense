import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { BadgeStatut, libelleRendezVous, ModificationRendezVous, nomMedecin } from '../../components/Creneaux'
import { Alerte, Bouton } from '../../components/Formulaire'
import { api, messageErreur } from '../../lib/api'
import { dateCourte } from '../../lib/dates'
import { STATUTS_ACTIFS, type RendezVous } from '../../types'

/** Rendez-vous du patient connecté : à venir et historique. */
export function useMesRendezVous() {
  const [rendezVous, setRendezVous] = useState<RendezVous[] | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const recharger = useCallback(async () => {
    try {
      const { data } = await api.get<{ data: RendezVous[] }>('/api/mes-rendez-vous')
      setRendezVous(data.data)
      setErreur(null)
    } catch (err) {
      setErreur(messageErreur(err))
    }
  }, [])

  useEffect(() => {
    recharger()
  }, [recharger])

  return { rendezVous, erreur, recharger }
}

/** À venir : statut confirmé ou à reprogrammer, du plus proche au plus lointain. */
export function aVenir(liste: RendezVous[]) {
  return liste.filter((r) => STATUTS_ACTIFS.includes(r.statut)).sort((a, b) => a.date_heure.localeCompare(b.date_heure))
}

/** Patient : ses rendez-vous, avec modification et annulation dans le délai (RG08). */
export default function MesRendezVous() {
  const { rendezVous, erreur, recharger } = useMesRendezVous()
  const [message, setMessage] = useState<string | null>(null)

  const prochains = rendezVous ? aVenir(rendezVous) : []
  const historique = rendezVous?.filter((r) => !STATUTS_ACTIFS.includes(r.statut)) ?? []

  function apresAction(texte: string) {
    setMessage(texte)
    recharger()
  }

  return (
    <section aria-labelledby="titre-mes-rdv" className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 id="titre-mes-rdv" className="text-2xl font-bold text-gray-900">
          Mes rendez-vous
        </h1>
        <Link to="/patient/prise-de-rendez-vous" className="rounded-lg bg-primaire-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primaire-700">
          Prendre un rendez-vous
        </Link>
      </div>

      {message && <Alerte type="succes">{message}</Alerte>}
      {erreur && <Alerte>{erreur}</Alerte>}
      {!rendezVous && !erreur && (
        <p role="status" className="text-gray-600">
          Chargement…
        </p>
      )}

      {rendezVous && (
        <>
          <div className="space-y-3">
            <h2 className="text-lg font-bold text-gray-900">À venir</h2>
            {prochains.length === 0 ? (
              <p className="text-gray-600">Aucun rendez-vous à venir.</p>
            ) : (
              <ul className="space-y-3">
                {prochains.map((r) => (
                  <CarteRendezVous key={r.id} rendezVous={r} onAction={apresAction} />
                ))}
              </ul>
            )}
          </div>

          {historique.length > 0 && (
            <div className="space-y-3">
              <h2 className="text-lg font-bold text-gray-900">Historique</h2>
              <ul className="space-y-3">
                {historique.map((r) => (
                  <CarteRendezVous key={r.id} rendezVous={r} onAction={apresAction} />
                ))}
              </ul>
            </div>
          )}
        </>
      )}
    </section>
  )
}

function CarteRendezVous({ rendezVous: r, onAction }: { rendezVous: RendezVous; onAction: (message: string) => void }) {
  const [mode, setMode] = useState<'aucun' | 'modification' | 'annulation'>('aucun')
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const actif = STATUTS_ACTIFS.includes(r.statut)

  async function annuler() {
    setEnvoi(true)
    setErreur(null)
    try {
      await api.post(`/api/rendez-vous/${r.id}/annulation`)
      onAction(`Le rendez-vous du ${libelleRendezVous(r)} est annulé.`)
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
        </div>
        <BadgeStatut statut={r.statut} libelle={r.statut_libelle} />
      </div>

      {r.statut === 'a_reprogrammer' && (
        <p className="mt-2 text-sm text-amber-900">
          Le médecin est indisponible à cette date : la clinique va vous proposer un autre créneau. Vous pouvez aussi choisir vous-même un autre créneau avec le même médecin.
        </p>
      )}

      {erreur && (
        <div className="mt-3">
          <Alerte>{erreur}</Alerte>
        </div>
      )}

      {actif &&
        (r.modifiable || r.annulable ? (
          <>
            <p className="mt-2 text-xs text-gray-600">
              Modifiable ou annulable en ligne jusqu'au {dateCourte(r.limite_modification.slice(0, 10))} à {r.limite_modification.slice(11)}.
            </p>
            {mode === 'aucun' && (
              <div className="mt-3 flex flex-wrap gap-2">
                {r.modifiable && (
                  <Bouton variante="secondaire" onClick={() => setMode('modification')}>
                    Modifier
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
                <p className="text-sm text-red-900">Confirmez-vous l'annulation de ce rendez-vous ? Le créneau sera libéré.</p>
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
                memeMedecin
                onFermer={() => setMode('aucun')}
                onModifie={(nouveau) => {
                  setMode('aucun')
                  onAction(`Votre rendez-vous est déplacé au ${libelleRendezVous(nouveau)}.`)
                }}
              />
            )}
          </>
        ) : (
          // RG08 : passé le délai, l'interface invite à contacter la clinique.
          <p className="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
            Le délai de modification en ligne est dépassé. Pour modifier ou annuler ce rendez-vous, veuillez contacter la clinique.
          </p>
        ))}
    </li>
  )
}
