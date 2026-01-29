import Inputmask from "inputmask";

export function defineUaPhoneMask() {
    if (window.applyUaPhoneMask) return; // уже есть

    window.applyUaPhoneMask = function (el) {
        if (!el || el.__uaPhoneMasked) return;
        if (!('value' in el)) return;

        const PREFIX = '+38 0 ';
        const im = new Inputmask({
            mask: '+38 0 99 999 99 99',
            placeholder: ' ',
            showMaskOnHover: false,
            showMaskOnFocus: true,
            clearIncomplete: true,
        });

        im.mask(el);

        if (!el.value || !String(el.value || '').startsWith(PREFIX)) im.setValue(PREFIX);

        const ensurePrefix = () => {
            if (!el || !el.value) return;
            let digits = String(el.value || '').replace(/\D/g, '');
            if (digits.startsWith('380')) digits = digits.slice(3);
            else if (digits.startsWith('38')) digits = digits.slice(2);
            else if (digits.startsWith('0')) digits = digits.slice(1);
            im.setValue(PREFIX + digits);
        };

        el.addEventListener('keydown', (e) => {
            if (!el.selectionStart) return;
            if (!e || !e.key) return;
            const pos = el.selectionStart ?? 0;
            if (pos <= PREFIX.length && ['Backspace','Delete','ArrowLeft','Home'].includes(e.key)) {
                e.preventDefault();
                el.setSelectionRange?.(PREFIX.length, PREFIX.length);
            }
        });

        el.addEventListener('input', ensurePrefix);

        el.addEventListener('focus', () => {
            if (!el.value) im.setValue(PREFIX);
            setTimeout(() => {
                el.setSelectionRange?.(el.value.length, el.value.length);
            }, 0);
        });

        el.__uaPhoneMasked = true;
    };

    window.dispatchEvent(new Event('ua-phone-mask-ready'));
}
