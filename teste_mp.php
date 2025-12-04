<?php
// Forçar exibição de erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Diagnóstico Mercado Pago</h2>";
echo "<b>Pasta atual do script:</b> " . __DIR__ . "<br><br>";

// 1. Verificar se a pasta vendor existe
if (!is_dir(__DIR__ . '/vendor')) {
    echo "<span style='color:red'>[ERRO] A pasta 'vendor' NÃO existe neste diretório.</span><br>";
    echo "Solução: Você precisa fazer upload da pasta vendor para dentro de: " . __DIR__;
    die();
} else {
    echo "<span style='color:green'>[OK] Pasta 'vendor' encontrada.</span><br>";
}

// 2. Verificar se o autoload existe
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "<span style='color:red'>[ERRO] O arquivo 'autoload.php' NÃO está dentro da pasta vendor.</span><br>";
    die();
} else {
    echo "<span style='color:green'>[OK] Arquivo 'autoload.php' encontrado.</span><br>";
    require_once $autoloadPath;
}

// 3. Verificar qual classe do Mercado Pago está instalada
echo "<hr>Testando classes:<br>";

if (class_exists('MercadoPago\MercadoPagoConfig')) {
    echo "<span style='color:green'>[SUCESSO] A classe 'MercadoPagoConfig' (Versão Nova) foi carregada!</span><br>";
    echo "Se você está vendo isso, o erro no seu outro arquivo é apenas o caminho do require.";
} elseif (class_exists('MercadoPago\SDK')) {
    echo "<span style='color:orange'>[ALERTA] A classe 'MercadoPago\SDK' (Versão Antiga) foi encontrada.</span><br>";
    echo "<b>O PROBLEMA É ESTE:</b> Você instalou a versão antiga da biblioteca, mas está tentando usar o código da versão nova (MercadoPagoConfig).<br>";
    echo "Solução: Mude seu código para usar <code>MercadoPago\SDK::setAccessToken(...)</code> OU atualize a biblioteca via composer.";
} else {
    echo "<span style='color:red'>[ERRO CRÍTICO] O Autoload carregou, mas NENHUMA classe do Mercado Pago foi encontrada.</span><br>";
    echo "Provável causa: O upload da pasta vendor via FTP ficou incompleto (arquivos faltando).";
}
?>