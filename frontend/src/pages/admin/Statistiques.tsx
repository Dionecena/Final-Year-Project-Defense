import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Alerte, Bouton, Champ } from '../../components/Formulaire'
import { api, erreursChamps, messageErreur } from '../../lib/api'
import type { Statistiques as DonneesStatistiques, StatutRdv } from '../../types'

const COULEURS_STATUT: Record<StatutRdv, string> = {
  confirme: 'bg-green-600',
  a_reprogrammer: 'bg-amber-500',
  annule: 'bg-gray-400',
  passe: 'bg-gray-600',
}

function formatDate(date: string) {
  const [a, m, j] = date.split('-')
  return `${j}/${m}/${a}`
}

function libelleMois(mois: string) {
  const [a, m] = mois.split('-').map(Number)
  const texte = new Date(a, m - 1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
  return texte.charAt(0).toUpperCase() + texte.slice(1)
}

/** Administrateur : statistiques de la période choisie (rendez-vous, préconsultations, indisponibilités). */
export default function Statistiques() {
  const [params, setParams] = useSearchParams()
  const [stats, setStats] = useState<DonneesStatistiques | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [du, setDu] = useState(params.get('du') ?? '')
  const [au, setAu] = useState(params.get('au') ?? '')

  const requete = params.toString()
  useEffect(() => {
    let actif = true
    api
      .get<{ data: DonneesStatistiques }>(`/api/admin/statistiques?${requete}`)
      .then(({ data }) => {
        if (!actif) return
        setStats(data.data)
        setDu(data.data.periode.du)
        setAu(data.data.periode.au)
        setErreur(null)
        setErreurs({})
      })
      .catch((err) => {
        if (!actif) return
        const parChamp = erreursChamps(err)
        setStats(null)
        setErreurs(parChamp)
        setErreur(Object.keys(parChamp).length ? 'Veuillez corriger la période.' : messageErreur(err))
      })
    return () => {
      actif = false
    }
  }, [requete])

  function appliquer(e: FormEvent) {
    e.preventDefault()
    const suivants = new URLSearchParams()
    if (du) suivants.set('du', du)
    if (au) suivants.set('au', au)
    setParams(suivants, { replace: true })
  }

  return (
    <section aria-labelledby="titre-statistiques" className="space-y-5">
      <h1 id="titre-statistiques" className="text-2xl font-bold text-gray-900">
        Statistiques
      </h1>

      <form onSubmit={appliquer} noValidate aria-label="Période" className="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm sm:flex-row sm:items-end">
        <Champ label="Du" type="date" value={du} onChange={(e) => setDu(e.target.value)} erreur={erreurs.du} className="sm:w-48" />
        <Champ label="Au" type="date" value={au} onChange={(e) => setAu(e.target.value)} erreur={erreurs.au} className="sm:w-48" />
        <Bouton type="submit">Afficher</Bouton>
      </form>

      {erreur && <Alerte>{erreur}</Alerte>}

      {!stats ? (
        !erreur && (
          <p role="status" className="text-gray-600">
            Chargement…
          </p>
        )
      ) : (
        <>
          <p className="text-sm text-gray-700">
            Période du {formatDate(stats.periode.du)} au {formatDate(stats.periode.au)}, bornes comprises. Les rendez-vous et les
            préconsultations sont comptés à la date du rendez-vous, tous statuts confondus.
          </p>

          <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <ChiffreCle libelle="Rendez-vous" valeur={stats.rendez_vous.total} />
            <ChiffreCle libelle="Préconsultations" valeur={stats.preconsultations.total} />
            <ChiffreCle libelle="Suggestions d'orientation émises" valeur={stats.preconsultations.suggestions} />
            <ChiffreCle libelle="Indisponibilités" valeur={stats.indisponibilites.total} />
          </dl>
          <p className="text-xs text-gray-600">
            Suggestions émises : seules les suggestions que le patient n'a pas suivies (il a conservé sa spécialité) sont enregistrées.
          </p>

          <div className="grid gap-5 lg:grid-cols-2">
            <Bloc titre="Rendez-vous par statut">
              <Barres
                lignes={stats.rendez_vous.par_statut.map((s) => ({ cle: s.statut, libelle: s.libelle, nombre: s.nombre, couleur: COULEURS_STATUT[s.statut] }))}
              />
            </Bloc>
            <Bloc titre="Rendez-vous par mois">
              <Barres lignes={stats.rendez_vous.par_mois.map((m) => ({ cle: m.mois, libelle: libelleMois(m.mois), nombre: m.nombre }))} />
            </Bloc>
            <Bloc titre="Rendez-vous par spécialité">
              <Barres lignes={stats.rendez_vous.par_specialite.map((s) => ({ cle: String(s.specialite_id), libelle: s.libelle, nombre: s.nombre }))} />
            </Bloc>
          </div>
        </>
      )}
    </section>
  )
}

function ChiffreCle({ libelle, valeur }: { libelle: string; valeur: number }) {
  return (
    <div className="flex flex-col-reverse justify-end rounded-xl bg-white p-4 shadow-sm">
      <dt className="text-sm text-gray-700">{libelle}</dt>
      <dd className="text-3xl font-bold text-gray-900">{valeur}</dd>
    </div>
  )
}

function Bloc({ titre, children }: { titre: string; children: ReactNode }) {
  return (
    <div className="rounded-xl bg-white p-4 shadow-sm">
      <h2 className="mb-3 text-lg font-bold text-gray-900">{titre}</h2>
      {children}
    </div>
  )
}

/** Barres horizontales en CSS : le nombre est écrit en clair, la barre n'est qu'une aide visuelle. */
function Barres({ lignes }: { lignes: { cle: string; libelle: string; nombre: number; couleur?: string }[] }) {
  const maximum = Math.max(1, ...lignes.map((l) => l.nombre))
  return (
    <ul className="space-y-2.5">
      {lignes.map((l) => (
        <li key={l.cle}>
          <div className="flex items-baseline justify-between gap-3 text-sm">
            <span className="text-gray-800">{l.libelle}</span>
            <span className="font-semibold tabular-nums text-gray-900">{l.nombre}</span>
          </div>
          <div aria-hidden="true" className="mt-1 h-2.5 rounded-full bg-gray-100">
            <div className={`h-2.5 rounded-full ${l.couleur ?? 'bg-primaire-600'}`} style={{ width: `${(l.nombre / maximum) * 100}%` }} />
          </div>
        </li>
      ))}
    </ul>
  )
}
