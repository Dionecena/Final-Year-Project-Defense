import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import axios from 'axios'
import { api, initialiserCsrf } from '../lib/api'
import type { Utilisateur } from '../types'

interface AuthState {
  utilisateur: Utilisateur | null
  chargement: boolean
  /** Recharge l'utilisateur connecté depuis GET /api/moi. */
  rafraichir: () => Promise<Utilisateur | null>
  deconnecter: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [utilisateur, setUtilisateur] = useState<Utilisateur | null>(null)
  const [chargement, setChargement] = useState(true)

  const rafraichir = useCallback(async () => {
    try {
      const { data } = await api.get<{ data: Utilisateur }>('/api/moi')
      setUtilisateur(data.data)
      return data.data
    } catch (erreur) {
      if (axios.isAxiosError(erreur) && erreur.response?.status === 401) {
        setUtilisateur(null)
        return null
      }
      throw erreur
    } finally {
      setChargement(false)
    }
  }, [])

  const deconnecter = useCallback(async () => {
    try {
      await initialiserCsrf()
      await api.post('/logout')
    } finally {
      setUtilisateur(null)
    }
  }, [])

  useEffect(() => {
    rafraichir().catch(() => setUtilisateur(null))
  }, [rafraichir])

  const valeur = useMemo(
    () => ({ utilisateur, chargement, rafraichir, deconnecter }),
    [utilisateur, chargement, rafraichir, deconnecter],
  )

  return <AuthContext.Provider value={valeur}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthState {
  const contexte = useContext(AuthContext)
  if (!contexte) throw new Error('useAuth doit être utilisé dans <AuthProvider>.')
  return contexte
}
