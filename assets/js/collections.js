// Watchlist features: shuffle dialog, multi-select picker, Binge Watch helpers, folder preview.
document.addEventListener('DOMContentLoaded', () => {
    // ---- Shuffle dialog: fetches 5 random movies from shuffle.php ----
    const dlg = document.getElementById('shuffle-dialog');
    if (dlg) {
        const box = dlg.querySelector('#shuffle-results');
        let scope = 'watchlist';
        const ghosts = '<div class="shuffle-card ghost"></div>'.repeat(5);
        const load = async () => {
            if (!box.children.length) box.innerHTML = ghosts;
            box.classList.add('is-shuffling');
            let html;
            try {
                const [res] = await Promise.all([fetch('shuffle.php?scope=' + encodeURIComponent(scope)), new Promise(r => setTimeout(r, 700))]);
                html = res.ok ? await res.text() : null;
            } catch (e) { html = null; }
            box.innerHTML = html ?? '<p class="muted shuffle-note">Could not shuffle right now. Please try again.</p>';
            box.classList.remove('is-shuffling');
        };
        document.querySelectorAll('[data-shuffle]').forEach(b => b.addEventListener('click', () => { scope = b.dataset.shuffle; dlg.showModal(); load(); }));
        document.getElementById('shuffle-again')?.addEventListener('click', load);
    }

    // ---- Picker: live search, checkboxes, select all shown ----
    document.querySelectorAll('[data-picker]').forEach(form => {
        const q = form.querySelector('[data-q]'), cards = [...form.querySelectorAll('.pick-card')];
        const count = form.querySelector('[data-count]'), shown = form.querySelector('[data-shown]');
        const submit = form.querySelector('[data-submit]'), label = form.querySelector('[data-submit-label]'), empty = form.querySelector('[data-empty]');
        const boxes = cards.map(c => c.querySelector('input'));
        const update = () => {
            const n = boxes.filter(b => b.checked && !b.disabled).length;
            count.textContent = n + ' selected';
            submit.disabled = n === 0;
            label.textContent = n ? `Add ${n} movie${n > 1 ? 's' : ''} to ${form.dataset.target}` : 'Select movies to add';
        };
        const filter = () => {
            const term = q.value.trim().toLowerCase();
            let visible = 0;
            cards.forEach(c => { const ok = !term || c.dataset.search.includes(term); c.hidden = !ok; if (ok) visible++; });
            shown.textContent = visible + ' shown';
            empty.hidden = visible > 0;
        };
        q.addEventListener('input', filter);
        q.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
        form.addEventListener('change', update);
        form.querySelector('[data-select-shown]').addEventListener('click', () => { cards.forEach(c => { const b = c.querySelector('input'); if (!c.hidden && !b.disabled) b.checked = true; }); update(); });
        form.querySelector('[data-clear]').addEventListener('click', () => { boxes.forEach(b => b.checked = false); update(); });
        update();
    });

    // ---- Binge Watch: tapping a movie fills the answer box; reopen the dialog after a bad answer ----
    document.querySelectorAll('[data-fill]').forEach(b => b.addEventListener('click', () => {
        const input = document.getElementById('binge-title');
        if (input) { input.value = b.dataset.fill; input.focus(); }
    }));
    if (new URLSearchParams(location.search).has('binge')) document.getElementById('binge-dialog')?.showModal();

    // ---- Folder form: live preview ----
    const ff = document.querySelector('[data-folder-form]');
    if (ff) {
        const prev = ff.querySelector('[data-preview]');
        const sync = () => {
            ff.querySelector('[data-prev-name]').textContent = ff.querySelector('[name="name"]').value.trim() || 'Folder name';
            ff.querySelector('[data-prev-desc]').textContent = ff.querySelector('[name="description"]').value.trim() || 'Description';
            const col = ff.querySelector('input[name="color"]:checked'), ic = ff.querySelector('input[name="icon"]:checked');
            if (col) prev.style.setProperty('--fc', col.nextElementSibling.style.getPropertyValue('--sw'));
            if (ic) ff.querySelector('[data-prev-icon]').innerHTML = ic.nextElementSibling.innerHTML;
        };
        ff.addEventListener('input', sync);
        ff.addEventListener('change', sync);
    }
});
