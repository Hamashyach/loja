<?php 
   
    define('BASE_URL', '/LionCompany');
    $pdo = new PDO('mysql:hostname=localhost;dbname=lion_company_db','root','');

    function get_all_configs($pdo) {
    $configs = [];
    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM tb_site_config");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $configs[$row['config_key']] = $row['config_value'];
        }
    } catch (PDOException $e) {
    }
    return $configs;
}
?> 

