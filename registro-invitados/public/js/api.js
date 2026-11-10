// Cliente de la API JSON. Usa la sesión de Laravel (cookie) y el token CSRF de la página.
const meta = (nombre) => document.querySelector(`meta[name="${nombre}"]`)?.content ?? '';

/** Ruta base de la app (vacía si está en la raíz del dominio) y de las imágenes. */
export const BASE = meta('base-url').replace(/\/$/, '');
export const ASSETS = meta('assets-url').replace(/\/$/, '');

async function pedir(metodo, ruta, cuerpo) {
  const esForm = cuerpo instanceof FormData; // el navegador pone el Content-Type de los archivos
  const respuesta = await fetch(`${BASE}/api${ruta}`, {
    method: metodo,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': meta('csrf-token'),
      'X-Requested-With': 'XMLHttpRequest',
      ...(cuerpo !== undefined && !esForm ? { 'Content-Type': 'application/json' } : {}),
    },
    body: cuerpo === undefined ? undefined : esForm ? cuerpo : JSON.stringify(cuerpo),
  });

  if (!respuesta.ok) {
    const datos = await respuesta.json().catch(() => ({}));
    let mensaje = datos.message;
    if (respuesta.status === 419) mensaje = 'La página estuvo abierta demasiado tiempo. Recárgala e inténtalo de nuevo.';
    const error = new Error(mensaje || 'No se pudo conectar con el servidor.');
    error.status = respuesta.status;
    // Sesión vencida: vuelve al inicio (menos en el intento de contraseña, que muestra su propio error).
    if (respuesta.status === 401 && ruta !== '/admin/login') location.assign(`${BASE}/`);
    throw error;
  }
  return respuesta;
}

export async function api(metodo, ruta, cuerpo) {
  const respuesta = await pedir(metodo, ruta, cuerpo);
  return respuesta.status === 204 ? null : respuesta.json();
}

/** Descarga un archivo (PDF/Excel) generado por el servidor. */
export async function descargar(ruta, nombreArchivo) {
  const respuesta = await pedir('GET', ruta);
  const url = URL.createObjectURL(await respuesta.blob());
  const enlace = document.createElement('a');
  enlace.href = url;
  enlace.download = nombreArchivo;
  document.body.append(enlace);
  enlace.click();
  enlace.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
