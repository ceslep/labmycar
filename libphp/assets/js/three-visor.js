/**
 * three-visor.js — Connected Molecular Network Background for Visor Area
 * Three.js scene with floating nodes connected by lines, reacting to mouse
 */
(function() {
  'use strict';

  const canvas = document.getElementById('three-visor-canvas');
  if (!canvas || typeof THREE === 'undefined') return;

  const container = canvas.parentElement;
  if (!container) return;

  const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isDark = document.documentElement.classList.contains('dark');

  // ─── Scene Setup ────────────────────────────────────────
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(
    55,
    container.clientWidth / container.clientHeight,
    0.1,
    200
  );
  camera.position.set(0, 0, 10);

  const renderer = new THREE.WebGLRenderer({
    canvas: canvas,
    alpha: true,
    antialias: true
  });
  renderer.setSize(container.clientWidth, container.clientHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

  // ─── Molecular Nodes ───────────────────────────────────
  const NODE_COUNT = prefersReducedMotion ? 25 : 55;
  const CONNECT_DISTANCE = 4.0;
  const BOUNDS = { x: 9, y: 6, z: 5 };

  const nodes = [];
  const nodeGroup = new THREE.Group();

  const nodeGeo = new THREE.SphereGeometry(0.1, 16, 16);
  const nodeColors = isDark
    ? [0x4f46e5, 0x06b6d4, 0x818cf8, 0x22d3ee, 0x6366f1]
    : [0x4f46e5, 0x06b6d4, 0x818cf8, 0x22d3ee, 0xa5b4fc];

  for (let i = 0; i < NODE_COUNT; i++) {
    const colorIdx = Math.floor(Math.random() * nodeColors.length);
    const mat = new THREE.MeshBasicMaterial({
      color: nodeColors[colorIdx],
      transparent: true,
      opacity: 0.55 + Math.random() * 0.25
    });

    const mesh = new THREE.Mesh(nodeGeo, mat);
    mesh.position.set(
      (Math.random() - 0.5) * BOUNDS.x * 2,
      (Math.random() - 0.5) * BOUNDS.y * 2,
      (Math.random() - 0.5) * BOUNDS.z * 2
    );

    // Each node has its own velocity
    mesh.userData = {
      velocity: new THREE.Vector3(
        (Math.random() - 0.5) * 0.008,
        (Math.random() - 0.5) * 0.008,
        (Math.random() - 0.5) * 0.004
      ),
      baseOpacity: mat.opacity,
      pulsePhase: Math.random() * Math.PI * 2
    };

    nodeGroup.add(mesh);
    nodes.push(mesh);
  }

  scene.add(nodeGroup);

  // ─── Connection Lines ──────────────────────────────────
  const lineMaterial = new THREE.LineBasicMaterial({
    color: isDark ? 0x818cf8 : 0xc7d2fe,
    transparent: true,
    opacity: isDark ? 0.12 : 0.18
  });

  let linesMeshes = [];

  function updateConnections() {
    // Remove old lines
    for (let i = linesMeshes.length - 1; i >= 0; i--) {
      scene.remove(linesMeshes[i]);
      linesMeshes[i].geometry.dispose();
    }
    linesMeshes = [];

    if (prefersReducedMotion) return;

    // Draw new connections
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dist = nodes[i].position.distanceTo(nodes[j].position);
        if (dist < CONNECT_DISTANCE) {
          const points = [nodes[i].position.clone(), nodes[j].position.clone()];
          const geo = new THREE.BufferGeometry().setFromPoints(points);
          const opacity = (isDark ? 0.12 : 0.18) * (1 - dist / CONNECT_DISTANCE);
          const mat = lineMaterial.clone();
          mat.opacity = opacity;
          const line = new THREE.Line(geo, mat);
          scene.add(line);
          linesMeshes.push(line);
        }
      }
    }
  }

  // ─── Ambient Particles ─────────────────────────────────
  const AMBIENT_COUNT = 120;
  const ambientPositions = new Float32Array(AMBIENT_COUNT * 3);
  const ambientSpeeds = [];

  for (let i = 0; i < AMBIENT_COUNT; i++) {
    ambientPositions[i * 3]     = (Math.random() - 0.5) * 24;
    ambientPositions[i * 3 + 1] = (Math.random() - 0.5) * 16;
    ambientPositions[i * 3 + 2] = (Math.random() - 0.5) * 12;
    ambientSpeeds.push({
      x: (Math.random() - 0.5) * 0.002,
      y: (Math.random() - 0.5) * 0.003,
      z: (Math.random() - 0.5) * 0.001
    });
  }

  const ambientGeo = new THREE.BufferGeometry();
  ambientGeo.setAttribute('position', new THREE.BufferAttribute(ambientPositions, 3));

  const ambientMat = new THREE.PointsMaterial({
    color: isDark ? 0x06b6d4 : 0x4f46e5,
    size: 0.04,
    transparent: true,
    opacity: isDark ? 0.35 : 0.25,
    sizeAttenuation: true
  });

  const ambientParticles = new THREE.Points(ambientGeo, ambientMat);
  scene.add(ambientParticles);

  // ─── Mouse Tracking ────────────────────────────────────
  let mouseX = 0;
  let mouseY = 0;

  container.addEventListener('mousemove', function(e) {
    const rect = container.getBoundingClientRect();
    mouseX = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
    mouseY = ((e.clientY - rect.top) / rect.height - 0.5) * 2;
  });

  // ─── Animation Loop ────────────────────────────────────
  let animationId;
  const clock = new THREE.Clock();
  let frameCount = 0;

  function animate() {
    animationId = requestAnimationFrame(animate);
    const elapsed = clock.getElapsedTime();
    frameCount++;

    if (prefersReducedMotion) {
      if (frameCount === 1) updateConnections();
      renderer.render(scene, camera);
      return;
    }

    // Update node positions
    for (let i = 0; i < NODE_COUNT; i++) {
      const node = nodes[i];
      const ud = node.userData;

      // Apply velocity
      node.position.add(ud.velocity);

      // Bounce off boundaries
      ['x', 'y', 'z'].forEach(function(axis) {
        const bound = BOUNDS[axis];
        if (Math.abs(node.position[axis]) > bound) {
          ud.velocity[axis] *= -1;
          node.position[axis] = Math.sign(node.position[axis]) * bound;
        }
      });

      // Gentle drift toward mouse position
      node.position.x += (mouseX * 3 - node.position.x) * 0.0004;
      node.position.y += (-mouseY * 2 - node.position.y) * 0.0004;

      // Pulse opacity
      ud.pulsePhase += 0.02;
      node.material.opacity = ud.baseOpacity + Math.sin(ud.pulsePhase) * 0.1;
    }

    // Update connections every 3 frames for performance
    if (frameCount % 3 === 0) {
      updateConnections();
    }

    // Ambient particle drift
    const aPos = ambientGeo.attributes.position.array;
    for (let i = 0; i < AMBIENT_COUNT; i++) {
      aPos[i * 3]     += ambientSpeeds[i].x + Math.sin(elapsed * 0.2 + i * 0.5) * 0.0003;
      aPos[i * 3 + 1] += ambientSpeeds[i].y + Math.cos(elapsed * 0.15 + i * 0.3) * 0.0003;
      aPos[i * 3 + 2] += ambientSpeeds[i].z;

      // Wrap around
      if (Math.abs(aPos[i * 3]) > 12) aPos[i * 3] *= -0.95;
      if (Math.abs(aPos[i * 3 + 1]) > 8) aPos[i * 3 + 1] *= -0.95;
      if (Math.abs(aPos[i * 3 + 2]) > 6) aPos[i * 3 + 2] *= -0.95;
    }
    ambientGeo.attributes.position.needsUpdate = true;

    renderer.render(scene, camera);
  }

  animate();

  // ─── Resize Handler ────────────────────────────────────
  window.addEventListener('resize', function() {
    if (!container) return;
    const w = container.clientWidth;
    const h = container.clientHeight;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
  });

  // ─── Cleanup ───────────────────────────────────────────
  window.threeVisorDestroy = function() {
    cancelAnimationFrame(animationId);
    renderer.dispose();
    nodeGeo.dispose();
    ambientGeo.dispose();
    ambientMat.dispose();
    lineMaterial.dispose();
    for (let i = linesMeshes.length - 1; i >= 0; i--) {
      linesMeshes[i].geometry.dispose();
      linesMeshes[i].material.dispose();
    }
  };

})();
