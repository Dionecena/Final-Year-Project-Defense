import { Link } from 'react-router-dom'

export default function PageIntrouvable() {
  return (
    <section aria-labelledby="titre-page">
      <h1 id="titre-page" className="text-2xl font-bold text-gray-900">
        Page introuvable
      </h1>
      <p className="mt-2 text-gray-600">La page demandée n'existe pas.</p>
      <Link to="/" className="mt-4 inline-block font-medium text-primaire-700 underline">
        Retour à l'accueil
      </Link>
    </section>
  )
}
