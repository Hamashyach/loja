<?php 

 // CONFIGURAÇÕES MERCADO PAGO
    define('MP_ACCESS_TOKEN', 'APP_USR-7259438993484884-120114-6fc14ac7f6ba9ebe2a5e501f184247cf-3032062927'); // Sandbox ou Produção
    define('MP_PUBLIC_KEY', 'APP_USR-ab4fdc11-2373-4caa-aefd-acf9429f1557');
    // CONFIGURAÇÕES MELHOR ENVIO
    define('ME_TOKEN', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5NTYiLCJqdGkiOiJkYTc5OGUwZmU0NjE3MWRkZmUzYjdmNTMyYWI5MzMwYmJmM2ZjNzdmZTA5ZDE2YWJiZDJiNjVlYzNkZTQ5OGNmMWFmMGRkOTc5ZTllMjU0NSIsImlhdCI6MTc2NDgxMDkzOS40Mzg0MSwibmJmIjoxNzY0ODEwOTM5LjQzODQxMiwiZXhwIjoxNzk2MzQ2OTM5LjQyOTkyNywic3ViIjoiYTA3MzkzYTMtNmRkNy00NGU0LWI0NzUtNGZjOGMwYjkyNzAzIiwic2NvcGVzIjpbImNhcnQtcmVhZCIsImNhcnQtd3JpdGUiLCJjb21wYW5pZXMtcmVhZCIsImNvbXBhbmllcy13cml0ZSIsImNvdXBvbnMtcmVhZCIsImNvdXBvbnMtd3JpdGUiLCJub3RpZmljYXRpb25zLXJlYWQiLCJvcmRlcnMtcmVhZCIsInByb2R1Y3RzLXJlYWQiLCJwcm9kdWN0cy1kZXN0cm95IiwicHJvZHVjdHMtd3JpdGUiLCJwdXJjaGFzZXMtcmVhZCIsInNoaXBwaW5nLWNhbGN1bGF0ZSIsInNoaXBwaW5nLWNhbmNlbCIsInNoaXBwaW5nLWNoZWNrb3V0Iiwic2hpcHBpbmctY29tcGFuaWVzIiwic2hpcHBpbmctZ2VuZXJhdGUiLCJzaGlwcGluZy1wcmV2aWV3Iiwic2hpcHBpbmctcHJpbnQiLCJzaGlwcGluZy1zaGFyZSIsInNoaXBwaW5nLXRyYWNraW5nIiwiZWNvbW1lcmNlLXNoaXBwaW5nIiwidHJhbnNhY3Rpb25zLXJlYWQiLCJ1c2Vycy1yZWFkIiwidXNlcnMtd3JpdGUiLCJ3ZWJob29rcy1yZWFkIiwid2ViaG9va3Mtd3JpdGUiLCJ3ZWJob29rcy1kZWxldGUiLCJ0ZGVhbGVyLXdlYmhvb2siXX0.Dhfm8htqSvvt5-WJAQ6B6ISBPhVyQLgGgue618BRBJPuaVdZQKk_lN9g746gObzQ2Y6Q9hmp9RipAblnV1Fulbx8EOiGpzOLGEPatQYOOUEzLiznzosrZ8vXSwDub-z_i0Dj-VBS-TXFlMtJp3W8KqKYy5A7MIo74MFzX3aXYXFwHIfgc-rnWWL8NejDNKJUSLyr4lDUwSS6q9sLxNC-EBlMDxM29snbMk_zuBekiE9scxUioRHL2JPdvm-en1cDpdjMT6ky3tUW21933un5TjRlCDi4QC48ay2p4u8qTxIMuTcYC0AZ0K7F0ITSwQRkngkoLZJQcbhyXfJi86jV0FFF4Algq2SPE6kKIBuRWxVaQwj_X2xW_hmpzZLoGHDDG2u2TGJ1r8KiUBrz5Qf3U1sbSdkfhVAopmGPxZXr9jrk_PM2Cm6y1TkfFvzvDtvAq27RHhDO99Lx_NUzlFQG2rwYB6qSTkgnIzf1hi3CFte9ZOQLwdgOZLt2VJ5xsmP-zHSzKZx0TXuiDBAHjM9HZh33sXHXko0p2_Jx4kpN8tFeQ05jGcVPdOGBGWGmn-KMsev2Fk-vatWOoyMQAhF-EnHIKs7R3n8y72W17hcxdhH6cy5bz8hFS2fzgOhefYu7rlcbIRZ_VqQwM8XuV3wKWcnmOtijZtK0uoD-xN-5-4Y'); // Token OAuth2
    define('ME_URL', 'https://sandbox.melhorenvio.com.br'); // Mude para https://melhorenvio.com.br em produção
    define('ME_CEP_ORIGEM', '44860157'); // Seu CEP de envio
   
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

