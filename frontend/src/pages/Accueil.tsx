import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import Animation3D from '../components/accueil/Animation3D'
import Revelation from '../components/accueil/Revelation'
import { api } from '../lib/api'
import { ACCUEIL_ROLE, MENTION_NON_DIAGNOSTIC, type Specialite } from '../types'

/**
 * Page d'accueil publique. Mise en page reprise du modèle fourni (docs/modele_accueil.html) ;
 * le contenu se limite à ce que la plateforme fait réellement : aucun chiffre inventé, aucun
 * médecin nommé, aucun témoignage. RG13 : l'accueil direct reste toujours possible.
 */
export default function Accueil() {
  const { utilisateur } = useAuth()
  const [specialites, setSpecialites] = useState<Specialite[] | null>(null)

  useEffect(() => {
    api
      .get<{ data: Specialite[] }>('/api/specialites')
      .then(({ data }) => setSpecialites(data.data))
      .catch(() => setSpecialites([]))
  }, [])

  // Le personnel est renvoyé vers son espace ; un visiteur passe par la connexion (RequireRole).
  const cible = utilisateur && utilisateur.role !== 'patient' ? ACCUEIL_ROLE[utilisateur.role] : '/patient/prise-de-rendez-vous'
  const libelleCible = utilisateur && utilisateur.role !== 'patient' ? 'Accéder à mon espace' : 'Prendre rendez-vous'

  return (
    <div className="overflow-x-hidden">
      {/* Hero */}
      <section className="relative overflow-hidden bg-gradient-to-br from-primaire-50 via-gray-50 to-medical-50">
        <div aria-hidden="true" className="pointer-events-none absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-medical-50 blur-3xl md:hidden" />
        <div aria-hidden="true" className="pointer-events-none absolute -right-20 -top-16 h-80 w-80 rounded-full bg-primaire-100 blur-3xl md:hidden" />
        {/* Sphère : en haut à droite, derrière le titre, sur smartphone ; à droite du texte sur grand écran. */}
        <div className="pointer-events-none absolute -right-32 -top-10 h-[24rem] w-[24rem] opacity-90 md:inset-y-0 md:-right-16 md:top-0 md:h-auto md:w-[55%] md:opacity-100">
          <Animation3D type="orbe" />
        </div>
        <div className="relative mx-auto max-w-6xl px-4 pb-20 pt-24 sm:py-24 md:py-28">
          <div className="max-w-xl">
            <Revelation>
              <p className="inline-flex items-center gap-2 rounded-full border border-primaire-600/15 bg-primaire-600/5 px-4 py-1.5 text-xs font-medium text-primaire-700">
                <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-primaire-600 motion-safe:animate-pulse" />
                Clinique Croix Bleue – Dakar
              </p>
            </Revelation>
            <Revelation delai={100}>
              <h1 className="mt-6 text-4xl font-extrabold leading-[1.05] tracking-tight text-gray-900 sm:text-5xl">
                Prenez rendez-vous <span className="text-primaire-600">en ligne</span>
              </h1>
            </Revelation>
            <Revelation delai={200}>
              <p className="mt-5 text-lg leading-relaxed text-gray-700">
                Consultez les créneaux réellement disponibles de nos spécialités, réservez avec une confirmation immédiate
                et, si vous le souhaitez, préparez votre consultation en décrivant le motif de votre visite.
              </p>
            </Revelation>
            <Revelation delai={300}>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <Link
                  to={cible}
                  className="inline-flex items-center justify-center gap-2 rounded-xl bg-primaire-600 px-6 py-3.5 font-semibold text-white shadow-sm transition hover:bg-primaire-700 motion-safe:hover:-translate-y-0.5"
                >
                  <IconeCalendrier />
                  {libelleCible}
                </Link>
                <a
                  href="#specialites"
                  className="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white/80 px-6 backdrop-blur py-3.5 font-semibold text-gray-900 transition hover:border-gray-400 motion-safe:hover:-translate-y-0.5"
                >
                  Voir les spécialités
                </a>
              </div>
            </Revelation>
          </div>
        </div>
      </section>

      {/* RG13 : la plateforme ne remplace pas l'accueil direct. */}
      <section aria-labelledby="titre-accueil-direct" className="relative z-10 mx-auto -mt-8 max-w-6xl px-4">
        <div className="rounded-2xl border-l-4 border-medical-700 bg-white p-5 shadow-lg sm:p-6">
          <h2 id="titre-accueil-direct" className="text-lg font-bold text-gray-900">
            Sans rendez-vous, c'est toujours possible
          </h2>
          <p className="mt-1 text-gray-700">
            Vous pouvez toujours vous présenter directement à l'accueil de la clinique, sans rendez-vous. La prise de
            rendez-vous en ligne est un service complémentaire.
          </p>
        </div>
      </section>

      {/* Points clés (cartes « verre » du modèle, sans chiffre inventé) */}
      <section aria-label="En bref" className="relative mx-auto max-w-6xl px-4 py-14">
        <div aria-hidden="true" className="pointer-events-none absolute left-1/4 top-1/2 h-48 w-48 -translate-y-1/2 rounded-full bg-primaire-100 blur-3xl" />
        <div aria-hidden="true" className="pointer-events-none absolute right-1/4 top-1/2 h-48 w-48 -translate-y-1/2 rounded-full bg-medical-50 blur-3xl" />
        <ul className="relative grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          <CarteVerre delai={0} titre={specialites ? String(specialites.length || '–') : '…'} texte="spécialités proposées" />
          <CarteVerre delai={100} titre="Créneaux réels" texte="selon les disponibilités des médecins" />
          <CarteVerre delai={200} titre="Confirmation" texte="immédiate, puis par courriel" />
          <CarteVerre delai={300} titre="Accueil direct" texte="toujours possible, sans rendez-vous" />
        </ul>
      </section>

      {/* Services */}
      <section id="services" aria-labelledby="titre-services" className="bg-white px-4 py-20">
        <EnTeteSection id="titre-services" etiquette="Services" titre="Ce que vous pouvez faire en ligne" />
        <p className="mt-3 text-center text-xs text-gray-500 md:hidden" aria-hidden="true">Faites glisser pour voir les services →</p>
        <div className="-mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 [scrollbar-width:none] md:mx-auto md:mt-12 md:grid md:max-w-6xl md:grid-cols-3 md:gap-5 md:overflow-visible md:p-0">
          <CarteService delai={0} icone={<IconeCalendrier />} couleur="bg-primaire-100 text-primaire-700" titre="Prise de rendez-vous" vedette>
            Choisissez une spécialité, un médecin et un créneau parmi ceux réellement libres. Le rendez-vous est confirmé
            immédiatement.
          </CarteService>
          <CarteService delai={100} icone={<IconeDocument />} couleur="bg-medical-50 text-medical-700" titre="Préconsultation facultative">
            Avant de réserver, vous pouvez indiquer le motif de votre visite et vos symptômes pour que le médecin en prenne
            connaissance. Vous pouvez aussi passer cette étape.
            <span className="mt-3 block text-xs text-gray-600">{MENTION_NON_DIAGNOSTIC}</span>
          </CarteService>
          <CarteService delai={200} icone={<IconeCourriel />} couleur="bg-green-100 text-green-800" titre="Suivi par courriel">
            Vous recevez un courriel à la confirmation, à la modification ou à l'annulation de votre rendez-vous, ainsi que
            si le médecin devient indisponible.
          </CarteService>
        </div>
      </section>

      {/* Transition : onde cardiaque */}
      <section aria-hidden="true" className="relative h-64 overflow-hidden bg-gradient-to-b from-white via-primaire-50 to-gray-50 md:h-80">
        <Animation3D type="onde" />
        <div className="absolute inset-0 flex flex-col items-center justify-center bg-[radial-gradient(ellipse_at_center,rgba(255,255,255,0.85)_0%,rgba(255,255,255,0.4)_35%,transparent_65%)] px-4 text-center">
          <p className="text-xl font-bold tracking-tight text-primaire-700">Préparez votre consultation</p>
          <p className="mt-1 text-sm text-gray-600">en quelques minutes, depuis votre téléphone</p>
        </div>
      </section>

      {/* Spécialités et jours de présence */}
      <section id="specialites" aria-labelledby="titre-specialites" className="scroll-mt-4 bg-gray-50 px-4 py-20">
        <EnTeteSection
          id="titre-specialites"
          etiquette="Spécialités"
          titre="Nos spécialités et leurs jours de présence"
          sousTitre="Plusieurs spécialités ne sont assurées que certains jours de la semaine."
        />
        <div className="mx-auto mt-12 max-w-6xl">
          {specialites === null ? (
            <p role="status" className="text-center text-gray-600">
              Chargement des spécialités…
            </p>
          ) : specialites.length === 0 ? (
            <p className="text-center text-gray-600">
              La liste des spécialités est momentanément indisponible.{' '}
              <Link to="/specialites" className="font-medium text-primaire-700 underline">
                Réessayer
              </Link>
            </p>
          ) : (
            <ul className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
              {specialites.map((s, i) => (
                <li key={s.id}>
                  <Revelation delai={(i % 4) * 80} className="h-full">
                    <div className="h-full rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primaire-600/30 hover:shadow-md sm:p-5">
                      <span aria-hidden="true" className="mb-2 block h-1 w-8 rounded-full bg-gradient-to-r from-primaire-600 to-medical-700" />
                      <h3 className="text-sm font-bold text-gray-900 sm:text-base">{s.libelle}</h3>
                      <p className="mt-1 text-sm text-primaire-700">{joursPresence(s.description)}</p>
                    </div>
                  </Revelation>
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>

      {/* Parcours */}
      <section id="parcours" aria-labelledby="titre-parcours" className="bg-white px-4 py-20">
        <EnTeteSection id="titre-parcours" etiquette="Comment ça marche" titre="Trois étapes simples" />
        <ol className="relative mx-auto mt-12 grid max-w-4xl gap-8 md:grid-cols-3 md:gap-10">
          <div aria-hidden="true" className="absolute bottom-7 left-7 top-7 w-0.5 bg-gradient-to-b from-primaire-600/40 via-primaire-600/20 to-transparent md:hidden" />
          <div aria-hidden="true" className="absolute left-[18%] right-[18%] top-7 hidden h-0.5 bg-gradient-to-r from-transparent via-primaire-600/25 to-transparent md:block" />
          <Etape numero={1} titre="Créez votre compte" delai={0}>
            Inscrivez-vous en quelques secondes avec votre adresse électronique et votre téléphone.
          </Etape>
          <Etape numero={2} titre="Choisissez spécialité et créneau" delai={150}>
            Remplissez si vous le souhaitez la préconsultation, puis choisissez un médecin et un créneau libre.
          </Etape>
          <Etape numero={3} titre="Recevez la confirmation" delai={300}>
            Votre rendez-vous est confirmé aussitôt et un courriel vous en rappelle la date, l'heure et le médecin.
          </Etape>
        </ol>
      </section>

      {/* Appel final */}
      <section className="relative overflow-hidden bg-gray-900 px-4 py-20 text-center">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute left-1/2 top-1/2 h-[36rem] w-[36rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgba(37,99,235,0.18),transparent_70%)]"
        />
        <Revelation className="relative mx-auto max-w-2xl">
          <h2 className="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Prêt à prendre {'rendez‑vous ?'}</h2>
          <p className="mt-4 text-gray-300">
            Réservez votre consultation en ligne, ou présentez-vous directement à l'accueil de la clinique.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link to={cible} className="rounded-xl bg-white px-7 py-3.5 font-bold text-gray-900 transition hover:bg-gray-100">
              {libelleCible}
            </Link>
            {!utilisateur && (
              <Link to="/inscription" className="rounded-xl border border-white/30 px-7 py-3.5 font-semibold text-white transition hover:bg-white/10">
                Créer un compte
              </Link>
            )}
          </div>
        </Revelation>
      </section>
    </div>
  )
}

/** « Jours de présence : Lundi, mercredi » → « Lundi, mercredi ». */
function joursPresence(description: string | null) {
  if (!description) return 'Jours de présence non communiqués'
  return description.replace(/^Jours de présence\s*:\s*/i, '')
}

function EnTeteSection({ id, etiquette, titre, sousTitre }: { id: string; etiquette: string; titre: string; sousTitre?: string }) {
  return (
    <Revelation className="mx-auto max-w-2xl text-center">
      <p className="text-xs font-semibold uppercase tracking-[0.12em] text-primaire-700">{etiquette}</p>
      <h2 id={id} className="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
        {titre}
      </h2>
      {sousTitre && <p className="mt-3 text-gray-600">{sousTitre}</p>}
    </Revelation>
  )
}

function CarteVerre({ titre, texte, delai }: { titre: string; texte: string; delai: number }) {
  return (
    <li>
      <Revelation delai={delai} className="h-full">
        <div className="h-full rounded-2xl border border-white/60 bg-white/70 p-4 text-center shadow-[0_8px_32px_rgba(0,0,0,0.06)] backdrop-blur-xl sm:p-6">
          <p className="text-lg font-extrabold leading-tight tracking-tight text-primaire-600 sm:text-2xl">{titre}</p>
          <p className="mt-1 text-sm font-medium text-gray-600">{texte}</p>
        </div>
      </Revelation>
    </li>
  )
}

function CarteService({
  icone,
  couleur,
  titre,
  children,
  delai,
  vedette = false,
}: {
  icone: ReactNode
  couleur: string
  titre: string
  children: ReactNode
  delai: number
  vedette?: boolean
}) {
  return (
    <Revelation delai={delai} className="w-[82%] shrink-0 snap-center md:h-full md:w-auto">
      <article
        className={`h-full rounded-2xl border p-7 transition motion-safe:hover:-translate-y-1 hover:shadow-lg ${
          vedette ? 'border-primaire-600/20 bg-gradient-to-br from-primaire-50 to-gray-50' : 'border-gray-200 bg-gray-50'
        }`}
      >
        <div className={`mb-5 flex h-12 w-12 items-center justify-center rounded-xl ${couleur}`}>{icone}</div>
        <h3 className="text-lg font-bold tracking-tight text-gray-900">{titre}</h3>
        <p className="mt-2 text-sm leading-relaxed text-gray-600">{children}</p>
      </article>
    </Revelation>
  )
}

function Etape({ numero, titre, children, delai }: { numero: number; titre: string; children: ReactNode; delai: number }) {
  return (
    <li className="relative">
      <Revelation delai={delai} className="flex items-start gap-4 md:block md:text-center">
        <span
          aria-hidden="true"
          className="relative flex h-14 w-14 shrink-0 md:mx-auto md:mb-5 items-center justify-center rounded-full bg-primaire-600 text-xl font-extrabold text-white shadow-[0_4px_20px_rgba(37,99,235,0.25)]"
        >
          {numero}
        </span>
        <div className="pt-1 md:pt-0">
          <h3 className="font-bold tracking-tight text-gray-900">
            <span className="sr-only">Étape {numero} : </span>
            {titre}
          </h3>
          <p className="mt-2 text-sm leading-relaxed text-gray-600 md:mx-auto md:max-w-xs">{children}</p>
        </div>
      </Revelation>
    </li>
  )
}

function IconeCalendrier() {
  return (
    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="4" width="18" height="18" rx="2" />
      <path d="M16 2v4M8 2v4M3 10h18" />
    </svg>
  )
}

function IconeDocument() {
  return (
    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M9 12h6m-3-3v6M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z" />
    </svg>
  )
}

function IconeCourriel() {
  return (
    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="m3 7 9 6 9-6" />
    </svg>
  )
}
