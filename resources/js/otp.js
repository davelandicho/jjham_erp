export function initOtpBoxes() {
    const group = document.querySelector('[data-otp]');
    if (! group) return;

    const boxes = [...group.querySelectorAll('[data-otp-box]')];
    const hidden = group.querySelector('input[name="code"]');
    
    const sync = () => {
        hidden.value = boxes.map((box) => box.value).join ('');
    };

    const fillForm = (start, digits) => {
        digits.split('').forEach((digit, i) => {
            if (boxes[start + i]) boxes[start + i]. value = digit;
        });
        boxes[Math.min(start + digits.length, boxes.length -1)].focus();
        sync();
    };

    boxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            const digits = box.value.replace(/\D/g, '');
            box.value = '';
            if (digits) fillForm(index, digits);
            else sync();
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && box.value === '' && index > 0) {
                boxes[index - 1].value = '';
                boxes[index - 1].focus();
                sync();
            }
        });
    });
}