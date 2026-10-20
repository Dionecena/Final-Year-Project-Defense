import { useEffect, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { BadgeStatut, estConflit, libelleRendezVous, nomMedecin, SelecteurCreneau, useCreneauxSpecialite } from '../../components/Creneaux'
import { Alerte, Bouton, Champ, ZoneTexte } from '../../components/Formulaire'
import { api, erreursChamps, messageErreur } from '../../lib/api'
import { MENTION_NON_DIAGNOSTIC, type Creneau, type RendezVous, type SaisiePreconsultation, type Specialite, type SuggestionOrientation } from '../../types'

type Etape = 1 | 2 | 3 | 4 | 5

const ETAPES: { numero: Etape; libelle: string }[] = [
  { numero: 1, libelle: 'Spécialité' },
  { numero: 2, libelle: 'Préconsultation' },
  { numero: 3, libelle: 'Orientation' },
  { numero: 4, libelle: 'Médecin et créneau' },
  { numero: 5, libelle: 'Confirmation' },
]

/** Prise de rendez-vous en 5 étapes (section 13). */
export default function PriseRendezVous() {
  const [params] = useSearchParams()
  const [specialites, setSpecialites] = useState<Specialite[] | null>(null)
  const [erreurListe, setErreurListe] = useState<string | null>(null)
  const [etape, setEtape] = useState<Etape>(1)
  const [specialite, setSpecialite] = useState<Specialite | null>(null)
  const [medecinId, setMedecinId] = useState('')
  const [creneau, setCreneau] = useState<Creneau | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const [reserve, setReserve] = useState<RendezVous | null>(null)
  // Préconsultation facultative (RG02) : saisie en cours, puis valeur retenue (null : étape passée).
  const [saisie, setSaisie] = useState<SaisiePreconsultation>({ motif: '', symptomes: '' })
  const [preconsultation, setPreconsultation] = useState<SaisiePreconsultation | null>(null)
  const [erreursPreconsultation, setErreursPreconsultation] = useState<Record<string, string>>({})
  const [suggestion, setSuggestion] = useState<SuggestionOrientation | null>(null)

  const { creneaux, erreur: erreurCreneaux, chargement, recharger } = useCreneauxSpecialite(etape >= 4 ? (specialite?.id ?? null) : null)

  useEffect(() => {
    api
      .get<{ data: Specialite[] }>('/api/specialites')
      .then(({ data }) => {
        setSpecialites(data.data)
        // Spécialité présélectionnée depuis la page « Spécialités » (?specialite=id).
        const initiale = data.data.find((s) => String(s.id) === params.get('specialite'))
        if (initiale) setSpecialite(initiale)
      })
      .catch((err) => setErreurListe(messageErreur(err)))
  }, [params])

  function aller(suivante: Etape) {
    setErreur(null)
    setEtape(suivante)
    window.scrollTo({ top: 0 })
  }

  function choisirSpecialite(s: Specialite) {
    if (s.id !== specialite?.id) {
      setMedecinId('')
      setCreneau(null)
      // La préconsultation sera revérifiée pour la nouvelle spécialité.
      setPreconsultation(null)
      setSuggestion(null)
    }
    setSpecialite(s)
  }

  /** RG02 : la préconsultation ne bloque jamais la réservation. */
  function passerPreconsultation() {
    setPreconsultation(null)
    setSuggestion(null)
    setErreursPreconsultation({})
    aller(4)
  }

  /** Vérification d'orientation (section 8.3) : l'étape 3 n'apparaît qu'en cas de suggestion (RG04). */
  async function validerPreconsultation(e: FormEvent) {
    e.preventDefault()
    if (!specialite) return
    const valeur = { motif: saisie.motif.trim(), symptomes: saisie.symptomes.trim() }
    setEnvoi(true)
    setErreur(null)
    setErreursPreconsultation({})
    try {
      const { data } = await api.post<{ data: { suggestion: SuggestionOrientation | null } }>('/api/orientation/verification', {
        specialite_id: specialite.id,
        ...valeur,
      })
      setPreconsultation(valeur)
      setSuggestion(data.data.suggestion)
      aller(data.data.suggestion ? 3 : 4)
    } catch (err) {
      const champs = erreursChamps(err)
      setErreursPreconsultation(champs)
      if (Object.keys(champs).length === 0) setErreur(messageErreur(err))
    } finally {
      setEnvoi(false)
    }
  }

  /** « Choisir la spécialité suggérée » : la préconsultation saisie est conservée. */
  function suivreSuggestion() {
    if (!suggestion) return
    const suggeree = specialites?.find((s) => s.id === suggestion.specialite.id) ?? suggestion.specialite
    setSpecialite(suggeree)
    setMedecinId('')
    setCreneau(null)
    setSuggestion(null)
    // RG11 : la limite de la nouvelle spécialité peut être plus courte.
    if (preconsultation && preconsultation.symptomes.length > suggeree.max_symptomes) {
      setPreconsultation(null)
      setErreursPreconsultation({
        symptomes: `Pour la spécialité ${suggeree.libelle}, les symptômes ne doivent pas dépasser ${suggeree.max_symptomes} caractères.`,
      })
      aller(2)
      return
    }
    aller(4)
  }

  async function confirmer() {
    if (!creneau) return
    setEnvoi(true)
    setErreur(null)
    try {
      const { data } = await api.post<{ data: RendezVous }>('/api/rendez-vous', {
        medecin_id: creneau.medecin.id,
        date_heure: creneau.date_heure,
        ...(preconsultation ? { preconsultation } : {}),
      })
      setReserve(data.data)
    } catch (err) {
      setErreur(messageErreur(err))
      // RG07 : le créneau a été pris entre-temps, le patient en choisit un autre.
      if (estConflit(err)) {
        setCreneau(null)
        setEtape(4)
        recharger()
      }
    } finally {
      setEnvoi(false)
    }
  }

  if (reserve) {
    return (
      <section aria-labelledby="titre-rdv-confirme" className="mx-auto max-w-xl space-y-4">
        <h1 id="titre-rdv-confirme" className="text-2xl font-bold text-gray-900">
          Rendez-vous confirmé
        </h1>
        <Alerte type="succes">Votre rendez-vous est enregistré et confirmé. Aucune validation supplémentaire n'est nécessaire.</Alerte>
        <dl className="space-y-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
          <Ligne terme="Spécialité" valeur={reserve.specialite.libelle} />
          <Ligne terme="Médecin" valeur={nomMedecin(reserve.medecin)} />
          <Ligne terme="Date et heure" valeur={libelleRendezVous(reserve)} />
          <div className="flex flex-wrap gap-2">
            <dt className="font-medium text-gray-700">Statut :</dt>
            <dd>
              <BadgeStatut statut={reserve.statut} libelle={reserve.statut_libelle} />
            </dd>
          </div>
        </dl>
        <div className="flex flex-wrap gap-2">
          <Link to="/patient/mes-rendez-vous" className="rounded-lg bg-primaire-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primaire-700">
            Voir mes rendez-vous
          </Link>
          <Link to="/patient" className="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 hover:bg-gray-50">
            Tableau de bord
          </Link>
        </div>
      </section>
    )
  }

  return (
    <section aria-labelledby="titre-prise-rdv" className="space-y-5">
      <h1 id="titre-prise-rdv" className="text-2xl font-bold text-gray-900">
        Prise de rendez-vous
      </h1>

      <ol aria-label="Étapes" className="grid grid-cols-5 gap-1 text-center text-xs sm:text-sm">
        {ETAPES.map((e) => (
          <li
            key={e.numero}
            aria-current={e.numero === etape ? 'step' : undefined}
            className={`rounded-md px-1 py-2 ${
              e.numero === etape ? 'bg-primaire-600 font-semibold text-white' : e.numero < etape ? 'bg-primaire-50 text-primaire-800' : 'bg-gray-100 text-gray-600'
            }`}
          >
            <span className="block font-bold">{e.numero}</span>
            <span className="hidden sm:inline">{e.libelle}</span>
            <span className="sr-only sm:hidden">{e.libelle}</span>
          </li>
        ))}
      </ol>

      {erreur && <Alerte>{erreur}</Alerte>}

      {etape === 1 && (
        <div className="space-y-4">
          <h2 className="text-lg font-bold text-gray-900">1. Choisissez la spécialité</h2>
          {erreurListe && <Alerte>{erreurListe}</Alerte>}
          {!specialites && !erreurListe && (
            <p role="status" className="text-gray-600">
              Chargement…
            </p>
          )}
          {specialites && (
            <fieldset>
              <legend className="sr-only">Spécialité</legend>
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {specialites.map((s) => (
                  <label
                    key={s.id}
                    className={`flex cursor-pointer gap-3 rounded-xl border bg-white p-3 shadow-sm ${
                      specialite?.id === s.id ? 'border-primaire-600 ring-2 ring-primaire-600/30' : 'border-gray-200'
                    }`}
                  >
                    <input type="radio" name="specialite" className="mt-1" checked={specialite?.id === s.id} onChange={() => choisirSpecialite(s)} />
                    <span>
                      <span className="block font-semibold text-gray-900">{s.libelle}</span>
                      {s.description && <span className="block text-sm text-gray-600">{s.description}</span>}
                    </span>
                  </label>
                ))}
              </div>
            </fieldset>
          )}
          <Bouton onClick={() => aller(2)} disabled={!specialite}>
            Continuer
          </Bouton>
        </div>
      )}

      {etape === 2 && specialite && (
        <form onSubmit={validerPreconsultation} noValidate className="space-y-4">
          <h2 className="text-lg font-bold text-gray-900">2. Préconsultation (facultative)</h2>
          <p className="text-gray-700">
            La préconsultation permet d'indiquer au médecin le motif de votre visite avant le rendez-vous. Elle est facultative : vous
            pouvez réserver sans la remplir.
          </p>
          {/* RG03 */}
          <Alerte type="info">{MENTION_NON_DIAGNOSTIC}</Alerte>
          <div className="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <Champ
              label="Motif de la consultation"
              required
              maxLength={255}
              autoComplete="off"
              value={saisie.motif}
              onChange={(e) => setSaisie({ ...saisie, motif: e.target.value })}
              erreur={erreursPreconsultation.motif}
            />
            <ZoneTexte
              label="Symptômes"
              required
              // RG11 : champ libre et court en psychiatrie, sans questionnaire.
              rows={specialite.saisie_restreinte ? 4 : 6}
              maxLength={specialite.max_symptomes}
              value={saisie.symptomes}
              onChange={(e) => setSaisie({ ...saisie, symptomes: e.target.value })}
              erreur={erreursPreconsultation.symptomes}
              aide={
                <div className="flex flex-wrap justify-between gap-2">
                  <span>
                    {specialite.saisie_restreinte
                      ? 'Décrivez brièvement, avec vos mots, ce qui vous amène.'
                      : 'Décrivez ce que vous ressentez et depuis quand.'}
                  </span>
                  <span aria-live="polite" className={saisie.symptomes.length >= specialite.max_symptomes ? 'font-semibold text-red-700' : ''}>
                    {saisie.symptomes.length} / {specialite.max_symptomes} caractères
                  </span>
                </div>
              }
            />
          </div>
          <div className="flex flex-wrap gap-2">
            <Bouton type="button" variante="secondaire" onClick={() => aller(1)} disabled={envoi}>
              Retour
            </Bouton>
            {/* RG02 : sans préconsultation, l'étape 3 est sautée (RG04 : pas de suggestion). */}
            <Bouton type="button" variante="secondaire" onClick={passerPreconsultation} disabled={envoi}>
              Passer cette étape
            </Bouton>
            <Bouton type="submit" disabled={envoi}>
              {envoi ? 'Vérification…' : 'Continuer'}
            </Bouton>
          </div>
        </form>
      )}

      {etape === 3 && specialite && suggestion && (
        <div className="space-y-4">
          <h2 className="text-lg font-bold text-gray-900">3. Suggestion d'orientation</h2>
          <div className="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <p className="text-gray-900">
              D'après le motif et les symptômes indiqués, la spécialité <strong>{suggestion.specialite.libelle}</strong> pourrait mieux
              correspondre à votre demande que <strong>{specialite.libelle}</strong>. Cette suggestion est indicative : vous pouvez
              conserver votre choix.
            </p>
            {/* RG03 */}
            <p className="text-sm font-medium text-amber-900">{MENTION_NON_DIAGNOSTIC}</p>
          </div>
          <div>
            <h3 className="mb-2 font-semibold text-gray-900">Prochains créneaux en {suggestion.specialite.libelle}</h3>
            {suggestion.creneaux.length === 0 ? (
              <p className="text-sm text-gray-700">Aucun créneau libre n'est proposé en ligne pour le moment dans cette spécialité.</p>
            ) : (
              <ul className="space-y-1">
                {suggestion.creneaux.map((c) => (
                  <li key={`${c.medecin.id}-${c.date_heure}`} className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900">
                    <span className="font-medium first-letter:uppercase">{libelleRendezVous(c)}</span> – {nomMedecin(c.medecin)}
                  </li>
                ))}
              </ul>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            <Bouton variante="secondaire" onClick={() => aller(2)}>
              Retour
            </Bouton>
            <Bouton variante="secondaire" onClick={() => aller(4)}>
              Conserver ma spécialité
            </Bouton>
            <Bouton onClick={suivreSuggestion}>Choisir la spécialité suggérée</Bouton>
          </div>
        </div>
      )}

      {etape === 4 && specialite && (
        <div className="space-y-4">
          <h2 className="text-lg font-bold text-gray-900">4. Choisissez le médecin et le créneau</h2>
          <p className="text-sm text-gray-700">
            {specialite.libelle} : créneaux libres pour les prochaines semaines.
            {chargement && <span role="status"> Actualisation…</span>}
          </p>
          {erreurCreneaux && <Alerte>{erreurCreneaux}</Alerte>}
          {creneaux ? (
            <SelecteurCreneau
              creneaux={creneaux}
              medecinId={medecinId}
              onMedecin={(id) => {
                setMedecinId(id)
                setCreneau(null)
              }}
              selection={creneau}
              onChoisir={setCreneau}
            />
          ) : (
            !erreurCreneaux && (
              <p role="status" className="text-gray-600">
                Chargement des créneaux…
              </p>
            )
          )}
          <div className="sticky bottom-0 flex flex-wrap gap-2 border-t border-gray-200 bg-gray-50/95 py-3">
            <Bouton variante="secondaire" onClick={() => aller(suggestion ? 3 : 2)}>
              Retour
            </Bouton>
            <Bouton onClick={() => aller(5)} disabled={!creneau}>
              {creneau ? `Continuer avec ${creneau.heure}` : 'Choisissez un créneau'}
            </Bouton>
          </div>
        </div>
      )}

      {etape === 5 && specialite && creneau && (
        <div className="space-y-4">
          <h2 className="text-lg font-bold text-gray-900">5. Vérifiez et confirmez</h2>
          <dl className="space-y-2 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <Ligne terme="Spécialité" valeur={specialite.libelle} />
            <Ligne terme="Médecin" valeur={nomMedecin(creneau.medecin)} />
            <Ligne terme="Date et heure" valeur={libelleRendezVous(creneau)} />
            <Ligne terme="Préconsultation" valeur={preconsultation ? `Remplie (motif : ${preconsultation.motif})` : 'Non remplie'} />
          </dl>
          <p className="text-sm text-gray-700">
            Le rendez-vous est confirmé immédiatement. Vous pourrez le modifier ou l'annuler en ligne jusqu'au délai fixé par la clinique avant son heure ; au-delà, contactez la
            clinique.
          </p>
          <div className="flex flex-wrap gap-2">
            <Bouton variante="secondaire" onClick={() => aller(4)} disabled={envoi}>
              Changer de créneau
            </Bouton>
            <Bouton onClick={confirmer} disabled={envoi}>
              {envoi ? 'Réservation…' : 'Confirmer le rendez-vous'}
            </Bouton>
          </div>
        </div>
      )}
    </section>
  )
}

function Ligne({ terme, valeur }: { terme: string; valeur: string }) {
  return (
    <div className="flex flex-wrap gap-x-2">
      <dt className="font-medium text-gray-700">{terme} :</dt>
      <dd className="text-gray-900 first-letter:uppercase">{valeur}</dd>
    </div>
  )
}
