import { useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { LIBELLES_ROLE, type Role } from '../types'

export const TITRE_PLATEFORME = 'Prise de rendez-vous en ligne – Clinique Croix Bleue'

interface Lien {
  vers: string
  libelle: string
}

const LIENS_PUBLICS: Lien[] = [
  { vers: '/', libelle: 'Accueil' },
  { vers: '/specialites', libelle: 'Spécialités' },
]

const LIENS_ROLE: Record<Role, Lien[]> = {
  patient: [
    { vers: '/patient', libelle: 'Tableau de bord' },
    { vers: '/patient/prise-de-rendez-vous', libelle: 'Prendre rendez-vous' },
    { vers: '/patient/mes-rendez-vous', libelle: 'Mes rendez-vous' },
  ],
  secretaire: [
    { vers: '/secretariat', libelle: 'Rendez-vous' },
    { vers: '/secretariat/planning', libelle: 'Planning des médecins' },
    { vers: '/secretariat/indisponibilites', libelle: 'Indisponibilités' },
    { vers: '/secretariat/a-reprogrammer', libelle: 'À reprogrammer' },
  ],
  medecin: [{ vers: '/medecin', libelle: 'Mon planning' }],
  administrateur: [
    { vers: '/admin', libelle: 'Statistiques' },
    { vers: '/admin/utilisateurs', libelle: 'Utilisateurs' },
    { vers: '/admin/specialites', libelle: 'Spécialités' },
  ],
}

function classeLien({ isActive }: { isActive: boolean }) {
  return [
    'block rounded-md px-3 py-2 text-sm font-medium',
    isActive ? 'bg-primaire-50 text-primaire-700' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900',
  ].join(' ')
}

export default function Layout() {
  const { utilisateur, deconnecter } = useAuth()
  const navigate = useNavigate()
  // La page d'accueil occupe toute la largeur (sections pleine page du modèle).
  const pleineLargeur = useLocation().pathname === '/'
  const [menuOuvert, setMenuOuvert] = useState(false)

  const liens = utilisateur ? LIENS_ROLE[utilisateur.role] : LIENS_PUBLICS

  async function seDeconnecter() {
    await deconnecter()
    setMenuOuvert(false)
    navigate('/')
  }

  return (
    <div className="flex min-h-screen flex-col">
      <a href="#contenu" className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:p-2">
        Aller au contenu
      </a>

      <header className="border-b border-gray-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
          <Link to="/" className="text-sm font-bold leading-tight text-gray-900 sm:text-base">
            {TITRE_PLATEFORME}
          </Link>

          <button
            type="button"
            className="rounded-md p-2 text-gray-700 hover:bg-gray-100 md:hidden"
            aria-expanded={menuOuvert}
            aria-controls="menu-principal"
            onClick={() => setMenuOuvert((v) => !v)}
          >
            <span className="sr-only">{menuOuvert ? 'Fermer le menu' : 'Ouvrir le menu'}</span>
            <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              {menuOuvert ? <path d="M6 6l12 12M18 6L6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
            </svg>
          </button>

          <nav
            id="menu-principal"
            aria-label="Navigation principale"
            className={`${menuOuvert ? 'block' : 'hidden'} absolute inset-x-0 top-14 z-40 border-b border-gray-200 bg-white px-4 pb-4 md:static md:flex md:items-center md:gap-1 md:border-0 md:p-0`}
          >
            {liens.map((lien) => (
              <NavLink key={lien.vers} to={lien.vers} end className={classeLien} onClick={() => setMenuOuvert(false)}>
                {lien.libelle}
              </NavLink>
            ))}

            {utilisateur ? (
              <div className="mt-2 flex items-center gap-3 border-t border-gray-200 pt-3 md:ml-3 md:mt-0 md:border-0 md:pt-0">
                <span className="text-sm text-gray-600">
                  {utilisateur.prenom} {utilisateur.nom}
                  <span className="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{LIBELLES_ROLE[utilisateur.role]}</span>
                </span>
                <button type="button" onClick={seDeconnecter} className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-50">
                  Se déconnecter
                </button>
              </div>
            ) : (
              <div className="mt-2 flex gap-2 border-t border-gray-200 pt-3 md:ml-3 md:mt-0 md:border-0 md:pt-0">
                <NavLink to="/connexion" className={classeLien} onClick={() => setMenuOuvert(false)}>
                  Connexion
                </NavLink>
                <Link
                  to="/inscription"
                  onClick={() => setMenuOuvert(false)}
                  className="rounded-md bg-primaire-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primaire-700"
                >
                  Créer un compte
                </Link>
              </div>
            )}
          </nav>
        </div>
      </header>

      <main id="contenu" className={pleineLargeur ? 'w-full flex-1' : 'mx-auto w-full max-w-6xl flex-1 px-4 py-6'}>
        <Outlet />
      </main>

      <footer className="border-t border-gray-200 bg-white">
        <p className="mx-auto max-w-6xl px-4 py-4 text-xs text-gray-600">
          {TITRE_PLATEFORME}. Vous pouvez toujours vous présenter directement à l'accueil de la clinique, sans rendez-vous.
        </p>
      </footer>
    </div>
  )
}
