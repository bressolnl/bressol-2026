document.addEventListener('DOMContentLoaded', () => {
    const slider = document.querySelector('[data-bressol-slider]');
    if (!slider) {
        return;
    }

    const slides = Array.from(slider.querySelectorAll('[data-bressol-slide]'));
    const dots = Array.from(slider.querySelectorAll('[data-bressol-dot]'));
    const counter = slider.querySelector('[data-bressol-counter] .bressol-hero__current');
    const total = slider.querySelector('[data-bressol-counter] .bressol-hero__total');
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
            dot.classList.toggle('bressol-is-active', i === index);
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

    update(0);
    start();
});
