/* Panel · utilidades comunes: tema claro/oscuro, llamadas a la API con CSRF, avisos y copiar al portapapeles. */
(function () {
  'use strict';

  var root = document.documentElement;
  var DDM = (window.DDM = window.DDM || {});

  DDM.base = document.body.getAttribute('data-base') || '';
  DDM.url = function (path) {
    return DDM.base + path;
  };
  DDM.csrf = function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  };

  /** POST con JSON. Siempre devuelve un objeto {ok, ...}; si la sesión ha caducado lleva al login. */
  DDM.api = function (path, body) {
    return fetch(DDM.url(path), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': DDM.csrf(),
      },
      body: JSON.stringify(body || {}),
    })
      .then(function (res) {
        return res.json().then(
          function (data) {
            if (res.status === 401) {
              window.location.href = DDM.url('/login');
            }
            return data;
          },
          function () {
            return { ok: false, error: 'Respuesta no válida del servidor.' };
          }
        );
      })
      .catch(function () {
        return { ok: false, error: 'No hay conexión con el servidor.' };
      });
  };

  var toastTimer = null;
  DDM.toast = function (message) {
    var old = document.querySelector('.toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      el.remove();
    }, 2600);
  };

  function copyFallback(text) {
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy') ? resolve() : reject();
      } catch (e) {
        reject(e);
      }
      ta.remove();
    });
  }

  /** Copia al portapapeles; si el navegador no deja usar la API moderna, usa el método clásico. */
  DDM.copy = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(function () {
        return copyFallback(text);
      });
    }
    return copyFallback(text);
  };

  /* ───── Tema ───── */
  function themeLabel() {
    var l = document.querySelector('[data-theme-label]');
    if (l) l.textContent = root.getAttribute('data-theme') === 'dark' ? 'Oscuro' : 'Claro';
  }
  themeLabel();
  var toggle = document.querySelector('[data-theme-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var dark = root.getAttribute('data-theme') !== 'dark';
      root.classList.add('theme-anim');
      if (dark) root.setAttribute('data-theme', 'dark');
      else root.removeAttribute('data-theme');
      try {
        localStorage.setItem('ddm-theme', dark ? 'dark' : 'light');
      } catch (e) {}
      themeLabel();
      setTimeout(function () {
        root.classList.remove('theme-anim');
      }, 600);
    });
  }
})();
