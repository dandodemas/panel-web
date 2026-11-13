/* Panel de agencia: copiar/mostrar contraseñas, generar contraseñas y confirmar borrados. */
(function () {
  'use strict';

  var DDM = window.DDM;
  var ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

  function generate(len) {
    var buf = new Uint32Array(len || 12);
    crypto.getRandomValues(buf);
    var out = '';
    for (var i = 0; i < buf.length; i++) out += ALPHABET[buf[i] % ALPHABET.length];
    return out;
  }

  function fetchPassword(id) {
    return DDM.api('/agencia/' + id + '/clave').then(function (r) {
      if (!r.ok) throw new Error(r.error || 'No se ha podido leer la contraseña.');
      return r.password;
    });
  }

  document.addEventListener('click', function (e) {
    var t;

    if ((t = e.target.closest('[data-copy-pass]'))) {
      fetchPassword(t.getAttribute('data-copy-pass'))
        .then(DDM.copy)
        .then(function () {
          DDM.toast('Contraseña copiada');
        })
        .catch(function (err) {
          DDM.toast(err && err.message ? err.message : 'No se ha podido copiar.');
        });
      return;
    }

    if ((t = e.target.closest('[data-copy-text]'))) {
      DDM.copy(t.getAttribute('data-copy-text')).then(
        function () {
          DDM.toast(t.getAttribute('data-toast') || 'Copiado');
        },
        function () {
          DDM.toast('No se ha podido copiar.');
        }
      );
      return;
    }

    if ((t = e.target.closest('[data-reveal-pass]'))) {
      var out = document.getElementById('current-pass');
      if (!out) return;
      if (out.getAttribute('data-shown') === '1') {
        out.textContent = '••••••••••';
        out.classList.add('mute');
        out.removeAttribute('data-shown');
        t.textContent = 'Mostrar';
        return;
      }
      fetchPassword(t.getAttribute('data-reveal-pass')).then(
        function (pw) {
          out.textContent = pw;
          out.classList.remove('mute');
          out.setAttribute('data-shown', '1');
          t.textContent = 'Ocultar';
        },
        function (err) {
          DDM.toast(err.message);
        }
      );
      return;
    }

    if ((t = e.target.closest('[data-generate-for]'))) {
      var input = document.getElementById(t.getAttribute('data-generate-for'));
      if (input) {
        input.value = generate(12);
        input.focus();
      }
    }
  });

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-confirm-delete]');
    if (!form) return;
    var name = form.getAttribute('data-confirm-delete');
    if (!window.confirm('¿Borrar «' + name + '» y todo su contenido y fotos? No se puede deshacer.')) e.preventDefault();
  });
})();
