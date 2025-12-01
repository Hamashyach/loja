document.addEventListener('DOMContentLoaded', () => {
    const hamburgerBtn = document.querySelector('.hamburger-btn');
    const mainNav = document.querySelector('.main-nav');
    const menuItemsWithSubmenu = document.querySelectorAll('.main-nav .has-megamenu');

    // Lógica para abrir/fechar o menu principal
    hamburgerBtn.addEventListener('click', () => {
        hamburgerBtn.classList.toggle('is-active');
        mainNav.classList.toggle('is-open');
        document.body.style.overflow = mainNav.classList.contains('is-open') ? 'hidden' : '';
    });

    menuItemsWithSubmenu.forEach(item => {
        const link = item.querySelector('a');
        const submenu = item.querySelector('.mega-menu');

        link.addEventListener('click', (event) => {
            if (window.innerWidth <= 992) {
                event.preventDefault();
                item.classList.toggle('submenu-active');
                submenu.classList.toggle('submenu-open');
            }
        });
    });
});