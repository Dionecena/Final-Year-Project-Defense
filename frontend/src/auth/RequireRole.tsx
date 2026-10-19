import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from './AuthContext'
import { ACCUEIL_ROLE, type Role } from '../types'

/**
 * Protège un espace de l'interface. Simple confort d'affichage : le contrôle d'accès
 * réel est fait par l'API (middleware de rôle et politiques d'accès).
 */
export default function RequireRole({ roles }: { roles: Role[] }) {
  const { utilisateur, chargement } = useAuth()
  const location = useLocation()

  if (chargement) {
    return <p className="p-6 text-center text-gray-600" role="status">Chargement…</p>
  }

  if (!utilisateur) {
    return <Navigate to="/connexion" replace state={{ depuis: location.pathname }} />
  }

  if (!roles.includes(utilisateur.role)) {
    return <Navigate to={ACCUEIL_ROLE[utilisateur.role]} replace />
  }

  return <Outlet />
}
