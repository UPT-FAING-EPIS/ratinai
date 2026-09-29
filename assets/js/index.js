/* Interacciones de la portada pública de RetinAI. */
(() => {
    'use strict';

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finePointer = window.matchMedia('(pointer: fine)');
    const menuButton = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('#site-navigation');
    const mobileViewport = window.matchMedia('(max-width: 680px)');

    const closeMenu = (restoreFocus = false) => {
        if (!menuButton || !navigation) return;
        navigation.classList.remove('is-open');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-label', 'Abrir menú');
        if (restoreFocus) menuButton.focus();
    };

    menuButton?.addEventListener('click', () => {
        const opened = menuButton.getAttribute('aria-expanded') !== 'true';
        navigation.classList.toggle('is-open', opened);
        menuButton.setAttribute('aria-expanded', String(opened));
        menuButton.setAttribute('aria-label', opened ? 'Cerrar menú' : 'Abrir menú');
    });
    navigation?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => closeMenu()));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menuButton?.getAttribute('aria-expanded') === 'true') closeMenu(true);
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.header-inner')) closeMenu();
    });
    mobileViewport.addEventListener('change', () => closeMenu());
    document.querySelector('.site-header')?.classList.add('navigation-ready');

    // La misma ilustración funciona sin puntero y con movimiento reducido.
    const hero = document.querySelector('.hero');
    const scene = document.querySelector('.eye-scene');
    const gaze = document.querySelector('#eye-gaze');
    let pointerFrame = 0;
    const resetGaze = () => {
        cancelAnimationFrame(pointerFrame);
        pointerFrame = 0;
        gaze?.style.setProperty('--gaze-x', '0px');
        gaze?.style.setProperty('--gaze-y', '0px');
    };
    hero?.addEventListener('pointermove', event => {
        if (reducedMotion.matches || !finePointer.matches || !gaze || !scene) return;
        cancelAnimationFrame(pointerFrame);
        pointerFrame = requestAnimationFrame(() => {
            const bounds = scene.getBoundingClientRect();
            const x = (event.clientX - bounds.left - bounds.width / 2) / (bounds.width / 2);
            const y = (event.clientY - bounds.top - bounds.height / 2) / (bounds.height / 2);
            gaze.style.setProperty('--gaze-x', Math.max(-19, Math.min(19, x * 19)) + 'px');
            gaze.style.setProperty('--gaze-y', Math.max(-12, Math.min(12, y * 12)) + 'px');
        });
    }, { passive: true });
    hero?.addEventListener('pointerleave', resetGaze);
    reducedMotion.addEventListener('change', resetGaze);

    const tabs = [...document.querySelectorAll('.demo-tab')];
    const panels = [...document.querySelectorAll('.demo-panel')];
    const activateTab = tab => {
        tabs.forEach(item => {
            const active = item === tab;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', String(active));
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = panel.id !== tab.getAttribute('aria-controls'); });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activateTab(tab));
        tab.addEventListener('keydown', event => {
            let target = index;
            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') target = (index + 1) % tabs.length;
            else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') target = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') target = 0;
            else if (event.key === 'End') target = tabs.length - 1;
            else return;
            event.preventDefault();
            activateTab(tabs[target]);
            tabs[target].focus();
        });
    });
    const demoViewport = window.matchMedia('(max-width: 900px)');
    const updateTabOrientation = () => document.querySelector('.demo-tabs')?.setAttribute('aria-orientation', demoViewport.matches ? 'horizontal' : 'vertical');
    updateTabOrientation();
    demoViewport.addEventListener('change', updateTabOrientation);

    if ('IntersectionObserver' in window && !reducedMotion.matches) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.remove('is-waiting');
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.08 });
        document.querySelectorAll('.reveal').forEach(element => {
            if (element.getBoundingClientRect().top < window.innerHeight) return;
            element.classList.add('is-waiting');
            observer.observe(element);
        });
        reducedMotion.addEventListener('change', () => {
            if (!reducedMotion.matches) return;
            observer.disconnect();
            document.querySelectorAll('.is-waiting').forEach(element => element.classList.remove('is-waiting'));
        });
    }

    // Conservar la navegación habitual al abrir enlaces en otra pestaña.
    let entering = false;
    let navigationTimer = 0;
    document.querySelectorAll('[data-enter]').forEach(link => {
        link.addEventListener('click', event => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || reducedMotion.matches) return;
            if (entering) { event.preventDefault(); return; }
            event.preventDefault();
            entering = true;
            document.body.classList.add('is-entering');
            navigationTimer = window.setTimeout(() => window.location.assign(link.href), 480);
        });
    });
    window.addEventListener('pageshow', () => {
        clearTimeout(navigationTimer);
        entering = false;
        document.body.classList.remove('is-entering');
        closeMenu();
        resetGaze();
    });
    const year = document.querySelector('#copyright-year');
    if (year) year.textContent = String(new Date().getFullYear());
})();
