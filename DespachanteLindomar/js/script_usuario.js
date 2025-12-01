document.addEventListener('DOMContentLoaded', function() {
    const formUsuario = document.getElementById('formUsuario');
    if (!formUsuario) return;

    const nivelAcessoSelect = document.getElementById('USR_NIVEL');
    const permissionsTable = document.querySelector('.permissions-table');
    const allCheckboxes = permissionsTable.querySelectorAll('input[type="checkbox"]');

    function togglePermissions(isAdmin) {
        allCheckboxes.forEach(checkbox => {
            checkbox.checked = isAdmin;
            checkbox.disabled = isAdmin;
        });
    }

    // Lógica para o seletor de Nível de Acesso
    nivelAcessoSelect.addEventListener('change', () => {
        if (nivelAcessoSelect.value === 'admin') {
            togglePermissions(true);
        } else {
            togglePermissions(false);
        }
    });

    // Lógica para os botões "Marcar Tudo" de cada linha
    permissionsTable.querySelectorAll('.check-all-row').forEach(headerCheckbox => {
        headerCheckbox.addEventListener('change', function() {
            const row = this.closest('tr');
            const rowCheckboxes = row.querySelectorAll('input[type="checkbox"]:not(.check-all-row)');
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    });
    
    // Verifica o estado inicial ao carregar a página
    if (nivelAcessoSelect.value === 'admin') {
        togglePermissions(true);
    }
});