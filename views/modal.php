<?php /* Ventana de crear/editar una pieza, con sus fotos. La rellena assets/modal.js */ ?>
<div class="overlay" id="modal" hidden>
  <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="modal-title" tabindex="-1">
    <button type="button" class="x-close" data-close aria-label="Cerrar"></button>
    <h2 id="modal-title" class="sheet-title">Nuevo contenido</h2>

    <form id="item-form" autocomplete="off" novalidate>
      <label class="field">
        <span>Título</span>
        <input name="title" required maxlength="200">
      </label>

      <div class="grid-2">
        <label class="field">
          <span>Plataforma</span>
          <input name="platform" required maxlength="60" list="platform-list">
          <datalist id="platform-list">
<?php foreach (PLATFORM_SUGGESTIONS as $p): ?>
            <option value="<?= e($p) ?>"></option>
<?php endforeach; ?>
          </datalist>
        </label>
        <label class="field">
          <span>Tipo</span>
          <select name="contentType">
<?php foreach (TYPE_ORDER as $t): ?>
            <option value="<?= e($t) ?>"><?= e(TYPE_LABEL[$t]) ?></option>
<?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="grid-2">
        <label class="field">
          <span>Fecha</span>
          <input name="date" type="date" required>
        </label>
        <label class="field">
          <span>Hora de subida</span>
          <input name="time" type="time" step="1800">
        </label>
      </div>

      <label class="field">
        <span>Estado</span>
        <select name="status">
<?php foreach (STATUS_ORDER as $s): ?>
          <option value="<?= e($s) ?>"><?= e(STATUS_LABEL[$s]) ?></option>
<?php endforeach; ?>
        </select>
      </label>

      <label class="field">
        <span class="field-line"><span>Enlace de la creatividad</span><span class="field-links"><button type="button" class="link" id="creative-drive" hidden>Traer foto</button><a class="link" id="creative-open" href="#" target="_blank" rel="noopener noreferrer" hidden>Abrir ↗</a></span></span>
        <input name="creativeUrl" type="url" maxlength="2000" placeholder="https://… (Drive, Canva, Figma…)">
      </label>

      <label class="field">
        <span>Notas</span>
        <textarea name="notes" rows="3" maxlength="2000"></textarea>
      </label>

      <div class="photos" id="photos">
        <div class="field-line">
          <span class="label">Fotos</span>
          <span class="mute small">Se borran solas a los <?= (int) photo_days() ?> días</span>
        </div>
        <div class="ph-grid" id="ph-grid"></div>
        <label class="ph-add" id="ph-add">
          <input type="file" id="ph-input" accept="image/jpeg,image/png,image/webp" multiple hidden>
          <span class="x" aria-hidden="true"></span> Añadir fotos
        </label>
        <p class="mute small" id="ph-hint">JPG, PNG o WebP. Se optimizan al subirlas.</p>
      </div>

      <p class="msg msg-err" id="form-err" role="alert" hidden></p>

      <div class="actions">
        <button type="submit" class="cta cta-lg" id="btn-save">Guardar</button>
        <button type="button" class="link link-danger" id="btn-delete" hidden>Borrar</button>
      </div>
    </form>
  </div>
</div>

<div class="lightbox" id="lightbox" hidden>
  <button type="button" class="x-close x-close-light" data-close-lightbox aria-label="Cerrar foto"></button>
  <img alt="" id="lightbox-img">
</div>
