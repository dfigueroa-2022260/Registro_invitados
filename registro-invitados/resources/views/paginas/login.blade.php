@extends('layouts.base')

@push('estilos')
  <link rel="stylesheet" href="{{ asset('css/login.css') }}" />
@endpush

@section('contenido')
<main class="pagina">
  <section class="tarjeta">
    <div class="ilustracion">
      <img src="{{ asset('assets/panel-udevipo.png') }}" alt="UDEVIPO, Unidad para el Desarrollo de Vivienda Popular" />
    </div>

    <form class="panel" id="form-login" novalidate>
      <img class="logo" src="{{ asset('assets/logo-udevipo.png') }}" alt="UDEVIPO" />
      <h1 class="titulo">Llena tus datos para ver tu asiento y verificar tu información</h1>

      <div class="campos">
        <label for="nombres">Nombres y apellidos</label>
        <input id="nombres" type="text" autocomplete="name" />
        <p class="aviso" id="aviso-nombres" hidden>Escribe tus nombres y apellidos.</p>

        <label for="empresa">Empresa o asociación que representa</label>
        <div class="selector">
          <select id="empresa">
            <option value="" disabled selected>Selecciona una opción</option>
            @foreach ($empresas as $empresa)
              <option value="{{ $empresa }}">{{ $empresa }}</option>
            @endforeach
          </select>
        </div>
        <p class="aviso" id="aviso-empresa" hidden>Selecciona tu empresa o asociación.</p>

        <p class="aviso aviso-general" id="aviso-general" role="alert" hidden></p>
      </div>

      <div class="pie">
        <button type="button" class="admin" id="abrir-admin">entrar como admin</button>
        <div class="botones">
          <button type="button" class="boton boton-borrar" id="borrar">Borrar</button>
          <button type="submit" class="boton boton-siguiente" id="siguiente">Siguiente</button>
        </div>
      </div>
    </form>
  </section>

  <div class="velo" id="velo" hidden></div>
  <div class="dialogo" id="dialogo-admin" role="dialog" aria-modal="true" aria-labelledby="titulo-admin" hidden>
    <h2 id="titulo-admin">Acceso de administrador</h2>
    <form id="form-admin" novalidate>
      <label for="clave">Contraseña</label>
      <input id="clave" type="password" autocomplete="current-password" />
      <p class="aviso" id="aviso-admin" role="alert" hidden></p>
      <div class="botones dialogo-botones">
        <button type="button" class="boton boton-borrar" id="cancelar-admin">Cancelar</button>
        <button type="submit" class="boton boton-siguiente" id="entrar-admin">Entrar</button>
      </div>
    </form>
  </div>
</main>
@endsection

@push('scripts')
  <script type="module" src="{{ asset('js/login.js') }}"></script>
@endpush
