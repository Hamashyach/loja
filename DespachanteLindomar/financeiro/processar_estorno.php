<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';
protegerPagina('acessar_financeiro');

if (!temPermissao('estornar_financeiro')) {
    header("Location: ficha_financeira.php?cpf_cnpj=" . urlencode($_POST['cpf_cnpj'] ?? '') . "&status=error&msg=" . urlencode("Permissão negada."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ficha_financeira.php");
    exit;
}

$usuario_id   = $_SESSION['UCIDUSER'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$cpf_cnpj     = $_POST['cpf_cnpj'] ?? '';
$cit_id       = isset($_POST['cit_id']) ? (int)$_POST['cit_id'] : 0;
$motivo       = trim($_POST['motivo'] ?? 'Estorno realizado pelo administrador.');

if (!$cit_id) {
    header("Location: ficha_financeira.php?cpf_cnpj=" . urlencode($cpf_cnpj) . "&status=error&msg=" . urlencode("Movimento inválido para estorno."));
    exit;
}

try {
    $conn->beginTransaction();

    // Busca o item original
    $stmtItem = $conn->prepare("SELECT * FROM CAIXA_ITEM WHERE CIT_CODIGO = ? FOR UPDATE");
    $stmtItem->execute([$cit_id]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new Exception("Movimento de caixa não encontrado.");
    if ((int)$item['CEC_ESTORNADO'] === 1) throw new Exception("Movimento já estornado.");

    $valor = (float)$item['CEC_VALOR'];
    $fin_id = $item['CEC_FIN_CODIGO'];
    $orig_cec = $item['CEC_CODIGO'];
    $orig_dep = $item['CEC_DEP_CODIGO'] ?? null;
    $orig_ors = $item['CEC_ORS_CODIGO'] ?? null;

    // Monta histórico aprimorado
    $dataHora = date('d/m/Y H:i');
    $historicoAntigo = trim($item['CEC_HISTORICO'] ?? '');
    $novoHistorico = "Estorno realizado em {$dataHora} por {$usuario_nome}. "
                   . "Motivo: {$motivo}. Valor: R$ " . number_format($valor, 2, ',', '.');

    $historicoFinal = $historicoAntigo 
        ? "{$historicoAntigo}\n---\n{$novoHistorico}"
        : $novoHistorico;

    // Marca o item original como estornado
    $stmtMark = $conn->prepare("UPDATE CAIXA_ITEM SET CEC_ESTORNADO = 1, CEC_HISTORICO = ? WHERE CIT_CODIGO = ?");
    $stmtMark->execute([$historicoFinal, $cit_id]);

    // Localiza ou cria o caixa do dia
    $hoje = date('Y-m-d');
    $stmtCx = $conn->prepare("SELECT * FROM CAIXA WHERE CAI_DATA = ? FOR UPDATE");
    $stmtCx->execute([$hoje]);
    $caixa = $stmtCx->fetch(PDO::FETCH_ASSOC);
    if (!$caixa) {
        $stmtNew = $conn->prepare("INSERT INTO CAIXA (CAI_DATA, CAI_VLRDEBITO, CAI_VLRCREDITO, CAI_SALDO) VALUES (?,0,0,'0.00')");
        $stmtNew->execute([$hoje]);
        $cai_codigo = $conn->lastInsertId();
    } else {
        $cai_codigo = $caixa['CAI_CODIGO'];
    }

    // Insere item inverso (débito)
    $complemento = "Estorno automático do CIT #{$cit_id} (FIN {$fin_id}) - {$motivo}";
    $histEstorno = "Lançamento de estorno em {$dataHora} por {$usuario_nome}. Valor: R$ " . number_format($valor, 2, ',', '.');
    $stmtInv = $conn->prepare("
        INSERT INTO CAIXA_ITEM
        (CAI_CODIGO, CEC_CODIGO, CEC_CREDEB, CEC_VALOR, CEC_FIN_CODIGO, CEC_DEP_CODIGO, CEC_COMPLEMENTO, CEC_ORS_CODIGO, CEC_ESTORNADO, CEC_HISTORICO)
        VALUES (?, ?, 'D', ?, ?, ?, ?, ?, 0, ?)
    ");
    $stmtInv->execute([$cai_codigo, $orig_cec, $valor, $fin_id, $orig_dep, $complemento, $orig_ors, $histEstorno]);

    // Atualiza valores do caixa
    $stmtDeb = $conn->prepare("UPDATE CAIXA SET CAI_VLRDEBITO = COALESCE(CAI_VLRDEBITO,0) + ? WHERE CAI_CODIGO = ?");
    $stmtDeb->execute([$valor, $cai_codigo]);
    $stmtSaldo = $conn->prepare("SELECT COALESCE(CAI_VLRCREDITO,0), COALESCE(CAI_VLRDEBITO,0) FROM CAIXA WHERE CAI_CODIGO = ?");
    $stmtSaldo->execute([$cai_codigo]);
    [$cred, $deb] = $stmtSaldo->fetch(PDO::FETCH_NUM);
    $novo_saldo = $cred - $deb;
    $stmtUpdSaldo = $conn->prepare("UPDATE CAIXA SET CAI_SALDO = ? WHERE CAI_CODIGO = ?");
    $stmtUpdSaldo->execute([number_format($novo_saldo, 2, '.', ''), $cai_codigo]);

    // Atualiza histórico no título financeiro
    $stmtFin = $conn->prepare("SELECT FIN_OBS FROM FINANCEIRO_PAGAR_RECEBER WHERE FIN_CODIGO = ?");
    $stmtFin->execute([$fin_id]);
    $finObsAntigo = $stmtFin->fetchColumn();
    $finObsNovo = trim(($finObsAntigo ? "{$finObsAntigo}\n" : '') . "Estorno de pagamento em {$dataHora} (CIT #{$cit_id}) por {$usuario_nome}. Motivo: {$motivo}. Valor: R$ " . number_format($valor, 2, ',', '.'));
    $stmtUpdFin = $conn->prepare("UPDATE FINANCEIRO_PAGAR_RECEBER SET FIN_OBS = ? WHERE FIN_CODIGO = ?");
    $stmtUpdFin->execute([$finObsNovo, $fin_id]);

    // Reabre título se ainda houver saldo pendente
    $stmtFinVals = $conn->prepare("SELECT FIN_VALOR, FIN_VALORTOTAL FROM FINANCEIRO_PAGAR_RECEBER WHERE FIN_CODIGO = ?");
    $stmtFinVals->execute([$fin_id]);
    $fin = $stmtFinVals->fetch(PDO::FETCH_ASSOC);
    $fin_total = (float)($fin['FIN_VALORTOTAL'] ?: $fin['FIN_VALOR']);

    $stmtPago = $conn->prepare("SELECT COALESCE(SUM(CEC_VALOR),0) FROM CAIXA_ITEM WHERE CEC_FIN_CODIGO = ? AND CEC_CREDEB = 'C' AND CEC_ESTORNADO = 0");
    $stmtPago->execute([$fin_id]);
    $pago = (float)$stmtPago->fetchColumn();

    if ($pago + 0.001 < $fin_total) {
        $stmtReabrir = $conn->prepare("UPDATE FINANCEIRO_PAGAR_RECEBER SET FIN_DATABAIXA = NULL WHERE FIN_CODIGO = ?");
        $stmtReabrir->execute([$fin_id]);
    }

    $conn->commit();

    $msg = "Estorno realizado com sucesso! Valor estornado: R$ " . number_format($valor, 2, ',', '.');
    header("Location: ficha_financeira.php?cpf_cnpj=" . urlencode($cpf_cnpj) . "&status=success&msg=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $msg = "Falha no estorno: " . $e->getMessage();
    header("Location: ficha_financeira.php?cpf_cnpj=" . urlencode($cpf_cnpj) . "&status=error&msg=" . urlencode($msg));
    exit;
}
?>
