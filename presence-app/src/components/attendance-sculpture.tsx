"use client";

import { useEffect, useRef, useState } from "react";
import { useReducedMotion } from "motion/react";
import { useTheme } from "next-themes";
import { Pause, Play } from "lucide-react";
import * as THREE from "three";
import { RoundedBoxGeometry } from "three/addons/geometries/RoundedBoxGeometry.js";

export function AttendanceSculpture({ percent }: { percent: number | null }) {
  const host = useRef<HTMLDivElement>(null);
  const reducedMotion = useReducedMotion();
  const { resolvedTheme } = useTheme();
  const [paused, setPaused] = useState(false);
  const motionAllowed = !reducedMotion && !paused;
  const motionRef = useRef(motionAllowed);
  const wake = useRef<(() => void) | null>(null);
  const value = Number.isFinite(percent)
    ? Math.max(0, Math.min(100, percent ?? 0))
    : 0;
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
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.15;
    renderer.setClearColor(0x000000, 0);
    element.appendChild(renderer.domElement);
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 30);
    camera.position.set(0, 0, 6);
    scene.add(new THREE.AmbientLight(0xffffff, 1.4));
    const key = new THREE.DirectionalLight(0xffffff, 2.5);
    key.position.set(-3, 4, 6);
    scene.add(key);
    const fill = new THREE.DirectionalLight(0xc5e4ff, 0.9);
    fill.position.set(3, -2, 2);
    scene.add(fill);
    const group = new THREE.Group();
    scene.add(group);
    const geometry = new RoundedBoxGeometry(0.083, 0.34, 0.2, 2, 0.014);
    const green = new THREE.MeshStandardMaterial({
      color: resolvedTheme === "dark" ? 0x2ee59d : 0x0f9d68,
      metalness: 0.25,
      roughness: 0.35,
    });
    const gray = new THREE.MeshStandardMaterial({
      color: resolvedTheme === "dark" ? 0x2a3530 : 0xd6dfda,
      metalness: 0.18,
      roughness: 0.5,
    });
    const count = 72;
    const filled = Math.round((value / 100) * count);
    for (let index = 0; index < count; index++) {
      const angle = (index / count) * Math.PI * 2;
      const segment = new THREE.Mesh(geometry, index < filled ? green : gray);
      segment.position.set(Math.sin(angle) * 1.2, Math.cos(angle) * 1.2, 0);
      segment.rotation.z = -angle;
      group.add(segment);
    }
    let visible = true;
    let pointerX = 0;
    let pointerY = 0;
    let frame = 0;
    let contextLost = false;
    const draw = (time: number, force = false) => {
      if (contextLost || (!force && (!visible || document.hidden))) return;
      const moving = motionRef.current;
      const x = moving ? 0.13 + Math.sin(time / 2800) * 0.06 + pointerY : 0.13;
      const y = moving
        ? -0.15 + Math.cos(time / 3400) * 0.08 + pointerX
        : -0.15;
      group.rotation.x += (x - group.rotation.x) * (moving ? 0.07 : 1);
      group.rotation.y += (y - group.rotation.y) * (moving ? 0.07 : 1);
      renderer.render(scene, camera);
      element.dataset.ready = "true";
      if (moving && visible && !document.hidden)
        frame = requestAnimationFrame(draw);
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
      camera.position.z = 6 / Math.min(1, camera.aspect);
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
      pointerX = ((event.clientX - rect.left) / rect.width - 0.5) * 0.2;
      pointerY = ((event.clientY - rect.top) / rect.height - 0.5) * 0.15;
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
      geometry.dispose();
      green.dispose();
      gray.dispose();
      renderer.dispose();
      renderer.domElement.remove();
      delete element.dataset.ready;
    };
  }, [value, resolvedTheme]);

  return (
    <>
      <div ref={host} className="attendance-sculpture" aria-hidden="true">
        <div
          className="sculpture-fallback"
          style={{
            width: "76%",
            height: "76%",
            border: 0,
            background: `conic-gradient(var(--primary) ${value}%, var(--line) 0)`,
            maskImage: "radial-gradient(circle, transparent 56%, #000 57%)",
          }}
        />
      </div>
      {!reducedMotion && (
        <button
          className="sculpture-pause"
          title={paused ? "Animer l’indicateur" : "Mettre l’animation en pause"}
          aria-label={
            paused ? "Animer l’indicateur" : "Mettre l’animation en pause"
          }
          aria-pressed={paused}
          onClick={() => setPaused(!paused)}
        >
          {paused ? <Play size={13} /> : <Pause size={13} />}
        </button>
      )}
    </>
  );
}
