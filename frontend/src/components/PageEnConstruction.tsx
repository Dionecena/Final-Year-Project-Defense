/** Emplacement d'un écran prévu dans un module ultérieur. */
export default function PageEnConstruction({ titre, module }: { titre: string; module: number }) {
  return (
    <section aria-labelledby="titre-page">
      <h1 id="titre-page" className="text-2xl font-bold text-gray-900">
        {titre}
      </h1>
      <p className="mt-2 text-gray-600">Cet écran sera réalisé au module {module}.</p>
    </section>
  )
}
