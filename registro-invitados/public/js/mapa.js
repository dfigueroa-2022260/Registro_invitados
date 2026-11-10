import { ASSETS } from './api.js';
import { h } from './dom.js';

/*
 * Mapa de la sala según el plano. Todo se dibuja en una "escena" fija de ESCENA.ancho x ESCENA.alto
 * y se escala al ancho disponible. Cada silla se ubica por su mesa y su número (los impresos en el plano).
 */
const ESCENA = { ancho: 1255, alto: 768 };
const DESPLAZA = 9; // la placa de cada puesto se corre hacia afuera de la línea central de la mesa
const U = { cx: 975, cy: 365, radio: 100, yArriba: 265, yAbajo: 465, xIzq: 257 };
const CURVA = 5; // puestos en la parte curva de la mesa central

// Unidad de cada puesto (en px de escena): caja de 64 x 96 con la placa en (32, 70).
const CAJA = { ancho: 64, alto: 96, origenX: 32, origenY: 70 };

const ETIQUETAS = {
  central: { x: 228, y: 265 },
  superior: { x: 228, y: 110 },
  inferior: { x: 228, y: 610 },
  izquierdo: { x: 88, y: 56 },
};

const lineal = (a, b, i, total) => (total > 1 ? a + ((b - a) * i) / (total - 1) : (a + b) / 2);
const asc = (x, y) => x - y;
const desc = (x, y) => y - x;

/** Posición de cada número de una mesa: { [numero]: { x, y, theta } } (theta = hacia dónde queda la silla). */
function posiciones(lado, numeros) {
  const pos = {};
  if (lado === 'superior') {
    const orden = [...numeros].sort(desc); // 33 … 24 de izquierda a derecha
    orden.forEach((n, i) => (pos[n] = { x: lineal(303, 963, i, orden.length), y: 110 - DESPLAZA, theta: -90 }));
  } else if (lado === 'inferior') {
    const orden = [...numeros].sort(desc);
    orden.forEach((n, i) => (pos[n] = { x: lineal(295, 948, i, orden.length), y: 610 + DESPLAZA, theta: 90 }));
  } else if (lado === 'izquierdo') {
    // De arriba hacia abajo: impares descendentes y luego pares ascendentes (7 5 3 1 2 4 6)
    const orden = [...numeros.filter((n) => n % 2).sort(desc), ...numeros.filter((n) => n % 2 === 0).sort(asc)];
    orden.forEach((n, i) => (pos[n] = { x: 88 - DESPLAZA, y: lineal(130, 630, i, orden.length), theta: 180 }));
  } else if (lado === 'central') {
    // Sentido horario desde arriba a la izquierda: pares descendentes (22 … 2) y luego impares ascendentes (1 … 23)
    const orden = [...numeros.filter((n) => n % 2 === 0).sort(desc), ...numeros.filter((n) => n % 2).sort(asc)];
    const brazo = Math.floor((orden.length - CURVA) / 2);
    orden.forEach((n, i) => {
      if (i < brazo) {
        pos[n] = { x: lineal(290, 940, i, brazo), y: U.yArriba - DESPLAZA, theta: -90 };
      } else if (i < brazo + CURVA) {
        const theta = (i - brazo - (CURVA - 1) / 2) * 36;
        const r = U.radio + DESPLAZA;
        const rad = (theta * Math.PI) / 180;
        pos[n] = { x: U.cx + r * Math.cos(rad), y: U.cy + r * Math.sin(rad), theta };
      } else {
        pos[n] = { x: lineal(940, 290, i - brazo - CURVA, brazo), y: U.yAbajo + DESPLAZA, theta: 90 };
      }
    });
  }
  return pos;
}

/** Mesas, monitores, cabinas e ingresos (dibujo fijo, sin datos de personas). */
const DECORADO = `
<svg viewBox="0 0 ${ESCENA.ancho} ${ESCENA.alto}" width="${ESCENA.ancho}" height="${ESCENA.alto}" aria-hidden="true">
  <g fill="#eef0f4" stroke="#c4c8d3" stroke-width="2">
    <rect x="1046" y="10" width="184" height="156" rx="6"/>
    <rect x="1076" y="590" width="154" height="142" rx="6"/>
  </g>
  <rect x="1092" y="34" width="106" height="12" fill="#7a4a22"/>
  <rect x="1090" y="632" width="14" height="86" fill="#7a4a22"/>
  <g fill="#5a3417" stroke="#8a5a2b" stroke-width="2">
    <rect x="257" y="89" width="743" height="42"/>
    <rect x="257" y="589" width="743" height="42"/>
    <rect x="67" y="82" width="42" height="598"/>
  </g>
  <path d="M${U.xIzq},${U.yArriba} H${U.cx} A${U.radio},${U.radio} 0 0 1 ${U.cx},${U.yAbajo} H${U.xIzq}"
        fill="none" stroke="#8a5a2b" stroke-width="46"/>
  <path d="M${U.xIzq},${U.yArriba} H${U.cx} A${U.radio},${U.radio} 0 0 1 ${U.cx},${U.yAbajo} H${U.xIzq}"
        fill="none" stroke="#5a3417" stroke-width="42"/>
  <g fill="#262830" stroke="#4a4d58" stroke-width="2">
    <rect x="470" y="290" width="96" height="34" rx="3"/>
    <rect x="705" y="290" width="96" height="34" rx="3"/>
    <rect x="470" y="412" width="96" height="30" rx="3"/>
    <rect x="705" y="412" width="96" height="30" rx="3"/>
    <rect x="340" y="330" width="34" height="86" rx="3"/>
    <rect x="920" y="326" width="34" height="86" rx="3"/>
  </g>
  <g fill="none" stroke="currentColor" stroke-width="2.5">
    <path d="M230,768 V726 Q290,698 350,768"/>
    <path d="M893,768 Q953,698 1012,726 V768"/>
  </g>
  <g fill="currentColor" font-size="19" font-weight="600" font-family="inherit">
    <text x="244" y="754">Ingreso</text>
    <text x="940" y="754">Ingreso</text>
  </g>
</svg>`;

/**
 * Dibuja el mapa dentro de `host`.
 * render({ letras, grupos, asientos: [{lado, numero, id, estado}], marcado, seleccionadoId, editable, resaltar, puntosPendientes })
 * manejadores: onSeleccionar(asiento), onAgregar({ lado, numero }), onCambiarLetra({ lado, letra })
 */
export function crearMapa(host, manejadores = {}) {
  host.classList.add('mapa-host');
  host.style.setProperty('--razon', String(ESCENA.ancho / ESCENA.alto));

  const marco = h('div', { class: 'mapa-marco' });
  const raiz = h('div', { class: 'mapa' }, marco);
  host.replaceChildren(raiz);

  let props = null;
  let editandoLado = null;
  let escena = null;

  function ajustarEscala() {
    const k = host.clientWidth / ESCENA.ancho;
    marco.style.height = `${ESCENA.alto * k}px`;
    if (escena) escena.style.transform = `scale(${k})`;
  }
  if (typeof ResizeObserver !== 'undefined') new ResizeObserver(ajustarEscala).observe(host);
  else window.addEventListener('resize', ajustarEscala);

  function confirmarLetra(lado, valor) {
    if (editandoLado !== lado) return;
    editandoLado = null;
    const letra = valor.trim();
    render();
    if (letra && letra.toUpperCase() !== props.letras[lado].toUpperCase()) {
      manejadores.onCambiarLetra?.({ lado, letra });
    }
  }

  function cancelarLetra() {
    editandoLado = null;
    render();
  }

  function editarLetra(lado) {
    editandoLado = lado;
    render();
  }

  function render(nuevas) {
    if (nuevas) props = nuevas;
    if (!props) return;
    const { letras, grupos, asientos, marcado = null, seleccionadoId = null, editable = false, resaltar = false, puntosPendientes = false } = props;
    raiz.classList.toggle('resaltar', resaltar);

    const letraNodo = (lado) => {
      if (!editable) return h('span', { class: 'letra' }, letras[lado]);
      if (editandoLado === lado) {
        const entrada = h('input', {
          class: 'letra-entrada',
          maxlength: 2,
          value: letras[lado],
          'aria-label': `Nueva letra de la ${grupos[lado].nombre.toLowerCase()}`,
        });
        entrada.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            confirmarLetra(lado, entrada.value);
          } else if (e.key === 'Escape') cancelarLetra();
        });
        entrada.addEventListener('blur', () => confirmarLetra(lado, entrada.value));
        return entrada;
      }
      return h(
        'button',
        {
          type: 'button',
          class: 'letra letra-editable',
          title: `Editar la letra de la ${grupos[lado].nombre.toLowerCase()}`,
          'aria-label': `Editar la letra de la ${grupos[lado].nombre.toLowerCase()} (${letras[lado]})`,
          onclick: () => editarLetra(lado),
        },
        letras[lado],
      );
    };

    // Estilo común de la caja de un puesto: se gira para que la silla quede siempre del lado de afuera de la mesa.
    const estiloPuesto = (p) => ({
      left: `${p.x - CAJA.origenX}px`,
      top: `${p.y - CAJA.origenY}px`,
      '--r': Math.round((p.theta + 90) * 100) / 100,
      transform: `rotate(${p.theta + 90}deg)`,
    });

    const puestos = [];
    for (const [lado, grupo] of Object.entries(grupos)) {
      const pos = posiciones(lado, grupo.numeros);
      const existentes = new Map(asientos.filter((a) => a.lado === lado).map((a) => [a.numero, a]));

      for (const numero of grupo.numeros) {
        const p = pos[numero];
        if (!p) continue;
        const a = existentes.get(numero);
        const etiqueta = `${letras[lado]}-${numero}`;

        if (!a) {
          // Asiento quitado: solo el administrador ve el hueco y puede volver a ponerlo.
          if (editable) {
            puestos.push(
              h(
                'button',
                {
                  type: 'button',
                  class: 'celda fantasma',
                  'data-lado': lado,
                  style: estiloPuesto(p),
                  title: `Agregar el asiento ${etiqueta}`,
                  'aria-label': `Agregar el asiento ${etiqueta}`,
                  onclick: () => manejadores.onAgregar?.({ lado, numero }),
                },
                h('span', { class: 'silla-fantasma', 'aria-hidden': 'true' }, '+'),
                h('span', { class: 'num' }, numero),
              ),
            );
          }
          continue;
        }

        const esMio = !!marcado && marcado.lado === lado && marcado.numero === numero;
        const clases = ['celda'];
        if (a.id != null && a.id === seleccionadoId) clases.push('sel');
        if (a.estado === 'libre') clases.push('libre');
        if (esMio) clases.push('mio');
        puestos.push(
          h(
            'button',
            {
              type: 'button',
              class: clases.join(' '),
              'data-lado': lado,
              'data-id': a.id,
              disabled: !editable,
              style: estiloPuesto(p),
              'aria-label': `Asiento ${etiqueta}`,
              'aria-pressed': editable ? String(a.id === seleccionadoId) : null,
              onclick: () => manejadores.onSeleccionar?.(a),
            },
            h('img', { class: 'silla', src: `${ASSETS}/silla.png`, alt: '' }),
            h('span', { class: 'placa', 'aria-hidden': 'true' }),
            h('span', { class: 'num' }, numero),
            a.estado === 'asistio' ? h('span', { class: 'punto', title: 'Ya llegó' }) : null,
            puntosPendientes && a.estado === 'asignado' ? h('span', { class: 'punto pendiente', title: 'Aún no llega' }) : null,
            esMio ? h('img', { class: 'casa', src: `${ASSETS}/casa.png`, alt: 'Tu asiento' }) : null,
          ),
        );
      }
    }

    const etiquetas = Object.keys(grupos)
      .filter((lado) => ETIQUETAS[lado])
      .map((lado) => h('div', { class: 'et', style: { left: `${ETIQUETAS[lado].x}px`, top: `${ETIQUETAS[lado].y}px` } }, letraNodo(lado)));

    const decorado = h('div', { class: 'decorado' });
    decorado.innerHTML = DECORADO; // dibujo fijo, sin datos de personas

    escena = h('div', { class: 'escena', style: { width: `${ESCENA.ancho}px`, height: `${ESCENA.alto}px` } }, decorado, ...puestos, ...etiquetas);
    marco.replaceChildren(escena);
    ajustarEscala();

    if (editandoLado) {
      const entrada = host.querySelector('.letra-entrada');
      entrada?.focus();
      entrada?.select();
    }
  }

  return { render };
}
