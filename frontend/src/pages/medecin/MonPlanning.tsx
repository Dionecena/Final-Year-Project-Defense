import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Alerte } from '../../components/Formulaire'
import { LegendePlanning, NavigationPeriode, usePlanning, VueJour, VueSemaine } from '../../components/Planning'
import { aujourdhui, dateCourte } from '../../lib/dates'
import DetailRendezVous from './DetailRendezVous'

type Vue = 'jour' | 'semaine'

/** Médecin : planning du jour et de la semaine, en lecture seule (RG12), et détail d'un rendez-vous. */
export default function MonPlanning() {
  const [params, setParams] = useSearchParams()
  const [vue, setVue] = useState<Vue>(params.get('vue') === 'semaine' ? 'semaine' : 'jour')
  const date = params.get('date') ?? aujourdhui()
  const { planning, erreur, chargement } = usePlanning('/api/medecin/planning', date)
  // Rendez-vous dont le détail est ouvert (?rdv=id), parmi ceux de la semaine affichée.
  const detail = planning?.rendez_vous.find((r) => String(r.id) === params.get('rdv')) ?? null

  function changer(suivants: { vue?: Vue; date?: string; rdv?: number | null }) {
    const p = new URLSearchParams(params)
    if (suivants.vue) {
      setVue(suivants.vue)
      p.set('vue', suivants.vue)
    }
    if (suivants.date) p.set('date', suivants.date)
    if (suivants.rdv !== undefined) {
      if (suivants.rdv === null) p.delete('rdv')
      else p.set('rdv', String(suivants.rdv))
    }
    setParams(p, { replace: true })
  }

  return (
    <section aria-labelledby="titre-mon-planning" className="space-y-5">
      <div>
        <h1 id="titre-mon-planning" className="text-2xl font-bold text-gray-900">
          Mon planning
        </h1>
        <p className="text-sm text-gray-600">
          Consultation seulement : pour tout changement, adressez-vous au secrétariat.
        </p>
      </div>

      <div className="flex flex-wrap items-end gap-4 rounded-xl bg-white p-4 shadow-sm">
        <div role="group" aria-label="Affichage" className="inline-flex rounded-lg border border-gray-300 p-0.5">
          {(['jour', 'semaine'] as Vue[]).map((v) => (
            <button
              key={v}
              type="button"
              aria-pressed={vue === v}
              onClick={() => changer({ vue: v })}
              className={`rounded-md px-4 py-2 text-sm font-semibold ${vue === v ? 'bg-primaire-600 text-white' : 'text-gray-800 hover:bg-gray-100'}`}
            >
              {v === 'jour' ? 'Jour' : 'Semaine'}
            </button>
          ))}
        </div>
        <NavigationPeriode
          date={date}
          pas={vue === 'jour' ? 1 : 7}
          libelle={vue === 'jour' ? 'Date' : 'Semaine du'}
          onChange={(d) => changer({ date: d })}
        />
      </div>

      {erreur && <Alerte>{erreur}</Alerte>}

      {!planning ? (
        !erreur && (
          <p role="status" className="text-gray-600">
            Chargement…
          </p>
        )
      ) : (
        <div className="space-y-3" aria-busy={chargement}>
          {vue === 'semaine' && (
            <h2 className="text-lg font-bold text-gray-900">
              Semaine du {dateCourte(planning.semaine.du)} au {dateCourte(planning.semaine.au)}
            </h2>
          )}
          {detail && <DetailRendezVous rendezVous={detail} onFermer={() => changer({ rdv: null })} />}
          <LegendePlanning />
          <p className="text-sm text-gray-600">Sélectionnez un rendez-vous pour afficher son détail et la préconsultation éventuelle.</p>
          {vue === 'jour' ? (
            <VueJour planning={planning} date={date} onRendezVous={(r) => changer({ rdv: r.id })} />
          ) : (
            <VueSemaine planning={planning} onRendezVous={(r) => changer({ rdv: r.id })} />
          )}
        </div>
      )}
    </section>
  )
}
