const drawer = document.getElementById('drawer');
const backdrop = document.getElementById('drawerBackdrop');
const openBtn = document.getElementById('drawerOpen');

function setDrawer(open) {
    drawer.classList.toggle('show', open);
    backdrop.classList.toggle('show', open);
    drawer.setAttribute('aria-hidden', !open);
    openBtn.setAttribute('aria-expanded', open);
    document.body.style.overflow = open ? 'hidden' : '';
    (open ? document.getElementById('drawerClose') : openBtn).focus();
}

openBtn.addEventListener('click', () => setDrawer(true));
document.getElementById('drawerClose').addEventListener('click', () => setDrawer(false));
backdrop.addEventListener('click', () => setDrawer(false));
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && drawer.classList.contains('show')) setDrawer(false);
});

if (window.jQuery && jQuery.fn.datepicker) {
    jQuery('.js-date-picker').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        yearRange: 'c-5:c+5'
    });
}