import { useEffect, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { nomMedecin } from '../../components/Creneaux'
import { Alerte, Bouton, Champ, Liste } from '../../components/Formulaire'
import LigneRendezVous from '../../components/LigneRendezVous'
import { LegendePlanning, usePlanning, VueSemaine } from '../../components/Planning'
import { api, erreursChamps, messageErreur } from '../../lib/api'
import { dateCourte } from '../../lib/dates'
import type { Medecin, RendezVous } from '../../types'

interface Indisponibilite {
  id: number
  medecin_id: number
  date_debut: string
  date_fin: string
  motif: string | null
}

interface Resultat {
  indisponibilite: Indisponibilite
  /** Rendez-vous passés à « à reprogrammer » (RG09). */
  rendez_vous: RendezVous[]
}

const VALEURS_INITIALES = { date_debut: '', date_fin: '', motif: '' }

/** « 2026-11-02T09:00 » (champ datetime-local) → « 2026-11-02 09:00 » (format de l'API). */
const versApi = (valeur: string) => valeur.replace('T', ' ')

/** « 2026-11-02 09:00 » → « 02/11/2026 à 09:00 ». */
const libelleDateHeure = (valeur: string) => `${dateCourte(valeur.slice(0, 10))} à ${valeur.slice(11)}`

/**
 * Secrétaire : enregistrement d'une indisponibilité (section 8.4). Les rendez-vous confirmés de la
 * période passent à « à reprogrammer » et sont affichés aussitôt, avec les actions « Reprogrammer »
 * et « Annuler ».
 */
export default function Indisponibilites() {
  const [params, setParams] = useSearchParams()
  const [medecins, setMedecins] = useState<Medecin[] | null>(null)
  const [valeurs, setValeurs] = useState(VALEURS_INITIALES)
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const [resultat, setResultat] = useState<Resultat | null>(null)
  const [traites, setTraites] = useState<Record<number, string>>({})

  const medecinId = params.get('medecin') ?? ''
  const medecin = medecins?.find((m) => String(m.id) === medecinId)
  const { planning, recharger } = usePlanning(
    resultat ? `/api/medecins/${resultat.indisponibilite.medecin_id}/planning` : null,
    resultat?.indisponibilite.date_debut.slice(0, 10) ?? '',
  )

  useEffect(() => {
    api
      .get<{ data: Medecin[] }>('/api/medecins')
      .then(({ data }) => setMedecins(data.data))
      .catch((err) => setErreurGenerale(messageErreur(err)))
  }, [])

  const maj = (champ: keyof typeof valeurs) => (e: React.ChangeEvent<HTMLInputElement>) => setValeurs((v) => ({ ...v, [champ]: e.target.value }))

  function choisirMedecin(id: string) {
    setResultat(null)
    setParams(id ? { medecin: id } : {}, { replace: true })
  }

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    setErreurs({})
    setErreurGenerale(null)
    setResultat(null)
    setTraites({})
    if (!medecinId) {
      setErreurs({ medecin_id: 'Veuillez choisir un médecin.' })
      return
    }
    setEnvoi(true)
    try {
      const { data } = await api.post<{ data: Resultat }>(`/api/medecins/${medecinId}/indisponibilites`, {
        date_debut: versApi(valeurs.date_debut),
        date_fin: versApi(valeurs.date_fin),
        motif: valeurs.motif,
      })
      setResultat(data.data)
      setValeurs(VALEURS_INITIALES)
    } catch (err) {
      const parChamp = erreursChamps(err)
      setErreurs(parChamp)
      setErreurGenerale(Object.keys(parChamp).length ? 'Veuillez corriger les champs signalés.' : messageErreur(err))
    } finally {
      setEnvoi(false)
    }
  }

  const concernes = resultat?.rendez_vous ?? []

  return (
    <section aria-labelledby="titre-indisponibilites" className="space-y-5">
      <div>
        <h1 id="titre-indisponibilites" className="text-2xl font-bold text-gray-900">
          Enregistrement d'une indisponibilité
        </h1>
        <p className="text-sm text-gray-600">
          Les rendez-vous confirmés du médecin pendant la période passent à « À reprogrammer » et leurs patients sont prévenus par
          courriel. Les créneaux de la période ne sont plus proposés.
        </p>
      </div>

      <form onSubmit={soumettre} noValidate aria-labelledby="titre-indisponibilites" className="space-y-4 rounded-xl bg-white p-5 shadow-sm">
        {erreurGenerale && <Alerte>{erreurGenerale}</Alerte>}

        <Liste label="Médecin" required value={medecinId} onChange={(e) => choisirMedecin(e.target.value)} disabled={!medecins} erreur={erreurs.medecin_id}>
          <option value="">{medecins ? 'Choisir un médecin' : 'Chargement…'}</option>
          {medecins?.map((m) => (
            <option key={m.id} value={m.id}>
              {nomMedecin(m)} – {m.specialite.libelle}
              {!m.actif && ' (désactivé)'}
            </option>
          ))}
        </Liste>

        <div className="grid gap-4 sm:grid-cols-2">
          <Champ label="Début" type="datetime-local" step={60} required value={valeurs.date_debut} onChange={maj('date_debut')} erreur={erreurs.date_debut} />
          <Champ
            label="Fin"
            type="datetime-local"
            step={60}
            required
            min={valeurs.date_debut || undefined}
            value={valeurs.date_fin}
            onChange={maj('date_fin')}
            erreur={erreurs.date_fin}
            aide="Les rendez-vous dont l'heure tombe entre le début et la fin, bornes comprises, sont concernés."
          />
        </div>
        <Champ label="Motif (facultatif)" maxLength={255} value={valeurs.motif} onChange={maj('motif')} erreur={erreurs.motif} />

        <Bouton type="submit" disabled={envoi}>
          {envoi ? 'Enregistrement…' : "Enregistrer l'indisponibilité"}
        </Bouton>
      </form>

      {resultat && (
        <div className="space-y-4" aria-live="polite">
          <Alerte type="succes">
            Indisponibilité enregistrée{medecin && ` pour ${nomMedecin(medecin)}`}, du {libelleDateHeure(resultat.indisponibilite.date_debut)} au{' '}
            {libelleDateHeure(resultat.indisponibilite.date_fin)}.
          </Alerte>

          <section aria-labelledby="titre-concernes" className="space-y-3">
            <h2 id="titre-concernes" className="text-lg font-bold text-gray-900">
              Rendez-vous concernés ({concernes.length})
            </h2>
            {concernes.length === 0 ? (
              <p className="text-gray-700">Aucun rendez-vous confirmé n'était prévu pendant cette période.</p>
            ) : (
              <>
                <p className="text-sm text-gray-700">
                  Ces rendez-vous sont à reprogrammer ou à annuler. Ils restent aussi dans la liste{' '}
                  <Link to="/secretariat/a-reprogrammer" className="font-medium text-primaire-700 underline">
                    « À reprogrammer »
                  </Link>
                  .
                </p>
                <ul className="space-y-3">
                  {concernes.map((r) =>
                    traites[r.id] ? (
                      <li key={r.id}>
                        <Alerte type="succes">{traites[r.id]}</Alerte>
                      </li>
                    ) : (
                      <LigneRendezVous
                        key={r.id}
                        rendezVous={r}
                        onAction={(texte) => {
                          setTraites((t) => ({ ...t, [r.id]: texte }))
                          recharger()
                        }}
                      />
                    ),
                  )}
                </ul>
              </>
            )}
          </section>

          {planning && (
            <section aria-labelledby="titre-planning-indisponibilite" className="space-y-3">
              <h2 id="titre-planning-indisponibilite" className="text-lg font-bold text-gray-900">
                Planning de la semaine du {dateCourte(planning.semaine.du)}
              </h2>
              <LegendePlanning />
              <VueSemaine planning={planning} />
            </section>
          )}
        </div>
      )}
    </section>
  )
}
