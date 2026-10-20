import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Alerte, Bouton, Champ, Liste } from '../../components/Formulaire'
import { LegendePlanning, NavigationPeriode, usePlanning, VueSemaine } from '../../components/Planning'
import { api, erreursChamps, messageErreur } from '../../lib/api'
import { aujourdhui, dateCourte } from '../../lib/dates'
import { JOURS_SEMAINE, type Medecin } from '../../types'

/** Secrétaire : planning hebdomadaire d'un médecin et ajout d'une disponibilité (RG14). */
export default function PlanningMedecins() {
  const [params, setParams] = useSearchParams()
  const [medecins, setMedecins] = useState<Medecin[] | null>(null)
  const [erreurListe, setErreurListe] = useState<string | null>(null)
  const [succes, setSucces] = useState<string | null>(null)

  const medecinId = params.get('medecin') ?? ''
  const date = params.get('date') ?? aujourdhui()
  const { planning, erreur, chargement, recharger } = usePlanning(medecinId ? `/api/medecins/${medecinId}/planning` : null, date)
  const medecin = medecins?.find((m) => String(m.id) === medecinId)

  useEffect(() => {
    api
      .get<{ data: Medecin[] }>('/api/medecins')
      .then(({ data }) => setMedecins(data.data))
      .catch((err) => setErreurListe(messageErreur(err)))
  }, [])

  function changer(cle: 'medecin' | 'date', valeur: string) {
    setSucces(null)
    const suivants = new URLSearchParams(params)
    if (valeur) suivants.set(cle, valeur)
    else suivants.delete(cle)
    setParams(suivants, { replace: true })
  }

  return (
    <section aria-labelledby="titre-planning-medecins" className="space-y-5">
      <h1 id="titre-planning-medecins" className="text-2xl font-bold text-gray-900">
        Planning des médecins
      </h1>

      {erreurListe && <Alerte>{erreurListe}</Alerte>}

      <div className="grid gap-4 rounded-xl bg-white p-4 shadow-sm md:grid-cols-[minmax(0,20rem)_1fr] md:items-end">
        <Liste label="Médecin" value={medecinId} onChange={(e) => changer('medecin', e.target.value)} disabled={!medecins}>
          <option value="">{medecins ? 'Choisir un médecin' : 'Chargement…'}</option>
          {medecins?.map((m) => (
            <option key={m.id} value={m.id}>
              {m.prenom} {m.nom} – {m.specialite.libelle}
              {!m.actif && ' (désactivé)'}
            </option>
          ))}
        </Liste>
        <NavigationPeriode date={date} pas={7} libelle="Semaine du" onChange={(d) => changer('date', d)} />
      </div>

      {!medecinId ? (
        <p className="text-gray-600">Choisissez un médecin pour afficher son planning.</p>
      ) : (
        <>
          {succes && <Alerte type="succes">{succes}</Alerte>}
          {erreur && <Alerte>{erreur}</Alerte>}

          {planning && (
            <div className="space-y-3" aria-busy={chargement}>
              <h2 className="text-lg font-bold text-gray-900">
                {planning.medecin.prenom} {planning.medecin.nom} – semaine du {dateCourte(planning.semaine.du)} au{' '}
                {dateCourte(planning.semaine.au)}
              </h2>
              <LegendePlanning />
              <VueSemaine planning={planning} />
            </div>
          )}

          {medecin &&
            (medecin.actif ? (
              <FormulaireDisponibilite
                key={medecin.id}
                medecinId={medecin.id}
                onAjoutee={(texte) => {
                  setSucces(texte)
                  recharger()
                }}
              />
            ) : (
              <Alerte type="info">Ce médecin est désactivé : aucune disponibilité ne peut lui être ajoutée.</Alerte>
            ))}
        </>
      )}
    </section>
  )
}

const VALEURS_INITIALES = { jour_semaine: '1', heure_debut: '', heure_fin: '', duree_creneau: '30' }

function FormulaireDisponibilite({ medecinId, onAjoutee }: { medecinId: number; onAjoutee: (message: string) => void }) {
  const [valeurs, setValeurs] = useState(VALEURS_INITIALES)
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  const maj = (champ: keyof typeof valeurs) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setValeurs((v) => ({ ...v, [champ]: e.target.value }))

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    setErreurs({})
    setErreurGenerale(null)
    setEnvoi(true)
    try {
      await api.post(`/api/medecins/${medecinId}/disponibilites`, {
        jour_semaine: Number(valeurs.jour_semaine),
        heure_debut: valeurs.heure_debut,
        heure_fin: valeurs.heure_fin,
        duree_creneau: valeurs.duree_creneau === '' ? null : Number(valeurs.duree_creneau),
      })
      const jour = JOURS_SEMAINE[Number(valeurs.jour_semaine) - 1]
      onAjoutee(`Disponibilité ajoutée : ${jour.toLowerCase()} de ${valeurs.heure_debut} à ${valeurs.heure_fin}.`)
      setValeurs(VALEURS_INITIALES)
    } catch (err) {
      const parChamp = erreursChamps(err)
      setErreurs(parChamp)
      setErreurGenerale(parChamp.medecin_id ?? (Object.keys(parChamp).length ? 'Veuillez corriger les champs signalés.' : messageErreur(err)))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <form onSubmit={soumettre} noValidate aria-labelledby="titre-ajout-disponibilite" className="space-y-4 rounded-xl border border-primaire-100 bg-white p-5 shadow-sm">
      <div>
        <h2 id="titre-ajout-disponibilite" className="text-lg font-bold text-gray-900">
          Ajout d'une disponibilité
        </h2>
        <p className="text-sm text-gray-600">
          Plage hebdomadaire, répétée chaque semaine. Une disponibilité ne peut ensuite être ni modifiée ni supprimée : tout
          changement passe par une indisponibilité.
        </p>
      </div>
      {erreurGenerale && <Alerte>{erreurGenerale}</Alerte>}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Liste label="Jour" required value={valeurs.jour_semaine} onChange={maj('jour_semaine')} erreur={erreurs.jour_semaine}>
          {JOURS_SEMAINE.map((jour, i) => (
            <option key={jour} value={i + 1}>
              {jour}
            </option>
          ))}
        </Liste>
        <Champ label="Heure de début" type="time" step={60} required value={valeurs.heure_debut} onChange={maj('heure_debut')} erreur={erreurs.heure_debut} />
        <Champ label="Heure de fin" type="time" step={60} required value={valeurs.heure_fin} onChange={maj('heure_fin')} erreur={erreurs.heure_fin} />
        <Champ
          label="Durée d'un créneau (minutes)"
          type="number"
          min={1}
          inputMode="numeric"
          required
          value={valeurs.duree_creneau}
          onChange={maj('duree_creneau')}
          erreur={erreurs.duree_creneau}
        />
      </div>

      <Bouton type="submit" disabled={envoi}>
        {envoi ? 'Enregistrement…' : 'Ajouter la disponibilité'}
      </Bouton>
    </form>
  )
}
