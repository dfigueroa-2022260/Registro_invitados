import { BASE, api } from './api.js';

const $ = (selector) => document.querySelector(selector);
const lienzo = $('#lienzo');
const marco = $('#marco');
const botonAceptar = $('#aceptar');
const aviso = $('#aviso');

let ctx;
let dibujando = false;
let tieneFirma = false;
let enviando = false;
let ultimo = { x: 0, y: 0 };
let medio = { x: 0, y: 0 };

function preparar() {
  const escala = window.devicePixelRatio || 1;
  const { width, height } = lienzo.getBoundingClientRect();
  lienzo.width = Math.round(width * escala);
  lienzo.height = Math.round(height * escala);
  ctx = lienzo.getContext('2d');
  ctx.scale(escala, escala);
  ctx.lineWidth = 2.5;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.strokeStyle = '#14213d';
  ctx.fillStyle = '#14213d';
}

function actualizarEstado() {
  marco.classList.toggle('con-firma', tieneFirma);
  botonAceptar.disabled = !tieneFirma || enviando;
}

const punto = (e) => {
  const rect = lienzo.getBoundingClientRect();
  return { x: e.clientX - rect.left, y: e.clientY - rect.top };
};

/** Curva suave: cada tramo va del punto medio anterior al nuevo, sin dejar huecos. */
function trazar(actual) {
  const nuevoMedio = { x: (ultimo.x + actual.x) / 2, y: (ultimo.y + actual.y) / 2 };
  ctx.beginPath();
  ctx.moveTo(medio.x, medio.y);
  ctx.quadraticCurveTo(ultimo.x, ultimo.y, nuevoMedio.x, nuevoMedio.y);
  ctx.stroke();
  medio = nuevoMedio;
  ultimo = actual;
}

function iniciar(e) {
  e.preventDefault();
  lienzo.setPointerCapture(e.pointerId);
  dibujando = true;
  ultimo = punto(e);
  medio = ultimo;
  // Un toque sin movimiento deja un punto.
  ctx.beginPath();
  ctx.arc(ultimo.x, ultimo.y, ctx.lineWidth / 2, 0, Math.PI * 2);
  ctx.fill();
  tieneFirma = true;
  actualizarEstado();
}

function mover(e) {
  if (!dibujando) return;
  e.preventDefault();
  // Usa todos los puntos que capturó el navegador entre cuadros para un trazo continuo.
  const puntos = e.getCoalescedEvents?.() ?? [];
  for (const p of puntos.length ? puntos : [e]) trazar(punto(p));
}

function terminar() {
  if (!dibujando) return;
  dibujando = false;
  // Cierra el último tramo hasta el punto final.
  ctx.beginPath();
  ctx.moveTo(medio.x, medio.y);
  ctx.lineTo(ultimo.x, ultimo.y);
  ctx.stroke();
}

/** Exporta la firma sobre fondo blanco (el lienzo es transparente). */
function exportar() {
  const destino = document.createElement('canvas');
  destino.width = lienzo.width;
  destino.height = lienzo.height;
  const c = destino.getContext('2d');
  c.fillStyle = '#ffffff';
  c.fillRect(0, 0, destino.width, destino.height);
  c.drawImage(lienzo, 0, 0);
  return destino.toDataURL('image/png');
}

function mostrarError(texto) {
  aviso.textContent = texto ?? '';
  aviso.hidden = !texto;
}

lienzo.addEventListener('pointerdown', iniciar);
lienzo.addEventListener('pointermove', mover);
lienzo.addEventListener('pointerup', terminar);
lienzo.addEventListener('pointercancel', terminar);
lienzo.addEventListener('pointerleave', terminar);

// Cambiar el tamaño borra el lienzo; solo se reajusta si aún no hay firma.
window.addEventListener('resize', () => { if (!tieneFirma) preparar(); });

$('#borrar').addEventListener('click', () => {
  ctx.clearRect(0, 0, lienzo.width, lienzo.height);
  tieneFirma = false;
  mostrarError(null);
  actualizarEstado();
});

/** Vuelve al registro para corregir los datos. */
$('#regresar').addEventListener('click', async () => {
  try {
    await api('POST', '/salir');
  } finally {
    location.assign(`${BASE}/`);
  }
});

botonAceptar.addEventListener('click', async () => {
  if (!tieneFirma || enviando) return;
  enviando = true;
  botonAceptar.textContent = 'Guardando…';
  actualizarEstado();
  mostrarError(null);
  try {
    await api('POST', '/firmas', { firma: exportar() });
    location.assign(`${BASE}/asiento`);
  } catch (error) {
    enviando = false;
    botonAceptar.textContent = 'Aceptar';
    actualizarEstado();
    mostrarError(error.message || 'No se pudo guardar la firma. Intenta de nuevo.');
  }
});

preparar();
actualizarEstado();
