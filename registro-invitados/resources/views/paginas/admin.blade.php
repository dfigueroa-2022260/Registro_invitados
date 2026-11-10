@extends('layouts.base')

@section('titulo', 'UDEVIPO · Configuración admin')

@push('estilos')
  <link rel="stylesheet" href="{{ asset('css/mapa.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}" />
@endpush

@section('contenido')
<main class="admin" id="admin">
  <div class="marco">
    <header class="cabecera">
      <svg class="engranaje" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="3.2" />
        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
      </svg>
      <h1>Configuración admin</h1>
      <button type="button" class="salir" id="salir">Salir</button>
    </header>

    <div class="contenido">
      <section class="izquierda">
        <div class="barra-acciones">
          <div class="menu-wrap">
            <button type="button" class="btn btn-azul" id="b-exportar" aria-haspopup="menu" aria-expanded="false">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4" /></svg>
              Exportar
            </button>
            <div class="menu" id="menu-exportar" role="menu" hidden>
              <button type="button" role="menuitem" data-formato="pdf">Descargar PDF</button>
              <button type="button" role="menuitem" data-formato="xlsx">Descargar Excel</button>
            </div>
          </div>

          <div class="menu-wrap">
            <button type="button" class="btn btn-azul" id="b-firmas" aria-haspopup="menu" aria-expanded="false">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 17l3.5-.8L19 5.7a2 2 0 0 0-2.8-2.8L5.8 13.4 5 17zM14.5 5.5l3 3" /></svg>
              Exportar firmas
            </button>
            <div class="menu" id="menu-firmas" role="menu" hidden>
              <button type="button" role="menuitem" data-firmas="carpeta">En carpeta (ZIP de imágenes)</button>
              <button type="button" role="menuitem" data-firmas="pdf">En documento PDF</button>
            </div>
          </div>

          <button type="button" class="btn btn-azul" id="b-importar">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3m0 0L8 7m4-4 4 4M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4" /></svg>
            Importar
          </button>

          <button type="button" class="btn btn-marino" id="b-buscar" aria-expanded="false">
            <span class="lupa" aria-hidden="true">
              <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.35-4.35" /></svg>
            </span>
            Buscar invitado o N.º
          </button>

          <button type="button" class="btn btn-azul" id="b-guardar-barra">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM7 3v6h8V3M7 21v-7h10v7" />
            </svg>
            Guardar
          </button>
        </div>

        <div class="buscador" id="buscador" hidden>
          <label class="sr-only" for="busqueda">Buscar invitado o número de asiento</label>
          <input id="busqueda" type="search" placeholder="Nombre, empresa, ID o número de asiento (ej. A-5)" autocomplete="off" />
          <p class="sin-resultados" id="sin-resultados" hidden>Sin resultados</p>
          <ul class="resultados" id="resultados" hidden></ul>
        </div>

        <div class="tema" role="group" aria-label="Configuración de modo oscuro">
          <p class="tema-titulo">Configuración de modo oscuro</p>
          <div class="tema-opciones">
            <button type="button" data-tema="claro">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
              Claro
            </button>
            <button type="button" data-tema="oscuro">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" /></svg>
              Oscuro
            </button>
            <button type="button" data-tema="gris">
              <span class="ajedrez" aria-hidden="true"></span>
              Gris elegante
            </button>
          </div>
          <div class="tema-hex">
            <span class="muestra" id="muestra" aria-hidden="true"></span>
            <input type="text" id="hex" maxlength="7" placeholder="Hex input" aria-label="Color en hexadecimal" />
            <span class="tema-hex-etiqueta">Entrada hexadecimal</span>
          </div>
        </div>

        <div id="mapa" hidden></div>
        <p class="leyenda" id="leyenda" hidden></p>
        <p class="error" id="error-carga" role="alert" hidden></p>
      </section>

      <aside class="derecha">
        <form class="formulario" id="form-asiento" novalidate>
          <label for="f-asiento">Número de asiento</label>
          <input id="f-asiento" type="text" readonly placeholder="Número de asiento" />

          <label for="f-nombres">Nombres y apellidos</label>
          <input id="f-nombres" type="text" maxlength="120" placeholder="Nombres y apellidos" disabled />

          <label for="f-id">ID</label>
          <input id="f-id" type="text" maxlength="40" placeholder="ID" disabled />

          <label for="f-empresa">Nombre de la empresa</label>
          <input id="f-empresa" type="text" maxlength="120" list="lista-empresas" placeholder="Nombre de la empresa" disabled />
          <datalist id="lista-empresas"></datalist>

          <label for="f-categoria">Categoría</label>
          <input id="f-categoria" type="text" maxlength="60" placeholder="Categoría" disabled />

          <p class="subtitulo">Invitado suplente <small>(si el titular no llega; ocupa este mismo asiento)</small></p>

          <label for="f-suplente">Suplente: nombres y apellidos</label>
          <input id="f-suplente" type="text" maxlength="120" placeholder="Nombres y apellidos del suplente" disabled />

          <label for="f-suplente-id">ID del suplente</label>
          <input id="f-suplente-id" type="text" maxlength="40" placeholder="ID del suplente" disabled />

          <p class="estado" id="estado">Selecciona un asiento del mapa para editarlo.</p>

          <div class="acciones-form">
            <button type="button" class="eliminar" id="b-eliminar" hidden>Eliminar asiento</button>
            <button type="submit" class="btn btn-azul btn-guardar" id="b-guardar" disabled>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 3h11l3 3v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM7 3v6h8V3M7 21v-7h10v7" />
              </svg>
              Guardar
            </button>
          </div>
        </form>
      </aside>
    </div>
  </div>

  <div class="velo" id="velo" hidden></div>
  <div class="dialogo" id="dialogo" role="alertdialog" aria-modal="true" aria-labelledby="conf-titulo" aria-describedby="conf-texto" hidden>
    <h2 id="conf-titulo">Eliminar asiento</h2>
    <div id="conf-texto">
      <p>¿Eliminar el asiento <strong id="conf-etiqueta"></strong>?</p>
      <ul class="detalles" id="conf-detalles" hidden></ul>
      <p>Esta acción no se puede deshacer.</p>
    </div>
    <div class="dialogo-botones">
      <button type="button" class="btn btn-marino" id="conf-cancelar">Cancelar</button>
      <button type="button" class="btn btn-peligro" id="conf-eliminar">Eliminar</button>
    </div>
  </div>

  <div class="dialogo dialogo-importar" id="dialogo-importar" role="dialog" aria-modal="true" aria-labelledby="imp-titulo" hidden>
    <h2 id="imp-titulo">Importar invitados</h2>

    <div id="imp-formulario">
      <p>
        Sube un archivo Excel (.xlsx) o CSV con las columnas <strong>Asiento</strong> (opcional, ej. A-5),
        <strong>Nombres y apellidos</strong>, <strong>ID</strong>, <strong>Empresa o asociación</strong>,
        <strong>Categoría</strong> y, si hay, <strong>Suplente</strong> e <strong>ID del suplente</strong>.
        Si dejas el asiento vacío se usa el siguiente asiento libre. No se borra a nadie.
      </p>
      <button type="button" class="enlace-plantilla" id="imp-plantilla">Descargar plantilla de Excel</button>

      <label class="imp-archivo" for="imp-archivo">Archivo</label>
      <input id="imp-archivo" type="file" accept=".xlsx,.xls,.csv,.txt" />

      <label class="imp-opcion">
        <input id="imp-reemplazar" type="checkbox" />
        <span>Reemplazar a quien ya ocupa un asiento (si no, esas filas se omiten)</span>
      </label>
    </div>

    <div id="imp-resultado" hidden>
      <p id="imp-resumen"></p>
      <ul class="detalles detalles-errores" id="imp-errores" hidden></ul>
    </div>

    <p class="error" id="imp-error" role="alert" hidden></p>

    <div class="dialogo-botones">
      <button type="button" class="btn btn-marino" id="imp-cancelar">Cancelar</button>
      <button type="button" class="btn btn-azul" id="imp-enviar" disabled>Importar</button>
    </div>
  </div>

  <div class="toast" id="toast" hidden></div>
</main>
@endsection

@push('scripts')
  <script type="module" src="{{ asset('js/admin.js') }}"></script>
@endpush
