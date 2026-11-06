import './bootstrap';

// Inline handlers (onsubmit="…") are blocked by the Content-Security-Policy,
// and interpolating names into them was an XSS risk. Behaviour is declared
// with data attributes instead, and the text is only ever read, never run.

// <form data-confirm="Delete Acme?">: ask before submitting.
document.addEventListener('submit', (event) => {
    const message = event.target instanceof HTMLFormElement ? event.target.dataset.confirm : undefined;

    if (message && !window.confirm(message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);

// <select data-autosubmit>: submit the surrounding form on change.
document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLSelectElement && 'autosubmit' in event.target.dataset) {
        event.target.form?.requestSubmit();
    }
});
