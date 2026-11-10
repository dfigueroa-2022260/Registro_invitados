import { BASE, api } from './api.js';
import { dentroDe, h } from './dom.js';
import { crearMapa } from './mapa.js';

const CLAVE = 'opciones-usuario-v2'; // v2: el resaltado de mi asiento ahora viene activado
const PREDETERMINADAS = { ver: false, resaltar: true, zoom: 1, oscuro: false };
const ACTUALIZAR_CADA_MS = 20_000;

const $ = (selector) => document.querySelector(selector);
const raiz = $('#raiz');
const contenedor = $('#contenedor');
const hostMapa = $('#mapa');
const errorMapa = $('#error-mapa');

// ----- Estado -----
let invitado = JSON.parse($('#datos-inicial').textContent).invitado; // { nombres, empresaNombre, asiento }
let mapa = null; // { letras, asientos }
let opciones = cargarOpciones();
let panel = null; // 'opciones' | 'buscar' | null
let encontrado = null;
let consulta = '';
let resultados = [];

function cargarOpciones() {
  try {
    return { ...PREDETERMINADAS, ...JSON.parse(localStorage.getItem(CLAVE) ?? '{}') };
  } catch {
    return { ...PREDETERMINADAS };
  }
}

const controlMapa = crearMapa(hostMapa);

// ----- Pintado -----
function pintarCabecera() {
  $('#d-etiqueta').textContent = invitado.asiento.etiqueta;
  $('#d-lado').textContent = invitado.asiento.zona ?? '-';
  $('#d-nombre').textContent = invitado.nombres;
  $('#d-nombre').title = invitado.nombres;
  $('#d-usuario').textContent = invitado.nombres;
}

function pintarMapa() {
  if (!mapa) return;
  errorMapa.hidden = true;
  hostMapa.hidden = false;
  hostMapa.style.width = `${opciones.zoom * 100}%`;
  hostMapa.style.maxWidth = opciones.zoom > 1 ? 'none' : '';
  controlMapa.render({
    letras: mapa.letras,
    grupos: mapa.grupos,
    asientos: mapa.asientos.map((a) => ({
      lado: a.lado,
      numero: a.numero,
      id: a.id,
      estado: opciones.ver ? a.estado : undefined,
    })),
    marcado: invitado.asiento,
    seleccionadoId: encontrado?.id ?? null,
    resaltar: opciones.resaltar,
    puntosPendientes: opciones.ver,
  });
}

function pintarLeyenda() {
  const leyenda = $('#leyenda');
  leyenda.hidden = !(opciones.ver && mapa);
  if (leyenda.hidden) return;
  const cuenta = (e) => mapa.asientos.filter((a) => a.estado === e).length;
  $('#l-llegaron').textContent = `Ya llegaron (${cuenta('asistio')})`;
  $('#l-pendientes').textContent = `Aún no llegan (${cuenta('asignado')})`;
  $('#l-libres').textContent = `Sin asignar (${cuenta('libre')})`;
}

function pintarOpciones() {
  raiz.classList.toggle('oscuro', opciones.oscuro);
  contenedor.classList.toggle('con-zoom', opciones.zoom > 1);
  $('#o-ver').setAttribute('aria-checked', String(opciones.ver));
  $('#o-resaltar').setAttribute('aria-checked', String(opciones.resaltar));
  $('#o-oscuro').setAttribute('aria-checked', String(opciones.oscuro));
  $('#ojo-abierto').style.display = opciones.ver ? '' : 'none';
  $('#ojo-cerrado').style.display = opciones.ver ? 'none' : '';
  for (const boton of document.querySelectorAll('[data-zoom]')) {
    boton.setAttribute('aria-checked', String(Number(boton.dataset.zoom) === opciones.zoom));
  }
}

function pintarPaneles() {
  $('#p-opciones').hidden = panel !== 'opciones';
  $('#p-buscar').hidden = panel !== 'buscar';
  $('#b-opciones').setAttribute('aria-expanded', String(panel === 'opciones'));
  $('#b-buscar').setAttribute('aria-expanded', String(panel === 'buscar'));
}

function pintarEncontrado() {
  const caja = $('#encontrado');
  caja.hidden = !encontrado;
  if (!encontrado) return;
  caja.replaceChildren(
    'Mostrando: ',
    h('strong', {}, encontrado.nombres),
    ` · ${encontrado.etiqueta} · ${encontrado.empresa} `,
    h('button', { type: 'button', class: 'enlace', onclick: () => { encontrado = null; pintarEncontrado(); pintarMapa(); } }, 'Quitar'),
  );
}

function pintarResultados() {
  const lista = $('#resultados');
  const ayuda = $('#ayuda');
  lista.hidden = resultados.length === 0;
  ayuda.hidden = resultados.length > 0;
  if (resultados.length) {
    lista.replaceChildren(
      ...resultados.map((r) =>
        h(
          'li',
          {},
          h(
            'button',
            { type: 'button', onclick: () => elegirResultado(r) },
            h('strong', {}, r.etiqueta),
            h('span', {}, r.nombres),
            h('small', {}, r.suplente ? `${r.empresa} · Suplente: ${r.suplente}` : r.empresa),
            h('em', { class: r.firmado ? 'llego' : null }, r.firmado ? 'Ya llegó' : 'Aún no llega'),
          ),
        ),
      ),
    );
  } else {
    const q = consulta.trim();
    ayuda.textContent = q.length >= 2 ? `Sin resultados para «${q}».` : 'Escribe al menos 2 letras del nombre, la empresa o el asiento.';
  }
}

// ----- Datos -----
async function actualizarMiRegistro() {
  try {
    const r = await api('GET', '/consulta/yo');
    const igual =
      invitado.nombres === r.nombres &&
      invitado.empresaNombre === r.empresaNombre &&
      JSON.stringify(invitado.asiento) === JSON.stringify(r.asiento);
    if (!igual) {
      invitado = { nombres: r.nombres, empresaNombre: r.empresaNombre, asiento: r.asiento };
      pintarCabecera();
      pintarMapa();
    }
  } catch (e) {
    // Si el administrador eliminó su asiento, ya no existe en la lista: vuelve al registro.
    if (e.status === 404) await salir();
  }
}

async function cargarMapa() {
  actualizarMiRegistro();
  try {
    mapa = await api('GET', '/consulta/mapa');
    pintarMapa();
    pintarLeyenda();
  } catch (e) {
    if (e.status !== 401 && !mapa) {
      errorMapa.textContent = 'No se pudo cargar el mapa de la sala. Intenta de nuevo.';
      errorMapa.hidden = false;
    }
  }
}

async function salir() {
  try {
    await api('POST', '/salir');
  } finally {
    location.assign(`${BASE}/`);
  }
}

// ----- Acciones -----
function enfocar(selector) {
  setTimeout(() => {
    const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    contenedor.querySelector(selector)?.scrollIntoView({ block: 'center', inline: 'center', behavior: reducido ? 'auto' : 'smooth' });
  }, 80);
}

const irAMiAsiento = () => enfocar('.celda.mio');

function cambiar(parcial) {
  opciones = { ...opciones, ...parcial };
  try {
    localStorage.setItem(CLAVE, JSON.stringify(opciones));
  } catch {
    /* sin almacenamiento: las opciones solo duran esta visita */
  }
  pintarOpciones();
  pintarMapa();
  pintarLeyenda();
  if (parcial.ver) cargarMapa();
  if (parcial.zoom || parcial.resaltar) irAMiAsiento();
}

function alternarPanel(cual) {
  panel = panel === cual ? null : cual;
  pintarPaneles();
  if (panel === 'buscar') setTimeout(() => $('#busqueda').focus());
}

function elegirResultado(r) {
  encontrado = r;
  panel = null;
  consulta = '';
  resultados = [];
  $('#busqueda').value = '';
  pintarPaneles();
  pintarResultados();
  pintarEncontrado();
  pintarMapa();
  enfocar(`.celda[data-id="${r.id}"]`);
}

// ----- Eventos -----
$('#salir').addEventListener('click', salir);
$('#b-opciones').addEventListener('click', () => alternarPanel('opciones'));
$('#b-buscar').addEventListener('click', () => alternarPanel('buscar'));
$('#o-ver').addEventListener('click', () => cambiar({ ver: !opciones.ver }));
$('#o-resaltar').addEventListener('click', () => cambiar({ resaltar: !opciones.resaltar }));
$('#o-oscuro').addEventListener('click', () => cambiar({ oscuro: !opciones.oscuro }));
$('#o-ir').addEventListener('click', () => { irAMiAsiento(); panel = null; pintarPaneles(); });
$('#o-restablecer').addEventListener('click', () => cambiar({ ...PREDETERMINADAS }));
for (const boton of document.querySelectorAll('[data-zoom]')) {
  boton.addEventListener('click', () => cambiar({ zoom: Number(boton.dataset.zoom) }));
}

let temporizadorBusqueda;
let secuencia = 0;
$('#busqueda').addEventListener('input', (e) => {
  consulta = e.target.value;
  pintarResultados();
  clearTimeout(temporizadorBusqueda);
  temporizadorBusqueda = setTimeout(async () => {
    const q = consulta.trim();
    const mia = ++secuencia; // descarta respuestas viejas
    if (q.length < 2) {
      resultados = [];
      return pintarResultados();
    }
    try {
      const datos = await api('GET', `/consulta/buscar?q=${encodeURIComponent(q)}`);
      if (mia === secuencia) { resultados = datos; pintarResultados(); }
    } catch {
      if (mia === secuencia) { resultados = []; pintarResultados(); }
    }
  }, 250);
});

document.addEventListener('click', (e) => {
  if (panel && !dentroDe(e, '.pop-wrap')) { panel = null; pintarPaneles(); }
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && panel) { panel = null; pintarPaneles(); }
});

// ----- Inicio -----
pintarCabecera();
pintarOpciones();
pintarPaneles();
pintarEncontrado();
pintarResultados();
cargarMapa();
// El mapa y el asiento se actualizan solos: así se ve quién va llegando y,
// si el administrador cambia o elimina asientos, la casita sigue en el lugar correcto.
setInterval(cargarMapa, ACTUALIZAR_CADA_MS);
