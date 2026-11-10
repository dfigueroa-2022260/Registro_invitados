/** Crea un elemento con atributos e hijos (los textos se insertan como texto, nunca como HTML). */
export function h(etiqueta, atributos = {}, ...hijos) {
  const el = document.createElement(etiqueta);
  for (const [clave, valor] of Object.entries(atributos ?? {})) {
    if (valor === false || valor == null) continue;
    if (clave === 'class') el.className = valor;
    else if (clave === 'style') for (const [p, v] of Object.entries(valor)) el.style.setProperty(p, String(v));
    else if (clave.startsWith('on') && typeof valor === 'function') el.addEventListener(clave.slice(2), valor);
    else el.setAttribute(clave, valor === true ? '' : String(valor));
  }
  for (const hijo of hijos.flat()) {
    if (hijo == null || hijo === false) continue;
    el.append(hijo instanceof Node ? hijo : document.createTextNode(String(hijo)));
  }
  return el;
}

export const normalizar = (texto) =>
  texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

/**
 * ¿El clic ocurrió dentro de un elemento que coincide con el selector?
 * Si el elemento ya se quitó del documento (la pantalla se redibujó), se considera dentro.
 */
export function dentroDe(evento, selector) {
  const destino = evento.target;
  return !(destino instanceof Element) || !destino.isConnected || !!destino.closest(selector);
}
