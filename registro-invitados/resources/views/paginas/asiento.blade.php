@extends('layouts.base')

@push('estilos')
  <link rel="stylesheet" href="{{ asset('css/mapa.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/asiento.css') }}" />
@endpush

@section('contenido')
<div class="asiento-host" id="raiz">
<main class="pagina">
  <div class="encabezado">
    <header class="barra">
      <img class="logo" src="{{ asset('assets/logo-udevipo.png') }}" alt="UDEVIPO" />

      <div class="datos">
        <div class="pastilla">
          <span class="etiqueta-dato">Número de asiento</span>
          <strong id="d-etiqueta"></strong>
        </div>
        <div class="pastilla">
          <span class="etiqueta-dato">Zona de asiento</span>
          <strong id="d-lado"></strong>
        </div>
        <div class="pastilla">
          <span class="etiqueta-dato">Nombre de la persona</span>
          <strong class="recorte" id="d-nombre"></strong>
        </div>
        <button type="button" class="pastilla salir" id="salir">Salir</button>
      </div>

      <div class="acciones">
        <!-- Opciones de usuario -->
        <div class="pop-wrap">
          <button type="button" class="icono" id="b-opciones" aria-label="Opciones de usuario" aria-haspopup="dialog" aria-expanded="false">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="12" cy="12" r="3" />
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
            </svg>
          </button>

          <div class="popover" id="p-opciones" role="dialog" aria-label="Opciones de usuario" hidden>
            <h2>Opciones de usuario</h2>

            <button type="button" class="opcion" id="o-ver" role="switch" aria-checked="false">
              <svg class="icono-opcion" viewBox="0 0 24 24" aria-hidden="true">
                <g id="ojo-abierto">
                  <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" />
                  <circle cx="12" cy="12" r="3" />
                </g>
                <g id="ojo-cerrado">
                  <path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.2A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.3 4.2M6.2 6.3A17 17 0 0 0 2 12s3.6 7 10 7c1.7 0 3.2-.4 4.5-1" />
                </g>
              </svg>
              <span class="opcion-texto">
                <strong>Ver quién ya llegó</strong>
                <small>Marca los asientos de quienes ya firmaron y los de quienes aún no llegan.</small>
              </span>
              <span class="pildora" aria-hidden="true"></span>
            </button>

            <button type="button" class="opcion" id="o-resaltar" role="switch" aria-checked="false">
              <svg class="icono-opcion" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 11.5 12 3l9 8.5V21h-6v-6H9v6H3z" />
              </svg>
              <span class="opcion-texto">
                <strong>Resaltar mi asiento</strong>
                <small>Pone un halo brillante alrededor de tu asiento.</small>
              </span>
              <span class="pildora" aria-hidden="true"></span>
            </button>

            <div class="opcion opcion-fija">
              <svg class="icono-opcion" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m21 21-4.35-4.35M11 8v6M8 11h6" />
              </svg>
              <span class="opcion-texto">
                <strong id="etq-zoom">Tamaño del mapa</strong>
                <span class="segmentos" role="radiogroup" aria-labelledby="etq-zoom">
                  <button type="button" role="radio" data-zoom="1" aria-checked="true">Normal</button>
                  <button type="button" role="radio" data-zoom="1.5" aria-checked="false">Grande</button>
                  <button type="button" role="radio" data-zoom="2" aria-checked="false">Muy grande</button>
                </span>
              </span>
            </div>

            <button type="button" class="opcion" id="o-oscuro" role="switch" aria-checked="false">
              <svg class="icono-opcion" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
              </svg>
              <span class="opcion-texto">
                <strong>Modo oscuro</strong>
                <small>Fondo oscuro, más cómodo con poca luz.</small>
              </span>
              <span class="pildora" aria-hidden="true"></span>
            </button>

            <div class="popover-pie">
              <button type="button" class="enlace" id="o-ir">Ir a mi asiento</button>
              <button type="button" class="enlace" id="o-restablecer">Restablecer</button>
            </div>
          </div>
        </div>

        <!-- Buscador -->
        <div class="pop-wrap">
          <button type="button" class="icono" id="b-buscar" aria-label="Buscar en la sala" aria-haspopup="dialog" aria-expanded="false">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <circle cx="11" cy="11" r="7" />
              <path d="m21 21-4.35-4.35" />
            </svg>
          </button>

          <div class="popover" id="p-buscar" role="dialog" aria-label="Buscar en la sala" hidden>
            <h2>Buscar en la sala</h2>
            <label class="sr-only" for="busqueda">Nombre, empresa o número de asiento</label>
            <input id="busqueda" type="search" autocomplete="off" placeholder="Nombre, empresa o asiento (ej. A-5)" />
            <ul class="resultados" id="resultados" hidden></ul>
            <p class="ayuda" id="ayuda"></p>
          </div>
        </div>

        <div class="usuario">
          <span class="rol">Invitado</span>
          <span class="nombre-usuario" id="d-usuario"></span>
        </div>
      </div>
    </header>

    <h1 class="titulo">Este es tu asiento</h1>

    <p class="encontrado" id="encontrado" role="status" hidden></p>

    <ul class="leyenda" id="leyenda" aria-label="Leyenda de asistencia" hidden>
      <li><span class="punto-leyenda llego"></span><span id="l-llegaron"></span></li>
      <li><span class="punto-leyenda pendiente"></span><span id="l-pendientes"></span></li>
      <li><span class="punto-leyenda libre"></span><span id="l-libres"></span></li>
    </ul>
  </div>

  <div class="desplazable" id="contenedor">
    <div id="mapa" hidden></div>
    <p class="error" id="error-mapa" role="alert" hidden></p>
  </div>
</main>
</div>

<script type="application/json" id="datos-inicial">{!! json_encode($datos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
  <script type="module" src="{{ asset('js/asiento.js') }}"></script>
@endpush
