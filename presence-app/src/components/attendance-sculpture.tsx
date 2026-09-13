"use client";

import { useEffect, useRef, useState } from "react";
import { useReducedMotion } from "motion/react";
import { Pause, Play } from "lucide-react";
import * as THREE from "three";

export function AttendanceSculpture({ percent }: { percent: number | null }) {
  const host = useRef<HTMLDivElement>(null);
  const reducedMotion = useReducedMotion();
  const [paused, setPaused] = useState(false);
  const motionAllowed = !reducedMotion && !paused;
  const motionRef = useRef(motionAllowed);
  useEffect(() => {
    motionRef.current = motionAllowed;
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
    renderer.setClearColor(0x000000, 0);
    element.appendChild(renderer.domElement);
    element.dataset.ready = "true";
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(34, 1, 0.1, 30);
    camera.position.set(0, 0, 6.8);
    scene.add(new THREE.AmbientLight(0xffffff, 2));
    const key = new THREE.DirectionalLight(0xffffff, 5);
    key.position.set(-3, 4, 6);
    scene.add(key);
    const fill = new THREE.DirectionalLight(0xa7ffdc, 2);
    fill.position.set(3, -2, 2);
    scene.add(fill);
    const group = new THREE.Group();
    scene.add(group);
    const geometry = new THREE.BoxGeometry(0.1, 0.39, 0.24);
    const green = new THREE.MeshStandardMaterial({
      color: 0x16856a,
      metalness: 0.36,
      roughness: 0.26,
    });
    const gray = new THREE.MeshStandardMaterial({
      color: 0xb8c6c0,
      metalness: 0.18,
      roughness: 0.5,
    });
    const count = 60;
    const filled = Math.round(
      (Math.max(0, Math.min(100, percent ?? 0)) / 100) * count,
    );
    for (let index = 0; index < count; index++) {
      const angle = (-index / count) * Math.PI * 2;
      const segment = new THREE.Mesh(geometry, index < filled ? green : gray);
      segment.position.set(Math.sin(angle) * 1.2, Math.cos(angle) * 1.2, 0);
      segment.rotation.z = -angle;
      group.add(segment);
    }
    let visible = true;
    let pointerX = 0;
    let pointerY = 0;
    let frame = 0;
    const resize = new ResizeObserver(() => {
      const { width, height } = element.getBoundingClientRect();
      renderer.setSize(width, height);
      camera.aspect = width / Math.max(1, height);
      camera.updateProjectionMatrix();
      renderer.render(scene, camera);
    });
    resize.observe(element);
    const observer = new IntersectionObserver((entries) => {
      visible = entries[0].isIntersecting;
    });
    observer.observe(element);
    const move = (event: PointerEvent) => {
      const rect = element.getBoundingClientRect();
      pointerX = ((event.clientX - rect.left) / rect.width - 0.5) * 0.35;
      pointerY = ((event.clientY - rect.top) / rect.height - 0.5) * 0.25;
    };
    const leave = () => {
      pointerX = 0;
      pointerY = 0;
    };
    element.addEventListener("pointermove", move);
    element.addEventListener("pointerleave", leave);
    const draw = (time: number) => {
      frame = requestAnimationFrame(draw);
      if (!visible || document.hidden) return;
      const moving = motionRef.current;
      const x = moving ? 0.18 + Math.sin(time / 2800) * 0.12 + pointerY : 0.18;
      const y = moving
        ? -0.22 + Math.cos(time / 3400) * 0.12 + pointerX
        : -0.22;
      if (
        !moving &&
        Math.abs(x - group.rotation.x) < 0.0001 &&
        Math.abs(y - group.rotation.y) < 0.0001
      )
        return;
      group.rotation.x += (x - group.rotation.x) * 0.055;
      group.rotation.y += (y - group.rotation.y) * 0.055;
      renderer.render(scene, camera);
    };
    frame = requestAnimationFrame(draw);
    const lost = (event: Event) => {
      event.preventDefault();
      delete element.dataset.ready;
    };
    renderer.domElement.addEventListener("webglcontextlost", lost);
    return () => {
      cancelAnimationFrame(frame);
      resize.disconnect();
      observer.disconnect();
      element.removeEventListener("pointermove", move);
      element.removeEventListener("pointerleave", leave);
      renderer.domElement.removeEventListener("webglcontextlost", lost);
      geometry.dispose();
      green.dispose();
      gray.dispose();
      renderer.dispose();
      renderer.domElement.remove();
      delete element.dataset.ready;
    };
  }, [percent]);

  return (
    <>
      <div ref={host} className="attendance-sculpture" aria-hidden="true">
        <div className="sculpture-fallback" />
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
