(() => {
  'use strict';
  const manager = document.querySelector('[data-update-manager]');
  if (!manager) return;
  const dialog = manager.querySelector('[data-update-confirm]');
  if (!dialog || typeof dialog.showModal !== 'function') {
    manager.querySelectorAll('[data-update-action] button').forEach(button => { button.disabled = true; });
    return;
  }
  let selected = null;
  let submitted = false;
  manager.querySelectorAll('[data-update-action]').forEach(form => {
    const confirm = event => {
      event.preventDefault();
      if (submitted || form.querySelector('button').disabled) return;
      selected = form;
      dialog.querySelector('h2').textContent = form.dataset.confirmTitle;
      dialog.querySelector('[data-update-confirm-body]').textContent = form.dataset.confirmBody;
      dialog.querySelector('[data-update-confirm-version]').textContent = form.dataset.confirmVersion;
      dialog.showModal();
      dialog.querySelector('[data-update-cancel]').focus();
    };
    form.addEventListener('submit', confirm);
    form.querySelector('button').addEventListener('click', confirm);
  });
  dialog.querySelector('[data-update-cancel]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { selected = null; });
  dialog.querySelector('[data-update-proceed]').addEventListener('click', () => {
    if (!selected || submitted) return;
    submitted = true;
    manager.querySelectorAll('button').forEach(button => { button.disabled = true; });
    HTMLFormElement.prototype.submit.call(selected);
  });
})();
