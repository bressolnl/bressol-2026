document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.querySelector('[data-bressol-drawer]');
    const hamburger = document.querySelector('[data-bressol-hamburger]');
    const drawerCloses = Array.from(document.querySelectorAll('[data-bressol-drawer-close]'));
    const body = document.body;

    const openDrawer = () => {
        if (!drawer) {
            return;
        }
        body.classList.add('bressol-drawer-open');
        drawer.setAttribute('aria-hidden', 'false');
    };

    const closeDrawer = () => {
        if (!drawer) {
            return;
        }
        body.classList.remove('bressol-drawer-open');
        drawer.setAttribute('aria-hidden', 'true');
    };

    if (hamburger) {
        hamburger.addEventListener('click', openDrawer);
    }

    drawerCloses.forEach((button) => {
        button.addEventListener('click', closeDrawer);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    const slider = document.querySelector('[data-bressol-slider]');
    if (!slider) {
        return;
    }

    const slides = Array.from(slider.querySelectorAll('[data-bressol-slide]'));
    const dots = Array.from(slider.querySelectorAll('[data-bressol-dot]'));
    const counter = slider.querySelector('[data-bressol-counter] .bressol-hero__current');
    const total = slider.querySelector('[data-bressol-counter] .bressol-hero__total');
    const prevButton = slider.querySelector('[data-bressol-prev]');
    const nextButton = slider.querySelector('[data-bressol-next]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let timer = null;

    if (total) {
        total.textContent = String(Math.max(1, slides.length)).padStart(2, '0');
    }

    const update = (nextIndex) => {
        if (slides.length === 0) {
            return;
        }
        index = (nextIndex + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.classList.toggle('bressol-is-active', i === index);
        });
        dots.forEach((dot, i) => {
            const isActive = i === index;
            dot.classList.toggle('bressol-is-active', isActive);
            if (isActive) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
        if (counter) {
            counter.textContent = String(index + 1).padStart(2, '0');
        }
    };

    const stop = () => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const start = () => {
        if (reduceMotion.matches || slides.length <= 1) {
            return;
        }
        stop();
        timer = window.setInterval(() => update(index + 1), 7000);
    };

    const pause = () => stop();

    const resume = () => start();

    dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
            update(i);
            pause();
        });
    });

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            update(index - 1);
            pause();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            update(index + 1);
            pause();
        });
    }

    slider.addEventListener('pointerenter', pause);
    slider.addEventListener('pointerleave', resume);
    slider.addEventListener('focusin', pause);
    slider.addEventListener('focusout', resume);

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            pause();
        } else {
            resume();
        }
    });

    let touchStartX = 0;
    slider.addEventListener('touchstart', (event) => {
        if (!event.touches || event.touches.length === 0) {
            return;
        }
        touchStartX = event.touches[0].clientX;
        pause();
    });

    slider.addEventListener('touchend', (event) => {
        const touch = event.changedTouches && event.changedTouches[0];
        if (!touch) {
            return;
        }
        const deltaX = touch.clientX - touchStartX;
        if (Math.abs(deltaX) > 40) {
            update(deltaX < 0 ? index + 1 : index - 1);
        }
        resume();
    });

    slider.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            update(index - 1);
            pause();
        }
        if (event.key === 'ArrowRight') {
            update(index + 1);
            pause();
        }
    });

    update(0);
    start();
});
