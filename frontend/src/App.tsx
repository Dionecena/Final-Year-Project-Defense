import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import RequireRole from './auth/RequireRole'
import Layout from './components/Layout'
import Accueil from './pages/Accueil'
import Connexion from './pages/Connexion'
import Inscription from './pages/Inscription'
import { ConditionsUtilisation, PolitiqueConfidentialite } from './pages/Juridique'
import GestionSpecialites from './pages/admin/GestionSpecialites'
import Statistiques from './pages/admin/Statistiques'
import Utilisateurs from './pages/admin/Utilisateurs'
import MonPlanning from './pages/medecin/MonPlanning'
import MesRendezVous from './pages/patient/MesRendezVous'
import PriseRendezVous from './pages/patient/PriseRendezVous'
import TableauDeBord from './pages/patient/TableauDeBord'
import PlanningMedecins from './pages/secretariat/PlanningMedecins'
import AReprogrammer from './pages/secretariat/AReprogrammer'
import Indisponibilites from './pages/secretariat/Indisponibilites'
import RendezVousSecretariat from './pages/secretariat/RendezVousSecretariat'
import Specialites from './pages/Specialites'
import PageIntrouvable from './pages/PageIntrouvable'

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<Layout />}>
            {/* Pages publiques */}
            <Route index element={<Accueil />} />
            <Route path="specialites" element={<Specialites />} />
            <Route path="connexion" element={<Connexion />} />
            <Route path="inscription" element={<Inscription />} />
            <Route path="confidentialite" element={<PolitiqueConfidentialite />} />
            <Route path="conditions-utilisation" element={<ConditionsUtilisation />} />

            {/* Espace patient */}
            <Route path="patient" element={<RequireRole roles={['patient']} />}>
              <Route index element={<TableauDeBord />} />
              <Route path="prise-de-rendez-vous" element={<PriseRendezVous />} />
              <Route path="mes-rendez-vous" element={<MesRendezVous />} />
            </Route>

            {/* Espace secrétaire */}
            <Route path="secretariat" element={<RequireRole roles={['secretaire']} />}>
              <Route index element={<RendezVousSecretariat />} />
              <Route path="planning" element={<PlanningMedecins />} />
              <Route path="indisponibilites" element={<Indisponibilites />} />
              <Route path="a-reprogrammer" element={<AReprogrammer />} />
            </Route>

            {/* Espace médecin */}
            <Route path="medecin" element={<RequireRole roles={['medecin']} />}>
              <Route index element={<MonPlanning />} />
            </Route>

            {/* Espace administrateur */}
            <Route path="admin" element={<RequireRole roles={['administrateur']} />}>
              <Route index element={<Statistiques />} />
              <Route path="utilisateurs" element={<Utilisateurs />} />
              <Route path="specialites" element={<GestionSpecialites />} />
            </Route>

            <Route path="*" element={<PageIntrouvable />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
