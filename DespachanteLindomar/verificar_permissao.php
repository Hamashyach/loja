<?php
function temPermissao($chave_permissao) {

    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        return false;
    }
    
    if (isset($_SESSION['cargo_id']) && $_SESSION['cargo_id'] == 1) {
        return true;
    }
    
  
    if (!isset($_SESSION['permissoes']) || !is_array($_SESSION['permissoes'])) {
        return false;
    }

    return in_array($chave_permissao, $_SESSION['permissoes']);
}

function protegerPagina($chave_permissao_necessaria, $caminho_login = '../index.php', $caminho_principal = '../pagina_principal.php') {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("Location: {$caminho_login}"); 
        exit;
    }
    
    // Se o usuário não tem a permissão necessária, bloqueia
    if (!temPermissao($chave_permissao_necessaria)) {
        http_response_code(403); // Acesso Negado
        die("
            <!DOCTYPE html>
            <html lang='pt-br'><head><title>Acesso Negado</title></head>
            <body>
                <div style='text-align: center; margin-top: 100px; font-family: sans-serif;'>
                    <h1>Acesso Negado (Erro 403)</h1>
                    <p>Seu cargo não possui permissão para acessar esta funcionalidade (<strong>{$chave_permissao_necessaria}</strong>).</p>
                    <a href='{$caminho_principal}'>Voltar para o Início</a>
                </div>
            </body>
            </html>
        ");
    }
}
?>