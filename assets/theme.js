/* Aplica el tema guardado antes de pintar (misma clave que dandodemas.com). */
(function () {
  try {
    if (localStorage.getItem('ddm-theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  } catch (e) {}
})();
