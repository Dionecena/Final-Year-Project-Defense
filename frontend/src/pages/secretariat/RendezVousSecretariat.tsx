import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { nomMedecin } from '../../components/Creneaux'
import { Alerte, Bouton, Champ, Liste } from '../../components/Formulaire'
import LigneRendezVous from '../../components/LigneRendezVous'
import { api, messageErreur } from '../../lib/api'
import { LIBELLES_STATUT, type Medecin, type RendezVous, type StatutRdv } from '../../types'

interface Page {
  data: RendezVous[]
  meta: { current_page: number; last_page: number; total: number }
}

const FILTRES = ['medecin_id', 'date', 'statut', 'page'] as const

/** Secrétaire : liste des rendez-vous filtrable (médecin, date, statut), modification et annulation sans délai. */
export default function RendezVousSecretariat() {
  const [params, setParams] = useSearchParams()
  const [medecins, setMedecins] = useState<Medecin[] | null>(null)
  const [page, setPage] = useState<Page | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [chargement, setChargement] = useState(false)

  const filtres = Object.fromEntries(FILTRES.map((cle) => [cle, params.get(cle) ?? ''])) as Record<(typeof FILTRES)[number], string>

  useEffect(() => {
    api
      .get<{ data: Medecin[] }>('/api/medecins')
      .then(({ data }) => setMedecins(data.data))
      .catch((err) => setErreur(messageErreur(err)))
  }, [])

  const requete = params.toString()
  const charger = useCallback(async () => {
    setChargement(true)
    try {
      const { data } = await api.get<Page>(`/api/secretariat/rendez-vous?${requete}`)
      setPage(data)
      setErreur(null)
    } catch (err) {
      setErreur(messageErreur(err))
    } finally {
      setChargement(false)
    }
  }, [requete])

  useEffect(() => {
    charger()
  }, [charger])

  function changer(cle: (typeof FILTRES)[number], valeur: string) {
    setMessage(null)
    const suivants = new URLSearchParams(params)
    if (valeur) suivants.set(cle, valeur)
    else suivants.delete(cle)
    if (cle !== 'page') suivants.delete('page')
    setParams(suivants, { replace: true })
  }

  function apresAction(texte: string) {
    setMessage(texte)
    charger()
  }

  return (
    <section aria-labelledby="titre-rdv-secretariat" className="space-y-5">
      <h1 id="titre-rdv-secretariat" className="text-2xl font-bold text-gray-900">
        Rendez-vous
      </h1>

      <form role="search" aria-label="Filtres" className="grid gap-4 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4 lg:items-end" onSubmit={(e) => e.preventDefault()}>
        <Liste label="Médecin" value={filtres.medecin_id} onChange={(e) => changer('medecin_id', e.target.value)}>
          <option value="">Tous les médecins</option>
          {medecins?.map((m) => (
            <option key={m.id} value={m.id}>
              {nomMedecin(m)} – {m.specialite.libelle}
              {!m.actif && ' (désactivé)'}
            </option>
          ))}
        </Liste>
        <Champ label="Date" type="date" value={filtres.date} onChange={(e) => changer('date', e.target.value)} />
        <Liste label="Statut" value={filtres.statut} onChange={(e) => changer('statut', e.target.value)}>
          <option value="">Tous les statuts</option>
          {(Object.keys(LIBELLES_STATUT) as StatutRdv[]).map((s) => (
            <option key={s} value={s}>
              {LIBELLES_STATUT[s]}
            </option>
          ))}
        </Liste>
        <Bouton type="button" variante="secondaire" onClick={() => setParams({}, { replace: true })} disabled={!requete}>
          Effacer les filtres
        </Bouton>
      </form>

      {message && <Alerte type="succes">{message}</Alerte>}
      {erreur && <Alerte>{erreur}</Alerte>}

      {page && (
        <div className="space-y-3" aria-busy={chargement}>
          <p className="text-sm text-gray-700" role="status">
            {page.meta.total} rendez-vous{chargement && ' – actualisation…'}
          </p>
          {page.data.length === 0 ? (
            <p className="text-gray-600">Aucun rendez-vous ne correspond aux filtres.</p>
          ) : (
            <ul className="space-y-3">
              {page.data.map((r) => (
                <LigneRendezVous key={r.id} rendezVous={r} onAction={apresAction} />
              ))}
            </ul>
          )}
          {page.meta.last_page > 1 && (
            <nav aria-label="Pagination" className="flex items-center gap-2">
              <Bouton variante="secondaire" disabled={page.meta.current_page <= 1} onClick={() => changer('page', String(page.meta.current_page - 1))}>
                ← Précédente
              </Bouton>
              <span className="text-sm text-gray-700">
                Page {page.meta.current_page} sur {page.meta.last_page}
              </span>
              <Bouton
                variante="secondaire"
                disabled={page.meta.current_page >= page.meta.last_page}
                onClick={() => changer('page', String(page.meta.current_page + 1))}
              >
                Suivante →
              </Bouton>
            </nav>
          )}
        </div>
      )}
    </section>
  )
}
