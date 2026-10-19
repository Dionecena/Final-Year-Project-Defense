import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { Alerte, Bouton, Champ } from '../components/Formulaire'
import { api, erreursChamps, initialiserCsrf, messageErreur } from '../lib/api'
import { ACCUEIL_ROLE } from '../types'

const VIDE = { nom: '', prenom: '', email: '', telephone: '', password: '', password_confirmation: '' }

/** Inscription : réservée aux patients (les comptes du personnel sont créés par l'administrateur). */
export default function Inscription() {
  const { utilisateur, rafraichir } = useAuth()
  const navigate = useNavigate()
  const [valeurs, setValeurs] = useState(VIDE)
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreurGenerale, setErreurGenerale] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  if (utilisateur) return <Navigate to={ACCUEIL_ROLE[utilisateur.role]} replace />

  const champ = (nom: keyof typeof VIDE) => ({
    value: valeurs[nom],
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => setValeurs((v) => ({ ...v, [nom]: e.target.value })),
    erreur: erreurs[nom],
  })

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    setErreurs({})
    setErreurGenerale(null)
    setEnvoi(true)
    try {
      await initialiserCsrf()
      await api.post('/api/inscription', valeurs)
      await rafraichir()
      navigate('/patient', { replace: true })
    } catch (err) {
      const parChamp = erreursChamps(err)
      setErreurs(parChamp)
      setErreurGenerale(Object.keys(parChamp).length ? 'Veuillez corriger les champs signalés.' : messageErreur(err))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <section aria-labelledby="titre-inscription" className="mx-auto max-w-lg">
      <h1 id="titre-inscription" className="text-2xl font-bold text-gray-900">
        Créer un compte patient
      </h1>
      <form onSubmit={soumettre} noValidate className="mt-6 space-y-4 rounded-xl bg-white p-5 shadow-sm">
        {erreurGenerale && <Alerte>{erreurGenerale}</Alerte>}
        <div className="grid gap-4 sm:grid-cols-2">
          <Champ label="Prénom" autoComplete="given-name" required {...champ('prenom')} />
          <Champ label="Nom" autoComplete="family-name" required {...champ('nom')} />
        </div>
        <Champ label="Adresse électronique" type="email" autoComplete="email" required {...champ('email')} />
        <Champ label="Téléphone" type="tel" autoComplete="tel" required {...champ('telephone')} />
        <Champ
          label="Mot de passe"
          type="password"
          autoComplete="new-password"
          required
          aide="Au moins 8 caractères, dont une lettre et un chiffre."
          {...champ('password')}
        />
        <Champ label="Confirmation du mot de passe" type="password" autoComplete="new-password" required {...champ('password_confirmation')} />
        <Bouton type="submit" disabled={envoi} className="w-full">
          {envoi ? 'Création…' : 'Créer mon compte'}
        </Bouton>
      </form>
      <p className="mt-4 text-sm text-gray-700">
        Déjà inscrit ?{' '}
        <Link to="/connexion" className="font-medium text-primaire-700 underline">
          Se connecter
        </Link>
      </p>
    </section>
  )
}
