(() => {
  'use strict';
  document.addEventListener('submit', (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    const message = form?.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
  });
})();
