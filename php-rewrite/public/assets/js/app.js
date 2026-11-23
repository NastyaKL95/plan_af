document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-iro-password-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-iro-password-toggle');
            const inp = id ? document.getElementById(id) : null;
            if (!inp || (inp.type !== 'password' && inp.type !== 'text')) {
                return;
            }
            const show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            const t = btn.querySelector('.iro-pw-toggle-text');
            if (t) {
                t.textContent = show ? 'Скрыть' : 'Показать';
            }
        });
    });

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) {
        return;
    }
    const cards = document.querySelectorAll('.card');
    cards.forEach((card) => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-2px)';
            card.style.transition = 'transform 0.12s ease';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
        });
    });
});
