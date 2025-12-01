<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}


$usuario_nome = htmlspecialchars($_SESSION['username']);
$usuario_login = htmlspecialchars($_SESSION['login']);

$is_admin = ($usuario_login === 'admin');

?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lindomar Despachante</title>
    <link rel="stylesheet" href="css/style_sistema.css">
</head>
<body>
     <header class="top-menu">
        <div class="top-menu-brand">
            <a href="pagina_principal.php" ><img src="img/logo.png" alt="Lindomar Despachante" class="top-logo"></a>
            
        </div>
        <div class="header-meio">           
           <p>Lindonar Despachante - versão 1.1</p>
        </div>
        <div class="header-actions">
            <p>Olá, <?= $usuario_nome; ?></p>
            <a href="logout.php" title="Fazer Logoff">
                Sair
            </a>
        </div>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <button id="sidebarToggle" title="Recolher menu">☰</button>
            </div>

            <nav class="sidebar-nav">
                <ul>    
                     <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/despachante.png" alt="" class="nav-icon">
                            <span>Despachante</span>
                        </a>
                        <ul class="submenu">
                             <li><a href="despachante/anotacao_recado.php"><span>Anotações e Recados</span></a></li>
                            <li><a href="despachante/agendamento_vistoria.php"><span>Agendamento de Vistoria</span></a></li>   
                            <li><a href="despachante/cadastro_clientes.php"><span>Cliente</span></a></li>
                            <li><a href="despachante/cadastro_veiculo.php"><span>Veículo</span></a></li>
                            <li><a href="despachante/cadastro_ordem_servico.php"><span>Ordem de Serviços</span></a></li>
                            <li><a href="despachante/cadastro_processos.php"><span>Processos</span></a></li>
                            <li><a href="despachante/cadastro_situacao_processo.php"><span>Situações de Processo</span></a></li>
                            <li><a href="despachante/cadastro_primeiro_emplacamento.php"><span>Primeiro Emplacamento</span></a></li>     
                            <li><a href="despachante/protocolo_entrada.php"><span>Protocolo Entrada Documentos</span></a></li>
                            <li><a href="despachante/protocolo_saida.php"><span>Protocolo Saída Documentos</span></a></li> 
                            <li><a href="despachante/cadastro_recibo.php"><span>Recibos</span></a></li> 
                            <li><a href="despachante/cadastro_intencao_venda.php"><span>Intenção de Venda (ATPV-e)</span></a></li> 
                            
                        </ul>
                    </li>

                    <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/financeiro.png" alt="" class="nav-icon">
                            <span>Controle Financeiro</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="financeiro/lembretes.php"><span>Lembretes Financeiros</span></a></li>
                            <li><a href="financeiro/contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="financeiro/contas_receber.php"><span>Contas a Receber</span></a></li>
                            <li><a href="financeiro/fluxo_caixa.php"><span>Fluxo de Caixa</span></a></li>
                            <li><a href="financeiro/cadastro_centro_custo.php"><span>Centro de Custo</span></a></li>
                        </ul>
                    </li>

                      <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/documento.png" alt="" class="nav-icon">
                            <span>Relatórios</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="relatorios/relatorio_clientes.php"><span>Clientes</span></a></li>
                            <li><a href="relatorios/relatorio_veiculos.php"><span>Veículos</span></a></li>
                            <li><a href="relatorios/relatorio_entrada_veiculos.php"><span>Entrada de Veículos</span></a></li>
                            <li><a href="relatorios/relatorio_ordem_servico.php"><span>Ordem de Serviço</span></a></li>
                            <li><a href="relatorios/relatorio_processos.php"><span>Processos</span></a></li>
                            <li><a href="relatorios/relatorio_contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="relatorios/relatorio_contas_receber.php"><span>Contas a Receber</span></a></li>                           
                            <li><a href="relatorios/relatorio_cnhs_vencendo.php"><span>CNH's Vencendo</span></a></li>            
                        </ul>
                    </li>

                    <li>
                        <a href="#" class="submenu-toggle">
                             <img src="img/icon/usuario.png" alt="" class="nav-icon">
                            <span>Usuários</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="usuarios/usuario.php"><span>Gerenciar Usuários</span></a></li>
                        </ul>
                    </li>

                     <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/configuração.png" alt="" class="nav-icon">
                            <span>Configuração</span>
                        </a>
                        <ul class="submenu">
                             <li><a href="veiculos/cadastro_condicao_pagamento.php"><span>Condições Pagamento</span></a></li>
                            <li><a href="veiculos/cadastro_tipos_servico.php"><span>Tipos de Serviço</span></a></li>
                            <li><a href="veiculos/cadastro_tipo_endereco.php"><span>Tipos de Endereço</span></a></li>
                            <li><a href="veiculos/cadastro_posto_vistoria.php"><span>Postos de Vistoria</span></a></li>
                            <li><a href="veiculos/cadastro_tempos_licenciamento.php"><span>Tempos de Licenciamento</span></a></li>
                            <li><a href="veiculos/cadastro_despachante.php"><span>Cadastro de Despachante</span></a></li>
                            <li><a href="veiculos/cadastro_municipios.php"><span>Cadastro de Municipio</span></a></li>
                            <li><a href="veiculos/cadastro_carroceria.php"><span>Carroceria</span></a></li>
                            <li><a href="veiculos/cadastro_categoria.php"><span>Categoria</span></a></li>
                            <li><a href="veiculos/cadastro_combustivel.php"><span>Combustível</span></a></li>
                            <li><a href="veiculos/cadastro_cor.php"><span>Cor</span></a></li>
                            <li><a href="veiculos/cadastro_especie.php"><span>Espécie</span></a></li>
                            <li><a href="veiculos/cadastro_modelo.php"><span>Marca/Modelo</span></a></li>
                            <li><a href="veiculos/cadastro_restricao.php"><span>Restrição</span></a></li>
                            <li><a href="veiculos/cadastro_tipo_veiculo.php"><span>Tipo de Veículo</span></a></li>
                        </ul>
                    </li>
                </ul>
            </nav>

             <div class="user-profile">
                <a href="#">
                    <img src="img/icon/user.png" alt="" class="nav-icon">
                    <span class="user-name"><?= $usuario_nome; ?></span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <h1>Olá,<?= $usuario_nome; ?></h1>
            <p>Este é o conteúdo principal da página.</p>
        </main>
    </div>

    <script>
      document.querySelectorAll('.submenu-toggle').forEach(toggle => {
          toggle.addEventListener('click', function(event) {
              event.preventDefault();
      
              const submenu = this.nextElementSibling;
              if (submenu && submenu.classList.contains('submenu')) {
                  submenu.classList.toggle('open');
              }
          });
      });

      document.getElementById('sidebarToggle').addEventListener('click', function() {
          document.querySelector('.main-container').classList.toggle('sidebar-collapsed');
      });
    </script>

</body>
</html>