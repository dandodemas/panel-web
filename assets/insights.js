/* Insights: al pasar por una barra (o tocarla en el móvil) sale una tarjeta con el preview de la publicación. */
(function () {
  'use strict';
  var plot = document.querySelector('.chart-plot');
  if (!plot) return;
  var bars = plot.querySelectorAll('.chart-bar[data-day]');
  if (!bars.length) return;

  var tip = document.createElement('div');
  tip.className = 'chart-tip';
  tip.hidden = true;
  tip.setAttribute('role', 'tooltip');
  plot.style.position = 'relative';
  plot.appendChild(tip);

  var LABELS = [['likes', '♥ Me gusta'], ['comments', 'Comentarios'], ['views', 'Visualizaciones'], ['reach', 'Alcance'], ['saves', 'Guardados'], ['shares', 'Compartidos']];

  function fmt(n) {
    return Number(n).toLocaleString('es-ES');
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  function show(bar) {
    var d = bar.dataset;
    tip.textContent = '';
    if (d.thumb) {
      var img = el('img', 'tip-img');
      img.src = d.thumb;
      img.alt = '';
      img.onerror = function () {
        img.remove();
      };
      tip.appendChild(img);
    }
    var body = el('div', 'tip-body');
    body.appendChild(el('div', 'tip-meta', d.day + (d.time ? ' · ' + d.time : '') + ' · ' + d.kind));
    var text = d.title || d.caption;
    if (text) body.appendChild(el('div', 'tip-text', text));
    body.appendChild(el('div', 'tip-value', d.value));
    var stats = {};
    try {
      stats = JSON.parse(d.stats || '{}');
    } catch (e) {}
    var list = el('ul', 'tip-stats');
    LABELS.forEach(function (l) {
      if (stats[l[0]] !== null && stats[l[0]] !== undefined && stats[l[0]] !== '') {
        var li = el('li');
        li.appendChild(el('span', '', l[1]));
        li.appendChild(el('b', '', fmt(stats[l[0]])));
        list.appendChild(li);
      }
    });
    if (list.children.length) body.appendChild(list);
    if (d.link) {
      var a = el('a', 'tip-link', 'Ver publicación ↗');
      a.href = d.link;
      a.target = '_blank';
      a.rel = 'noopener';
      body.appendChild(a);
    }
    tip.appendChild(body);
    tip.hidden = false;

    var pr = plot.getBoundingClientRect();
    var br = bar.getBoundingClientRect();
    var left = br.left - pr.left + br.width / 2 - tip.offsetWidth / 2;
    left = Math.max(0, Math.min(left, pr.width - tip.offsetWidth));
    var top = br.top - pr.top - tip.offsetHeight - 10;
    if (top < 0) top = br.bottom - pr.top + 10;
    tip.style.left = left + 'px';
    tip.style.top = top + 'px';
  }
  function hide() {
    tip.hidden = true;
  }

  bars.forEach(function (bar) {
    bar.addEventListener('mouseenter', function () {
      show(bar);
    });
    bar.addEventListener('focus', function () {
      show(bar);
    });
    bar.addEventListener('mouseleave', function (e) {
      if (!e.relatedTarget || !tip.contains(e.relatedTarget)) setTimeout(function () {
        if (!tip.matches(':hover')) hide();
      }, 120);
    });
    bar.addEventListener('blur', hide);
    bar.addEventListener('click', function (e) {
      e.stopPropagation();
      show(bar);
    });
  });
  tip.addEventListener('mouseleave', hide);
  document.addEventListener('click', hide);
})();
