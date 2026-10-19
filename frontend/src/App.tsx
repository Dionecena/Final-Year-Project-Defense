import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import RequireRole from './auth/RequireRole'
import Layout from './components/Layout'
import PageEnConstruction from './components/PageEnConstruction'
import Accueil from './pages/Accueil'
import Connexion from './pages/Connexion'
import Inscription from './pages/Inscription'
import Utilisateurs from './pages/admin/Utilisateurs'
import PageIntrouvable from './pages/PageIntrouvable'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<Layout />}>
            {/* Pages publiques */}
            <Route index element={<Accueil />} />
            <Route path="specialites" element={<PageEnConstruction titre="Spécialités" module={3} />} />
            <Route path="connexion" element={<Connexion />} />
            <Route path="inscription" element={<Inscription />} />

            {/* Espace patient */}
            <Route path="patient" element={<RequireRole roles={['patient']} />}>
              <Route index element={<PageEnConstruction titre="Tableau de bord" module={5} />} />
              <Route path="prise-de-rendez-vous" element={<PageEnConstruction titre="Prise de rendez-vous" module={5} />} />
              <Route path="mes-rendez-vous" element={<PageEnConstruction titre="Mes rendez-vous" module={5} />} />
            </Route>

            {/* Espace secrétaire */}
            <Route path="secretariat" element={<RequireRole roles={['secretaire']} />}>
              <Route index element={<PageEnConstruction titre="Rendez-vous" module={5} />} />
              <Route path="planning" element={<PageEnConstruction titre="Planning des médecins" module={4} />} />
              <Route path="indisponibilites" element={<PageEnConstruction titre="Indisponibilités" module={7} />} />
              <Route path="a-reprogrammer" element={<PageEnConstruction titre="Rendez-vous à reprogrammer" module={7} />} />
            </Route>

            {/* Espace médecin */}
            <Route path="medecin" element={<RequireRole roles={['medecin']} />}>
              <Route index element={<PageEnConstruction titre="Mon planning" module={4} />} />
            </Route>

            {/* Espace administrateur */}
            <Route path="admin" element={<RequireRole roles={['administrateur']} />}>
              <Route index element={<PageEnConstruction titre="Statistiques" module={9} />} />
              <Route path="utilisateurs" element={<Utilisateurs />} />
              <Route path="specialites" element={<PageEnConstruction titre="Gestion des spécialités" module={3} />} />
            </Route>

            <Route path="*" element={<PageIntrouvable />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
