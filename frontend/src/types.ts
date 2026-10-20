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
  /** RG11 : symptômes saisis dans un champ libre et court (psychiatrie). */
  saisie_restreinte: boolean
  /** RG11 : longueur maximale du champ « symptômes ». */
  max_symptomes: number
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
    /** Existence d'une préconsultation (RG10 : jamais son contenu). */
    a_preconsultation: boolean
    patient: { nom: string; prenom: string }
  }[]
}

/** Créneau libre (heure de Dakar). `date_heure` est la valeur à envoyer pour réserver. */
export interface Creneau {
  date_heure: string
  date: string
  heure: string
  fin: string
  medecin: { id: number; nom: string; prenom: string }
}

/** Créneaux libres d'une période, regroupés par jour. */
export interface Creneaux {
  du: string
  au: string
  jours: { date: string; creneaux: Creneau[] }[]
}

export interface RendezVous {
  id: number
  date_heure: string
  date: string
  heure: string
  statut: StatutRdv
  statut_libelle: string
  medecin: { id: number; nom: string; prenom: string }
  specialite: { id: number; libelle: string }
  /** Secrétariat uniquement. */
  patient?: { id: number; nom: string; prenom: string; telephone: string | null }
  /** Secrétariat uniquement : existence d'une préconsultation (RG10 : jamais son contenu). */
  a_preconsultation?: boolean
  /** Droits de l'utilisateur connecté (RG08), calculés par le serveur. */
  modifiable: boolean
  annulable: boolean
  /** Heure limite de modification en ligne par le patient. */
  limite_modification: string
}

/** Préconsultation facultative (RG02) : motif et symptômes seulement (collecte minimale). */
export interface SaisiePreconsultation {
  motif: string
  symptomes: string
}

/** Contenu d'une préconsultation, lisible par le médecin du rendez-vous et le patient (RG10). */
export interface Preconsultation extends SaisiePreconsultation {
  date_saisie: string | null
  /** Suggestion d'orientation que le patient n'a pas suivie. */
  specialite_suggeree: { id: number; libelle: string } | null
}

/** Suggestion d'orientation indicative (RG03, RG04) et prochains créneaux de la spécialité. */
export interface SuggestionOrientation {
  specialite: Specialite
  creneaux: Creneau[]
}

/** Statuts qui occupent encore un créneau. */
export const STATUTS_ACTIFS: StatutRdv[] = ['confirme', 'a_reprogrammer']

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

/** Statistiques de l'administrateur : comptes agrégés seulement (aucune donnée nominative). */
export interface Statistiques {
  /** Bornes comprises (AAAA-MM-JJ) ; regroupement par mois. */
  periode: { du: string; au: string; regroupement: 'mois' }
  rendez_vous: {
    total: number
    par_statut: { statut: StatutRdv; libelle: string; nombre: number }[]
    par_specialite: { specialite_id: number; libelle: string; nombre: number }[]
    /** Mois de la période au format AAAA-MM, y compris ceux sans rendez-vous. */
    par_mois: { mois: string; nombre: number }[]
  }
  /** `suggestions` : suggestions d'orientation que le patient n'a pas suivies (les seules enregistrées). */
  preconsultations: { total: number; suggestions: number }
  indisponibilites: { total: number }
}

export const MENTION_NON_DIAGNOSTIC =
  'Ces informations ne constituent pas un diagnostic et ne remplacent pas une consultation médicale.'
