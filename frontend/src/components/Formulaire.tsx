import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes } from 'react'

const classeChamp =
  'mt-1 block w-full rounded-lg border bg-white px-3 py-2.5 text-gray-900 shadow-sm focus:border-primaire-600 focus:outline-none focus:ring-2 focus:ring-primaire-600/30'

interface ProprietesChamp extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  erreur?: string
  aide?: string
}

/** Champ de saisie avec label associé, aide et message d'erreur accessibles. */
export function Champ({ label, erreur, aide, id, className, ...props }: ProprietesChamp) {
  const idAuto = useId()
  const idChamp = id ?? idAuto
  const idAide = aide ? `${idChamp}-aide` : undefined
  const idErreur = erreur ? `${idChamp}-erreur` : undefined

  return (
    <div className={className}>
      <label htmlFor={idChamp} className="block text-sm font-medium text-gray-800">
        {label}
        {props.required && <span aria-hidden="true" className="text-red-700"> *</span>}
      </label>
      <input
        id={idChamp}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={[idAide, idErreur].filter(Boolean).join(' ') || undefined}
        className={`${classeChamp} ${erreur ? 'border-red-600' : 'border-gray-300'}`}
        {...props}
      />
      {aide && (
        <p id={idAide} className="mt-1 text-xs text-gray-600">
          {aide}
        </p>
      )}
      {erreur && (
        <p id={idErreur} className="mt-1 text-sm text-red-700">
          {erreur}
        </p>
      )}
    </div>
  )
}

interface ProprietesSelect extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string
  erreur?: string
  children: ReactNode
}

export function Liste({ label, erreur, id, className, children, ...props }: ProprietesSelect) {
  const idAuto = useId()
  const idChamp = id ?? idAuto
  const idErreur = erreur ? `${idChamp}-erreur` : undefined

  return (
    <div className={className}>
      <label htmlFor={idChamp} className="block text-sm font-medium text-gray-800">
        {label}
        {props.required && <span aria-hidden="true" className="text-red-700"> *</span>}
      </label>
      <select
        id={idChamp}
        aria-invalid={erreur ? true : undefined}
        aria-describedby={idErreur}
        className={`${classeChamp} ${erreur ? 'border-red-600' : 'border-gray-300'}`}
        {...props}
      >
        {children}
      </select>
      {erreur && (
        <p id={idErreur} className="mt-1 text-sm text-red-700">
          {erreur}
        </p>
      )}
    </div>
  )
}

export function Alerte({ type = 'erreur', children }: { type?: 'erreur' | 'succes' | 'info'; children: ReactNode }) {
  const styles = {
    erreur: 'border-red-300 bg-red-50 text-red-800',
    succes: 'border-green-300 bg-green-50 text-green-800',
    info: 'border-primaire-100 bg-primaire-50 text-primaire-800',
  }
  return (
    <div role={type === 'erreur' ? 'alert' : 'status'} className={`rounded-lg border px-4 py-3 text-sm ${styles[type]}`}>
      {children}
    </div>
  )
}

export function Bouton({
  variante = 'primaire',
  className = '',
  ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & { variante?: 'primaire' | 'secondaire' | 'danger' }) {
  const styles = {
    primaire: 'bg-primaire-600 text-white hover:bg-primaire-700 disabled:bg-primaire-600/60',
    secondaire: 'border border-gray-300 bg-white text-gray-900 hover:bg-gray-50 disabled:text-gray-500',
    danger: 'bg-red-700 text-white hover:bg-red-800 disabled:bg-red-700/60',
  }
  return (
    <button
      className={`inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold disabled:cursor-not-allowed ${styles[variante]} ${className}`}
      {...props}
    />
  )
}
