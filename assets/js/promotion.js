(function () {
  'use strict';
  function sync(form) {
    if (!form || !form.matches('[data-promotion-selector]')) return;
    var choice = form.querySelector('input[name="promotion_mode"]:checked');
    var code = form.querySelector('[data-promotion-code]');
    var field = form.querySelector('[data-promotion-reward]');
    var reward = choice && choice.value === 'reward';
    if (code) { code.disabled = !reward; code.required = !!reward; }
    if (field) field.hidden = !reward;
  }
  document.addEventListener('change', function (event) {
    if (event.target.matches('input[name="promotion_mode"]')) sync(event.target.closest('form'));
  });
  document.addEventListener('submit', function (event) { sync(event.target); }, true);
  document.querySelectorAll('[data-promotion-selector]').forEach(sync);
}());
