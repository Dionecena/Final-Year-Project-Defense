import { useCallback, useEffect, useState } from 'react'
import { api, messageErreur } from '../lib/api'
import { ajouterJours, aujourdhui, dateCourte, jourSemaine, libelleDate } from '../lib/dates'
import type { Planning, StatutRdv } from '../types'
import { Bouton, Champ } from './Formulaire'

/** Charge le planning de la semaine qui contient `date` (null : pas de requête). */
export function usePlanning(url: string | null, date: string) {
  const [planning, setPlanning] = useState<Planning | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [chargement, setChargement] = useState(false)

  const recharger = useCallback(async () => {
    if (!url) {
      setPlanning(null)
      return
    }
    setChargement(true)
    setErreur(null)
    try {
      const { data } = await api.get<{ data: Planning }>(url, { params: { date } })
      setPlanning(data.data)
    } catch (err) {
      setErreur(messageErreur(err))
    } finally {
      setChargement(false)
    }
  }, [url, date])

  useEffect(() => {
    recharger()
  }, [recharger])

  return { planning, erreur, chargement, recharger }
}

/** Navigation d'une semaine (ou d'un jour) à l'autre, avec choix direct de la date. */
export function NavigationPeriode({
  date,
  onChange,
  pas,
  libelle,
}: {
  date: string
  onChange: (date: string) => void
  pas: 1 | 7
  libelle: string
}) {
  const unite = pas === 7 ? 'Semaine' : 'Jour'
  const e = pas === 7 ? 'e' : ''
  return (
    <div className="flex flex-wrap items-end gap-2">
      <Bouton variante="secondaire" aria-label={`${unite} précédent${e}`} onClick={() => onChange(ajouterJours(date, -pas))}>
        ← Précédent{e}
      </Bouton>
      <Bouton variante="secondaire" onClick={() => onChange(aujourdhui())}>
        {pas === 7 ? 'Cette semaine' : "Aujourd'hui"}
      </Bouton>
      <Bouton variante="secondaire" aria-label={`${unite} suivant${e}`} onClick={() => onChange(ajouterJours(date, pas))}>
        Suivant{e} →
      </Bouton>
      <Champ label={libelle} type="date" className="w-44" value={date} onChange={(e) => e.target.value && onChange(e.target.value)} />
    </div>
  )
}

const STYLES_STATUT: Record<StatutRdv, string> = {
  confirme: 'border-green-300 bg-green-50 text-green-900',
  a_reprogrammer: 'border-amber-300 bg-amber-50 text-amber-900',
  annule: 'border-gray-300 bg-gray-50 text-gray-600 line-through decoration-gray-400',
  passe: 'border-gray-300 bg-gray-100 text-gray-700',
}

/** Contenu d'une journée : plages de disponibilité, indisponibilités et rendez-vous. */
function Journee({ planning, date }: { planning: Planning; date: string }) {
  const plages = planning.disponibilites.filter((d) => d.jour_semaine === jourSemaine(date))
  const debutJour = `${date} 00:00`
  const finJour = `${ajouterJours(date, 1)} 00:00`
  const indisponibilites = planning.indisponibilites.filter((i) => i.date_debut < finJour && i.date_fin >= debutJour)
  const rendezVous = planning.rendez_vous.filter((r) => r.date === date)

  if (plages.length === 0 && indisponibilites.length === 0 && rendezVous.length === 0) {
    return <p className="text-sm text-gray-500">Aucune consultation.</p>
  }

  return (
    <div className="space-y-2">
      {plages.length > 0 && (
        <ul aria-label="Disponibilités" className="space-y-1">
          {plages.map((d) => (
            <li key={d.id} className="rounded-md bg-primaire-50 px-2 py-1 text-sm text-primaire-800">
              <span className="font-semibold">
                {d.heure_debut}–{d.heure_fin}
              </span>{' '}
              · créneaux de {d.duree_creneau} min
            </li>
          ))}
        </ul>
      )}

      {indisponibilites.map((i) => (
        <p key={i.id} className="rounded-md border border-red-300 bg-red-50 px-2 py-1 text-sm text-red-800">
          <span className="font-semibold">Indisponible</span> du {dateCourte(i.date_debut.slice(0, 10))} {i.date_debut.slice(11)} au{' '}
          {dateCourte(i.date_fin.slice(0, 10))} {i.date_fin.slice(11)}
          {i.motif && ` (${i.motif})`}
        </p>
      ))}

      {rendezVous.length > 0 && (
        <ul aria-label="Rendez-vous" className="space-y-1">
          {rendezVous.map((r) => (
            <li key={r.id} className={`rounded-md border px-2 py-1 text-sm ${STYLES_STATUT[r.statut]}`}>
              <span className="font-semibold">{r.heure}</span> {r.patient.prenom} {r.patient.nom}
              <span className="ml-1 text-xs no-underline">({r.statut_libelle})</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

/** Vue semaine : une colonne par jour sur grand écran, une liste de jours sur smartphone. */
export function VueSemaine({ planning }: { planning: Planning }) {
  const jours = Array.from({ length: 7 }, (_, i) => ajouterJours(planning.semaine.du, i))
  const aujourd = aujourdhui()

  return (
    <ol className="grid gap-3 lg:grid-cols-7 lg:gap-2">
      {jours.map((jour) => (
        <li
          key={jour}
          aria-current={jour === aujourd ? 'date' : undefined}
          className={`rounded-xl border bg-white p-3 shadow-sm ${jour === aujourd ? 'border-primaire-600' : 'border-gray-200'}`}
        >
          <h3 className="mb-2 text-sm font-bold text-gray-900 first-letter:uppercase">{libelleDate(jour)}</h3>
          <Journee planning={planning} date={jour} />
        </li>
      ))}
    </ol>
  )
}

/** Vue d'un seul jour (planning du jour du médecin). */
export function VueJour({ planning, date }: { planning: Planning; date: string }) {
  return (
    <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <h3 className="mb-3 font-bold text-gray-900 first-letter:uppercase">{libelleDate(date, true)}</h3>
      <Journee planning={planning} date={date} />
    </div>
  )
}

/** Légende des couleurs des rendez-vous. */
export function LegendePlanning() {
  return (
    <p className="flex flex-wrap gap-2 text-xs text-gray-700">
      <span className="rounded bg-primaire-50 px-1.5 py-0.5 text-primaire-800">Disponibilité</span>
      <span className="rounded border border-green-300 bg-green-50 px-1.5 py-0.5 text-green-900">Confirmé</span>
      <span className="rounded border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-amber-900">À reprogrammer</span>
      <span className="rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 text-gray-700">Passé</span>
      <span className="rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-gray-600 line-through">Annulé</span>
      <span className="rounded border border-red-300 bg-red-50 px-1.5 py-0.5 text-red-800">Indisponibilité</span>
    </p>
  )
}
