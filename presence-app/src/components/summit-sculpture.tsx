"use client";

import { useEffect, useRef, useState } from "react";
import { useReducedMotion } from "motion/react";
import { useTheme } from "next-themes";
import { Pause, Play } from "lucide-react";
import * as THREE from "three";
import { RoundedBoxGeometry } from "three/addons/geometries/RoundedBoxGeometry.js";

/**
 * Le sommet : trois paliers qui montent, L1, L2, L3. Une bille part du
 * premier, grimpe, et s'installe sur le dernier — celui qui porte la couleur
 * de la marque, autour duquel tournent les trois marques du sceau. Montré à
 * l'étudiant de troisième année sur l'onglet Migration : il n'y a plus de
 * salle à changer, il est arrivé en haut.
 *
 * Mêmes conventions que les autres sculptures : dessin à la demande (rien
 * quand l'écran est caché ou l'élément hors champ), matières mates, repli
 * statique sans WebGL et sans animation quand le système le demande.
 */
export function SummitSculpture({ niveaux = ["L1", "L2", "L3"] }: { niveaux?: string[] }) {
  const host = useRef<HTMLDivElement>(null);
  const reducedMotion = useReducedMotion();
  const { resolvedTheme } = useTheme();
  const [paused, setPaused] = useState(false);
  const motionAllowed = !reducedMotion && !paused;
  const motionRef = useRef(motionAllowed);
  const wake = useRef<(() => void) | null>(null);
  const etiquettes = niveaux.join("|");

  useEffect(() => {
    motionRef.current = motionAllowed;
    wake.current?.();
  }, [motionAllowed]);

  useEffect(() => {
    const element = host.current;
    if (!element) return;
    let renderer: THREE.WebGLRenderer;
    try {
      renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    } catch {
      return;
    }
    const sombre = resolvedTheme === "dark";
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;
    renderer.setClearColor(0x000000, 0);
    element.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    // Focale longue : la perspective s'aplatit, les trois paliers gardent leurs proportions.
    const camera = new THREE.PerspectiveCamera(22, 1, 0.1, 60);
    scene.add(new THREE.AmbientLight(0xffffff, sombre ? 1.1 : 1.5));
    const key = new THREE.DirectionalLight(0xffffff, sombre ? 2.2 : 2.6);
    key.position.set(-3, 5, 6);
    scene.add(key);
    const fill = new THREE.DirectionalLight(0xd7f5e8, 0.8);
    fill.position.set(4, -1, 3);
    scene.add(fill);

    const group = new THREE.Group();
    group.position.set(0, -0.35, 0);
    scene.add(group);

    // Trois paliers, de plus en plus hauts, de gauche à droite.
    const neutre = new THREE.MeshStandardMaterial({
      color: sombre ? 0x2a3530 : 0xd6dfda,
      metalness: 0.12,
      roughness: 0.62,
    });
    // Le dernier palier ne prend la couleur qu'à l'arrivée de la bille.
    const couleurNeutre = new THREE.Color(sombre ? 0x2a3530 : 0xd6dfda);
    const couleurMenthe = new THREE.Color(sombre ? 0x2ee59d : 0x0f9d68);
    const menthe = new THREE.MeshStandardMaterial({
      color: couleurNeutre.clone(),
      metalness: 0.12,
      roughness: 0.55,
    });
    const largeur = 1.05;
    const profondeur = 1.05;
    const pas = 1.12;
    const hauteurs = [0.5, 1.05, 1.7];
    const geometries: THREE.BufferGeometry[] = [];
    const sommets: THREE.Vector3[] = [];
    const labels = niveaux.slice(0, 3);
    hauteurs.forEach((hauteur, index) => {
      const geometry = new RoundedBoxGeometry(largeur, hauteur, profondeur, 3, 0.09);
      geometries.push(geometry);
      const palier = new THREE.Mesh(geometry, index === hauteurs.length - 1 ? menthe : neutre);
      const x = (index - 1) * pas;
      palier.position.set(x, hauteur / 2, 0);
      group.add(palier);
      sommets.push(new THREE.Vector3(x, hauteur, 0));
      if (labels[index]) {
        const sprite = etiquette(labels[index], sombre ? "#8e9a93" : "#67736c");
        sprite.position.set(x, -0.28, profondeur / 2 + 0.05);
        group.add(sprite);
      }
    });

    // La bille : la personne qui monte.
    const billeGeometry = new THREE.SphereGeometry(0.2, 40, 40);
    geometries.push(billeGeometry);
    const encre = new THREE.MeshStandardMaterial({
      color: sombre ? 0xf4f7f5 : 0x0f1512,
      metalness: 0.15,
      roughness: 0.45,
    });
    const bille = new THREE.Mesh(billeGeometry, encre);
    group.add(bille);

    // Les trois marques du sceau, en orbite au-dessus du dernier palier.
    const orbite = new THREE.Group();
    const dernier = sommets[sommets.length - 1];
    orbite.position.set(dernier.x, dernier.y + 0.62, 0);
    orbite.rotation.x = 1.25;
    group.add(orbite);
    const marqueGeometry = new THREE.SphereGeometry(0.06, 20, 20);
    geometries.push(marqueGeometry);
    for (let index = 0; index < 3; index++) {
      const angle = (index / 3) * Math.PI * 2;
      const marque = new THREE.Mesh(marqueGeometry, encre);
      marque.position.set(Math.cos(angle) * 0.62, Math.sin(angle) * 0.62, 0);
      orbite.add(marque);
    }
    const anneauGeometry = new THREE.TorusGeometry(0.62, 0.012, 12, 96);
    geometries.push(anneauGeometry);
    const anneauMateriau = new THREE.MeshStandardMaterial({
      color: sombre ? 0x5f6b65 : 0x9aa59f,
      metalness: 0.1,
      roughness: 0.7,
    });
    orbite.add(new THREE.Mesh(anneauGeometry, anneauMateriau));

    // La montée : une parabole d'un sommet au suivant, un palier par seconde,
    // puis un léger balancement au repos. À l'arrivée, le dernier palier
    // prend la couleur et les marques du sceau se déploient.
    const DUREE_PAS = 1000;
    const debutMontee = performance.now() + 500;
    const arrivee = debutMontee + DUREE_PAS * (sommets.length - 1);
    const arriver = (time: number) => {
      const p = motionRef.current ? Math.max(0, Math.min(1, (time - arrivee) / 700)) : 1;
      const douce = p * p * (3 - 2 * p);
      menthe.color.lerpColors(couleurNeutre, couleurMenthe, douce);
      const echelle = 0.01 + douce * (1 + Math.sin(douce * Math.PI) * 0.12);
      orbite.scale.setScalar(echelle);
    };
    const placer = (time: number) => {
      const ecoule = time - debutMontee;
      const rayon = 0.2;
      arriver(time);
      if (!motionRef.current || ecoule >= DUREE_PAS * (sommets.length - 1)) {
        const repos = ecoule - DUREE_PAS * (sommets.length - 1);
        const balance = motionRef.current && repos > 0 ? Math.sin(repos / 900) * 0.03 : 0;
        bille.position.set(dernier.x, dernier.y + rayon + balance, 0);
        return;
      }
      if (ecoule < 0) {
        bille.position.set(sommets[0].x, sommets[0].y + rayon, 0);
        return;
      }
      const etape = Math.floor(ecoule / DUREE_PAS);
      const t = (ecoule % DUREE_PAS) / DUREE_PAS;
      const douce = t * t * (3 - 2 * t);
      const de = sommets[etape];
      const vers = sommets[etape + 1];
      const arc = Math.sin(t * Math.PI) * 0.55;
      bille.position.set(
        de.x + (vers.x - de.x) * douce,
        de.y + (vers.y - de.y) * douce + rayon + arc,
        0,
      );
    };

    let visible = true;
    let pointerX = 0;
    let pointerY = 0;
    let frame = 0;
    let contextLost = false;
    const draw = (time: number, force = false) => {
      if (contextLost || (!force && (!visible || document.hidden))) return;
      const moving = motionRef.current;
      placer(time);
      orbite.rotation.z = moving ? time / 2600 : 0;
      const x = moving ? 0.22 + Math.sin(time / 3000) * 0.03 + pointerY : 0.22;
      const y = moving ? -0.52 + Math.cos(time / 3600) * 0.06 + pointerX : -0.52;
      group.rotation.x += (x - group.rotation.x) * (moving ? 0.07 : 1);
      group.rotation.y += (y - group.rotation.y) * (moving ? 0.07 : 1);
      renderer.render(scene, camera);
      element.dataset.ready = "true";
      if (moving && visible && !document.hidden) frame = requestAnimationFrame(draw);
    };
    const start = (force = false) => {
      cancelAnimationFrame(frame);
      draw(performance.now(), force);
    };
    wake.current = start;

    const resize = new ResizeObserver(() => {
      const { width, height } = element.getBoundingClientRect();
      if (!width || !height) return;
      renderer.setSize(width, height);
      camera.aspect = width / Math.max(1, height);
      camera.position.set(0.15, 1.6, 10.5 / Math.min(1, camera.aspect));
      camera.lookAt(0.1, 0.45, 0);
      camera.updateProjectionMatrix();
      start(true);
    });
    resize.observe(element);
    const observer = new IntersectionObserver((entries) => {
      visible = entries[entries.length - 1].isIntersecting;
      start();
    });
    observer.observe(element);
    const move = (event: PointerEvent) => {
      const rect = element.getBoundingClientRect();
      pointerX = ((event.clientX - rect.left) / rect.width - 0.5) * 0.25;
      pointerY = ((event.clientY - rect.top) / rect.height - 0.5) * 0.12;
    };
    const leave = () => {
      pointerX = 0;
      pointerY = 0;
    };
    element.addEventListener("pointermove", move);
    element.addEventListener("pointerleave", leave);
    const visibilityChanged = () => start();
    document.addEventListener("visibilitychange", visibilityChanged);
    const lost = (event: Event) => {
      event.preventDefault();
      contextLost = true;
      cancelAnimationFrame(frame);
      delete element.dataset.ready;
    };
    const restored = () => {
      contextLost = false;
      start(true);
    };
    renderer.domElement.addEventListener("webglcontextlost", lost);
    renderer.domElement.addEventListener("webglcontextrestored", restored);
    return () => {
      cancelAnimationFrame(frame);
      wake.current = null;
      resize.disconnect();
      observer.disconnect();
      element.removeEventListener("pointermove", move);
      element.removeEventListener("pointerleave", leave);
      document.removeEventListener("visibilitychange", visibilityChanged);
      renderer.domElement.removeEventListener("webglcontextlost", lost);
      renderer.domElement.removeEventListener("webglcontextrestored", restored);
      group.traverse((objet) => {
        if (objet instanceof THREE.Sprite) {
          objet.material.map?.dispose();
          objet.material.dispose();
        }
      });
      geometries.forEach((geometry) => geometry.dispose());
      neutre.dispose();
      menthe.dispose();
      encre.dispose();
      anneauMateriau.dispose();
      renderer.dispose();
      renderer.domElement.remove();
      delete element.dataset.ready;
    };
    // `etiquettes` résume le tableau `niveaux` : une même liste ne relance pas la scène.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resolvedTheme, etiquettes]);

  return (
    <div className="summit-scene">
      <div ref={host} className="summit-sculpture" aria-hidden="true">
        <div className="summit-fallback">
          {niveaux.slice(0, 3).map((niveau, index) => (
            <span key={niveau} style={{ height: `${34 + index * 30}%` }} data-sommet={index === 2 || undefined}>
              {niveau}
            </span>
          ))}
        </div>
      </div>
      {!reducedMotion && (
        <button
          className="sculpture-pause"
          title={paused ? "Animer" : "Mettre l’animation en pause"}
          aria-label={paused ? "Animer" : "Mettre l’animation en pause"}
          aria-pressed={paused}
          onClick={() => setPaused(!paused)}
        >
          {paused ? <Play size={13} /> : <Pause size={13} />}
        </button>
      )}
    </div>
  );
}

/** Une étiquette de niveau dessinée sur un canvas, posée devant son palier. */
function etiquette(texte: string, couleur: string): THREE.Sprite {
  const canvas = document.createElement("canvas");
  canvas.width = 256;
  canvas.height = 128;
  const contexte = canvas.getContext("2d");
  if (contexte) {
    contexte.font = "600 84px system-ui, sans-serif";
    contexte.fillStyle = couleur;
    contexte.textAlign = "center";
    contexte.textBaseline = "middle";
    contexte.fillText(texte, 128, 64);
  }
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  const materiau = new THREE.SpriteMaterial({ map: texture, transparent: true, depthWrite: false });
  const sprite = new THREE.Sprite(materiau);
  sprite.scale.set(0.7, 0.35, 1);
  return sprite;
}
