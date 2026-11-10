import { BASE, api, descargar } from './api.js';
import { dentroDe, h, normalizar } from './dom.js';
import { crearMapa } from './mapa.js';

const $ = (selector) => document.querySelector(selector);

// ----- Temas -----
const TEMAS = {
  claro: { '--fondo': '#ffffff', '--panel': '#f4f4f5', '--texto': '#1b1b1f', '--form': '#a9a7b6' },
  oscuro: { '--fondo': '#0f1115', '--panel': '#1b1e26', '--texto': '#eef0f4', '--form': '#3a3d4a' },
  gris: { '--fondo': '#2a2b2f', '--panel': '#3c3d42', '--texto': '#f0f0f2', '--form': '#5c5d66' },
};
const CLAVE_TEMA = 'admin-tema';
const CLAVE_HEX = 'admin-hex';
const HEX = /^#?([0-9a-f]{6}|[0-9a-f]{3})$/i;

function normalizarHex(valor) {
  const v = valor.trim();
  if (!HEX.test(v)) return null;
  let hex = v.replace('#', '');
  if (hex.length === 3) hex = [...hex].map((c) => c + c).join('');
  return `#${hex.toLowerCase()}`;
}

function esOscuro(hex) {
  const n = parseInt(hex.slice(1), 16);
  const [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 < 0.55;
}

function leer(clave) {
  try { return localStorage.getItem(clave); } catch { return null; }
}
function escribir(clave, valor) {
  try { localStorage.setItem(clave, valor); } catch { /* el tema solo dura esta sesión */ }
}

// ----- Estado -----
let sala = null; // { letras, asientos }
let seleccionadoId = null;
let guardando = false;
let tema = leer(CLAVE_TEMA) || 'claro';
let hexGuardado = normalizarHex(leer(CLAVE_HEX) ?? '') ?? '';
let hexTexto = hexGuardado;
let focoPrevio = null;
let temporizadorAviso;

const etiquetaDe = (a) => `${sala.letras[a.lado]}-${a.numero}`;
const seleccionado = () => sala?.asientos.find((a) => a.id === seleccionadoId) ?? null;

const controlMapa = crearMapa($('#mapa'), {
  onSeleccionar: (a) => seleccionar(a.id),
  onAgregar: (dato) => agregar(dato),
  onCambiarLetra: (cambio) => cambiarLetra(cambio),
});

const campos = {
  nombres: $('#f-nombres'),
  documento: $('#f-id'),
  empresa: $('#f-empresa'),
  categoria: $('#f-categoria'),
  suplenteNombres: $('#f-suplente'),
  suplenteDocumento: $('#f-suplente-id'),
};

function habilitarFormulario(si) {
  for (const campo of Object.values(campos)) campo.disabled = !si;
}

function llenarFormulario(a) {
  for (const [clave, campo] of Object.entries(campos)) campo.value = a?.[clave] ?? '';
}

// ----- Pintado -----
function mostrar(texto, error = false) {
  const toast = $('#toast');
  toast.textContent = texto;
  toast.classList.toggle('toast-error', error);
  toast.setAttribute('role', error ? 'alert' : 'status');
  toast.hidden = false;
  clearTimeout(temporizadorAviso);
  temporizadorAviso = setTimeout(() => { toast.hidden = true; }, 3200);
}

function pintarMapa() {
  if (!sala) return;
  $('#mapa').hidden = false;
  controlMapa.render({
    letras: sala.letras,
    grupos: sala.grupos,
    asientos: sala.asientos.map((a) => ({
      lado: a.lado,
      numero: a.numero,
      id: a.id,
      estado: a.firmado ? 'asistio' : a.nombres ? 'asignado' : 'libre',
    })),
    seleccionadoId,
    editable: true,
  });
  const con = sala.asientos.filter((a) => a.nombres);
  const leyenda = $('#leyenda');
  leyenda.hidden = false;
  leyenda.textContent = `${con.filter((a) => a.firmado).length} de ${con.length} invitados ya firmaron · Toca una silla para editarla · Toca + para volver a poner un asiento quitado · Toca la letra de una mesa para cambiarla`;

  const empresas = [...new Set(sala.asientos.map((a) => a.empresa).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
  $('#lista-empresas').replaceChildren(...empresas.map((e) => h('option', { value: e })));
}

function pintarPanel() {
  const s = seleccionado();
  $('#f-asiento').value = s ? etiquetaDe(s) : '';
  $('#b-eliminar').hidden = !s;
  $('#b-guardar').disabled = guardando || !s;
  $('#b-guardar-barra').disabled = guardando;
  const estado = $('#estado');
  estado.classList.toggle('ok', !!s?.firmado);
  if (!s) estado.textContent = 'Selecciona un asiento del mapa para editarlo.';
  else if (s.firmado) {
    const quien = s.firmaPor === 'suplente' ? ' (suplente)' : '';
    estado.textContent = `Asistió${quien} · firmó el ${fecha(s.fechaFirma)}`;
  }
  else if (s.nombres) estado.textContent = 'Aún no ha firmado';
  else estado.textContent = 'Asiento libre';
}

function fecha(iso) {
  return iso ? new Date(iso).toLocaleString('es-GT', { timeZone: 'America/Guatemala', dateStyle: 'short', timeStyle: 'short' }) : '';
}

function pintarTema() {
  const vars =
    tema === 'hex' && hexGuardado
      ? {
          '--fondo': `color-mix(in srgb, ${hexGuardado} 82%, ${esOscuro(hexGuardado) ? '#000' : '#fff'})`,
          '--panel': hexGuardado,
          '--texto': esOscuro(hexGuardado) ? '#f2f3f6' : '#1b1b1f',
          '--form': `color-mix(in srgb, ${hexGuardado} 55%, #8d8aa0)`,
        }
      : TEMAS[tema] ?? TEMAS.claro;
  for (const [clave, valor] of Object.entries(vars)) $('#admin').style.setProperty(clave, valor);
  for (const boton of document.querySelectorAll('[data-tema]')) boton.classList.toggle('activo', boton.dataset.tema === tema);
  $('#muestra').style.background = hexGuardado || '#777';
  $('#hex').classList.toggle('invalido', !!hexTexto && !normalizarHex(hexTexto));
}

function pintarBusqueda() {
  const q = normalizar($('#busqueda').value);
  const encontrados = q && sala
    ? sala.asientos.filter((a) => normalizar(`${etiquetaDe(a)} ${a.nombres} ${a.suplenteNombres} ${a.empresa} ${a.documento}`).includes(q)).slice(0, 8)
    : [];
  $('#sin-resultados').hidden = !(q && !encontrados.length);
  const lista = $('#resultados');
  lista.hidden = !encontrados.length;
  lista.replaceChildren(
    ...encontrados.map((a) =>
      h(
        'li',
        {},
        h(
          'button',
          { type: 'button', onclick: () => { seleccionar(a.id); alternarBusqueda(false); } },
          h('strong', {}, etiquetaDe(a)),
          h('span', {}, a.nombres || 'Libre'),
          h('small', {}, a.suplenteNombres ? `${a.empresa} · Suplente: ${a.suplenteNombres}` : a.empresa),
        ),
      ),
    ),
  );
}

// ----- Asientos -----
function seleccionar(id) {
  const asiento = sala?.asientos.find((a) => a.id === id);
  if (!asiento) return;
  seleccionadoId = asiento.id;
  habilitarFormulario(true);
  llenarFormulario(asiento);
  pintarMapa();
  pintarPanel();
}

async function agregar({ lado, numero }) {
  try {
    const nuevo = await api('POST', '/admin/asientos', { lado, numero });
    sala.asientos.push(nuevo);
    seleccionar(nuevo.id);
    mostrar(`Asiento ${etiquetaDe(nuevo)} agregado`);
  } catch (e) {
    mostrar(e.message || 'No se pudo agregar el asiento', true);
  }
}

async function guardar() {
  if (seleccionadoId == null) {
    mostrar('Selecciona un asiento del mapa para editarlo', true);
    return;
  }
  guardando = true;
  pintarPanel();
  try {
    const id = seleccionadoId;
    const actualizado = await api('PUT', `/admin/asientos/${id}`, {
      nombres: campos.nombres.value,
      documento: campos.documento.value,
      empresa: campos.empresa.value,
      categoria: campos.categoria.value,
      suplenteNombres: campos.suplenteNombres.value,
      suplenteDocumento: campos.suplenteDocumento.value,
    });
    sala.asientos = sala.asientos.map((a) => (a.id === id ? actualizado : a));
    llenarFormulario(actualizado);
    mostrar(`Asiento ${etiquetaDe(actualizado)} guardado`);
  } catch (e) {
    mostrar(e.message || 'No se pudo guardar', true);
  } finally {
    guardando = false;
    pintarMapa();
    pintarPanel();
  }
}

async function cambiarLetra({ lado, letra }) {
  try {
    const { letras } = await api('PUT', `/admin/lados/${lado}`, { letra });
    sala.letras = letras;
    pintarMapa();
    pintarPanel();
    mostrar(`Fila renombrada a ${letras[lado]}`);
  } catch (e) {
    mostrar(e.message || 'No se pudo cambiar la letra', true);
    pintarMapa();
  }
}

async function cargarSala() {
  sala = await api('GET', '/admin/sala');
  pintarMapa();
  pintarPanel();
}

// ----- Eliminar (cuadro de confirmación) -----
function abrirConfirmacion() {
  const s = seleccionado();
  if (!s) return;

  const detalles = [];
  if (s.nombres) {
    const empresa = s.empresa ? ` (${s.empresa})` : '';
    detalles.push(`Está asignado a ${s.nombres}${empresa}. Esa persona dejará de estar en la lista de invitados.`);
  }
  if (s.suplenteNombres) detalles.push(`También se quitará a su suplente, ${s.suplenteNombres}.`);
  if (s.firmado) detalles.push('Ya firmó: se borrará su firma guardada y su registro de asistencia, y no saldrá en la lista exportada.');
  detalles.push('El asiento se quita del mapa (queda un hueco con + para volver a ponerlo). Los demás asientos conservan su número.');

  focoPrevio = document.activeElement;
  $('#conf-etiqueta').textContent = etiquetaDe(s);
  const lista = $('#conf-detalles');
  lista.hidden = detalles.length === 0;
  lista.replaceChildren(...detalles.map((d) => h('li', {}, d)));
  $('#velo').hidden = false;
  $('#dialogo').hidden = false;
  $('#conf-cancelar').focus();
}

function cerrarConfirmacion() {
  $('#velo').hidden = true;
  $('#dialogo').hidden = true;
}

function cancelarEliminar() {
  cerrarConfirmacion();
  focoPrevio?.focus();
}

async function confirmarEliminar() {
  const s = seleccionado();
  if (!s) return cerrarConfirmacion();
  const etiqueta = etiquetaDe(s);
  cerrarConfirmacion();
  try {
    await api('DELETE', `/admin/asientos/${s.id}`);
    seleccionadoId = null;
    llenarFormulario(null);
    habilitarFormulario(false);
    // Se vuelve a pedir la sala porque los asientos siguientes cambiaron de número.
    await cargarSala();
    mostrar(`Asiento ${etiqueta} eliminado`);
  } catch (e) {
    mostrar(e.message || 'No se pudo eliminar', true);
  }
}

// ----- Búsqueda y menús -----
function alternarBusqueda(forzar) {
  const caja = $('#buscador');
  caja.hidden = forzar === undefined ? !caja.hidden : !forzar;
  $('#b-buscar').setAttribute('aria-expanded', String(!caja.hidden));
  $('#busqueda').value = '';
  pintarBusqueda();
  if (!caja.hidden) $('#busqueda').focus();
}

const MENUS = { exportar: ['#b-exportar', '#menu-exportar'], firmas: ['#b-firmas', '#menu-firmas'] };

/** Abre o cierra un menú (y cierra los demás). alternarMenu(null) los cierra todos. */
function alternarMenu(cual, forzar) {
  for (const [clave, [boton, menu]] of Object.entries(MENUS)) {
    const caja = $(menu);
    const abrir = clave === cual && (forzar === undefined ? caja.hidden : forzar);
    caja.hidden = !abrir;
    $(boton).setAttribute('aria-expanded', String(abrir));
  }
}

// ----- Eventos -----
$('#form-asiento').addEventListener('submit', (e) => { e.preventDefault(); guardar(); });
$('#b-guardar-barra').addEventListener('click', guardar);
$('#b-eliminar').addEventListener('click', abrirConfirmacion);
$('#conf-cancelar').addEventListener('click', cancelarEliminar);
$('#conf-eliminar').addEventListener('click', confirmarEliminar);
$('#velo').addEventListener('click', () => (importAbierto() ? cerrarImportar() : cancelarEliminar()));
$('#b-buscar').addEventListener('click', () => alternarBusqueda());
$('#busqueda').addEventListener('input', pintarBusqueda);
$('#b-exportar').addEventListener('click', () => alternarMenu('exportar'));
$('#b-firmas').addEventListener('click', () => alternarMenu('firmas'));

for (const boton of document.querySelectorAll('[data-formato]')) {
  boton.addEventListener('click', async () => {
    alternarMenu(null);
    const formato = boton.dataset.formato;
    try {
      await descargar(`/admin/exportar?formato=${formato}`, `asistencia-${new Date().toLocaleDateString('en-CA')}.${formato}`);
    } catch (e) {
      mostrar(e.message || 'No se pudo exportar el archivo', true);
    }
  });
}

// Exportar todas las firmas registradas: carpeta (ZIP de imágenes) o documento PDF.
for (const boton of document.querySelectorAll('[data-firmas]')) {
  boton.addEventListener('click', async () => {
    alternarMenu(null);
    const formato = boton.dataset.firmas;
    const extension = formato === 'pdf' ? 'pdf' : 'zip';
    try {
      await descargar(`/admin/firmas/exportar?formato=${formato}`, `firmas-${new Date().toLocaleDateString('en-CA')}.${extension}`);
    } catch (e) {
      mostrar(e.message || 'No se pudieron exportar las firmas', true);
    }
  });
}

for (const boton of document.querySelectorAll('[data-tema]')) {
  boton.addEventListener('click', () => { tema = boton.dataset.tema; escribir(CLAVE_TEMA, tema); pintarTema(); });
}

$('#hex').addEventListener('input', (e) => {
  hexTexto = e.target.value;
  const hex = normalizarHex(hexTexto);
  if (hex) {
    hexGuardado = hex;
    tema = 'hex';
    escribir(CLAVE_TEMA, tema);
    escribir(CLAVE_HEX, hex);
  }
  pintarTema();
});

$('#salir').addEventListener('click', async () => {
  try {
    await api('POST', '/admin/logout');
  } finally {
    location.assign(`${BASE}/`);
  }
});

document.addEventListener('click', (e) => {
  if (!dentroDe(e, '.menu-wrap')) alternarMenu(null);
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  if (!$('#dialogo').hidden) cancelarEliminar();
  else if (importAbierto()) cerrarImportar();
});

/** Mantiene el foco dentro de un cuadro mientras está abierto. */
function atraparFoco(cuadro) {
  cuadro.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    const controles = [...cuadro.querySelectorAll('button:not(:disabled), input:not(:disabled)')].filter((c) => c.offsetParent !== null);
    if (!controles.length) return;
    const primero = controles[0];
    const ultimo = controles[controles.length - 1];
    if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
    else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
  });
}
atraparFoco($('#dialogo'));
atraparFoco($('#dialogo-importar'));

// ----- Importar invitados desde Excel/CSV -----
const importAbierto = () => !$('#dialogo-importar').hidden;
let importando = false;
let importTerminado = false;

function abrirImportar() {
  focoPrevio = document.activeElement;
  importTerminado = false;
  $('#imp-archivo').value = '';
  $('#imp-reemplazar').checked = false;
  $('#imp-formulario').hidden = false;
  $('#imp-resultado').hidden = true;
  $('#imp-error').hidden = true;
  $('#imp-enviar').hidden = false;
  $('#imp-enviar').disabled = true;
  $('#imp-enviar').textContent = 'Importar';
  $('#imp-cancelar').textContent = 'Cancelar';
  $('#velo').hidden = false;
  $('#dialogo-importar').hidden = false;
  $('#imp-archivo').focus();
}

function cerrarImportar() {
  if (importando) return;
  $('#velo').hidden = true;
  $('#dialogo-importar').hidden = true;
  focoPrevio?.focus();
  // Si se importó algo, el mapa se refresca al cerrar.
  if (importTerminado) cargarSala().catch(() => {});
}

$('#b-importar').addEventListener('click', abrirImportar);
$('#imp-cancelar').addEventListener('click', cerrarImportar);
$('#imp-archivo').addEventListener('change', () => {
  $('#imp-enviar').disabled = !$('#imp-archivo').files.length;
  $('#imp-error').hidden = true;
});
$('#imp-plantilla').addEventListener('click', async () => {
  try {
    await descargar('/admin/plantilla', 'plantilla-invitados.xlsx');
  } catch {
    mostrar('No se pudo descargar la plantilla', true);
  }
});

$('#imp-enviar').addEventListener('click', async () => {
  const archivo = $('#imp-archivo').files[0];
  if (!archivo || importando) return;
  const datos = new FormData();
  datos.append('archivo', archivo);
  datos.append('reemplazar', $('#imp-reemplazar').checked ? '1' : '0');

  importando = true;
  $('#imp-enviar').disabled = true;
  $('#imp-enviar').textContent = 'Importando…';
  $('#imp-error').hidden = true;
  try {
    const r = await api('POST', '/admin/importar', datos);
    importTerminado = true;
    const partes = [];
    if (r.agregados) partes.push(`${r.agregados} agregado${r.agregados === 1 ? '' : 's'}`);
    if (r.actualizados) partes.push(`${r.actualizados} actualizado${r.actualizados === 1 ? '' : 's'}`);
    if (r.omitidos) partes.push(`${r.omitidos} omitido${r.omitidos === 1 ? '' : 's'}`);
    $('#imp-resumen').textContent = `Listo: ${partes.join(', ') || 'no hubo cambios'}.`;
    const lista = $('#imp-errores');
    lista.hidden = !r.errores.length;
    lista.replaceChildren(...r.errores.map((t) => h('li', {}, t)));
    if (r.totalErrores > r.errores.length) {
      lista.append(h('li', {}, `… y ${r.totalErrores - r.errores.length} más.`));
    }
    $('#imp-formulario').hidden = true;
    $('#imp-resultado').hidden = false;
    $('#imp-enviar').hidden = true;
    $('#imp-cancelar').textContent = 'Cerrar';
    $('#imp-cancelar').focus();
  } catch (e) {
    $('#imp-error').textContent = e.message || 'No se pudo importar el archivo.';
    $('#imp-error').hidden = false;
    $('#imp-enviar').disabled = false;
    $('#imp-enviar').textContent = 'Importar';
  } finally {
    importando = false;
  }
});

// ----- Inicio -----
$('#hex').value = hexTexto;
pintarTema();
pintarPanel();
cargarSala().catch(() => {
  const error = $('#error-carga');
  error.textContent = 'No se pudo cargar la configuración. Revisa que el servidor esté corriendo.';
  error.hidden = false;
});
