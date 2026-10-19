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
