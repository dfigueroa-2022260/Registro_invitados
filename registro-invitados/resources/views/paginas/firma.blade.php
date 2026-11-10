@extends('layouts.base')

@push('estilos')
  <link rel="stylesheet" href="{{ asset('css/firma.css') }}" />
@endpush

@section('contenido')
<main class="pagina">
  <section class="tarjeta">
    <aside class="lateral">
      <img class="logo" src="{{ asset('assets/logo-udevipo.png') }}" alt="UDEVIPO" />
      <p class="texto">
        Por motivos de seguridad, control de aforo y verificación de identidad, le solicitamos
        amablemente registrar sus datos y estampar su firma al ingresar al evento. Su colaboración
        es fundamental para garantizar una mejor organización y el bienestar de todos los
        asistentes. ¡Muchas gracias!
      </p>
      <button type="button" class="regresar" id="regresar">Regresar</button>
    </aside>

    <div class="contenido">
      <h1 class="titulo">Deja aquí tu firma</h1>

      <div class="marco" id="marco">
        <canvas id="lienzo" role="img" aria-label="Recuadro para dibujar tu firma con el mouse, el dedo o un lápiz táctil"></canvas>
      </div>

      <p class="aviso" id="aviso" role="alert" hidden></p>

      <div class="botones">
        <button type="button" class="boton boton-borrar" id="borrar">Borrar</button>
        <button type="button" class="boton boton-aceptar" id="aceptar" disabled>Aceptar</button>
      </div>
    </div>
  </section>
</main>
@endsection

@push('scripts')
  <script type="module" src="{{ asset('js/firma.js') }}"></script>
@endpush
