import { useCallback, useEffect, useId, useState, type FormEvent } from 'react'
import { Alerte, Bouton, Champ } from '../../components/Formulaire'
import { api, erreursChamps, messageErreur } from '../../lib/api'
import type { Specialite } from '../../types'

type Edition = { mode: 'creation' } | { mode: 'modification'; specialite: Specialite }

/** Gestion des spécialités par l'administrateur : liste, création, modification (pas de suppression). */
export default function GestionSpecialites() {
  const [specialites, setSpecialites] = useState<Specialite[] | null>(null)
  const [edition, setEdition] = useState<Edition | null>(null)
  const [message, setMessage] = useState<{ type: 'succes' | 'erreur'; texte: string } | null>(null)

  const charger = useCallback(async () => {
    try {
      const { data } = await api.get<{ data: Specialite[] }>('/api/admin/specialites')
      setSpecialites(data.data)
    } catch (err) {
      setMessage({ type: 'erreur', texte: messageErreur(err) })
    }
  }, [])

  useEffect(() => {
    charger()
  }, [charger])

  return (
    <section aria-labelledby="titre-gestion-specialites" className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 id="titre-gestion-specialites" className="text-2xl font-bold text-gray-900">
          Gestion des spécialités
        </h1>
        <Bouton
          onClick={() => {
            setMessage(null)
            setEdition({ mode: 'creation' })
          }}
        >
          Nouvelle spécialité
        </Bouton>
      </div>

      {message && <Alerte type={message.type}>{message.texte}</Alerte>}

      {edition && (
        <FormulaireSpecialite
          key={edition.mode === 'modification' ? edition.specialite.id : 'creation'}
          edition={edition}
          onAnnuler={() => setEdition(null)}
          onEnregistre={(texte) => {
            setEdition(null)
            setMessage({ type: 'succes', texte })
            charger()
          }}
        />
      )}

      {!specialites ? (
        <p role="status" className="text-gray-600">
          Chargement…
        </p>
      ) : (
        <ul className="divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm">
          {specialites.map((s) => (
            <li key={s.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="font-semibold text-gray-900">{s.libelle}</p>
                <p className="text-sm text-gray-700">{s.description ?? 'Aucune description'}</p>
                <p className="text-xs text-gray-600">
                  {s.nombre_medecins ?? 0} médecin{(s.nombre_medecins ?? 0) > 1 ? 's' : ''}
                </p>
              </div>
              <Bouton
                variante="secondaire"
                onClick={() => {
                  setMessage(null)
                  setEdition({ mode: 'modification', specialite: s })
                }}
              >
                Modifier<span className="sr-only"> {s.libelle}</span>
              </Bouton>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function FormulaireSpecialite({
  edition,
  onAnnuler,
  onEnregistre,
}: {
  edition: Edition
  onAnnuler: () => void
  onEnregistre: (message: string) => void
}) {
  const existante = edition.mode === 'modification' ? edition.specialite : null
  const [libelle, setLibelle] = useState(existante?.libelle ?? '')
  const [description, setDescription] = useState(existante?.description ?? '')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const idDescription = useId()

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    setErreurs({})
    setErreurGenerale(null)
    setEnvoi(true)
    try {
      if (existante) {
        await api.patch(`/api/admin/specialites/${existante.id}`, { libelle, description })
        onEnregistre('Spécialité modifiée.')
      } else {
        await api.post('/api/admin/specialites', { libelle, description })
        onEnregistre('Spécialité créée.')
      }
    } catch (err) {
      const parChamp = erreursChamps(err)
      setErreurs(parChamp)
      setErreurGenerale(Object.keys(parChamp).length ? 'Veuillez corriger les champs signalés.' : messageErreur(err))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <form onSubmit={soumettre} noValidate aria-labelledby="titre-formulaire-specialite" className="space-y-4 rounded-xl border border-primaire-100 bg-white p-5 shadow-sm">
      <h2 id="titre-formulaire-specialite" className="text-lg font-bold text-gray-900">
        {existante ? `Modifier la spécialité ${existante.libelle}` : 'Nouvelle spécialité'}
      </h2>
      {erreurGenerale && <Alerte>{erreurGenerale}</Alerte>}

      <Champ
        label="Libellé"
        required
        maxLength={100}
        value={libelle}
        onChange={(e) => setLibelle(e.target.value)}
        erreur={erreurs.libelle}
        aide={existante ? 'Le libellé sert aussi de clé dans la configuration (orientation, saisie restreinte) : à modifier avec prudence.' : undefined}
      />

      <div>
        <label htmlFor={idDescription} className="block text-sm font-medium text-gray-800">
          Description (jours de présence)
        </label>
        <textarea
          id={idDescription}
          rows={3}
          maxLength={1000}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          aria-invalid={erreurs.description ? true : undefined}
          aria-describedby={`${idDescription}-aide${erreurs.description ? ` ${idDescription}-erreur` : ''}`}
          className={`mt-1 block w-full rounded-lg border bg-white px-3 py-2.5 text-gray-900 shadow-sm focus:border-primaire-600 focus:outline-none focus:ring-2 focus:ring-primaire-600/30 ${erreurs.description ? 'border-red-600' : 'border-gray-300'}`}
        />
        <p id={`${idDescription}-aide`} className="mt-1 text-xs text-gray-600">
          Facultatif. Exemple : « Jours de présence : Lundi, mercredi ». Affiché sur la page publique des spécialités.
        </p>
        {erreurs.description && (
          <p id={`${idDescription}-erreur`} className="mt-1 text-sm text-red-700">
            {erreurs.description}
          </p>
        )}
      </div>

      <div className="flex gap-2">
        <Bouton type="submit" disabled={envoi}>
          {envoi ? 'Enregistrement…' : 'Enregistrer'}
        </Bouton>
        <Bouton type="button" variante="secondaire" onClick={onAnnuler}>
          Annuler
        </Bouton>
      </div>
    </form>
  )
}
