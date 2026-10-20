(() => {
  'use strict';

  const sync = (form) => {
    if (!form?.matches('[data-promotion-selector]')) return;

    const choice = form.querySelector('input[name="promotion_mode"]:checked');
    const code = form.querySelector('[data-promotion-code]');
    const field = form.querySelector('[data-promotion-reward]');

    const isReward = choice?.value === 'reward';

    if (code) {
      code.disabled = !isReward;
      code.required = isReward;
    }

    if (field) {
      field.hidden = !isReward;
    }
  };

  document.addEventListener('change', (e) => {
    if (e.target.matches('input[name="promotion_mode"]')) {
      sync(e.target.closest('form'));
    }
  });

  document.addEventListener('submit', (e) => sync(e.target), true);

  document.querySelectorAll('[data-promotion-selector]').forEach(sync);
})();