document.addEventListener('click', function (event) {
    const heading = event.target.closest('[data-division-id]');

    if (! heading) {
        return;
    }

    const isSelected = heading.dataset.selected === 'true';
    const url = new URL(window.location.href);
    url.searchParams.set('division', isSelected ? 'all' : heading.dataset.divisionId);

    window.location.href = url.toString();
});
