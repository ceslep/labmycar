/**
 * three-login.js — DNA Helix 3D Background for Login Page
 * Three.js scene with rotating double helix + floating particles
 */
(function() {
  'use strict';

  const canvas = document.getElementById('three-login-canvas');
  if (!canvas || typeof THREE === 'undefined') return;

  const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isDark = document.documentElement.classList.contains('dark');

  // ─── Scene Setup ────────────────────────────────────────
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(
    65,
    window.innerWidth / window.innerHeight,
    0.1,
    1000
  );
  camera.position.set(0, 1, 7);

  const renderer = new THREE.WebGLRenderer({
    canvas: canvas,
    alpha: true,
    antialias: true
  });
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

  // ─── DNA Helix Parameters ──────────────────────────────
  const HELIX_RADIUS = 1.8;
  const HELIX_HEIGHT = 14;
  const HELIX_TURNS = 3.5;
  const HELIX_SEGMENTS = 120;
  const RUNG_EVERY = 6;

  // Colors adapt to light/dark mode
  const COLOR_STRAND_1 = isDark ? 0x6366f1 : 0x4f46e5;
  const COLOR_STRAND_2 = isDark ? 0x22d3ee : 0x06b6d4;
  const COLOR_RUNG = isDark ? 0x818cf8 : 0xa5b4fc;
  const COLOR_PARTICLE = isDark ? 0x818cf8 : 0xa5b4fc;

  // ─── Build DNA Strands ─────────────────────────────────
  function buildHelixPoints(strandOffset) {
    const points = [];
    for (let i = 0; i <= HELIX_SEGMENTS; i++) {
      const t = (i / HELIX_SEGMENTS) * Math.PI * 2 * HELIX_TURNS + strandOffset;
      const y = (i / HELIX_SEGMENTS) * HELIX_HEIGHT - HELIX_HEIGHT / 2;
      const x = Math.cos(t) * HELIX_RADIUS;
      const z = Math.sin(t) * HELIX_RADIUS;
      points.push(new THREE.Vector3(x, y, z));
    }
    return points;
  }

  const strand1Points = buildHelixPoints(0);
  const strand2Points = buildHelixPoints(Math.PI);

  const curve1 = new THREE.CatmullRomCurve3(strand1Points);
  const curve2 = new THREE.CatmullRomCurve3(strand2Points);

  const tubeGeo1 = new THREE.TubeGeometry(curve1, 160, 0.07, 8, false);
  const tubeGeo2 = new THREE.TubeGeometry(curve2, 160, 0.07, 8, false);

  const tubeMat1 = new THREE.MeshBasicMaterial({
    color: COLOR_STRAND_1,
    transparent: true,
    opacity: 0.75
  });
  const tubeMat2 = new THREE.MeshBasicMaterial({
    color: COLOR_STRAND_2,
    transparent: true,
    opacity: 0.75
  });

  const helixGroup = new THREE.Group();
  helixGroup.add(new THREE.Mesh(tubeGeo1, tubeMat1));
  helixGroup.add(new THREE.Mesh(tubeGeo2, tubeMat2));

  // ─── Build Rungs (connecting bars) ────────────────────
  for (let i = 0; i <= HELIX_SEGMENTS; i += RUNG_EVERY) {
    const t = (i / HELIX_SEGMENTS) * Math.PI * 2 * HELIX_TURNS;
    const y = (i / HELIX_SEGMENTS) * HELIX_HEIGHT - HELIX_HEIGHT / 2;

    const p1 = new THREE.Vector3(
      Math.cos(t) * HELIX_RADIUS,
      y,
      Math.sin(t) * HELIX_RADIUS
    );
    const p2 = new THREE.Vector3(
      Math.cos(t + Math.PI) * HELIX_RADIUS,
      y,
      Math.sin(t + Math.PI) * HELIX_RADIUS
    );

    const mid = p1.clone().lerp(p2, 0.5);
    const length = p1.distanceTo(p2);

    const rungGeo = new THREE.CylinderGeometry(0.025, 0.025, length, 6);
    const rungMat = new THREE.MeshBasicMaterial({
      color: COLOR_RUNG,
      transparent: true,
      opacity: 0.35
    });
    const rung = new THREE.Mesh(rungGeo, rungMat);
    rung.position.copy(mid);
    rung.lookAt(p2);
    rung.rotateX(Math.PI / 2);
    helixGroup.add(rung);

    // Small sphere at each end of the rung
    const sphereGeo = new THREE.SphereGeometry(0.06, 8, 8);
    const sphereMat1 = new THREE.MeshBasicMaterial({
      color: COLOR_STRAND_1,
      transparent: true,
      opacity: 0.6
    });
    const sphereMat2 = new THREE.MeshBasicMaterial({
      color: COLOR_STRAND_2,
      transparent: true,
      opacity: 0.6
    });
    const s1 = new THREE.Mesh(sphereGeo, sphereMat1);
    s1.position.copy(p1);
    const s2 = new THREE.Mesh(sphereGeo, sphereMat2);
    s2.position.copy(p2);
    helixGroup.add(s1);
    helixGroup.add(s2);
  }

  scene.add(helixGroup);

  // ─── Floating Particles ────────────────────────────────
  const PARTICLE_COUNT = 250;
  const particlePositions = new Float32Array(PARTICLE_COUNT * 3);
  const particleSpeeds = [];

  for (let i = 0; i < PARTICLE_COUNT; i++) {
    particlePositions[i * 3]     = (Math.random() - 0.5) * 22;
    particlePositions[i * 3 + 1] = (Math.random() - 0.5) * 18;
    particlePositions[i * 3 + 2] = (Math.random() - 0.5) * 14;
    particleSpeeds.push({
      x: (Math.random() - 0.5) * 0.003,
      y: (Math.random() - 0.5) * 0.004,
      z: (Math.random() - 0.5) * 0.002
    });
  }

  const particleGeo = new THREE.BufferGeometry();
  particleGeo.setAttribute('position', new THREE.BufferAttribute(particlePositions, 3));

  const particleMat = new THREE.PointsMaterial({
    color: COLOR_PARTICLE,
    size: 0.06,
    transparent: true,
    opacity: 0.55,
    sizeAttenuation: true
  });

  const particles = new THREE.Points(particleGeo, particleMat);
  scene.add(particles);

  // ─── Ambient Glow Particles (larger, more transparent) ─
  const GLOW_COUNT = 30;
  const glowPositions = new Float32Array(GLOW_COUNT * 3);
  for (let i = 0; i < GLOW_COUNT; i++) {
    glowPositions[i * 3]     = (Math.random() - 0.5) * 16;
    glowPositions[i * 3 + 1] = (Math.random() - 0.5) * 12;
    glowPositions[i * 3 + 2] = (Math.random() - 0.5) * 10;
  }
  const glowGeo = new THREE.BufferGeometry();
  glowGeo.setAttribute('position', new THREE.BufferAttribute(glowPositions, 3));
  const glowMat = new THREE.PointsMaterial({
    color: 0x6366f1,
    size: 0.2,
    transparent: true,
    opacity: 0.15,
    sizeAttenuation: true
  });
  scene.add(new THREE.Points(glowGeo, glowMat));

  // ─── Mouse Tracking ────────────────────────────────────
  let mouseX = 0;
  let mouseY = 0;
  let targetRotX = 0;
  let targetRotZ = 0;

  document.addEventListener('mousemove', function(e) {
    mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
    mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
  });

  // ─── Animation Loop ────────────────────────────────────
  let animationId;
  const clock = new THREE.Clock();

  function animate() {
    animationId = requestAnimationFrame(animate);
    if (prefersReducedMotion) {
      renderer.render(scene, camera);
      return;
    }

    const elapsed = clock.getElapsedTime();

    // Rotate helix slowly
    helixGroup.rotation.y = elapsed * 0.15;

    // Mouse-driven parallax (smooth)
    targetRotX = mouseY * 0.12;
    targetRotZ = mouseX * 0.06;
    helixGroup.rotation.x += (targetRotX - helixGroup.rotation.x) * 0.02;
    scene.rotation.z += (targetRotZ - scene.rotation.z) * 0.015;

    // Floating particles drift
    const pPos = particleGeo.attributes.position.array;
    for (let i = 0; i < PARTICLE_COUNT; i++) {
      pPos[i * 3]     += particleSpeeds[i].x + Math.sin(elapsed * 0.3 + i) * 0.0005;
      pPos[i * 3 + 1] += particleSpeeds[i].y + Math.cos(elapsed * 0.2 + i * 0.7) * 0.0005;
      pPos[i * 3 + 2] += particleSpeeds[i].z;

      // Wrap around boundaries
      if (Math.abs(pPos[i * 3]) > 11) pPos[i * 3] *= -0.9;
      if (Math.abs(pPos[i * 3 + 1]) > 9) pPos[i * 3 + 1] *= -0.9;
      if (Math.abs(pPos[i * 3 + 2]) > 7) pPos[i * 3 + 2] *= -0.9;
    }
    particleGeo.attributes.position.needsUpdate = true;

    renderer.render(scene, camera);
  }

  animate();

  // ─── Resize Handler ────────────────────────────────────
  window.addEventListener('resize', function() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });

  // ─── Cleanup ───────────────────────────────────────────
  window.threeLoginDestroy = function() {
    cancelAnimationFrame(animationId);
    renderer.dispose();
    tubeGeo1.dispose();
    tubeGeo2.dispose();
    tubeMat1.dispose();
    tubeMat2.dispose();
    particleGeo.dispose();
    particleMat.dispose();
  };

})();
