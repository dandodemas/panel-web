/* Tablero: arrastrar tarjetas entre columnas y reordenarlas. Se guarda en el servidor al soltar. */
(function () {
  'use strict';

  var DDM = window.DDM;
  var board = document.getElementById('board');
  if (!board) return;
  var slug = board.getAttribute('data-slug');

  var dragging = null;
  var placeholder = document.createElement('div');
  placeholder.className = 'drop-ph';

  function updateCounts() {
    board.querySelectorAll('.col').forEach(function (col) {
      var n = col.querySelectorAll('.card').length;
      col.querySelector('[data-count]').textContent = n;
      var tab = tabFor(col.getAttribute('data-status'));
      if (tab) tab.querySelector('[data-tab-count]').textContent = n;
    });
  }

  /* ───── Móvil: pestañas (una columna cada vez) ───── */
  var tabsEl = document.getElementById('board-tabs');
  var fab = document.querySelector('.fab[data-new-item]');
  var current = null;

  function tabFor(status) {
    return tabsEl ? tabsEl.querySelector('[data-tab="' + status + '"]') : null;
  }
  function showTab(status, remember) {
    var cols = board.querySelectorAll('.col');
    var found = false;
    cols.forEach(function (c) {
      if (c.getAttribute('data-status') === status) found = true;
    });
    if (!found) status = cols[0].getAttribute('data-status');
    current = status;
    cols.forEach(function (c) {
      c.classList.toggle('on', c.getAttribute('data-status') === status);
    });
    if (tabsEl) {
      tabsEl.querySelectorAll('.tab').forEach(function (t) {
        var on = t.getAttribute('data-tab') === status;
        t.classList.toggle('on', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        if (on && t.scrollIntoView) {
          try {
            tabsEl.scrollTo({ left: Math.max(0, t.getBoundingClientRect().left - tabsEl.getBoundingClientRect().left + tabsEl.scrollLeft - 16), behavior: 'smooth' });
          } catch (e) {}
        }
      });
    }
    if (fab) fab.setAttribute('data-new-item', status);
    if (remember) {
      try {
        sessionStorage.setItem('ddm-tab-' + slug, status);
      } catch (e) {}
    }
  }
  if (tabsEl) {
    tabsEl.addEventListener('click', function (e) {
      var t = e.target.closest('[data-tab]');
      if (t) showTab(t.getAttribute('data-tab'), true);
    });
    var saved = null;
    try {
      saved = sessionStorage.getItem('ddm-tab-' + slug);
    } catch (e) {}
    showTab(saved || board.querySelector('.col').getAttribute('data-status'), false);
  }

  /* ───── Móvil: botón «Mover» (sin arrastrar) ───── */
  var order = [];
  var labels = {};
  board.querySelectorAll('.col').forEach(function (c) {
    var st = c.getAttribute('data-status');
    order.push(st);
    labels[st] = c.getAttribute('aria-label') || st;
  });
  var sheetEl = null;
  function closeMove() {
    if (sheetEl) sheetEl.hidden = true;
  }
  function openMove(card) {
    if (!sheetEl) {
      sheetEl = document.createElement('div');
      sheetEl.className = 'move-sheet';
      sheetEl.hidden = true;
      sheetEl.innerHTML =
        '<div class="ms-back" data-ms-close></div><div class="ms-box" role="dialog" aria-modal="true" aria-label="Mover a otra columna">' +
        '<h2 class="ms-title">Mover a…</h2><p class="ms-sub"></p><div class="ms-list"></div></div>';
      document.body.appendChild(sheetEl);
      sheetEl.addEventListener('click', function (e) {
        if (e.target.closest('[data-ms-close]')) return closeMove();
        var opt = e.target.closest('[data-ms-status]');
        if (!opt) return;
        var card2 = sheetEl._card;
        var status = opt.getAttribute('data-ms-status');
        var from = card2.closest('.col').getAttribute('data-status');
        closeMove();
        if (status === from) return;
        var zone = board.querySelector('.col[data-status="' + status + '"] [data-dropzone]');
        zone.appendChild(card2);
        updateCounts();
        DDM.toast('Movido a ' + labels[status]);
        DDM.api('/b/' + slug + '/items/' + card2.getAttribute('data-id') + '/mover', { status: status, position: 9999 }).then(function (r) {
          if (!r.ok) {
            DDM.toast(r.error || 'No se ha podido mover. Recargando…');
            setTimeout(function () {
              window.location.reload();
            }, 900);
          }
        });
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMove();
      });
    }
    sheetEl._card = card;
    var from = card.closest('.col').getAttribute('data-status');
    sheetEl.querySelector('.ms-sub').textContent = card.querySelector('.ctitle') ? card.querySelector('.ctitle').textContent : '';
    sheetEl.querySelector('.ms-list').innerHTML = order
      .map(function (st) {
        return '<button type="button" class="ms-opt" data-ms-status="' + st + '"' + (st === from ? ' aria-current="true"' : '') + '><span>' + labels[st] + '</span>' + (st === from ? '<span>aquí</span>' : '') + '</button>';
      })
      .join('');
    sheetEl.hidden = false;
  }
  board.addEventListener('click', function (e) {
    var mv = e.target.closest('[data-move]');
    if (!mv) return;
    e.stopPropagation();
    openMove(mv.closest('.card'));
  });

  function cleanup() {
    if (dragging) dragging.classList.remove('dragging');
    placeholder.remove();
    board.querySelectorAll('.cards.over').forEach(function (z) {
      z.classList.remove('over');
    });
    dragging = null;
  }

  function cardAfter(zone, y) {
    var cards = Array.prototype.slice.call(zone.querySelectorAll('.card:not(.dragging)'));
    for (var i = 0; i < cards.length; i++) {
      var r = cards[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) return cards[i];
    }
    return null;
  }

  board.addEventListener('dragstart', function (e) {
    var card = e.target.closest && e.target.closest('.card');
    if (!card) return;
    dragging = card;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', card.getAttribute('data-id'));
    setTimeout(function () {
      card.classList.add('dragging');
    }, 0);
  });

  board.addEventListener('dragend', cleanup);

  board.addEventListener('dragover', function (e) {
    if (!dragging) return;
    var zone = e.target.closest('[data-dropzone]');
    if (!zone) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    board.querySelectorAll('.cards.over').forEach(function (z) {
      if (z !== zone) z.classList.remove('over');
    });
    zone.classList.add('over');
    var after = cardAfter(zone, e.clientY);
    if (after) zone.insertBefore(placeholder, after);
    else zone.appendChild(placeholder);
  });

  board.addEventListener('drop', function (e) {
    if (!dragging) return;
    var zone = e.target.closest('[data-dropzone]');
    if (!zone) return;
    e.preventDefault();
    var card = dragging;
    var fromZone = card.parentNode;
    if (placeholder.parentNode === zone) zone.insertBefore(card, placeholder);
    else zone.appendChild(card);
    var status = zone.closest('.col').getAttribute('data-status');
    var position = Array.prototype.indexOf.call(zone.querySelectorAll('.card'), card);
    cleanup();
    updateCounts();
    DDM.api('/b/' + slug + '/items/' + card.getAttribute('data-id') + '/mover', { status: status, position: position }).then(function (r) {
      if (!r.ok) {
        DDM.toast(r.error || 'No se ha podido mover. Recargando…');
        setTimeout(function () {
          window.location.reload();
        }, 900);
      }
    });
    void fromZone;
  });
})();
