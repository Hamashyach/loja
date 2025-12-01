const mainContainer = document.querySelector('.main-container');

document.querySelectorAll('.submenu-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(event) {
        event.preventDefault();

        if (mainContainer && mainContainer.classList.contains('sidebar-collapsed')) {
            mainContainer.classList.remove('sidebar-collapsed');
        }

        const submenu = this.nextElementSibling;
        if (submenu && submenu.classList.contains('submenu')) {
            submenu.classList.toggle('open');
        }
    });
});

document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.querySelector('.main-container').classList.toggle('sidebar-collapsed');
});