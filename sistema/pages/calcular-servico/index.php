<?php
// ==========================================
// 1. CONEXÃO E TABELAS
// ==========================================
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';

// Tabela de cálculos de custo
$pdo->exec("CREATE TABLE IF NOT EXISTS custos_calculadora (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome_calculo TEXT,
    produto_id INTEGER,
    qtd_itens INTEGER,
    tipo_custo TEXT,              -- total | unitario
    valor_custo_informado REAL,
    tipo_venda TEXT,              -- total | unitario
    valor_venda_informado REAL,
    outros_custos REAL,
    custo_total REAL,
    custo_unitario REAL,
    preco_venda_total REAL,
    preco_venda_unitario REAL,
    lucro_total REAL,
    lucro_unitario REAL,
    margem_percent REAL,
    observacoes TEXT,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE SET NULL
)");

// Buscar produtos para o select
$listaProdutos = [];
try {
    $stmtProd = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome ASC");
    $listaProdutos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Se ainda não existe tabela produtos, ignora
}

// ==========================================
// 2. PROCESSAR FORMULÁRIO (CALCULAR / SALVAR)
// ==========================================
$msgSucesso = null;
$msgErro = null;
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_calculo') {
    try {
        $idCalculo           = !empty($_POST['id_calculo']) ? (int)$_POST['id_calculo'] : null;
        $nomeCalculo         = trim($_POST['nome_calculo'] ?? '');
        $produtoId           = !empty($_POST['produto_id']) ? (int)$_POST['produto_id'] : null;
        $qtdItens            = isset($_POST['qtd_itens']) ? max(1, (int)$_POST['qtd_itens']) : 1;

        $tipoCusto           = ($_POST['tipo_custo'] ?? 'total') === 'unitario' ? 'unitario' : 'total';
        $valorCustoInformado = (float)str_replace(',', '.', $_POST['valor_custo'] ?? 0);

        $tipoVenda           = ($_POST['tipo_venda'] ?? 'unitario') === 'total' ? 'total' : 'unitario';
        $valorVendaInformado = (float)str_replace(',', '.', $_POST['preco_venda'] ?? 0);

        $outrosCustos        = (float)str_replace(',', '.', $_POST['outros_custos'] ?? 0);
        $observacoes         = trim($_POST['observacoes'] ?? '');

        if ($valorCustoInformado <= 0 || $qtdItens <= 0) {
            throw new Exception('Informe quantidade e custo corretamente.');
        }

        // --- Cálculos principais ---
        if ($tipoCusto === 'total') {
            $custo_total_produtos  = $valorCustoInformado;
            $custo_unitario_prod   = $valorCustoInformado / $qtdItens;
        } else { // unitário
            $custo_unitario_prod   = $valorCustoInformado;
            $custo_total_produtos  = $valorCustoInformado * $qtdItens;
        }

        // Outros custos considerados como TOTAL do lote
        $outros_total   = $outrosCustos;
        $outros_unit    = $outros_total / $qtdItens;

        $custo_total_lote   = $custo_total_produtos + $outros_total;
        $custo_unitario     = $custo_total_lote / $qtdItens;

        // Preço de venda
        if ($tipoVenda === 'unitario') {
            $preco_venda_unitario = $valorVendaInformado;
            $preco_venda_total    = $preco_venda_unitario * $qtdItens;
        } else {
            $preco_venda_total    = $valorVendaInformado;
            $preco_venda_unitario = $preco_venda_total / $qtdItens;
        }

        $lucro_total    = $preco_venda_total - $custo_total_lote;
        $lucro_unitario = $preco_venda_unitario - $custo_unitario;

        $margem_percent = ($preco_venda_total > 0)
            ? ($lucro_total / $preco_venda_total) * 100
            : 0;

        // Resultado para exibir logo após salvar
        $resultado = [
            'qtd'                 => $qtdItens,
            'custo_total'         => $custo_total_lote,
            'custo_unitario'      => $custo_unitario,
            'preco_total'         => $preco_venda_total,
            'preco_unitario'      => $preco_venda_unitario,
            'lucro_total'         => $lucro_total,
            'lucro_unitario'      => $lucro_unitario,
            'margem_percent'      => $margem_percent,
            'outros_custos_total' => $outros_total
        ];

        // Salvar no banco
        if ($idCalculo) {
            $stmt = $pdo->prepare("UPDATE custos_calculadora
                SET nome_calculo = ?, produto_id = ?, qtd_itens = ?, tipo_custo = ?, valor_custo_informado = ?,
                    tipo_venda = ?, valor_venda_informado = ?, outros_custos = ?, custo_total = ?, custo_unitario = ?,
                    preco_venda_total = ?, preco_venda_unitario = ?, lucro_total = ?, lucro_unitario = ?, margem_percent = ?,
                    observacoes = ?
                WHERE id = ?");
            $stmt->execute([
                $nomeCalculo ?: null,
                $produtoId ?: null,
                $qtdItens,
                $tipoCusto,
                $valorCustoInformado,
                $tipoVenda,
                $valorVendaInformado,
                $outrosCustos,
                $custo_total_lote,
                $custo_unitario,
                $preco_venda_total,
                $preco_venda_unitario,
                $lucro_total,
                $lucro_unitario,
                $margem_percent,
                $observacoes ?: null,
                $idCalculo
            ]);
            $msgSucesso = 'Cálculo atualizado com sucesso!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO custos_calculadora
                (nome_calculo, produto_id, qtd_itens, tipo_custo, valor_custo_informado, tipo_venda,
                 valor_venda_informado, outros_custos, custo_total, custo_unitario, preco_venda_total,
                 preco_venda_unitario, lucro_total, lucro_unitario, margem_percent, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $nomeCalculo ?: null,
                $produtoId ?: null,
                $qtdItens,
                $tipoCusto,
                $valorCustoInformado,
                $tipoVenda,
                $valorVendaInformado,
                $outrosCustos,
                $custo_total_lote,
                $custo_unitario,
                $preco_venda_total,
                $preco_venda_unitario,
                $lucro_total,
                $lucro_unitario,
                $margem_percent,
                $observacoes ?: null
            ]);
            $msgSucesso = 'Cálculo salvo com sucesso!';
        }

    } catch (Exception $e) {
        $msgErro = 'Erro ao calcular/salvar: ' . $e->getMessage();
    }
}

// ==========================================
// 3. EXCLUIR CÁLCULO
// ==========================================
if (isset($_GET['excluir']) && $_GET['excluir'] !== '') {
    try {
        $idDel = (int)$_GET['excluir'];
        $stmt = $pdo->prepare("DELETE FROM custos_calculadora WHERE id = ?");
        $stmt->execute([$idDel]);
        $msgSucesso = 'Cálculo excluído com sucesso!';
    } catch (Exception $e) {
        $msgErro = 'Erro ao excluir cálculo: ' . $e->getMessage();
    }
}

// ==========================================
// 4. BUSCAR LISTA DE CÁLCULOS
// ==========================================
$busca = trim($_GET['busca'] ?? '');
$paramsLista = [];
$sqlLista = "SELECT c.*, p.nome AS produto_nome
             FROM custos_calculadora c
             LEFT JOIN produtos p ON p.id = c.produto_id";

if ($busca !== '') {
    $sqlLista .= " WHERE c.nome_calculo LIKE :busca OR p.nome LIKE :busca";
    $paramsLista[':busca'] = "%{$busca}%";
}

$sqlLista .= " ORDER BY c.data_criacao DESC";

$stmtCalcs = $pdo->prepare($sqlLista);
$stmtCalcs->execute($paramsLista);
$listaCalculos = $stmtCalcs->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 5. SE FOR EDIÇÃO, BUSCAR DADOS PARA FORM
// ==========================================
$edicao = null;
if (isset($_GET['editar']) && $_GET['editar'] !== '') {
    $idEdit = (int)$_GET['editar'];
    $stmtEd = $pdo->prepare("SELECT * FROM custos_calculadora WHERE id = ?");
    $stmtEd->execute([$idEdit]);
    $edicao = $stmtEd->fetch(PDO::FETCH_ASSOC) ?: null;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Calculadora de Custos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-soft: #dbeafe;
            --danger-color: #dc2626;
            --success-color: #16a34a;
        }

        body {
            background:#f3f4f6;
        }

        main.custos-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }

        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:16px;
            margin-bottom:20px;
        }

        .page-header h1 {
            margin:0;
            font-size:1.6rem;
            color:#111827;
        }

        .page-header p {
            margin:4px 0 0;
            color:#6b7280;
        }

        .tag-soft {
            display:inline-flex;
            align-items:center;
            padding:4px 10px;
            border-radius:999px;
            background:#e5f3ff;
            color:#1d4ed8;
            font-size:0.75rem;
            font-weight:500;
        }

        .layout-grid {
            display:grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 3fr);
            gap:20px;
        }

        .card {
            background:#ffffff;
            border-radius:16px;
            border:1px solid #e5e7eb;
            box-shadow:0 8px 20px rgba(15,23,42,0.04);
            padding:16px 18px 18px;
        }

        .card h2 {
            margin:0 0 6px;
            font-size:1rem;
            color:#111827;
        }

        .card small {
            color:#9ca3af;
        }

        .section-title {
            font-size:0.85rem;
            text-transform:uppercase;
            color:#9ca3af;
            margin:10px 0 6px;
            font-weight:600;
        }

        .form-grid {
            display:grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap:10px 14px;
        }

        .full-width {
            grid-column:span 2;
        }

        .input-group label {
            display:block;
            font-size:0.8rem;
            color:#4b5563;
            margin-bottom:4px;
            font-weight:500;
        }

        .input-control,
        .input-textarea,
        .select-control {
            width:100%;
            padding:8px 10px;
            border-radius:10px;
            border:1px solid #d1d5db;
            background:#ffffff;
            font-size:0.9rem;
            color:#111827;
        }

        .input-control:focus,
        .input-textarea:focus,
        .select-control:focus {
            outline:none;
            border-color:var(--primary-color);
            box-shadow:0 0 0 1px rgba(37,99,235,0.35);
        }

        .input-textarea {
            resize:vertical;
            min-height:70px;
        }

        .radio-group {
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .radio-pill {
            display:flex;
            align-items:center;
            gap:4px;
            font-size:0.78rem;
            padding:4px 10px;
            border-radius:999px;
            border:1px solid #d1d5db;
            background:#f9fafb;
            cursor:pointer;
        }

        .radio-pill input {
            margin:0;
        }

        .btn-primary {
            background:var(--primary-color);
            color:#ffffff;
            border:none;
            padding:9px 18px;
            border-radius:999px;
            font-size:0.9rem;
            font-weight:600;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:8px;
        }

        .btn-secondary {
            background:#f9fafb;
            color:#374151;
            border:1px solid #d1d5db;
            padding:8px 14px;
            border-radius:999px;
            font-size:0.85rem;
            cursor:pointer;
        }

        .msg {
            padding:8px 10px;
            border-radius:10px;
            font-size:0.85rem;
            margin-bottom:12px;
        }

        .msg-sucesso {
            background:#dcfce7;
            border:1px solid #bbf7d0;
            color:#166534;
        }

        .msg-erro {
            background:#fee2e2;
            border:1px solid #fecaca;
            color:#b91c1c;
        }

        .resumo-grid {
            display:grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap:10px;
            margin-top:10px;
        }

        .resumo-item {
            padding:10px 10px;
            border-radius:12px;
            background:#f9fafb;
            border:1px solid #e5e7eb;
        }

        .resumo-label {
            font-size:0.75rem;
            color:#6b7280;
            margin-bottom:2px;
        }

        .resumo-valor {
            font-size:0.95rem;
            font-weight:600;
            color:#111827;
        }

        .status-lucro {
            padding:10px 12px;
            border-radius:12px;
            margin-top:10px;
            font-size:0.9rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .status-lucro.lucro {
            background:#ecfdf3;
            border:1px solid #bbf7d0;
            color:#166534;
        }

        .status-lucro.prejuizo {
            background:#fef2f2;
            border:1px solid #fecaca;
            color:#b91c1c;
        }

        .status-lucro span strong {
            font-weight:700;
        }

        .calc-list-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            margin-bottom:10px;
        }

        .calc-search {
            display:flex;
            gap:6px;
            width:100%;
        }

        .calc-search input {
            flex:1;
            padding:7px 10px;
            border-radius:999px;
            border:1px solid #d1d5db;
            font-size:0.85rem;
        }

        .calc-search button {
            border-radius:999px;
            border:1px solid #d1d5db;
            padding:7px 12px;
            background:#f9fafb;
            font-size:0.8rem;
            cursor:pointer;
        }

        .cards-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap:10px;
            margin-top:6px;
        }

        .calc-card {
            border-radius:14px;
            border:1px solid #e5e7eb;
            padding:10px 10px 12px;
            background:#ffffff;
            display:flex;
            flex-direction:column;
            gap:4px;
        }

        .calc-card-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:8px;
        }

        .calc-card-title {
            font-size:0.9rem;
            font-weight:600;
            color:#111827;
        }

        .calc-card-sub {
            font-size:0.75rem;
            color:#6b7280;
        }

        .pill-status {
            font-size:0.7rem;
            padding:2px 8px;
            border-radius:999px;
            font-weight:600;
        }

        .pill-status.lucro {
            background:#dcfce7;
            color:#166534;
        }

        .pill-status.prejuizo {
            background:#fee2e2;
            color:#b91c1c;
        }

        .calc-card-row {
            display:flex;
            justify-content:space-between;
            font-size:0.8rem;
            color:#4b5563;
        }

        .calc-card-actions {
            display:flex;
            justify-content:flex-end;
            gap:6px;
            margin-top:6px;
        }

        .btn-link {
            border:none;
            background:none;
            font-size:0.78rem;
            color:#2563eb;
            cursor:pointer;
        }

        .btn-link-danger {
            color:#dc2626;
        }

        @media (max-width: 860px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="custos-main">
    <div class="page-header">
        <div>
            <h1>Calculadora de Custos</h1>
            <p>Simule e salve cálculos de preço de venda, lucro e margem para seus produtos.</p>
        </div>
        <span class="tag-soft">Modo claro ativado 😄</span>
    </div>

    <?php if ($msgSucesso): ?>
        <div class="msg msg-sucesso"><?php echo htmlspecialchars($msgSucesso); ?></div>
    <?php endif; ?>

    <?php if ($msgErro): ?>
        <div class="msg msg-erro"><?php echo htmlspecialchars($msgErro); ?></div>
    <?php endif; ?>

    <div class="layout-grid">
        <!-- CARD ESQUERDA: FORMULÁRIO -->
        <div class="card">
            <h2><?php echo $edicao ? 'Editar Cálculo' : 'Novo Cálculo'; ?></h2>
            <small>Informe custos e preço de venda para calcular automaticamente lucro e margem.</small>

            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="acao" value="salvar_calculo">
                <input type="hidden" name="id_calculo" value="<?php echo $edicao['id'] ?? ''; ?>">

                <div class="form-grid">
                    <div class="input-group full-width">
                        <label>Nome do cálculo (opcional)</label>
                        <input
                            type="text"
                            class="input-control"
                            name="nome_calculo"
                            placeholder="Ex: Lote camisetas pretas, Kit revenda junho..."
                            value="<?php echo htmlspecialchars($edicao['nome_calculo'] ?? ''); ?>">
                    </div>

                    <div class="input-group full-width">
                        <label>Vincular a um produto (opcional)</label>
                        <select name="produto_id" class="select-control">
                            <option value="">— Não vincular —</option>
                            <?php foreach ($listaProdutos as $p): ?>
                                <option value="<?php echo $p['id']; ?>"
                                    <?php echo (!empty($edicao['produto_id']) && $edicao['produto_id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Quantidade de itens</label>
                        <input
                            type="number"
                            class="input-control"
                            name="qtd_itens"
                            min="1"
                            required
                            value="<?php echo htmlspecialchars($edicao['qtd_itens'] ?? '10'); ?>">
                    </div>

                    <div class="input-group">
                        <label>Como você informou o custo?</label>
                        <div class="radio-group">
                            <?php
                            $tipoCustoEd = $edicao['tipo_custo'] ?? 'total';
                            ?>
                            <label class="radio-pill">
                                <input type="radio" name="tipo_custo" value="total" <?php echo $tipoCustoEd === 'unitario' ? '' : 'checked'; ?>>
                                Total do lote
                            </label>
                            <label class="radio-pill">
                                <input type="radio" name="tipo_custo" value="unitario" <?php echo $tipoCustoEd === 'unitario' ? 'checked' : ''; ?>>
                                Valor unitário
                            </label>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Valor de custo informado (R$)</label>
                        <input
                            type="number"
                            step="0.01"
                            class="input-control"
                            name="valor_custo"
                            required
                            placeholder="Ex: 200 (total) ou 20 (unitário)"
                            value="<?php echo htmlspecialchars($edicao['valor_custo_informado'] ?? ''); ?>">
                    </div>

                    <div class="input-group">
                        <label>Outros custos (total do lote) (opcional)</label>
                        <input
                            type="number"
                            step="0.01"
                            class="input-control"
                            name="outros_custos"
                            placeholder="Combustível, alimentação, taxas..."
                            value="<?php echo htmlspecialchars($edicao['outros_custos'] ?? '0'); ?>">
                    </div>

                    <div class="input-group">
                        <label>Preço de venda informado</label>
                        <div class="radio-group" style="margin-bottom:4px;">
                            <?php
                            $tipoVendaEd = $edicao['tipo_venda'] ?? 'unitario';
                            ?>
                            <label class="radio-pill">
                                <input type="radio" name="tipo_venda" value="unitario" <?php echo $tipoVendaEd === 'total' ? '' : 'checked'; ?>>
                                Unitário
                            </label>
                            <label class="radio-pill">
                                <input type="radio" name="tipo_venda" value="total" <?php echo $tipoVendaEd === 'total' ? 'checked' : ''; ?>>
                                Total do lote
                            </label>
                        </div>
                        <input
                            type="number"
                            step="0.01"
                            class="input-control"
                            name="preco_venda"
                            required
                            placeholder="Ex: preço unitário ou valor total"
                            value="<?php echo htmlspecialchars($edicao['valor_venda_informado'] ?? ''); ?>">
                    </div>

                    <div class="input-group full-width">
                        <label>Observações (opcional)</label>
                        <textarea
                            class="input-textarea"
                            name="observacoes"
                            placeholder="Registre observações sobre este cálculo, fornecedor, data da compra etc."><?php
                            echo htmlspecialchars($edicao['observacoes'] ?? '');
                            ?></textarea>
                    </div>
                </div>

                <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                    <?php if ($edicao): ?>
                        <a href="custos.php" class="btn-secondary" style="text-decoration:none;">Cancelar edição</a>
                    <?php endif; ?>
                    <button type="submit" class="btn-primary">
                        <span>Calcular e salvar</span>
                    </button>
                </div>
            </form>

            <?php if ($resultado): ?>
                <?php
                $lucroTotal  = $resultado['lucro_total'];
                $lucroUnit   = $resultado['lucro_unitario'];
                $margem      = $resultado['margem_percent'];
                $prejuizo    = $lucroTotal < 0;
                ?>
                <div style="margin-top:16px;">
                    <div class="section-title">Resumo deste cálculo</div>
                    <div class="resumo-grid">
                        <div class="resumo-item">
                            <div class="resumo-label">Custo total (produtos + outros)</div>
                            <div class="resumo-valor">
                                R$ <?php echo number_format($resultado['custo_total'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="resumo-item">
                            <div class="resumo-label">Custo por unidade</div>
                            <div class="resumo-valor">
                                R$ <?php echo number_format($resultado['custo_unitario'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="resumo-item">
                            <div class="resumo-label">Preço de venda unitário</div>
                            <div class="resumo-valor">
                                R$ <?php echo number_format($resultado['preco_unitario'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="resumo-item">
                            <div class="resumo-label">Preço de venda total</div>
                            <div class="resumo-valor">
                                R$ <?php echo number_format($resultado['preco_total'], 2, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="resumo-item">
                            <div class="resumo-label">Lucro por unidade</div>
                            <div class="resumo-valor">
                                R$ <?php echo number_format($lucroUnit, 2, ',', '.'); ?>
                            </div>
                        </div>
                        <div class="resumo-item">
                            <div class="resumo-label">Margem sobre venda</div>
                            <div class="resumo-valor">
                                <?php echo number_format($margem, 1, ',', '.'); ?>%
                            </div>
                        </div>
                    </div>

                    <div class="status-lucro <?php echo $prejuizo ? 'prejuizo' : 'lucro'; ?>">
                        <span>
                            <?php if ($prejuizo): ?>
                                <strong>Prejuízo neste lote</strong><br>
                                Reveja preço de venda ou custos.
                            <?php else: ?>
                                <strong>Lucro neste lote</strong><br>
                                Estrutura atual está saudável.
                            <?php endif; ?>
                        </span>
                        <span style="font-weight:700;">
                            R$ <?php echo number_format($lucroTotal, 2, ',', '.'); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- CARD DIREITA: LISTA DE CÁLCULOS -->
        <div class="card">
            <div class="calc-list-header">
                <div>
                    <h2>Cálculos salvos</h2>
                    <small>Veja histórico de simulações de preço por lote/produto.</small>
                </div>
            </div>

            <form method="GET" class="calc-search">
                <input
                    type="text"
                    name="busca"
                    placeholder="Buscar por nome do cálculo ou produto..."
                    value="<?php echo htmlspecialchars($busca); ?>">
                <button type="submit">Filtrar</button>
            </form>

            <?php if (empty($listaCalculos)): ?>
                <p style="margin-top:14px; font-size:0.85rem; color:#6b7280;">
                    Nenhum cálculo salvo ainda. Faça um cálculo ao lado e salve para começar o histórico.
                </p>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($listaCalculos as $c): ?>
                        <?php
                            $lucroTot = (float)$c['lucro_total'];
                            $prejuizoCard = $lucroTot < 0;
                            $margemCard = (float)$c['margem_percent'];
                        ?>
                        <div class="calc-card">
                            <div class="calc-card-header">
                                <div>
                                    <div class="calc-card-title">
                                        <?php echo htmlspecialchars($c['nome_calculo'] ?: 'Cálculo #' . $c['id']); ?>
                                    </div>
                                    <div class="calc-card-sub">
                                        <?php if ($c['produto_nome']): ?>
                                            Produto: <?php echo htmlspecialchars($c['produto_nome']); ?> ·
                                        <?php endif; ?>
                                        <?php echo (int)$c['qtd_itens']; ?> itens ·
                                        <?php
                                        $data = $c['data_criacao'] ? date('d/m/Y H:i', strtotime($c['data_criacao'])) : '';
                                        echo $data;
                                        ?>
                                    </div>
                                </div>
                                <span class="pill-status <?php echo $prejuizoCard ? 'prejuizo' : 'lucro'; ?>">
                                    <?php echo $prejuizoCard ? 'Prejuízo' : 'Lucro'; ?>
                                </span>
                            </div>

                            <div class="calc-card-row">
                                <span>Custo unitário</span>
                                <span>R$ <?php echo number_format($c['custo_unitario'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="calc-card-row">
                                <span>Venda unitária</span>
                                <span>R$ <?php echo number_format($c['preco_venda_unitario'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="calc-card-row">
                                <span>Lucro total</span>
                                <span>R$ <?php echo number_format($lucroTot, 2, ',', '.'); ?></span>
                            </div>
                            <div class="calc-card-row">
                                <span>Margem</span>
                                <span><?php echo number_format($margemCard, 1, ',', '.'); ?>%</span>
                            </div>

                            <div class="calc-card-actions">
                                <button
                                    type="button"
                                    class="btn-link"
                                    onclick="window.location.href='?editar=<?php echo (int)$c['id']; ?>'">
                                    Editar
                                </button>
                                <button
                                    type="button"
                                    class="btn-link btn-link-danger"
                                    onclick="confirmarExclusao(<?php echo (int)$c['id']; ?>)">
                                    Excluir
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    function confirmarExclusao(id) {
        if (confirm('Deseja realmente excluir este cálculo?')) {
            window.location.href = '?excluir=' + id;
        }
    }
</script>
</body>
</html>
