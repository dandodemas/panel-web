/* Ventana de crear/editar una pieza, con subida de fotos. Se abre desde el tablero y el calendario. */
(function () {
  'use strict';

  var DDM = window.DDM;
  var bootEl = document.getElementById('boot');
  var modal = document.getElementById('modal');
  if (!bootEl || !modal) return;

  var boot = JSON.parse(bootEl.textContent);
  var slug = boot.slug;
  var items = {};
  boot.items.forEach(function (it) {
    items[it.id] = it;
  });

  var sheet = modal.querySelector('.sheet');
  var form = document.getElementById('item-form');
  var titleEl = document.getElementById('modal-title');
  var errEl = document.getElementById('form-err');
  var btnSave = document.getElementById('btn-save');
  var btnDelete = document.getElementById('btn-delete');
  var phGrid = document.getElementById('ph-grid');
  var phInput = document.getElementById('ph-input');
  var phAdd = document.getElementById('ph-add');
  var creativeOpen = document.getElementById('creative-open');
  var creativeDrive = document.getElementById('creative-drive');
  var lightbox = document.getElementById('lightbox');
  var lightboxImg = document.getElementById('lightbox-img');

  var MAX_BYTES = 15 * 1024 * 1024;
  var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

  var state = { mode: 'new', id: null, queue: [], busy: false, changed: false, opener: null };

  function showError(msg) {
    errEl.textContent = msg;
    errEl.hidden = !msg;
  }

  function field(name) {
    return form.elements[name];
  }

  /* ───── Fotos ───── */
  function makeTile(cls) {
    var t = document.createElement('div');
    t.className = 'ph ' + (cls || '');
    return t;
  }

  function renderPhoto(p) {
    var t = makeTile(p.expired ? 'ph-expired' : '');
    t.setAttribute('data-photo', p.id);
    if (p.expired) {
      t.textContent = 'Foto caducada';
      return t;
    }
    var img = document.createElement('img');
    img.src = p.thumb;
    img.alt = p.name || 'Foto';
    img.loading = 'lazy';
    img.addEventListener('click', function () {
      lightboxImg.src = p.url;
      lightbox.hidden = false;
    });
    var del = document.createElement('button');
    del.type = 'button';
    del.className = 'ph-del';
    del.setAttribute('aria-label', 'Borrar foto');
    del.textContent = '×';
    del.addEventListener('click', function () {
      DDM.api('/b/' + slug + '/fotos/' + p.id + '/borrar').then(function (r) {
        if (!r.ok) return DDM.toast(r.error || 'No se ha podido borrar la foto.');
        t.remove();
        state.changed = true;
        var it = items[state.id];
        if (it) it.photos = it.photos.filter(function (x) {
          return x.id !== p.id;
        });
      });
    });
    var days = document.createElement('span');
    days.className = 'ph-days';
    days.textContent = p.daysLeft + ' d';
    days.title = 'Se borra sola el ' + p.expiresAt;
    t.appendChild(img);
    t.appendChild(del);
    t.appendChild(days);
    return t;
  }

  function renderPhotos(photos) {
    phGrid.textContent = '';
    (photos || []).forEach(function (p) {
      phGrid.appendChild(renderPhoto(p));
    });
  }

  function checkFile(file) {
    if (ALLOWED.indexOf(file.type) === -1) return 'Formato no admitido (usa JPG, PNG o WebP).';
    if (file.size > MAX_BYTES) return 'Pesa más de 15 MB.';
    return '';
  }

  function uploadOne(itemId, file, tile) {
    return new Promise(function (resolve) {
      var bar = tile.querySelector('i');
      var xhr = new XMLHttpRequest();
      xhr.open('POST', DDM.url('/b/' + slug + '/items/' + itemId + '/fotos'));
      xhr.setRequestHeader('X-CSRF-Token', DDM.csrf());
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable && bar) bar.style.transform = 'scaleX(' + (e.loaded / e.total).toFixed(3) + ')';
      };
      xhr.onload = function () {
        var r;
        try {
          r = JSON.parse(xhr.responseText);
        } catch (e) {
          r = { ok: false, errors: ['Respuesta no válida del servidor.'] };
        }
        resolve(r);
      };
      xhr.onerror = function () {
        resolve({ ok: false, errors: ['Sin conexión.'] });
      };
      var fd = new FormData();
      fd.append('photos', file);
      xhr.send(fd);
    });
  }

  function uploadFiles(itemId, files) {
    var chain = Promise.resolve();
    files.forEach(function (file) {
      chain = chain.then(function () {
        var tile = makeTile('ph-up');
        tile.innerHTML = '<span></span><i></i>';
        tile.firstChild.textContent = 'Subiendo…';
        phGrid.appendChild(tile);
        return uploadOne(itemId, file, tile).then(function (r) {
          if (r.ok && r.photos && r.photos.length) {
            var photo = r.photos[0];
            var real = renderPhoto(photo);
            tile.replaceWith(real);
            state.changed = true;
            var it = items[itemId];
            if (it) it.photos.push(photo);
          } else {
            var msg = (r.errors && r.errors[0]) || r.error || 'No se ha podido subir.';
            tile.className = 'ph ph-err';
            tile.textContent = msg.replace(/^[^:]*: /, '');
            tile.title = msg;
          }
        });
      });
    });
    return chain;
  }

  function addFiles(fileList) {
    var files = Array.prototype.slice.call(fileList || []);
    var bad = [];
    var good = [];
    files.forEach(function (f) {
      var problem = checkFile(f);
      if (problem) bad.push(f.name + ': ' + problem);
      else good.push(f);
    });
    if (bad.length) showError(bad.join(' · '));
    else showError('');
    if (!good.length) return;

    if (state.mode === 'edit') {
      uploadFiles(state.id, good);
    } else {
      // Pieza nueva: las fotos se suben al guardar.
      good.forEach(function (f) {
        state.queue.push(f);
        var t = makeTile('');
        var img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.alt = f.name;
        t.appendChild(img);
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'ph-del';
        del.setAttribute('aria-label', 'Quitar foto');
        del.textContent = '×';
        del.addEventListener('click', function () {
          state.queue = state.queue.filter(function (x) {
            return x !== f;
          });
          t.remove();
        });
        t.appendChild(del);
        phGrid.appendChild(t);
      });
    }
  }

  phInput.addEventListener('change', function () {
    addFiles(phInput.files);
    phInput.value = '';
  });
  ['dragenter', 'dragover'].forEach(function (ev) {
    sheet.addEventListener(ev, function (e) {
      if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) {
        e.preventDefault();
        phAdd.classList.add('over');
      }
    });
  });
  sheet.addEventListener('dragleave', function (e) {
    if (!sheet.contains(e.relatedTarget)) phAdd.classList.remove('over');
  });
  sheet.addEventListener('drop', function (e) {
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
      e.preventDefault();
      phAdd.classList.remove('over');
      addFiles(e.dataTransfer.files);
    }
  });

  /* ───── Abrir / cerrar ───── */
  function open(opts) {
    opts = opts || {};
    var item = opts.item || null;
    state = { mode: item ? 'edit' : 'new', id: item ? item.id : null, queue: [], busy: false, changed: false, opener: document.activeElement };

    form.reset();
    showError('');
    titleEl.textContent = item ? 'Editar contenido' : 'Nuevo contenido';
    btnDelete.hidden = !item;
    btnSave.disabled = false;
    btnSave.textContent = 'Guardar';

    field('title').value = item ? item.title : '';
    field('platform').value = item ? item.platform : 'Instagram';
    field('contentType').value = item ? item.contentType : 'POST';
    field('date').value = item ? item.date : opts.date || boot.today || '';
    field('time').value = item ? item.time : '12:00';
    field('status').value = item ? item.status : opts.status || 'IDEA';
    field('creativeUrl').value = item ? item.creativeUrl : '';
    field('notes').value = item ? item.notes : '';
    syncCreativeLink();
    renderPhotos(item ? item.photos : []);

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    setTimeout(function () {
      field('title').focus();
    }, 30);
  }

  function close() {
    lightbox.hidden = true;
    modal.hidden = true;
    document.body.style.overflow = '';
    if (state.changed) {
      window.location.reload();
      return;
    }
    if (state.opener && state.opener.focus) state.opener.focus();
  }

  function syncCreativeLink() {
    var v = field('creativeUrl').value.trim();
    var ok = /^https?:\/\/.+/i.test(v);
    creativeOpen.hidden = !ok;
    if (ok) creativeOpen.href = v;
    // «Traer foto» solo en piezas ya guardadas con un enlace de Drive (el servidor usa el enlace guardado).
    var it = state.mode === 'edit' ? items[state.id] : null;
    creativeDrive.hidden = !(it && /^https:\/\/(drive|docs)\.google\.com\//i.test(it.creativeUrl || '') && v === it.creativeUrl);
  }

  creativeDrive.addEventListener('click', function () {
    var itemId = state.id;
    creativeDrive.disabled = true;
    creativeDrive.textContent = 'Trayendo…';
    DDM.api('/b/' + slug + '/items/' + itemId + '/fotos/drive').then(function (r) {
      creativeDrive.disabled = false;
      creativeDrive.textContent = 'Traer foto';
      if (!r.ok) return DDM.toast(r.error || 'No se ha podido traer la foto.');
      var photo = r.photos[0];
      phGrid.appendChild(renderPhoto(photo));
      state.changed = true;
      var it = items[itemId];
      if (it) it.photos.push(photo);
    });
  });
  field('creativeUrl').addEventListener('input', syncCreativeLink);

  modal.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) close();
  });
  lightbox.addEventListener('click', function (e) {
    if (e.target === lightbox || e.target.closest('[data-close-lightbox]')) lightbox.hidden = true;
  });
  document.addEventListener('keydown', function (e) {
    if (modal.hidden) return;
    if (e.key === 'Escape') {
      if (!lightbox.hidden) lightbox.hidden = true;
      else close();
    } else if (e.key === 'Tab') {
      var f = sheet.querySelectorAll('a[href],button:not([disabled]):not([hidden]),input:not([hidden]),select,textarea');
      f = Array.prototype.filter.call(f, function (n) {
        return n.offsetParent !== null;
      });
      if (!f.length) return;
      var first = f[0];
      var last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  /* ───── Guardar / borrar ───── */
  function formData() {
    return {
      title: field('title').value.trim(),
      platform: field('platform').value.trim(),
      contentType: field('contentType').value,
      date: field('date').value,
      time: field('time').value,
      status: field('status').value,
      creativeUrl: field('creativeUrl').value.trim(),
      notes: field('notes').value.trim(),
    };
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (state.busy) return;
    var data = formData();
    if (!data.title) return showError('El título es obligatorio.');
    if (!data.platform) return showError('Indica una plataforma.');
    if (!data.date) return showError('Indica una fecha.');

    state.busy = true;
    btnSave.disabled = true;
    btnSave.textContent = 'Guardando…';
    showError('');

    var path = state.mode === 'edit' ? '/b/' + slug + '/items/' + state.id : '/b/' + slug + '/items';
    DDM.api(path, data).then(function (r) {
      if (!r.ok) {
        state.busy = false;
        btnSave.disabled = false;
        btnSave.textContent = 'Guardar';
        return showError(r.error || 'No se ha podido guardar.');
      }
      var id = state.mode === 'edit' ? state.id : r.id;
      var done = function () {
        window.location.reload();
      };
      if (state.queue.length) {
        btnSave.textContent = 'Subiendo fotos…';
        uploadFiles(id, state.queue).then(done);
      } else {
        done();
      }
    });
  });

  btnDelete.addEventListener('click', function () {
    if (state.mode !== 'edit') return;
    if (!window.confirm('¿Borrar esta pieza y sus fotos? No se puede deshacer.')) return;
    DDM.api('/b/' + slug + '/items/' + state.id + '/borrar').then(function (r) {
      if (!r.ok) return showError(r.error || 'No se ha podido borrar.');
      window.location.reload();
    });
  });

  /* ───── Disparadores desde la página ───── */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-move]')) return;
    var add = e.target.closest('[data-new-item]');
    if (add) {
      e.preventDefault();
      open({ date: add.getAttribute('data-date') || '', status: add.getAttribute('data-new-item') || '' });
      return;
    }
    var el = e.target.closest('.card[data-id], .chip[data-id], .ag-item[data-id]');
    if (el && items[el.getAttribute('data-id')]) {
      open({ item: items[el.getAttribute('data-id')] });
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var el = e.target.closest && e.target.closest('.card[data-id], .chip[data-id], .ag-item[data-id]');
    if (el && e.target === el && items[el.getAttribute('data-id')]) {
      e.preventDefault();
      open({ item: items[el.getAttribute('data-id')] });
    }
  });

  DDM.itemModal = { open: open, close: close };
})();
