import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { useAuth } from '../../auth/AuthContext'
import { Alerte, Bouton, Champ, Liste } from '../../components/Formulaire'
import { api, erreursChamps, messageErreur } from '../../lib/api'
import { LIBELLES_ROLE, type Role, type Utilisateur } from '../../types'

interface Specialite {
  id: number
  libelle: string
}

interface Page<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}

const ROLES_PERSONNEL: Role[] = ['secretaire', 'medecin', 'administrateur']

type Edition = { mode: 'creation' } | { mode: 'modification'; utilisateur: Utilisateur }

export default function Utilisateurs() {
  const { utilisateur: moi } = useAuth()
  const [page, setPage] = useState<Page<Utilisateur> | null>(null)
  const [numeroPage, setNumeroPage] = useState(1)
  const [filtres, setFiltres] = useState({ role: '', actif: '', recherche: '' })
  const [specialites, setSpecialites] = useState<Specialite[]>([])
  const [edition, setEdition] = useState<Edition | null>(null)
  const [message, setMessage] = useState<{ type: 'succes' | 'erreur'; texte: string } | null>(null)

  const charger = useCallback(async () => {
    try {
      const params = Object.fromEntries(Object.entries({ ...filtres, page: String(numeroPage) }).filter(([, v]) => v !== ''))
      const { data } = await api.get<Page<Utilisateur>>('/api/admin/utilisateurs', { params })
      setPage(data)
    } catch (err) {
      setMessage({ type: 'erreur', texte: messageErreur(err) })
    }
  }, [filtres, numeroPage])

  useEffect(() => {
    charger()
  }, [charger])

  useEffect(() => {
    api.get<{ data: Specialite[] }>('/api/specialites').then(({ data }) => setSpecialites(data.data))
  }, [])

  async function desactiver(u: Utilisateur) {
    if (!window.confirm(`Désactiver le compte de ${u.prenom} ${u.nom} ? Il ne pourra plus se connecter.`)) return
    try {
      await api.post(`/api/admin/utilisateurs/${u.id}/desactivation`)
      setMessage({ type: 'succes', texte: 'Compte désactivé.' })
      charger()
    } catch (err) {
      setMessage({ type: 'erreur', texte: messageErreur(err) })
    }
  }

  async function reactiver(u: Utilisateur) {
    try {
      await api.patch(`/api/admin/utilisateurs/${u.id}`, { actif: true })
      setMessage({ type: 'succes', texte: 'Compte réactivé.' })
      charger()
    } catch (err) {
      setMessage({ type: 'erreur', texte: messageErreur(err) })
    }
  }

  return (
    <section aria-labelledby="titre-utilisateurs" className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 id="titre-utilisateurs" className="text-2xl font-bold text-gray-900">
          Gestion des utilisateurs
        </h1>
        <Bouton onClick={() => setEdition({ mode: 'creation' })}>Nouveau compte du personnel</Bouton>
      </div>

      {message && <Alerte type={message.type}>{message.texte}</Alerte>}

      {edition && (
        <FormulaireCompte
          edition={edition}
          specialites={specialites}
          estMoi={edition.mode === 'modification' && edition.utilisateur.id === moi?.id}
          onAnnuler={() => setEdition(null)}
          onEnregistre={(texte) => {
            setEdition(null)
            setMessage({ type: 'succes', texte })
            charger()
          }}
        />
      )}

      <form
        role="search"
        className="grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-3"
        onSubmit={(e) => e.preventDefault()}
      >
        <Champ
          label="Rechercher (nom, prénom, adresse)"
          type="search"
          value={filtres.recherche}
          onChange={(e) => {
            setNumeroPage(1)
            setFiltres((f) => ({ ...f, recherche: e.target.value }))
          }}
        />
        <Liste
          label="Rôle"
          value={filtres.role}
          onChange={(e) => {
            setNumeroPage(1)
            setFiltres((f) => ({ ...f, role: e.target.value }))
          }}
        >
          <option value="">Tous les rôles</option>
          {(Object.keys(LIBELLES_ROLE) as Role[]).map((r) => (
            <option key={r} value={r}>
              {LIBELLES_ROLE[r]}
            </option>
          ))}
        </Liste>
        <Liste
          label="État"
          value={filtres.actif}
          onChange={(e) => {
            setNumeroPage(1)
            setFiltres((f) => ({ ...f, actif: e.target.value }))
          }}
        >
          <option value="">Tous</option>
          <option value="1">Actifs</option>
          <option value="0">Désactivés</option>
        </Liste>
      </form>

      {!page ? (
        <p role="status" className="text-gray-600">
          Chargement…
        </p>
      ) : page.data.length === 0 ? (
        <p className="text-gray-600">Aucun utilisateur ne correspond à ces critères.</p>
      ) : (
        <>
          <ul className="divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm">
            {page.data.map((u) => (
              <li key={u.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="font-semibold text-gray-900">
                    {u.prenom} {u.nom}
                    {!u.actif && <span className="ml-2 rounded bg-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-800">Désactivé</span>}
                  </p>
                  <p className="text-sm text-gray-700">
                    {LIBELLES_ROLE[u.role]}
                    {u.specialite && ` – ${u.specialite.libelle}`} · {u.email} · {u.telephone}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Bouton variante="secondaire" onClick={() => setEdition({ mode: 'modification', utilisateur: u })}>
                    Modifier
                  </Bouton>
                  {u.actif ? (
                    u.id !== moi?.id && (
                      <Bouton variante="danger" onClick={() => desactiver(u)}>
                        Désactiver
                      </Bouton>
                    )
                  ) : (
                    <Bouton variante="secondaire" onClick={() => reactiver(u)}>
                      Réactiver
                    </Bouton>
                  )}
                </div>
              </li>
            ))}
          </ul>
          <nav aria-label="Pagination" className="flex items-center justify-between text-sm text-gray-700">
            <span>
              {page.meta.total} utilisateur{page.meta.total > 1 ? 's' : ''} · page {page.meta.current_page} sur {page.meta.last_page}
            </span>
            <span className="flex gap-2">
              <Bouton variante="secondaire" disabled={page.meta.current_page <= 1} onClick={() => setNumeroPage((n) => n - 1)}>
                Précédente
              </Bouton>
              <Bouton variante="secondaire" disabled={page.meta.current_page >= page.meta.last_page} onClick={() => setNumeroPage((n) => n + 1)}>
                Suivante
              </Bouton>
            </span>
          </nav>
        </>
      )}
    </section>
  )
}

function FormulaireCompte({
  edition,
  specialites,
  estMoi,
  onAnnuler,
  onEnregistre,
}: {
  edition: Edition
  specialites: Specialite[]
  estMoi: boolean
  onAnnuler: () => void
  onEnregistre: (message: string) => void
}) {
  const existant = edition.mode === 'modification' ? edition.utilisateur : null
  const [valeurs, setValeurs] = useState({
    prenom: existant?.prenom ?? '',
    nom: existant?.nom ?? '',
    email: existant?.email ?? '',
    telephone: existant?.telephone ?? '',
    role: existant?.role ?? 'secretaire',
    specialite_id: existant?.specialite ? String(existant.specialite.id) : '',
    password: '',
    password_confirmation: '',
  })
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  // Le rôle d'un patient ou d'un médecin, et son propre rôle, ne sont pas modifiables.
  const roleModifiable = !existant || (existant.role !== 'patient' && existant.role !== 'medecin' && !estMoi)

  const maj = (champ: keyof typeof valeurs) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setValeurs((v) => ({ ...v, [champ]: e.target.value }))

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    setErreurs({})
    setErreurGenerale(null)
    setEnvoi(true)

    const corps: Record<string, unknown> = {
      prenom: valeurs.prenom,
      nom: valeurs.nom,
      email: valeurs.email,
      telephone: valeurs.telephone,
    }
    if (roleModifiable) corps.role = valeurs.role
    if (valeurs.role === 'medecin') corps.specialite_id = valeurs.specialite_id ? Number(valeurs.specialite_id) : null
    if (!existant || valeurs.password) {
      corps.password = valeurs.password
      corps.password_confirmation = valeurs.password_confirmation
    }

    try {
      if (existant) {
        await api.patch(`/api/admin/utilisateurs/${existant.id}`, corps)
        onEnregistre('Compte modifié.')
      } else {
        await api.post('/api/admin/utilisateurs', corps)
        onEnregistre('Compte créé.')
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
    <form onSubmit={soumettre} noValidate aria-labelledby="titre-formulaire-compte" className="space-y-4 rounded-xl border border-primaire-100 bg-white p-5 shadow-sm">
      <h2 id="titre-formulaire-compte" className="text-lg font-bold text-gray-900">
        {existant ? `Modifier le compte de ${existant.prenom} ${existant.nom}` : 'Nouveau compte du personnel'}
      </h2>
      {erreurGenerale && <Alerte>{erreurGenerale}</Alerte>}

      <div className="grid gap-4 sm:grid-cols-2">
        <Champ label="Prénom" required value={valeurs.prenom} onChange={maj('prenom')} erreur={erreurs.prenom} />
        <Champ label="Nom" required value={valeurs.nom} onChange={maj('nom')} erreur={erreurs.nom} />
        <Champ label="Adresse électronique" type="email" required value={valeurs.email} onChange={maj('email')} erreur={erreurs.email} />
        <Champ label="Téléphone" type="tel" required value={valeurs.telephone} onChange={maj('telephone')} erreur={erreurs.telephone} />

        {roleModifiable ? (
          <Liste label="Rôle" required value={valeurs.role} onChange={maj('role')} erreur={erreurs.role}>
            {ROLES_PERSONNEL.map((r) => (
              <option key={r} value={r}>
                {LIBELLES_ROLE[r]}
              </option>
            ))}
          </Liste>
        ) : (
          <div>
            <p className="block text-sm font-medium text-gray-800">Rôle</p>
            <p className="mt-1 py-2.5 text-gray-900">{LIBELLES_ROLE[valeurs.role as Role]} (non modifiable)</p>
          </div>
        )}

        {valeurs.role === 'medecin' && (
          <Liste label="Spécialité" required value={valeurs.specialite_id} onChange={maj('specialite_id')} erreur={erreurs.specialite_id}>
            <option value="">Choisir une spécialité</option>
            {specialites.map((s) => (
              <option key={s.id} value={s.id}>
                {s.libelle}
              </option>
            ))}
          </Liste>
        )}

        <Champ
          label={existant ? 'Nouveau mot de passe (facultatif)' : 'Mot de passe initial'}
          type="password"
          autoComplete="new-password"
          required={!existant}
          aide="Au moins 8 caractères, dont une lettre et un chiffre."
          value={valeurs.password}
          onChange={maj('password')}
          erreur={erreurs.password}
        />
        <Champ
          label="Confirmation du mot de passe"
          type="password"
          autoComplete="new-password"
          required={!existant}
          value={valeurs.password_confirmation}
          onChange={maj('password_confirmation')}
        />
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
