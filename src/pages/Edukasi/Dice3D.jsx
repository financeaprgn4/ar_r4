import React, { useRef, useEffect, useMemo } from 'react';
import { Canvas, useFrame } from '@react-three/fiber';
import { OrbitControls, RoundedBox } from '@react-three/drei';
import * as THREE from 'three';
import { Edges } from '@react-three/drei';

// Membuat tekstur titik dadu
const createDotTexture = (dots) => {
  const size = 128;
  const dotSize = 12;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, size, size);
  ctx.fillStyle = '#000000';

  const getPosition = (x, y) => {
    const offset = size / 4;
    return [size / 2 + x * offset, size / 2 + y * offset];
  };

  const dotMap = {
    1: [[0, 0]],
    2: [[-1, -1], [1, 1]],
    3: [[-1, -1], [0, 0], [1, 1]],
    4: [[-1, -1], [-1, 1], [1, -1], [1, 1]],
    5: [[-1, -1], [-1, 1], [0, 0], [1, -1], [1, 1]],
    6: [[-1, -1], [-1, 0], [-1, 1], [1, -1], [1, 0], [1, 1]],
  };

  dotMap[dots].forEach(([gx, gy]) => {
    const [x, y] = getPosition(gx, gy);
    ctx.beginPath();
    ctx.arc(x, y, dotSize, 0, Math.PI * 2);
    ctx.fill();
  });

  return new THREE.CanvasTexture(canvas);
};

// Komponen Dadu
const Dice = ({ rolling, result, size = 1.5 }) => {
  const meshRef = useRef();
  const rotationSpeed = useRef(0.3);
  const stopTimeout = useRef(null);

  const faceTextures = useMemo(() => {
    const tex = {};
    for (let i = 1; i <= 6; i++) {
      tex[i] = createDotTexture(i);
    }
    return tex;
  }, []);

  const materials = useMemo(() => {
    return [
      new THREE.MeshStandardMaterial({ map: faceTextures[6] }), // right (+X)
      new THREE.MeshStandardMaterial({ map: faceTextures[5] }), // left (-X)
      new THREE.MeshStandardMaterial({ map: faceTextures[1] }), // top (+Y)
      new THREE.MeshStandardMaterial({ map: faceTextures[2] }), // bottom (-Y)
      new THREE.MeshStandardMaterial({ map: faceTextures[3] }), // front (+Z)
      new THREE.MeshStandardMaterial({ map: faceTextures[4] })  // back (-Z)
    ];
  }, [faceTextures]);

  const getRotationForResult = (val) => {
    const map = {
      1: [0, 0, 0],                        // 1 di atas
      2: [Math.PI, 0, 0],                  // 2 di atas (rotasi 180 derajat X)
      3: [-Math.PI / 2, 0, 0],             // 3 di atas (rotasi -90 derajat X)
      4: [Math.PI / 2, 0, 0],              // 4 di atas (rotasi 90 derajat X)
      5: [0, 0, -Math.PI / 2],             // 5 di atas (rotasi -90 derajat Z)
      6: [0, 0, Math.PI / 2],              // 6 di atas (rotasi 90 derajat Z)
    };
    return map[val] || [0, 0, 0];
  };

  // Animasi rotasi saat rolling
  useFrame(() => {
    if (rolling && meshRef.current) {
      meshRef.current.rotation.x += 0.3;
      meshRef.current.rotation.y += 0.3;
    }
  });

  // Atur rotasi ke sisi hasil saat rolling selesai
  useEffect(() => {
    if (!rolling && result && meshRef.current) {
      stopTimeout.current = setTimeout(() => {
        const [x, y, z] = getRotationForResult(result);
        meshRef.current.rotation.set(x, y, z);
      }, 100); // jeda kecil untuk natural
    }
    return () => clearTimeout(stopTimeout.current);
  }, [rolling, result]);

  return (
    <mesh ref={meshRef}>
        <boxGeometry args={[size, size, size]} />
        {materials.map((mat, i) => (
            <primitive key={i} attach={`material-${i}`} object={mat} />
        ))}
        
        {/* Tambahkan garis di sudut */}
        <Edges scale={1.01} threshold={15} color="white" />
    </mesh>
  );
};

// Komponen Dice3D utama
const Dice3D = ({ isRolling, result, size = 2, onHide }) => {
  const containerRef = useRef();

  useEffect(() => {
    const handleClickOutside = (e) => {
        if (containerRef.current && !containerRef.current.contains(e.target)) {
        onHide();
        }
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, [onHide]);

  return (
    <div
      ref={containerRef}
      style={{
        width: size * 150,
        height: size * 150,
        position: 'absolute',
        top: 20,
        left: '50%',
        transform: 'translateX(-50%)',
        zIndex: 999,
      }}
    >
      <Canvas camera={{ position: [3, 3, 3], fov: 45 }}>
        <ambientLight intensity={0.8} />
        <directionalLight position={[5, 5, 5]} />
        <OrbitControls enableZoom={false} enableRotate={false} />
        <Dice rolling={isRolling} result={result} size={size} />
      </Canvas>
    </div>
  );
};

export default Dice3D;
