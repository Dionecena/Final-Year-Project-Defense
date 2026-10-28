import { useState, useSyncExternalStore } from 'react'
import { estInstallee, estIos, inviteInstallation, oublierInvite, suivreInvite } from '../lib/pwa'
import { Bouton } from './Formulaire'

const CLE_REFUS = 'installation-refusee-le'
// Après « Plus tard », la fenêtre ne revient qu'au bout de 7 jours.
const DELAI_RAPPEL = 7 * 24 * 3600 * 1000

function refuseRecemment() {
  try {
    return Date.now() - Number(localStorage.getItem(CLE_REFUS) ?? 0) < DELAI_RAPPEL
  } catch {
    return false
  }
}

/**
 * Fenêtre proposant d'installer l'application, affichée aux seuls utilisateurs connectés
 * (Layout ne la rend que si un utilisateur est connecté).
 */
export default function InstallationApplication() {
  const invite = useSyncExternalStore(suivreInvite, inviteInstallation)
  const [ferme, setFerme] = useState(() => estInstallee() || refuseRecemment())
  if (ferme || (invite === null && !estIos())) return null

  function plusTard() {
    try {
      localStorage.setItem(CLE_REFUS, String(Date.now()))
    } catch {
      // Stockage indisponible (navigation privée) : la fenêtre reviendra à la prochaine visite.
    }
    setFerme(true)
  }

  async function installer() {
    if (!invite) return
    await invite.prompt()
    await invite.userChoice
    oublierInvite()
    setFerme(true)
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/40 p-4 sm:items-center" onKeyDown={(e) => e.key === 'Escape' && plusTard()}>
      <div role="dialog" aria-modal="true" aria-labelledby="installation-titre" aria-describedby="installation-texte" className="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
        <div className="flex items-center gap-3">
          <img src="/icones/icone-192.png" alt="" width={48} height={48} className="rounded-xl" />
          <h2 id="installation-titre" className="text-base font-semibold text-gray-900">
            Installer l'application
          </h2>
        </div>
        <p id="installation-texte" className="mt-3 text-sm text-gray-700">
          {invite
            ? "Ajoutez la prise de rendez-vous à votre écran d'accueil pour l'ouvrir en un geste, comme une application."
            : "Pour l'ajouter à votre écran d'accueil : dans Safari, touchez le bouton Partager, puis « Sur l'écran d'accueil »."}
        </p>
        <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Bouton autoFocus={!invite} variante="secondaire" onClick={plusTard}>
            {invite ? 'Plus tard' : "J'ai compris"}
          </Bouton>
          {invite && (
            <Bouton autoFocus onClick={installer}>
              Installer
            </Bouton>
          )}
        </div>
      </div>
    </div>
  )
}
