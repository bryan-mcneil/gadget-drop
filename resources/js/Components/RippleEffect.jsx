import { useEffect } from 'react';

const CSS = `
@keyframes gadget-ripple {
    0%   { transform: scale(0); opacity: 1; }
    100% { transform: scale(1); opacity: 0; }
}
.gadget-ripple {
    position: fixed;
    border-radius: 50%;
    pointer-events: none;
    z-index: 99999;
    animation: gadget-ripple ease-out forwards;
    transform-origin: center center;
}`;

const SIZE = 80;

function spawn(x, y) {
    const el = document.createElement('div');
    el.className = 'gadget-ripple';
    // Subtract half the size so the element is centered exactly on the click point
    el.style.cssText = [
        `left:${x - SIZE / 2}px`,
        `top:${y - SIZE / 2}px`,
        `width:${SIZE}px`,
        `height:${SIZE}px`,
        `border:1.5px solid rgba(99,102,241,0.6)`,
        `animation-duration:680ms`,
    ].join(';');
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 750);
}

export default function RippleEffect() {
    useEffect(() => {
        const style = document.createElement('style');
        style.textContent = CSS;
        document.head.appendChild(style);

        const onClick = (e) => spawn(e.clientX, e.clientY);
        const onTouch = (e) => {
            const t = e.changedTouches[0];
            spawn(t.clientX, t.clientY);
        };

        document.addEventListener('click',      onClick);
        document.addEventListener('touchstart', onTouch, { passive: true });

        return () => {
            document.removeEventListener('click',      onClick);
            document.removeEventListener('touchstart', onTouch);
            style.remove();
        };
    }, []);

    return null;
}
