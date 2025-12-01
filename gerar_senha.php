<?php
// /gerar_senha.php

// A senha que queremos usar
$senha = 'admin123';

// Gera um hash seguro usando o PHP do SEU servidor
$hash = password_hash($senha, PASSWORD_DEFAULT);

// Mostra o hash na tela
echo "Seu novo hash para a senha 'admin123' é: <br><br>";
echo $hash;

?>