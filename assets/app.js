document.addEventListener('click', (e) => {
    if (e.target.matches('[data-add-row]')) {
        const tpl = document.querySelector('#item-template');
        const tbody = document.querySelector('#items-body');
        if (!tpl || !tbody) return;
        tbody.insertAdjacentHTML('beforeend', tpl.innerHTML);
    }

    if (e.target.matches('[data-remove-row]')) {
        const row = e.target.closest('tr');
        if (row) row.remove();
    }
});
