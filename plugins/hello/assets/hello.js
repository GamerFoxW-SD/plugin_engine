document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-hello-plugin]').forEach((card) => {
        const button = card.querySelector('[data-hello-button]');
        const message = card.querySelector('[data-hello-message]');
        let clicks = 0;

        button?.addEventListener('click', () => {
            clicks += 1;
            message.textContent = `Hello World! A plugin működik. Kattintások: ${clicks}`;
        });
    });
});
