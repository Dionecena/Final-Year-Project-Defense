export type Role = 'patient' | 'secretaire' | 'medecin' | 'administrateur'

export type StatutRdv = 'confirme' | 'annule' | 'a_reprogrammer' | 'passe'

export interface Utilisateur {
  id: number
  nom: string
  prenom: string
  email: string
  telephone: string | null
  role: Role
  actif: boolean
  specialite?: { id: number; libelle: string } | null
}

export interface Specialite {
  id: number
  libelle: string
  /** Jours de présence communiqués par la clinique. */
  description: string | null
  /** Liste de l'administrateur uniquement. */
  nombre_medecins?: number
}

export interface Medecin {
  /** Identifiant du compte (users.id = medecins.user_id). */
  id: number
  nom: string
  prenom: string
  actif: boolean
  specialite: { id: number; libelle: string }
}

export interface Disponibilite {
  id: number
  medecin_id: number
  /** 1 = lundi … 7 = dimanche. */
  jour_semaine: number
  heure_debut: string
  heure_fin: string
  duree_creneau: number
}

/** Planning d'une semaine (lundi à dimanche), dates et heures dans le fuseau Africa/Dakar. */
export interface Planning {
  medecin: Medecin
  semaine: { du: string; au: string }
  disponibilites: Disponibilite[]
  indisponibilites: { id: number; date_debut: string; date_fin: string; motif: string | null }[]
  rendez_vous: {
    id: number
    date: string
    heure: string
    statut: StatutRdv
    statut_libelle: string
    patient: { nom: string; prenom: string }
  }[]
}

export const JOURS_SEMAINE = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'] as const

export const LIBELLES_ROLE: Record<Role, string> = {
  patient: 'Patient',
  secretaire: 'Secrétaire',
  medecin: 'Médecin',
  administrateur: 'Administrateur',
}

export const LIBELLES_STATUT: Record<StatutRdv, string> = {
  confirme: 'Confirmé',
  annule: 'Annulé',
  a_reprogrammer: 'À reprogrammer',
  passe: 'Passé',
}

/** Page d'arrivée de chaque espace après connexion. */
export const ACCUEIL_ROLE: Record<Role, string> = {
  patient: '/patient',
  secretaire: '/secretariat',
  medecin: '/medecin',
  administrateur: '/admin',
}

export const MENTION_NON_DIAGNOSTIC =
  'Ces informations ne constituent pas un diagnostic et ne remplacent pas une consultation médicale.'
