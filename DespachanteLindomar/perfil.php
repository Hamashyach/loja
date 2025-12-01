<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil</title>
    <link rel="stylesheet" href="css/style_sistema.css">
    
</head>
<body>
    <header class="top-menu">
        <div class="top-menu-brand">
            <a href="pagina_principal.php" ><img src="img/logo-.png" alt="Lindomar Despachante" class="top-logo"></a>
            
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
                            <img src="img/icon/geral.png" alt="" class="nav-icon">
                            <span>Geral</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="geral/cadastro_despachante.php"><span>Cadastro de Despachante</span></a></li>
                            <li><a href="geral/cadastro_municipios.php"><span>Cadastro de Municipio</span></a></li>
                            <li><a href="geral/anotacao_recado.php"><span>Cadastro de Anotações e Recados</span></a></li>
                        </ul>
                    </li>                   
                     <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/despachante.png" alt="" class="nav-icon">
                            <span>Despachante</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="despachante/cadastro_clientes.php"><span>Cliente</span></a></li>
                            <li><a href="despachante/cadastro_veiculo.php"><span>Veículo</span></a></li>
                            <li><a href="despachante/cadastro_tempos_licenciamento.php"><span>Tempos de Licenciamento</span></a></li>
                            <li><a href="despachante/cadastro_tipos_servico.php"><span>Tipos de Serviço</span></a></li>
                            <li><a href="despachante/cadastro_tipo_endereco.php"><span>Tipos de Endereço</span></a></li>
                            <li><a href="despachante/cadastro_posto_vistoria.php"><span>Postos de Vistoria</span></a></li>
                            <li><a href="despachante/cadastro_recibo.php"><span>Recibos Simples</span></a></li>
                            <li><a href="despachante/cadastro_ordem_servico.php"><span>Ordem de Serviços</span></a></li>
                            <li><a href="despachante/cadastro_processos.php"><span>Processos</span></a></li>
                            <li><a href="despachante/cadastro_situacao_processo.php"><span>Situações de Processo</span></a></li>
                            <li><a href="despachante/cadastro_condicao_pagamento.php"><span>Condições Pagamento</span></a></li>
                            <li><a href="despachante/cadastro_primeiro_emplacamento.php"><span>Primeiro Emplacamento</span></a></li>     
                            <li><a href="despachante/protocolo_entrada.php"><span>Protocolo Entrada Documentos</span></a></li>
                            <li><a href="despachante/protocolo_saida.php"><span>Protocolo Saída Documentos</span></a></li>
                        </ul>
                    </li>

                    <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/financeiro.png" alt="" class="nav-icon">
                            <span>Controle Financeiro</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="financeiro/lembretes.php"><span>Lembretes Financeiros</span></a></li>
                            <li><a href="financeiro/ficha_financeira.php"><span>Ficha Financeira</span></a></li>
                            <li><a href="financeiro/contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="financeiro/contas_receber.php"><span>Contas a Receber</span></a></li>
                            <li><a href="financeiro/fluxo_caixa.php"><span>Fluxo de Caixa</span></a></li>
                        </ul>
                    </li>

                      <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/documento.png" alt="" class="nav-icon">
                            <span>Relatórios</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="relatorios/clientes.php"><span>Clientes</span></a></li>
                            <li><a href="relatorios/relatorio_veiculos.php"><span>Veículos</span></a></li>
                            <li><a href="relatorios/municipios.php"><span>Municipios</span></a></li>
                            <li><a href="relatorios/licenciamento.php"><span>Licenciamentos</span></a></li>
                            <li><a href="relatorios/ficha_financeira.php"><span>Ficha Financeira</span></a></li>
                            <li><a href="relatorios/ordens_servico.php"><span>Ordem de Serviço</span></a></li>
                            <li><a href="relatorios/contas_a_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="relatorios/contas_a_receber.php"><span>Contas a Receber</span></a></li>
                            <li><a href="relatorios/caixa.php"><span>Caixa</span></a></li>
                            <li><a href="relatorios/aniversariante.php"><span>Aniversariantes</span></a></li>                           
                            <li><a href="relatorios/vistoria_agendada.php"><span>Vistorias Agendadas</span></a></li>
                            <li><a href="relatorios/cnh_vencendo.php"><span>CNH's Vencendo</span></a></li>
                            <li><a href="relatorios/processo.php"><span>Processos</span></a></li>
                            <li><a href="relatorios/entrada_veiculos.php"><span>Entrada de Veículos</span></a></li>
                            <li><a href="relatorios/historico.php"><span>Histórico de Documentos</span></a></li>
                            <li><a href="relatorios/comissoes.php"><span>Comissões</span></a></li>
                            <li><a href="relatorios/despachante.php"><span>Despachantes</span></a></li>
                        </ul>
                    </li>

                    <li>
                        <a href="#" class="submenu-toggle">
                            <img src="img/icon/carro.png" alt="" class="nav-icon">
                            <span>Dados Veículos</span>
                        </a>
                        <ul class="submenu">
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

                    <li>
                        <a href="#" class="submenu-toggle">
                             <img src="img/icon/usuario.png" alt="" class="nav-icon">
                            <span>Usuários</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="usuarios/usuario.php"><span>Gerenciar Usuários</span></a></li>
                        </ul>
                    </li>
                </ul>
            </nav>

             <div class="user-profile">
                <a href="perfil.php">
                    <img src="img/icon/user.png" alt="" class="nav-icon">
                    <span class="user-name"><?= $usuario_nome; ?></span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <h1>Meu Perfil</h1>

            <form id="formPerfil" method="POST" enctype="multipart/form-data">
                <fieldset class="form-section">
                    <legend>Foto de Perfil</legend>
                    <div class="profile-picture-section">
                        <div class="profile-picture-display">
                            <img src="../img/icon/user.png" alt="Foto do Perfil" id="imagemPerfil">
                        </div>
                        <div class="profile-picture-controls">
                            <label for="inputFoto" class="btn">Mudar Foto</label>
                            <input type="file" id="inputFoto" name="USR_FOTO" accept="image/png, image/jpeg" style="display: none;">
                            <p class="form-hint">Envie uma imagem JPG ou PNG. Tamanho máximo: 2MB.</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Informações Pessoais</legend>
                    <div class="form-row">
                        <div class="form-group" style="flex-grow: 2;">
                            <label for="UCUSERNAME">Nome Completo</label>
                            <input type="text" id="UCUSERNAME" name="UCUSERNAME" value="Nome do Usuário Logado" required>
                        </div>
                        <div class="form-group">
                            <label for="UCLOGIN">Login de Acesso</label>
                            <input type="text" id="UCLOGIN" name="UCLOGIN" value="login.do.usuario" readonly>
                        </div>
                    </div>
                     <div class="form-row">
                        <div class="form-group" style="flex-grow: 1;">
                            <label for="UCEMAIL">E-mail</label>
                            <input type="email" id="UCEMAIL" name="UCEMAIL" value="email@do.usuario" required>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Alterar Senha</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="SENHA_ATUAL">Senha Atual</label>
                            <input type="password" id="SENHA_ATUAL" name="SENHA_ATUAL" placeholder="Digite sua senha atual para confirmar">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="NOVA_SENHA">Nova Senha</label>
                            <input type="password" id="NOVA_SENHA" name="NOVA_SENHA" placeholder="Deixe em branco para não alterar">
                        </div>
                        <div class="form-group">
                            <label for="CONFIRMAR_SENHA">Confirmar Nova Senha</label>
                            <input type="password" id="CONFIRMAR_SENHA" name="CONFIRMAR_SENHA">
                        </div>
                    </div>
                </fieldset>
                
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    <a href="../pagina_principal.php" class="btn btn-danger">Cancelar</a>
                </div>
            </form>
        </main>
    </div>

    <script src="../js/script_menu.js"></script>
    <script src="../js/script.js"></script>
    <script src="../js/script_perfil.js"></script>
</body>
</html>