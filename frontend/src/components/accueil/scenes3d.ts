/**
 * Animations décoratives de la page d'accueil, reprises du modèle fourni (docs/modele_accueil.html) :
 * sphère en fil de fer et onde cardiaque. Ce module importe three.js ; il n'est chargé qu'à la
 * demande (import dynamique), jamais avec la préférence « mouvement réduit ». Sur smartphone
 * (option compact), la géométrie et la résolution sont allégées pour rester fluides.
 */
import * as THREE from 'three'

export type Scene3D = 'orbe' | 'onde'

/** Démarre l'animation dans le canevas et renvoie la fonction qui l'arrête et libère la mémoire. */
export function demarrerScene(type: Scene3D, canvas: HTMLCanvasElement, { compact = false } = {}): () => void {
  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: !compact, powerPreference: 'low-power' })
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, compact ? 1.5 : 2))
  renderer.setClearColor(0x000000, 0)

  const scene = new THREE.Scene()
  const camera = new THREE.PerspectiveCamera(type === 'orbe' ? 45 : 55, 1, 0.1, 200)
  const animer = type === 'orbe' ? construireOrbe(scene, camera, compact) : construireOnde(scene, camera, compact)

  function redimensionner() {
    const largeur = canvas.clientWidth
    const hauteur = canvas.clientHeight
    if (largeur === 0 || hauteur === 0) return
    camera.aspect = largeur / hauteur
    camera.updateProjectionMatrix()
    renderer.setSize(largeur, hauteur, false)
  }
  redimensionner()
  const observateurTaille = new ResizeObserver(redimensionner)
  observateurTaille.observe(canvas)

  // L'animation ne tourne que lorsque le canevas est visible à l'écran.
  let visible = true
  const observateurVisibilite = new IntersectionObserver(([entree]) => {
    visible = entree.isIntersecting
  })
  observateurVisibilite.observe(canvas)

  let image = 0
  function boucle() {
    image = requestAnimationFrame(boucle)
    if (!visible) return
    animer()
    renderer.render(scene, camera)
  }
  boucle()

  return () => {
    cancelAnimationFrame(image)
    observateurTaille.disconnect()
    observateurVisibilite.disconnect()
    scene.traverse((objet) => {
      if (objet instanceof THREE.Mesh || objet instanceof THREE.Points) {
        objet.geometry.dispose()
        ;(objet.material as THREE.Material).dispose()
      }
    })
    renderer.dispose()
  }
}

function materiau(couleur: number, opacite: number, filDeFer = true) {
  return new THREE.MeshBasicMaterial({ color: couleur, wireframe: filDeFer, transparent: true, opacity: opacite, side: THREE.DoubleSide })
}

/** Sphère en fil de fer, halo et particules (hero du modèle). */
function construireOrbe(scene: THREE.Scene, camera: THREE.PerspectiveCamera, compact: boolean) {
  // Sur smartphone, la sphère est reculée pour tenir entière dans son canevas carré.
  camera.position.z = compact ? 5.2 : 4

  const sphere = new THREE.Mesh(new THREE.IcosahedronGeometry(1.5, compact ? 3 : 4), materiau(0x2563eb, compact ? 0.15 : 0.12))
  const halo = new THREE.Mesh(new THREE.IcosahedronGeometry(1.6, 3), materiau(0x0ea5e9, compact ? 0.035 : 0.05, false))

  const nombre = compact ? 140 : 200
  const positions = new Float32Array(nombre * 3)
  for (let i = 0; i < positions.length; i++) positions[i] = (Math.random() - 0.5) * 6
  const geometrieParticules = new THREE.BufferGeometry()
  geometrieParticules.setAttribute('position', new THREE.BufferAttribute(positions, 3))
  const particules = new THREE.Points(
    geometrieParticules,
    new THREE.PointsMaterial({ color: 0x3b82f6, size: compact ? 0.025 : 0.015, transparent: true, opacity: 0.45 }),
  )

  scene.add(sphere, halo, particules)

  // La sphère accélère brièvement quand on fait défiler la page (effet sensible au toucher).
  let dernierDefilement = window.scrollY
  let elan = 0

  return () => {
    elan = elan * 0.92 + Math.min(Math.abs(window.scrollY - dernierDefilement), 60) * 0.0004
    dernierDefilement = window.scrollY
    sphere.rotation.x += 0.001 + elan * 0.5
    sphere.rotation.y += 0.002 + elan
    halo.rotation.x -= 0.0005
    halo.rotation.y -= 0.001
    particules.rotation.y += 0.0003
  }
}

/** Forme d'un battement de cœur (onde P, complexe QRS, onde T), t dans [0, 1[. */
function ecg(t: number) {
  t = ((t % 1) + 1) % 1
  if (t < 0.1) return 0
  if (t < 0.15) return Math.sin(((t - 0.1) * Math.PI) / 0.05) * 0.3
  if (t < 0.2) return 0
  if (t < 0.22) return -0.2
  if (t < 0.28) return -0.2 + ((t - 0.22) / 0.06) * 3.2
  if (t < 0.34) return 3.0 - ((t - 0.28) / 0.06) * 3.5
  if (t < 0.38) return -0.5 + ((t - 0.34) / 0.04) * 0.5
  if (t < 0.5) return 0
  if (t < 0.6) return Math.sin(((t - 0.5) * Math.PI) / 0.1) * 0.6
  return 0
}

/** Nappe en fil de fer animée par une onde cardiaque (transition du modèle). */
function construireOnde(scene: THREE.Scene, camera: THREE.PerspectiveCamera, compact: boolean) {
  camera.position.set(0, 8, 18)
  camera.lookAt(0, 0, 0)

  const largeur = 90
  const profondeur = 36
  const nappe = new THREE.Mesh(new THREE.PlaneGeometry(largeur, profondeur, compact ? 80 : 220, compact ? 22 : 60), materiau(0x2563eb, compact ? 0.11 : 0.09))
  const reflet = new THREE.Mesh(new THREE.PlaneGeometry(largeur, profondeur, compact ? 80 : 220, compact ? 22 : 60), materiau(0x0ea5e9, 0.05))
  for (const maillage of [nappe, reflet]) maillage.rotation.x = -Math.PI / 2.8
  reflet.position.y = 0.3
  scene.add(nappe, reflet)

  const positions = nappe.geometry.attributes.position as THREE.BufferAttribute
  const positionsReflet = reflet.geometry.attributes.position as THREE.BufferAttribute
  // Dans le plan avant rotation, la profondeur est l'axe Y et la hauteur l'axe Z.
  let temps = 0

  return () => {
    temps += 0.008
    for (let i = 0; i < positions.count; i++) {
      const x = positions.getX(i)
      const y = positions.getY(i)
      const attenuation = Math.max(1 - Math.abs(y) / (profondeur * 0.5), 0.1)
      const hauteur = ecg(x / 40 + 0.5 - temps * 0.3) * 1.2 * attenuation
      positions.setZ(i, hauteur)
      positionsReflet.setZ(i, hauteur * 0.7 + 0.15)
    }
    positions.needsUpdate = true
    positionsReflet.needsUpdate = true
  }
}
