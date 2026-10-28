import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Alerte } from '../components/Formulaire'
import { MENTION_NON_DIAGNOSTIC } from '../types'

/** Mise en page commune aux deux pages d'information légale (publiques, RG01). */
function PageJuridique({ id, titre, children }: { id: string; titre: string; children: ReactNode }) {
  return (
    <article aria-labelledby={id} className="mx-auto max-w-3xl space-y-5">
      <h1 id={id} className="text-2xl font-bold text-gray-900">
        {titre}
      </h1>
      {/* Plateforme de démonstration : le texte reste à valider par la clinique avant toute mise en service. */}
      <Alerte type="info">
        Version de démonstration utilisée avec des données fictives. Ce texte doit être validé par la clinique avant toute mise en
        service avec de vrais patients.
      </Alerte>
      <div className="space-y-5 rounded-xl bg-white p-5 text-gray-800 shadow-sm">{children}</div>
    </article>
  )
}

function Partie({ titre, children }: { titre: string; children: ReactNode }) {
  return (
    <section className="space-y-2">
      <h2 className="text-lg font-semibold text-gray-900">{titre}</h2>
      {children}
    </section>
  )
}

const classeListe = 'list-disc space-y-1 pl-5'

export function PolitiqueConfidentialite() {
  return (
    <PageJuridique id="titre-confidentialite" titre="Politique de confidentialité">
      <Partie titre="Responsable du traitement">
        <p>
          La Clinique Croix Bleue est responsable du traitement des données collectées sur cette plateforme, conformément à la loi
          n° 2008-12 du 25 janvier 2008 sur la protection des données à caractère personnel.
        </p>
      </Partie>
      <Partie titre="Données collectées">
        <ul className={classeListe}>
          <li>À l'inscription : nom, prénom, adresse électronique, téléphone et mot de passe (enregistré sous forme chiffrée).</li>
          <li>À la prise de rendez-vous : spécialité, médecin, date et heure.</li>
          <li>
            Si vous la remplissez, la préconsultation : motif et symptômes uniquement. Ce sont des données de santé : elles ne sont
            enregistrées qu'avec votre consentement explicite.
          </li>
          <li>La date de votre consentement, pour pouvoir en apporter la preuve.</li>
        </ul>
      </Partie>
      <Partie titre="Finalités">
        <p>
          Ces données servent uniquement à gérer vos rendez-vous, à vous envoyer les courriels qui les concernent (confirmation,
          modification, annulation) et à préparer la consultation. Elles ne sont ni vendues ni utilisées à des fins publicitaires. Les
          statistiques de la clinique sont calculées sans nom de patient.
        </p>
      </Partie>
      <Partie titre="Qui peut voir vos données">
        <ul className={classeListe}>
          <li>La préconsultation : seulement vous et le médecin de votre rendez-vous.</li>
          <li>Vos rendez-vous : le secrétariat, pour organiser les plannings, et le médecin concerné.</li>
          <li>Votre compte : l'administrateur de la plateforme.</li>
        </ul>
      </Partie>
      <Partie titre="Hébergement et sécurité">
        <p>
          Les données sont enregistrées dans une base de données hébergée hors du Sénégal. Les accès sont contrôlés selon le rôle de
          chaque utilisateur et les mots de passe ne sont jamais conservés en clair.
        </p>
      </Partie>
      <Partie titre="Vos droits">
        <p>
          Vous disposez d'un droit d'accès, de rectification, d'opposition et de suppression de vos données. Vous pouvez retirer à tout
          moment votre consentement à la préconsultation : il suffit de ne pas la remplir pour vos prochains rendez-vous. Pour exercer
          vos droits, adressez-vous à l'accueil de la clinique. Vous pouvez aussi saisir la Commission de protection des données
          personnelles (CDP).
        </p>
      </Partie>
    </PageJuridique>
  )
}

export function ConditionsUtilisation() {
  return (
    <PageJuridique id="titre-conditions" titre="Conditions d'utilisation">
      <Partie titre="Objet">
        <p>
          La plateforme permet aux patients de consulter les spécialités de la clinique, de réserver, modifier ou annuler un rendez-vous
          et, s'ils le souhaitent, de remplir une préconsultation. Elle complète l'accueil de la clinique sans le remplacer : vous pouvez
          toujours vous y présenter directement, sans rendez-vous.
        </p>
      </Partie>
      <Partie titre="Compte">
        <p>
          La création d'un compte patient suppose d'accepter ces conditions et la{' '}
          <Link to="/confidentialite" className="font-medium text-primaire-700 underline">
            politique de confidentialité
          </Link>
          . Vous vous engagez à fournir des informations exactes et à garder votre mot de passe confidentiel. Les comptes du personnel sont
          créés par l'administrateur.
        </p>
      </Partie>
      <Partie titre="Rendez-vous">
        <ul className={classeListe}>
          <li>Un rendez-vous réservé est confirmé aussitôt.</li>
          <li>Vous pouvez le modifier ou l'annuler jusqu'à 24 heures avant son heure.</li>
          <li>
            Si le médecin devient indisponible, le secrétariat reprogramme ou annule le rendez-vous et vous prévient par courriel.
          </li>
        </ul>
      </Partie>
      <Partie titre="Préconsultation et orientation">
        <p>
          La préconsultation est facultative. La suggestion d'orientation éventuelle n'est qu'une indication : vous restez libre de votre
          choix de spécialité. {MENTION_NON_DIAGNOSTIC}
        </p>
      </Partie>
      <Partie titre="Urgences">
        <p>La plateforme n'est pas un service d'urgence. En cas d'urgence, présentez-vous directement à la clinique.</p>
      </Partie>
    </PageJuridique>
  )
}
