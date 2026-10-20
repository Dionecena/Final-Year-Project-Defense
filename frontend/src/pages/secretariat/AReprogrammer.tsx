import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Alerte, Bouton } from '../../components/Formulaire'
import LigneRendezVous from '../../components/LigneRendezVous'
import { api, messageErreur } from '../../lib/api'
import type { RendezVous } from '../../types'

interface Page {
  data: RendezVous[]
  meta: { current_page: number; last_page: number; total: number }
}

/**
 * Secrétaire : rendez-vous « à reprogrammer » à la suite d'une indisponibilité (RG09), avec les
 * actions « Reprogrammer » (nouveau créneau libre, retour à « confirmé ») et « Annuler ».
 * Liste du secrétariat filtrée sur le statut, triée par date.
 */
export default function AReprogrammer() {
  const [numeroPage, setNumeroPage] = useState(1)
  const [page, setPage] = useState<Page | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [chargement, setChargement] = useState(false)

  const charger = useCallback(async () => {
    setChargement(true)
    try {
      const { data } = await api.get<Page>('/api/secretariat/rendez-vous', { params: { statut: 'a_reprogrammer', page: numeroPage } })
      // Après un traitement, la dernière page peut être vide : revenir à la précédente.
      if (data.data.length === 0 && numeroPage > 1) {
        setNumeroPage((n) => n - 1)
        return
      }
      setPage(data)
      setErreur(null)
    } catch (err) {
      setErreur(messageErreur(err))
    } finally {
      setChargement(false)
    }
  }, [numeroPage])

  useEffect(() => {
    charger()
  }, [charger])

  return (
    <section aria-labelledby="titre-a-reprogrammer" className="space-y-5">
      <div>
        <h1 id="titre-a-reprogrammer" className="text-2xl font-bold text-gray-900">
          Rendez-vous à reprogrammer
        </h1>
        <p className="text-sm text-gray-600">
          Rendez-vous touchés par une indisponibilité du médecin. Reprogrammez-les sur un créneau libre ou annulez-les ; le patient
          est prévenu par courriel.
        </p>
      </div>

      {message && <Alerte type="succes">{message}</Alerte>}
      {erreur && <Alerte>{erreur}</Alerte>}

      {page && (
        <div className="space-y-3" aria-busy={chargement}>
          <p className="text-sm text-gray-700" role="status">
            {page.meta.total} rendez-vous à reprogrammer{chargement && ' – actualisation…'}
          </p>
          {page.data.length === 0 ? (
            <p className="text-gray-600">
              Aucun rendez-vous à reprogrammer.{' '}
              <Link to="/secretariat/indisponibilites" className="font-medium text-primaire-700 underline">
                Enregistrer une indisponibilité
              </Link>
            </p>
          ) : (
            <ul className="space-y-3">
              {page.data.map((r) => (
                <LigneRendezVous
                  key={r.id}
                  rendezVous={r}
                  onAction={(texte) => {
                    setMessage(texte)
                    charger()
                  }}
                />
              ))}
            </ul>
          )}
          {page.meta.last_page > 1 && (
            <nav aria-label="Pagination" className="flex items-center gap-2">
              <Bouton variante="secondaire" disabled={page.meta.current_page <= 1} onClick={() => setNumeroPage(page.meta.current_page - 1)}>
                ← Précédente
              </Bouton>
              <span className="text-sm text-gray-700">
                Page {page.meta.current_page} sur {page.meta.last_page}
              </span>
              <Bouton
                variante="secondaire"
                disabled={page.meta.current_page >= page.meta.last_page}
                onClick={() => setNumeroPage(page.meta.current_page + 1)}
              >
                Suivante →
              </Bouton>
            </nav>
          )}
        </div>
      )}
    </section>
  )
}
