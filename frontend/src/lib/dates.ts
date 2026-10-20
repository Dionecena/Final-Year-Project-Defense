/**
 * Dates au format AAAA-MM-JJ, dans le fuseau de la clinique (Africa/Dakar), indépendamment du
 * fuseau du navigateur. Les calculs se font en UTC pour éviter tout décalage.
 */
export const FUSEAU = 'Africa/Dakar'

export function aujourdhui(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: FUSEAU, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())
}

function versDate(iso: string): Date {
  const [a, m, j] = iso.split('-').map(Number)
  return new Date(Date.UTC(a, m - 1, j))
}

export function ajouterJours(iso: string, jours: number): string {
  const d = versDate(iso)
  d.setUTCDate(d.getUTCDate() + jours)
  return d.toISOString().slice(0, 10)
}

/** 1 = lundi … 7 = dimanche. */
export function jourSemaine(iso: string): number {
  return ((versDate(iso).getUTCDay() + 6) % 7) + 1
}

/** « lundi 2 novembre 2026 » (ou sans l'année). */
export function libelleDate(iso: string, avecAnnee = false): string {
  return new Intl.DateTimeFormat('fr-FR', {
    timeZone: 'UTC',
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    ...(avecAnnee ? { year: 'numeric' } : {}),
  }).format(versDate(iso))
}

/** « 02/11/2026 ». */
export function dateCourte(iso: string): string {
  return iso.split('-').reverse().join('/')
}
