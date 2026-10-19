import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { Alerte, Bouton, Champ } from '../components/Formulaire'
import { api, erreursChamps, initialiserCsrf, messageErreur } from '../lib/api'
import { ACCUEIL_ROLE } from '../types'

export default function Connexion() {
  const { utilisateur, rafraichir } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const depuis = (location.state as { depuis?: string } | null)?.depuis

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  if (utilisateur) return <Navigate to={ACCUEIL_ROLE[utilisateur.role]} replace />

  async function soumettre(e: FormEvent) {
    e.preventDefault()
    setErreur(null)
    setEnvoi(true)
    try {
      await initialiserCsrf()
      await api.post('/login', { email, password })
      const connecte = await rafraichir()
      if (connecte) navigate(depuis ?? ACCUEIL_ROLE[connecte.role], { replace: true })
    } catch (err) {
      setErreur(erreursChamps(err).email ?? messageErreur(err))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <section aria-labelledby="titre-connexion" className="mx-auto max-w-md">
      <h1 id="titre-connexion" className="text-2xl font-bold text-gray-900">
        Connexion
      </h1>
      <form onSubmit={soumettre} noValidate className="mt-6 space-y-4 rounded-xl bg-white p-5 shadow-sm">
        {erreur && <Alerte>{erreur}</Alerte>}
        <Champ label="Adresse électronique" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
        <Champ label="Mot de passe" type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
        <Bouton type="submit" disabled={envoi} className="w-full">
          {envoi ? 'Connexion…' : 'Se connecter'}
        </Bouton>
      </form>
      <p className="mt-4 text-sm text-gray-700">
        Pas encore de compte ?{' '}
        <Link to="/inscription" className="font-medium text-primaire-700 underline">
          Créer un compte patient
        </Link>
      </p>
    </section>
  )
}
