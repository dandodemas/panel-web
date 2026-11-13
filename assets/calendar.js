/* Calendario: arrastrar una pieza a otro día (conserva la hora). */
(function () {
  'use strict';

  var DDM = window.DDM;
  /* Móvil: la lista por días arranca en el día de hoy (o el siguiente con piezas) */
  var agenda = document.getElementById('agenda');
  if (agenda && window.matchMedia('(max-width: 760px)').matches) {
    var days = agenda.querySelectorAll('.ag-day');
    var today = new Date().toLocaleDateString('en-CA', { timeZone: 'Europe/Madrid' });
    for (var i = 0; i < days.length; i++) {
      if (days[i].getAttribute('data-date') >= today) {
        if (i > 0) days[i].scrollIntoView();
        break;
      }
    }
  }

  var cal = document.getElementById('calendar');
  if (!cal) return;
  var slug = cal.getAttribute('data-slug');
  var dragging = null;

  cal.addEventListener('dragstart', function (e) {
    var chip = e.target.closest && e.target.closest('.chip');
    if (!chip) return;
    dragging = chip;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', chip.getAttribute('data-id'));
    setTimeout(function () {
      chip.classList.add('dragging');
    }, 0);
  });

  function cleanup() {
    if (dragging) dragging.classList.remove('dragging');
    cal.querySelectorAll('.day.over').forEach(function (d) {
      d.classList.remove('over');
    });
    dragging = null;
  }
  cal.addEventListener('dragend', cleanup);

  cal.addEventListener('dragover', function (e) {
    if (!dragging) return;
    var day = e.target.closest('.day');
    if (!day) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    cal.querySelectorAll('.day.over').forEach(function (d) {
      if (d !== day) d.classList.remove('over');
    });
    day.classList.add('over');
  });

  cal.addEventListener('drop', function (e) {
    if (!dragging) return;
    var day = e.target.closest('.day');
    if (!day) return;
    e.preventDefault();
    var chip = dragging;
    var from = chip.closest('.day');
    cleanup();
    if (from === day) return;
    day.appendChild(chip);
    DDM.api('/b/' + slug + '/items/' + chip.getAttribute('data-id') + '/mover', { date: day.getAttribute('data-date') }).then(function (r) {
      if (!r.ok) {
        DDM.toast(r.error || 'No se ha podido mover. Recargando…');
        setTimeout(function () {
          window.location.reload();
        }, 900);
      } else {
        window.location.reload();
      }
    });
  });
})();
