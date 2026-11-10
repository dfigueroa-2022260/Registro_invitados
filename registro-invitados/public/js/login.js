import { BASE, api } from './api.js';

const $ = (selector) => document.querySelector(selector);
const form = $('#form-login');
const nombres = $('#nombres');
const empresa = $('#empresa');
const avisoNombres = $('#aviso-nombres');
const avisoEmpresa = $('#aviso-empresa');
const avisoGeneral = $('#aviso-general');
const botonSiguiente = $('#siguiente');

const tocado = { nombres: false, empresa: false };
const nombresValido = () => nombres.value.trim().length >= 3;
const empresaValida = () => empresa.value !== '';

function pintar() {
  const malNombres = tocado.nombres && !nombresValido();
  const malEmpresa = tocado.empresa && !empresaValida();
  avisoNombres.hidden = !malNombres;
  avisoEmpresa.hidden = !malEmpresa;
  nombres.setAttribute('aria-invalid', String(malNombres));
  empresa.setAttribute('aria-invalid', String(malEmpresa));
}

nombres.addEventListener('blur', () => { tocado.nombres = true; pintar(); });
nombres.addEventListener('input', pintar);
empresa.addEventListener('blur', () => { tocado.empresa = true; pintar(); });
empresa.addEventListener('change', () => { tocado.empresa = true; pintar(); });

$('#borrar').addEventListener('click', () => {
  form.reset();
  tocado.nombres = tocado.empresa = false;
  avisoGeneral.hidden = true;
  pintar();
});

form.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  avisoGeneral.hidden = true;
  tocado.nombres = tocado.empresa = true;
  pintar();
  if (!nombresValido() || !empresaValida()) return;

  botonSiguiente.disabled = true;
  botonSiguiente.textContent = 'Verificando…';
  try {
    await api('POST', '/invitados/verificar', { nombres: nombres.value, empresa: empresa.value });
    location.assign(`${BASE}/firma`);
  } catch (error) {
    botonSiguiente.disabled = false;
    botonSiguiente.textContent = 'Siguiente';
    avisoGeneral.textContent = error.message;
    avisoGeneral.hidden = false;
  }
});

// ----- Acceso de administrador -----
const velo = $('#velo');
const dialogo = $('#dialogo-admin');
const clave = $('#clave');
const avisoAdmin = $('#aviso-admin');
const botonEntrar = $('#entrar-admin');

function abrirAdmin() {
  clave.value = '';
  avisoAdmin.hidden = true;
  velo.hidden = false;
  dialogo.hidden = false;
  clave.focus();
}

function cerrarAdmin() {
  velo.hidden = true;
  dialogo.hidden = true;
}

$('#abrir-admin').addEventListener('click', abrirAdmin);
$('#cancelar-admin').addEventListener('click', cerrarAdmin);
velo.addEventListener('click', cerrarAdmin);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarAdmin(); });

$('#form-admin').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  if (!clave.value) {
    avisoAdmin.textContent = 'Escribe la contraseña.';
    avisoAdmin.hidden = false;
    return;
  }
  botonEntrar.disabled = true;
  botonEntrar.textContent = 'Entrando…';
  avisoAdmin.hidden = true;
  try {
    await api('POST', '/admin/login', { password: clave.value });
    location.assign(`${BASE}/admin`);
  } catch (error) {
    botonEntrar.disabled = false;
    botonEntrar.textContent = 'Entrar';
    avisoAdmin.textContent = error.message;
    avisoAdmin.hidden = false;
  }
});
