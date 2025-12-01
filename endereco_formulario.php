<?php
require 'templates/header.php';

if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id'])) {
    header('Location: login.php?aviso=login_necessario');
    exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];
$endereco_id = (int)($_GET['id'] ?? 0);
$acao = $endereco_id > 0 ? 'editar' : 'novo';

// Valores padrão
$endereco = [
    'tipo' => '', 'cep' => '', 'endereco' => '', 'numero' => '', 
    'complemento' => '', 'bairro' => '', 'cidade' => '', 'estado' => ''
];
$titulo = "Adicionar Novo Endereço";

// Modo Edição
if ($acao == 'editar') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE id = ? AND cliente_id = ?");
        $stmt->execute([$endereco_id, $cliente_id]);
        $fetched_endereco = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetched_endereco) {
            $endereco = $fetched_endereco;
            $titulo = "Editar Endereço";
        } else {
            header('Location: perfil.php?erro=endereco_nao_encontrado#enderecos-panel');
            exit;
        }
    } catch (PDOException $e) {
        die("Erro ao buscar endereço: " . $e->getMessage());
    }
}
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1 style="font-size: 1.5em; letter-spacing: 1.6;"><?php echo $titulo; ?></h1>
        </div>
        <form action="endereco_processa.php" method="POST" class="account-form" style="padding: 80px;">
            <input type="hidden" name="acao" value="<?php echo $acao; ?>">
            <input type="hidden" name="id" value="<?php echo $endereco_id; ?>">

            <div class="form-group">
                <label for="tipo">Tipo de Endereço</label>
                <select id="tipo" name="tipo" required style="padding:8px 15px; background-color: #444; color: #fff; font-size: 1em;">
                    <option value="Principal" <?php echo ($endereco['tipo'] == 'Principal') ? 'selected' : ''; ?>>Principal</option>
                    <option value="Trabalho" <?php echo ($endereco['tipo'] == 'Trabalho') ? 'selected' : ''; ?>>Trabalho</option>
                    <option value="Outro" <?php echo ($endereco['tipo'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="cep">CEP <span id="cep-status" style="color: #bb9a65; font-size: 0.9em;"></span></label>
                <input type="text" id="cep" name="cep" value="<?php echo htmlspecialchars($endereco['cep']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="endereco">Rua/Avenida</label>
                <input type="text" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco['endereco']); ?>" required>
            </div>
            
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="numero">Número</label>
                    <input type="text" id="numero" name="numero" value="<?php echo htmlspecialchars($endereco['numero']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="complemento">Complemento</label>
                    <input type="text" id="complemento" name="complemento" value="<?php echo htmlspecialchars($endereco['complemento']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="bairro">Bairro</label>
                <input type="text" id="bairro" name="bairro" value="<?php echo htmlspecialchars($endereco['bairro']); ?>" required>
            </div>
            
            <div class="form-group-inline">
                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" value="<?php echo htmlspecialchars($endereco['cidade']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="estado">Estado (UF)</label>
                    <input type="text" id="estado" name="estado" value="<?php echo htmlspecialchars($endereco['estado']); ?>" maxlength="2" required>
                </div>
            </div>
            <div class="buttons-address" style="display: flex; gap: 20px;">
            <button type="submit" class="submit-btn"><?php echo $acao == 'novo' ? 'Cadastrar Endereço' : 'Salvar Alterações'; ?></button>
            <a class="submit-btn" style="text-align: center;" href="perfil.php#enderecos-panel" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>
</main>
<?php require 'templates/footer.php'; ?>