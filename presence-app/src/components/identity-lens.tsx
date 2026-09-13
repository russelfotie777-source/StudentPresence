"use client";

import { useEffect, useRef } from "react";
import * as THREE from "three";
import { RoomEnvironment } from "three/addons/environments/RoomEnvironment.js";

export type IdentityLensStatus =
  | "loading"
  | "idle"
  | "adjust"
  | "ready"
  | "verifying"
  | "success"
  | "error";

interface IdentityLensProps {
  status: IdentityLensStatus;
  paused?: boolean;
}

const SIGNALS: Record<IdentityLensStatus, { color: number; speed: number }> = {
  loading: { color: 0x336bce, speed: 0.15 },
  idle: { color: 0x698e8a, speed: 0.045 },
  adjust: { color: 0xaa762e, speed: 0.06 },
  ready: { color: 0x079779, speed: 0.09 },
  verifying: { color: 0x286edd, speed: 0.5 },
  success: { color: 0x08a778, speed: 0.025 },
  error: { color: 0xb45648, speed: 0 },
};

export function IdentityLens({ status, paused = false }: IdentityLensProps) {
  const host = useRef<HTMLDivElement>(null);
  const update = useRef<
    ((nextStatus: IdentityLensStatus, nextPaused: boolean) => void) | null
  >(null);
  const initial = useRef({ status, paused });

  useEffect(() => {
    const element = host.current;
    if (!element) return;

    let renderer: THREE.WebGLRenderer;
    try {
      renderer = new THREE.WebGLRenderer({
        alpha: true,
        antialias: true,
        powerPreference: "low-power",
      });
    } catch {
      return;
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
    renderer.setClearColor(0x000000, 0);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 0.98;
    renderer.domElement.style.cssText =
      "display:block;width:100%;height:100%;pointer-events:none";
    element.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    scene.environmentRotation.set(0.6, 0.3, 0.3);
    const camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0.1, 12);
    camera.position.z = 5;
    const geometries = new Set<THREE.BufferGeometry>();
    const materials = new Set<THREE.Material>();
    let environment: THREE.WebGLRenderTarget | null = null;

    const illuminate = () => {
      const room = new RoomEnvironment();
      const generator = new THREE.PMREMGenerator(renderer);
      try {
        const next = generator.fromScene(room, 0.035);
        environment?.dispose();
        environment = next;
        scene.environment = next.texture;
      } finally {
        room.dispose();
        generator.dispose();
      }
    };
    try {
      illuminate();
    } catch {
      // Directional lighting keeps the camera collar usable without an env map.
    }

    const silver = new THREE.MeshStandardMaterial({
      color: 0xb3bbb9,
      metalness: 0.95,
      roughness: 0.3,
    });
    const polished = new THREE.MeshStandardMaterial({
      color: 0xf0f4f2,
      metalness: 1,
      roughness: 0.16,
    });
    const graphite = new THREE.MeshStandardMaterial({
      color: 0x233531,
      metalness: 0.86,
      roughness: 0.29,
    });
    const engraving = new THREE.MeshStandardMaterial({
      color: 0x54625c,
      metalness: 0.25,
      roughness: 0.55,
    });
    const signal = new THREE.MeshPhysicalMaterial({
      color: SIGNALS[initial.current.status].color,
      emissive: SIGNALS[initial.current.status].color,
      emissiveIntensity: 0.2,
      metalness: 0.5,
      roughness: 0.23,
      clearcoat: 1,
      clearcoatRoughness: 0.18,
      side: THREE.DoubleSide,
    });
    const sapphire = new THREE.MeshStandardMaterial({
      color: 0x1f5cb4,
      emissive: 0x1a468f,
      emissiveIntensity: 0.16,
      metalness: 0.62,
      roughness: 0.22,
      side: THREE.DoubleSide,
    });
    [silver, polished, graphite, engraving, signal, sapphire].forEach((item) =>
      materials.add(item),
    );

    const collar = new THREE.Group();
    scene.add(collar);

    // Orthographic radii preserve a clear 59.5% opening around the 58% video.
    const turn = (
      profile: [number, number][],
      material: THREE.Material,
      parent: THREE.Object3D = collar,
    ) => {
      const geometry = new THREE.LatheGeometry(
        [...profile]
          .reverse()
          .map(([radius, depth]) => new THREE.Vector2(radius, depth)),
        160,
      );
      geometries.add(geometry);
      const mesh = new THREE.Mesh(geometry, material);
      mesh.rotation.x = Math.PI / 2;
      parent.add(mesh);
      return mesh;
    };

    turn(
      [
        [0.597, -0.042],
        [0.597, 0.005],
        [0.603, 0.026],
        [0.626, 0.053],
        [0.655, 0.063],
        [0.66, 0.048],
        [0.645, -0.04],
        [0.597, -0.042],
      ],
      graphite,
    );
    turn(
      [
        [0.639, -0.043],
        [0.638, 0.031],
        [0.649, 0.067],
        [0.666, 0.079],
        [0.824, 0.079],
        [0.853, 0.058],
        [0.868, 0.029],
        [0.868, -0.03],
        [0.852, -0.046],
        [0.639, -0.043],
      ],
      silver,
    );
    turn(
      [
        [0.855, -0.051],
        [0.864, 0.034],
        [0.879, 0.052],
        [0.891, 0.047],
        [0.901, 0.028],
        [0.901, -0.036],
        [0.89, -0.051],
        [0.855, -0.051],
      ],
      polished,
    );

    const trace = (radius: number, thickness: number, depth: number) => {
      const geometry = new THREE.TorusGeometry(radius, thickness, 6, 160);
      geometries.add(geometry);
      const mesh = new THREE.Mesh(geometry, polished);
      mesh.position.z = depth;
      collar.add(mesh);
    };
    trace(0.608, 0.004, 0.035);
    trace(0.66, 0.002, 0.08);
    trace(0.703, 0.0014, 0.081);
    trace(0.827, 0.002, 0.08);
    trace(0.862, 0.003, 0.042);
    trace(0.89, 0.002, 0.051);

    const annulus = (
      inner: number,
      outer: number,
      material: THREE.Material,
      depth: number,
      start = 0,
      sweep = Math.PI * 2,
      parent: THREE.Object3D = collar,
    ) => {
      const geometry = new THREE.RingGeometry(
        inner,
        outer,
        Math.max(8, Math.ceil((160 * sweep) / (Math.PI * 2))),
        1,
        start,
        sweep,
      );
      geometries.add(geometry);
      const mesh = new THREE.Mesh(geometry, material);
      mesh.position.z = depth;
      parent.add(mesh);
    };

    annulus(0.674, 0.695, graphite, 0.08);
    annulus(0.837, 0.84, engraving, 0.07);
    for (let index = 0; index < 6; index++) {
      const radius = 0.714 + index * 0.004;
      annulus(radius, radius + 0.00065, engraving, 0.0802);
    }

    const movingSignal = new THREE.Group();
    collar.add(movingSignal);
    for (let index = 0; index < 3; index++) {
      annulus(
        0.678,
        0.691,
        signal,
        0.081,
        (index * Math.PI * 2) / 3,
        Math.PI * 0.39,
        movingSignal,
      );
    }

    const calibrations = new THREE.Group();
    collar.add(calibrations);
    const tickGeometry = new THREE.BoxGeometry(0.002, 0.013, 0.001);
    geometries.add(tickGeometry);
    const ticks = new THREE.InstancedMesh(tickGeometry, engraving, 120);
    const tick = new THREE.Object3D();
    for (let index = 0; index < 120; index++) {
      const angle = (index * Math.PI * 2) / 120;
      const major = index % 10 === 0;
      const length = major ? 2.25 : index % 5 === 0 ? 1.6 : 1;
      const radius = major ? 0.796 : 0.803;
      tick.position.set(
        Math.sin(angle) * radius,
        Math.cos(angle) * radius,
        0.081,
      );
      tick.rotation.z = -angle;
      tick.scale.set(major ? 1.5 : 1, length, 1);
      tick.updateMatrix();
      ticks.setMatrixAt(index, tick.matrix);
    }
    calibrations.add(ticks);
    for (let index = 0; index < 4; index++) {
      annulus(
        0.875,
        0.885,
        sapphire,
        0.055,
        index * Math.PI * 0.5 + 0.7,
        0.027,
      );
    }

    const keyLight = new THREE.DirectionalLight(0xffffff, 2.2);
    keyLight.position.set(-3, 4, 5);
    const fillLight = new THREE.DirectionalLight(0xccded9, 0.8);
    fillLight.position.set(3, -2, 4);
    scene.add(keyLight, fillLight, new THREE.AmbientLight(0xffffff, 0.4));

    let currentStatus = initial.current.status;
    let isPaused = initial.current.paused;
    let visible = true;
    let lost = false;
    let disposed = false;
    let frame = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let elapsed = 0;
    let lastPaint = 0;
    let hasSize = false;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const nextColor = new THREE.Color(SIGNALS[currentStatus].color);

    const stop = () => {
      window.cancelAnimationFrame(frame);
      clearTimeout(timer);
      frame = 0;
      timer = undefined;
      lastPaint = 0;
    };
    const canPaint = () =>
      !disposed && !lost && visible && !document.hidden && hasSize;
    const canAnimate = () =>
      canPaint() && !isPaused && !reducedMotion.matches && currentStatus !== "error";

    const paint = (time: number) => {
      frame = 0;
      if (!canPaint()) return;
      const delta = lastPaint ? Math.min((time - lastPaint) / 1000, 0.06) : 0;
      lastPaint = time;
      if (canAnimate()) {
        elapsed += delta;
        movingSignal.rotation.z += delta * SIGNALS[currentStatus].speed;
        calibrations.rotation.z = -elapsed * 0.018;
        collar.rotation.x = Math.sin(elapsed * 0.24) * 0.012;
        collar.rotation.y = Math.cos(elapsed * 0.2) * 0.012;
      }
      renderer.render(scene, camera);
      element.dataset.ready = "true";
      if (canAnimate()) {
        timer = setTimeout(() => {
          timer = undefined;
          if (canAnimate()) frame = requestAnimationFrame(paint);
        }, Math.max(0, 1000 / 30 - (performance.now() - time)));
      }
    };

    const refresh = () => {
      stop();
      if (canPaint()) frame = requestAnimationFrame(paint);
    };
    const sync = (nextStatus: IdentityLensStatus, nextPaused: boolean) => {
      currentStatus = nextStatus;
      isPaused = nextPaused;
      nextColor.setHex(SIGNALS[currentStatus].color);
      signal.color.copy(nextColor);
      signal.emissive.copy(nextColor);
      signal.emissiveIntensity = currentStatus === "success" ? 0.4 : 0.2;
      refresh();
    };
    update.current = sync;

    const resize = new ResizeObserver(() => {
      const { width, height } = element.getBoundingClientRect();
      hasSize = width > 0 && height > 0;
      if (!hasSize) {
        stop();
        return;
      }
      renderer.setSize(width, height, false);
      const aspect = width / height;
      camera.left = -Math.max(aspect, 1);
      camera.right = Math.max(aspect, 1);
      camera.top = Math.max(1 / aspect, 1);
      camera.bottom = -Math.max(1 / aspect, 1);
      camera.updateProjectionMatrix();
      refresh();
    });
    resize.observe(element);
    const visibility = new IntersectionObserver(([entry]) => {
      visible = entry.isIntersecting;
      refresh();
    });
    visibility.observe(element);
    document.addEventListener("visibilitychange", refresh);
    reducedMotion.addEventListener("change", refresh);

    const contextLost = (event: Event) => {
      event.preventDefault();
      lost = true;
      stop();
      delete element.dataset.ready;
      renderer.domElement.style.visibility = "hidden";
    };
    const contextRestored = () => {
      try {
        illuminate();
        lost = false;
        renderer.domElement.style.visibility = "visible";
        refresh();
      } catch {
        // Keep the transparent CSS fallback if this device cannot restore WebGL.
      }
    };
    renderer.domElement.addEventListener("webglcontextlost", contextLost);
    renderer.domElement.addEventListener("webglcontextrestored", contextRestored);

    return () => {
      disposed = true;
      update.current = null;
      stop();
      resize.disconnect();
      visibility.disconnect();
      document.removeEventListener("visibilitychange", refresh);
      reducedMotion.removeEventListener("change", refresh);
      renderer.domElement.removeEventListener("webglcontextlost", contextLost);
      renderer.domElement.removeEventListener("webglcontextrestored", contextRestored);
      geometries.forEach((geometry) => geometry.dispose());
      materials.forEach((material) => material.dispose());
      ticks.dispose();
      environment?.dispose();
      scene.clear();
      renderer.dispose();
      renderer.forceContextLoss();
      renderer.domElement.remove();
      delete element.dataset.ready;
    };
  }, []);

  useEffect(() => {
    update.current?.(status, paused);
  }, [status, paused]);

  return (
    <div
      ref={host}
      className="identity-lens"
      aria-hidden="true"
      style={{ position: "absolute", inset: 0, pointerEvents: "none" }}
    />
  );
}
