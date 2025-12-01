<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once 'config.php'; 


if (!isset($conn) || !($conn instanceof PDO)) {
    $_SESSION['login_error'] = "Erro de configuração do servidor.";
    header("Location: index.php"); 
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $login_input = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING);
    $senha_digitada = filter_input(INPUT_POST, 'senha', FILTER_SANITIZE_STRING);

    if (empty($login_input) || empty($senha_digitada)) {
        $_SESSION['login_error'] = "Por favor, preencha todos os campos.";
        header("Location: index.php"); 
        exit;
    }
var_dump($login_input);
    try {
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare("SELECT UCIDUSER, UCUSERNAME, UCLOGIN, UCPASSWORD, UCEMAIL FROM USUARIO WHERE UCEMAIL = :input OR UCLOGIN = :input");
        $stmt->bindParam(':input', $login_input);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            
            if (password_verify($senha_digitada, $usuario['UCPASSWORD'])) { 
        
            $stmt_seguranca = $conn->prepare("
                SELECT u.CARGO_ID, c.NOME_CARGO
                FROM USUARIO u
                LEFT JOIN CARGOS c ON u.CARGO_ID = c.ID
                WHERE u.UCIDUSER = ?
            ");
            $stmt_seguranca->execute([$usuario['UCIDUSER']]);
            $dados_cargo = $stmt_seguranca->fetch(PDO::FETCH_ASSOC);

            $cargo_id = $dados_cargo['CARGO_ID'] ?? null;
            $permissoes = [];
            
            if ($cargo_id) {
                $stmt_permissoes = $conn->prepare("
                    SELECT p.CHAVE_PERMISSAO 
                    FROM CARGO_PERMISSOES cp
                    JOIN PERMISSOES p ON cp.PERMISSAO_ID = p.ID
                    WHERE cp.CARGO_ID = ?
                ");
                $stmt_permissoes->execute([$cargo_id]);
                $permissoes = $stmt_permissoes->fetchAll(PDO::FETCH_COLUMN);
            }

            // --- 2. SALVA NA SESSÃO ---
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $usuario['UCIDUSER'];
            $_SESSION['username'] = $usuario['UCUSERNAME'];
            $_SESSION['login'] = $usuario['UCLOGIN'];
            
            $_SESSION['cargo_id'] = $cargo_id; 
            $_SESSION['cargo_nome'] = $dados_cargo['NOME_CARGO'] ?? 'Sem Cargo'; 
            $_SESSION['permissoes'] = $permissoes; 
            
            $application_id = 'Despachante Lindomar';
            $machine_name = 'WEB_LOGIN'; 
            $current_date = date('d/m/y H:i'); 
            $ucidlogon = generate_uuid(); 

            $stmt_log = $conn->prepare("INSERT INTO USUARIO_LOGADO (UCIDLOGON, UCIDUSER, UCAPPLICATIONID, UCMACHINENAME, UCDATA) VALUES (:ucidlogon, :uciduser, :applicationid, :machinename, :data)");
            $stmt_log->bindParam(':ucidlogon', $ucidlogon);
            $stmt_log->bindParam(':uciduser', $usuario['UCIDUSER']);
            $stmt_log->bindParam(':applicationid', $application_id);
            $stmt_log->bindParam(':machinename', $machine_name);
            $stmt_log->bindParam(':data', $current_date);
            $stmt_log->execute();

            header("Location: pagina_principal.php"); 
            exit;

        } else {
            
            $_SESSION['login_error'] = "Login ou Senha incorretos.";
        }
    } else {
        
        $_SESSION['login_error'] = "Login ou Senha incorretos.";
    }

    } catch (PDOException $e) {
      
        error_log("Erro de PDO: " . $e->getMessage()); 
        $_SESSION['login_error'] = "Erro de servidor. Tente novamente mais tarde.";
      
    }
}


if (!isset($_SESSION['loggedin']) || $_SERVER["REQUEST_METHOD"] != "POST") {
   
    if (!isset($_SESSION['login_error'])) {
        $_SESSION['login_error'] = "Acesso inválido.";
    }
}

header("Location: index.php");
exit;


function generate_uuid() {

    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $data = openssl_random_pseudo_bytes(16);
    } else {
        $data = uniqid('', true);
        $data = md5($data);
        return sprintf('%s-%s-%s-%s-%s',
            substr($data, 0, 8),
            substr($data, 8, 4),
            substr($data, 12, 4),
            substr($data, 16, 4),
            substr($data, 20, 12)
        );
    }
    
  
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
?>