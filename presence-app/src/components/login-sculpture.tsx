"use client";

import { useEffect, useRef } from "react";
import * as THREE from "three";
import { RoomEnvironment } from "three/addons/environments/RoomEnvironment.js";

export function LoginSculpture({ paused }: { paused: boolean }) {
  const host = useRef<HTMLDivElement>(null);
  const pausedRef = useRef(paused);
  useEffect(() => {
    pausedRef.current = paused;
  }, [paused]);

  useEffect(() => {
    const element = host.current;
    if (!element) return;
    let renderer: THREE.WebGLRenderer;
    try {
      renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    } catch {
      return;
    }
    renderer.setPixelRatio(Math.min(devicePixelRatio, 1.75));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.3;
    renderer.setClearColor(0x000000, 0);
    element.appendChild(renderer.domElement);
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(33, 1, 0.1, 50);
    camera.position.set(0, 0, 9.8);
    const pmrem = new THREE.PMREMGenerator(renderer);
    const room = new RoomEnvironment();
    const environment = pmrem.fromScene(room, 0.04);
    scene.environment = environment.texture;
    room.dispose();
    pmrem.dispose();

    const sculpture = new THREE.Group();
    scene.add(sculpture);
    const metal = new THREE.MeshStandardMaterial({
      color: 0xc5d2cc,
      metalness: 1,
      roughness: 0.23,
    });
    const darkMetal = new THREE.MeshStandardMaterial({
      color: 0x273c32,
      metalness: 0.9,
      roughness: 0.26,
    });
    const enamel = new THREE.MeshPhysicalMaterial({
      color: 0x0f9d68,
      metalness: 0.5,
      roughness: 0.19,
      clearcoat: 1,
      clearcoatRoughness: 0.12,
    });
    const ringGeometry = new THREE.TorusGeometry(1.68, 0.19, 24, 120);
    const outer = new THREE.Mesh(ringGeometry, metal);
    outer.rotation.set(0.32, -0.36, -0.35);
    sculpture.add(outer);
    const innerGeometry = new THREE.TorusGeometry(1.42, 0.045, 12, 100);
    const inner = new THREE.Mesh(innerGeometry, darkMetal);
    inner.rotation.set(-0.27, 0.46, 0.1);
    inner.position.z = -0.15;
    sculpture.add(inner);
    const ribs = new THREE.Group();
    outer.add(ribs);
    const ribGeometry = new THREE.TorusGeometry(1.68, 0.014, 6, 120);
    for (let i = 0; i < 7; i++) {
      const rib = new THREE.Mesh(ribGeometry, metal);
      rib.position.z = (i - 3) * 0.045;
      rib.scale.setScalar(1.12);
      ribs.add(rib);
    }
    const shape = new THREE.Shape();
    shape.moveTo(-1.02, 0.02);
    shape.lineTo(-0.67, 0.36);
    shape.lineTo(-0.15, -0.18);
    shape.lineTo(0.91, 1.0);
    shape.lineTo(1.27, 0.65);
    shape.lineTo(-0.13, -0.91);
    shape.closePath();
    const checkGeometry = new THREE.ExtrudeGeometry(shape, {
      depth: 0.3,
      bevelEnabled: true,
      bevelThickness: 0.1,
      bevelSize: 0.1,
      bevelSegments: 5,
      steps: 1,
      curveSegments: 20,
    });
    checkGeometry.center();
    const check = new THREE.Mesh(checkGeometry, enamel);
    check.position.set(0.05, 0.02, 0.38);
    check.rotation.set(-0.13, -0.08, -0.08);
    sculpture.add(check);
    const light = new THREE.DirectionalLight(0xffffff, 3);
    light.position.set(-3, 5, 5);
    scene.add(light, new THREE.AmbientLight(0xffffff, 0.4));

    let width = 1;
    let height = 1;
    let visible = true;
    let lost = false;
    let frame = 0;
    let elapsed = 0;
    let last = 0;
    let pointerX = 0;
    let pointerY = 0;
    let dirty = true;
    const resize = new ResizeObserver(() => {
      ({ width, height } = element.getBoundingClientRect());
      renderer.setSize(width, height);
      camera.aspect = width / Math.max(height, 1);
      const fitDistance =
        2.15 /
        (Math.tan(THREE.MathUtils.degToRad(camera.fov / 2)) *
          Math.min(camera.aspect, 1));
      camera.position.z = Math.max(
        width < 760 && height < 300 ? 7.5 : 9.8,
        fitDistance,
      );
      camera.updateProjectionMatrix();
      dirty = true;
    });
    resize.observe(element);
    const observer = new IntersectionObserver((entries) => {
      visible = entries[0].isIntersecting;
      dirty = true;
    });
    observer.observe(element);
    const move = (event: PointerEvent) => {
      const rect = element.getBoundingClientRect();
      pointerX = ((event.clientX - rect.left) / width - 0.5) * 0.45;
      pointerY = ((event.clientY - rect.top) / height - 0.5) * 0.25;
    };
    const reset = () => {
      pointerX = 0;
      pointerY = 0;
    };
    element.addEventListener("pointermove", move);
    element.addEventListener("pointerleave", reset);
    const draw = (time: number) => {
      frame = requestAnimationFrame(draw);
      const delta = Math.min((time - last) / 1000, 0.05);
      last = time;
      if (!visible || document.hidden || lost) return;
      if (!pausedRef.current) {
        elapsed += delta;
        sculpture.rotation.y +=
          (-0.17 +
            Math.sin(elapsed * 0.45) * 0.2 +
            pointerX -
            sculpture.rotation.y) *
          0.06;
        sculpture.rotation.x +=
          (0.06 +
            Math.cos(elapsed * 0.35) * 0.1 +
            pointerY -
            sculpture.rotation.x) *
          0.06;
        sculpture.position.y = Math.sin(elapsed * 0.8) * 0.09;
        inner.rotation.z = elapsed * 0.08;
        dirty = true;
      }
      if (dirty) {
        renderer.render(scene, camera);
        element.dataset.ready = "true";
        dirty = false;
      }
    };
    frame = requestAnimationFrame(draw);
    const contextLost = (event: Event) => {
      event.preventDefault();
      lost = true;
      delete element.dataset.ready;
    };
    const contextRestored = () => {
      lost = false;
      dirty = true;
    };
    renderer.domElement.addEventListener("webglcontextlost", contextLost);
    renderer.domElement.addEventListener(
      "webglcontextrestored",
      contextRestored,
    );
    return () => {
      cancelAnimationFrame(frame);
      resize.disconnect();
      observer.disconnect();
      element.removeEventListener("pointermove", move);
      element.removeEventListener("pointerleave", reset);
      renderer.domElement.removeEventListener("webglcontextlost", contextLost);
      renderer.domElement.removeEventListener(
        "webglcontextrestored",
        contextRestored,
      );
      [ringGeometry, innerGeometry, ribGeometry, checkGeometry].forEach(
        (geometry) => geometry.dispose(),
      );
      [metal, darkMetal, enamel].forEach((material) => material.dispose());
      environment.dispose();
      renderer.dispose();
      renderer.domElement.remove();
      delete element.dataset.ready;
    };
  }, []);

  return <div ref={host} className="login-sculpture" aria-hidden="true" />;
}
