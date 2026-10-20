import axios from 'axios'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { api, messageErreur } from '../lib/api'
import { libelleDate } from '../lib/dates'
import type { Creneau, Creneaux, RendezVous, StatutRdv } from '../types'
import { Alerte, Bouton, Liste } from './Formulaire'

/** Nombre de jours affichés avant « Afficher plus de jours ». */
const JOURS_PAR_PAGE = 5

/** Charge les créneaux libres de la spécialité sur tout l'horizon de réservation. */
export function useCreneauxSpecialite(specialiteId: number | null) {
  const [creneaux, setCreneaux] = useState<Creneaux | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [chargement, setChargement] = useState(false)

  const recharger = useCallback(async () => {
    if (specialiteId === null) return
    setChargement(true)
    setErreur(null)
    try {
      const { data } = await api.get<{ data: Creneaux }>(`/api/specialites/${specialiteId}/creneaux`)
      setCreneaux(data.data)
    } catch (err) {
      setErreur(messageErreur(err))
    } finally {
      setChargement(false)
    }
  }, [specialiteId])

  useEffect(() => {
    recharger()
  }, [recharger])

  return { creneaux, erreur, chargement, recharger }
}

export function nomMedecin(m: { prenom: string; nom: string }) {
  return `${m.prenom} ${m.nom}`
}

/** « mercredi 4 novembre 2026 à 10:00 ». */
export function libelleRendezVous(r: { date: string; heure: string }) {
  return `${libelleDate(r.date, true)} à ${r.heure}`
}

/** Vrai si l'erreur est un conflit 409 (créneau pris entre-temps, RG07). */
export function estConflit(erreur: unknown) {
  return axios.isAxiosError(erreur) && erreur.response?.status === 409
}

const STYLES_BADGE: Record<StatutRdv, string> = {
  confirme: 'bg-green-100 text-green-900',
  a_reprogrammer: 'bg-amber-100 text-amber-900',
  annule: 'bg-gray-200 text-gray-700',
  passe: 'bg-gray-100 text-gray-700',
}

export function BadgeStatut({ statut, libelle }: { statut: StatutRdv; libelle: string }) {
  return <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${STYLES_BADGE[statut]}`}>{libelle}</span>
}

/**
 * Choix d'un médecin (facultatif) et d'un créneau libre parmi ceux de la spécialité.
 * Les créneaux sont regroupés par jour ; le créneau choisi est mis en évidence.
 */
export function SelecteurCreneau({
  creneaux,
  medecinId,
  onMedecin,
  selection,
  onChoisir,
  exclure,
}: {
  creneaux: Creneaux
  /** '' : tous les médecins. */
  medecinId: string
  onMedecin: (id: string) => void
  selection: Creneau | null
  onChoisir: (creneau: Creneau) => void
  /** Créneau à ne pas proposer (celui du rendez-vous à modifier). */
  exclure?: { medecinId: number; dateHeure: string }
}) {
  const [nbJours, setNbJours] = useState(JOURS_PAR_PAGE)

  const medecins = useMemo(() => {
    const parId = new Map<number, Creneau['medecin']>()
    creneaux.jours.forEach((j) => j.creneaux.forEach((c) => parId.set(c.medecin.id, c.medecin)))
    return [...parId.values()].sort((a, b) => a.nom.localeCompare(b.nom, 'fr'))
  }, [creneaux])

  const jours = useMemo(
    () =>
      creneaux.jours
        .map((j) => ({
          date: j.date,
          creneaux: j.creneaux.filter(
            (c) =>
              (!medecinId || String(c.medecin.id) === medecinId) &&
              !(exclure && c.medecin.id === exclure.medecinId && c.date_heure === exclure.dateHeure),
          ),
        }))
        .filter((j) => j.creneaux.length > 0),
    [creneaux, medecinId, exclure],
  )

  const plusieursMedecins = medecins.length > 1 && !medecinId

  return (
    <div className="space-y-4">
      <Liste
        label="Médecin"
        className="max-w-sm"
        value={medecinId}
        onChange={(e) => {
          onMedecin(e.target.value)
          setNbJours(JOURS_PAR_PAGE)
        }}
      >
        <option value="">Tous les médecins de la spécialité</option>
        {medecinId && !medecins.some((m) => String(m.id) === medecinId) && (
          <option value={medecinId}>Médecin actuel (aucun créneau libre)</option>
        )}
        {medecins.map((m) => (
          <option key={m.id} value={m.id}>
            {nomMedecin(m)}
          </option>
        ))}
      </Liste>

      {jours.length === 0 ? (
        <Alerte type="info">
          Aucun créneau libre n'est disponible en ligne pour le moment. Vous pouvez vous présenter directement à l'accueil de la
          clinique.
        </Alerte>
      ) : (
        <>
          <ol className="space-y-3">
            {jours.slice(0, nbJours).map((jour) => (
              <li key={jour.date}>
                <fieldset className="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                  <legend className="px-1 text-sm font-bold text-gray-900 first-letter:uppercase">{libelleDate(jour.date, true)}</legend>
                  <div className="mt-1 flex flex-wrap gap-2">
                    {jour.creneaux.map((c) => {
                      const choisi = selection?.date_heure === c.date_heure && selection.medecin.id === c.medecin.id
                      return (
                        <button
                          key={`${c.medecin.id}-${c.date_heure}`}
                          type="button"
                          aria-pressed={choisi}
                          onClick={() => onChoisir(c)}
                          className={`rounded-lg border px-3 py-2 text-sm font-semibold ${
                            choisi
                              ? 'border-primaire-700 bg-primaire-600 text-white'
                              : 'border-primaire-100 bg-primaire-50 text-primaire-800 hover:border-primaire-600'
                          }`}
                        >
                          {c.heure}
                          {plusieursMedecins && <span className="block text-xs font-normal">{nomMedecin(c.medecin)}</span>}
                          <span className="sr-only">
                            {' '}
                            avec {nomMedecin(c.medecin)}, {libelleDate(c.date, true)}
                          </span>
                        </button>
                      )
                    })}
                  </div>
                </fieldset>
              </li>
            ))}
          </ol>
          {jours.length > nbJours && (
            <Bouton variante="secondaire" onClick={() => setNbJours((n) => n + JOURS_PAR_PAGE)}>
              Afficher plus de jours
            </Bouton>
          )}
        </>
      )}
    </div>
  )
}

/**
 * Changement de créneau d'un rendez-vous (même médecin ou autre médecin de la même spécialité),
 * par le patient ou la secrétaire. En cas de conflit (409), les créneaux sont rechargés.
 */
export function ModificationRendezVous({
  rendezVous,
  onModifie,
  onFermer,
}: {
  rendezVous: RendezVous
  onModifie: (r: RendezVous) => void
  onFermer: () => void
}) {
  const { creneaux, erreur: erreurChargement, recharger } = useCreneauxSpecialite(rendezVous.specialite.id)
  const [medecinId, setMedecinId] = useState(String(rendezVous.medecin.id))
  const [selection, setSelection] = useState<Creneau | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function enregistrer() {
    if (!selection) return
    setEnvoi(true)
    setErreur(null)
    try {
      const { data } = await api.patch<{ data: RendezVous }>(`/api/rendez-vous/${rendezVous.id}`, {
        medecin_id: selection.medecin.id,
        date_heure: selection.date_heure,
      })
      onModifie(data.data)
    } catch (err) {
      setErreur(messageErreur(err))
      if (estConflit(err)) {
        setSelection(null)
        recharger()
      }
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="mt-3 space-y-4 rounded-xl border border-primaire-100 bg-primaire-50/40 p-4">
      <p className="text-sm text-gray-700">
        Choisissez un nouveau créneau en {rendezVous.specialite.libelle}, avec le même médecin ou un autre médecin de la spécialité.
      </p>
      {erreurChargement && <Alerte>{erreurChargement}</Alerte>}
      {erreur && <Alerte>{erreur}</Alerte>}
      {creneaux ? (
        <SelecteurCreneau
          creneaux={creneaux}
          medecinId={medecinId}
          onMedecin={(id) => {
            setMedecinId(id)
            setSelection(null)
          }}
          selection={selection}
          onChoisir={setSelection}
          exclure={{ medecinId: rendezVous.medecin.id, dateHeure: rendezVous.date_heure }}
        />
      ) : (
        !erreurChargement && (
          <p role="status" className="text-sm text-gray-600">
            Chargement des créneaux…
          </p>
        )
      )}
      {selection && (
        <p className="text-sm font-medium text-gray-900">
          Nouveau créneau : {libelleRendezVous(selection)} avec {nomMedecin(selection.medecin)}.
        </p>
      )}
      <div className="flex flex-wrap gap-2">
        <Bouton onClick={enregistrer} disabled={!selection || envoi}>
          {envoi ? 'Enregistrement…' : 'Confirmer le changement'}
        </Bouton>
        <Bouton variante="secondaire" onClick={onFermer}>
          Fermer
        </Bouton>
      </div>
    </div>
  )
}
