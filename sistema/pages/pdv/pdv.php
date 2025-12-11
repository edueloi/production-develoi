<?php
// ==========================================
// 1. BACKEND & LÓGICA DE VENDAS
// ==========================================
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';

// Garantir tabelas
$pdo->exec("CREATE TABLE IF NOT EXISTS vendas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data_venda DATETIME DEFAULT CURRENT_TIMESTAMP,
    cliente_id INTEGER,
    nome_cliente TEXT,
    cpf_cliente TEXT,
    valor_total REAL,
    forma_pagamento TEXT,
    valor_recebido REAL,
    troco REAL,
    observacoes TEXT
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS vendas_itens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    venda_id INTEGER,
    produto_id INTEGER,
    variacao_id INTEGER,
    nome_produto TEXT,
    cor TEXT,
    tamanho TEXT,
    quantidade INTEGER,
    preco_unit REAL,
    subtotal REAL,
    FOREIGN KEY(venda_id) REFERENCES vendas(id) ON DELETE CASCADE
)");

// --- PROCESSAR VENDA (POST) ---
$msgSucesso = null;
$msgErro = null;
$ultimaVendaId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'finalizar_venda') {
    try {
        $pdo->beginTransaction();

        $carrinho       = json_decode($_POST['carrinho_json'] ?? '[]', true);
        $idCliente      = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
        $nomeManual     = trim($_POST['nome_cliente_manual'] ?? '');
        $cpfManual      = trim($_POST['cpf_cliente_manual'] ?? '');
        $pagamento      = $_POST['forma_pagamento'] ?? 'dinheiro';
        $valorRecebido  = (float)($_POST['valor_recebido'] ?? 0);
        $obs            = trim($_POST['observacoes'] ?? '');

        if (empty($carrinho)) throw new Exception("O carrinho está vazio.");

        // Calcular Totais
        $totalVenda = 0;
        foreach ($carrinho as $item) {
            $totalVenda += ($item['quantidade'] * $item['preco_unit']);
        }

        // Validar Troco
        $troco = 0;
        if ($pagamento === 'dinheiro') {
            if ($valorRecebido < $totalVenda) throw new Exception("Valor recebido insuficiente.");
            $troco = $valorRecebido - $totalVenda;
        } else {
            $valorRecebido = $totalVenda;
        }

        // Definir Cliente
        $nomeFinal = $nomeManual;
        $cpfFinal  = $cpfManual;
        
        if ($idCliente) {
            $stmtCli = $pdo->prepare("SELECT nome, cpf FROM clientes WHERE id = ?");
            $stmtCli->execute([$idCliente]);
            $dadosCli = $stmtCli->fetch(PDO::FETCH_ASSOC);
            if ($dadosCli) {
                $nomeFinal = $dadosCli['nome'];
                $cpfFinal  = $dadosCli['cpf'];
            }
        }

        // 1. Registrar Venda
        $stmtVenda = $pdo->prepare("INSERT INTO vendas (cliente_id, nome_cliente, cpf_cliente, valor_total, forma_pagamento, valor_recebido, troco, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtVenda->execute([$idCliente, $nomeFinal, $cpfFinal, $totalVenda, $pagamento, $valorRecebido, $troco, $obs]);
        $ultimaVendaId = $pdo->lastInsertId();

        // 2. Itens e Baixa de Estoque
        $stmtItem = $pdo->prepare("INSERT INTO vendas_itens (venda_id, produto_id, variacao_id, nome_produto, cor, tamanho, quantidade, preco_unit, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtEstoque = $pdo->prepare("UPDATE produtos_variacoes SET estoque = estoque - ? WHERE id = ?");

        foreach ($carrinho as $item) {
            $subtotal = $item['quantidade'] * $item['preco_unit'];
            
            // Tratamento para serviços (produto_id nulo ou flag) vs produtos
            $prodId = isset($item['produto_id']) ? $item['produto_id'] : null;
            $varId  = isset($item['variacao_id']) ? $item['variacao_id'] : null;

            $stmtItem->execute([
                $ultimaVendaId, 
                $prodId, 
                $varId, 
                $item['nome_produto'], 
                $item['cor'] ?? '', 
                $item['tamanho'] ?? '', 
                $item['quantidade'], 
                $item['preco_unit'], 
                $subtotal
            ]);

            // Só baixa estoque se tiver variação vinculada (produtos físicos)
            if ($varId) {
                $stmtEstoque->execute([$item['quantidade'], $varId]);
            }
        }

        $pdo->commit();
        $msgSucesso = "Venda realizada com sucesso!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $msgErro = $e->getMessage();
    }
}

// --- DADOS PARA O FRONTEND ---
// 1. Produtos
$sqlProd = "SELECT p.id, p.nome, p.marca, p.imagem_capa, p.preco_venda as preco, 
            v.id as var_id, v.cor, v.tamanho, v.estoque, v.preco_venda as var_preco 
            FROM produtos p 
            JOIN produtos_variacoes v ON v.produto_id = p.id 
            WHERE p.ativo = 1 AND v.estoque > 0
            ORDER BY p.nome";
$produtos = $pdo->query($sqlProd)->fetchAll(PDO::FETCH_ASSOC);

// 2. Serviços
$servicos = [];
try {
    // Verifica se tabela existe antes de consultar para evitar erros em instalação nova
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='servicos'");
    if($check->fetch()) {
        $sqlServ = "SELECT id, nome, categoria, imagem, preco, duracao_minutos FROM servicos WHERE ativo = 1 ORDER BY nome";
        $servicos = $pdo->query($sqlServ)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Exception $e) { /* ignora */ }

// 3. Clientes
$clientes = $pdo->query("SELECT id, nome, cpf FROM clientes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>PDV Profissional</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* ================= VÁRIAVEIS DE TEMA ================= */
        :root {
            --bg-app: #f1f5f9;
            --bg-panel: #ffffff;
            --primary: #4f46e5;       /* Indigo */
            --primary-dark: #4338ca;
            --accent: #0ea5e9;        /* Sky Blue */
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --danger: #ef4444;
            --radius-lg: 16px;
            --radius-md: 10px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-app);
            color: var(--text-main);
            margin: 0;
            height: 100vh;
            overflow: hidden; /* App-like feel */
        }

        /* ================= LAYOUT PRINCIPAL ================= */
        .pdv-wrapper {
            display: grid;
            grid-template-columns: 1fr 420px; /* Coluna esquerda flexível, direita fixa */
            height: calc(100vh - 70px); /* Desconta altura aproximada do menu */
            gap: 20px;
            padding: 20px;
            box-sizing: border-box;
        }

        /* --- COLUNA ESQUERDA (CATÁLOGO) --- */
        .catalog-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: 100%;
            overflow: hidden;
        }

        /* Barra de Busca e Filtros */
        .top-bar {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-container {
            flex: 1;
            position: relative;
        }
        .search-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .search-input {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-md);
            font-size: 1rem;
            outline: none;
            transition: 0.2s;
            box-shadow: var(--shadow-sm);
        }
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .filter-tabs {
            display: flex;
            background: #e2e8f0;
            padding: 4px;
            border-radius: var(--radius-md);
        }
        .tab-btn {
            border: none;
            background: transparent;
            padding: 10px 20px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 8px;
            transition: 0.2s;
        }
        .tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        /* Grid de Produtos */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            overflow-y: auto;
            padding: 5px;
            padding-bottom: 40px; /* Espaço extra footer */
        }

        .item-card {
            background: var(--bg-panel);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .item-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        .item-card:active { transform: scale(0.98); }

        .card-img {
            height: 110px;
            width: 100%;
            object-fit: cover;
            background: #f1f5f9;
            border-bottom: 1px solid var(--border);
        }
        .card-body { padding: 12px; flex: 1; display: flex; flex-direction: column; }
        
        .card-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; line-height: 1.3; color: var(--text-main); }
        .card-sub { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px; }
        
        .card-footer { 
            margin-top: auto; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .price-tag { font-weight: 700; color: var(--primary); font-size: 1rem; }
        .stock-badge { 
            font-size: 0.7rem; font-weight: 600; padding: 2px 8px; 
            border-radius: 12px; background: #ecfdf5; color: #059669; 
        }

        /* --- COLUNA DIREITA (CART) --- */
        .cart-panel {
            background: var(--bg-panel);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 100%;
        }

        /* Header do Carrinho (Cliente) */
        .cart-header {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }
        .client-selector {
            display: flex;
            gap: 10px;
        }
        .client-select-box {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            background: white;
        }
        .btn-add-client {
            width: 40px; height: 40px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 8px;
            color: var(--primary);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }

        /* Manual inputs (hidden by default) */
        .manual-client-inputs {
            display: none;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
            margin-top: 10px;
            animation: slideDown 0.2s;
        }
        @keyframes slideDown { from {opacity:0; transform:translateY(-5px);} to {opacity:1; transform:translateY(0);} }

        /* Lista de Itens */
        .cart-items-container {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background: white;
        }
        .cart-table { width: 100%; border-collapse: collapse; }
        .cart-table th {
            position: sticky; top: 0; background: #f8fafc;
            text-align: left; padding: 10px 15px; font-size: 0.75rem;
            color: var(--text-muted); text-transform: uppercase;
            border-bottom: 1px solid var(--border); z-index: 10;
        }
        .cart-row { border-bottom: 1px solid #f1f5f9; }
        .cart-row td { padding: 12px 15px; vertical-align: middle; }
        
        .c-info { display: flex; flex-direction: column; }
        .c-name { font-weight: 600; font-size: 0.9rem; color: var(--text-main); }
        .c-meta { font-size: 0.75rem; color: var(--text-muted); }

        .qty-control {
            display: flex; align-items: center; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; width: 80px;
        }
        .btn-qty {
            width: 24px; height: 28px; background: #f8fafc; border: none; cursor: pointer; color: var(--text-muted); font-weight: bold;
        }
        .btn-qty:hover { background: #e2e8f0; }
        .input-qty {
            width: 32px; border: none; text-align: center; font-size: 0.9rem; outline: none; -moz-appearance: textfield;
        }

        .btn-trash { color: #fee2e2; background: white; border: 1px solid #fecaca; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .btn-trash:hover { background: #ef4444; color: white; border-color: #ef4444; }
        .btn-trash i { font-size: 0.9rem; }

        /* Checkout Footer */
        .checkout-footer {
            background: #ffffff;
            border-top: 1px solid var(--border);
            padding: 20px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.03);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        .pay-btn {
            border: 1px solid var(--border);
            background: #f8fafc;
            padding: 10px 5px;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            transition: 0.2s;
            display: flex; flex-direction: column; align-items: center; gap: 5px;
        }
        .pay-btn i { font-size: 1.2rem; }
        .pay-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
        
        /* Estado Ativo do Botão de Pagamento */
        .pay-btn.active {
            background: #eff6ff; /* Azul bem claro */
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .money-input-group {
            display: none; /* Só aparece se dinheiro */
            margin-bottom: 15px;
            background: #f0fdf4;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
        }
        .money-label { font-size: 0.8rem; color: #166534; font-weight: 600; display: block; margin-bottom: 5px; }
        .money-field {
            width: 100%; padding: 8px; border: 1px solid #86efac; border-radius: 6px; outline: none; font-size: 1rem; color: #14532d;
        }

        .summary-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 5px; font-size: 0.9rem; color: var(--text-muted);
        }
        .total-row {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-top: 10px; margin-bottom: 15px;
            padding-top: 10px; border-top: 1px dashed var(--border);
        }
        .total-label { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
        .total-amount { font-size: 1.8rem; font-weight: 800; color: var(--primary); line-height: 1; }

        .btn-finish {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            transition: 0.2s;
            display: flex; justify-content: center; align-items: center; gap: 10px;
        }
        .btn-finish:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4); }
        .btn-finish:active { transform: translateY(0); }

        /* MENSAGEM SUCESSO FLUTUANTE */
        .toast {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            background: white; border-left: 5px solid var(--success);
            padding: 15px 20px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: flex; align-items: center; gap: 15px;
            animation: slideInRight 0.3s ease;
        }
        .btn-print {
            padding: 6px 12px; background: #ecfdf5; color: #059669; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem; border: 1px solid #6ee7b7;
        }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Responsividade */
        @media (max-width: 1000px) {
            .pdv-wrapper { grid-template-columns: 1fr; height: auto; }
            .cart-panel { height: 600px; margin-top: 20px; }
            body { overflow: auto; }
        }
    </style>
</head>
<body>

<?php if($msgSucesso): ?>
    <div class="toast">
        <div>
            <div style="font-weight: 700; color: var(--text-main); margin-bottom: 2px;">Venda Finalizada!</div>
            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $msgSucesso; ?></div>
        </div>
        <?php if($ultimaVendaId): ?>
            <a href="pdv_nota.php?id=<?php echo $ultimaVendaId; ?>" target="_blank" class="btn-print">
                <i class="bi bi-printer"></i> Imprimir
            </a>
        <?php endif; ?>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:#999; cursor:pointer; font-size:1.2rem;">&times;</button>
    </div>
<?php endif; ?>

<main class="pdv-wrapper">
    
    <section class="catalog-panel">
        <div class="top-bar">
            <div class="search-container">
                <i class="bi bi-search"></i>
                <input type="text" id="searchBox" class="search-input" placeholder="Buscar produto, código ou serviço..." onkeyup="filtrarCatalogo()">
            </div>
            <div class="filter-tabs">
                <button class="tab-btn active" onclick="filtrarTipo('todos', this)">Todos</button>
                <button class="tab-btn" onclick="filtrarTipo('produto', this)">Produtos</button>
                <button class="tab-btn" onclick="filtrarTipo('servico', this)">Serviços</button>
            </div>
        </div>

        <div class="items-grid" id="gridItens">
            <?php foreach($produtos as $p): 
                $img = !empty($p['imagem_capa']) ? '../../assets/uploads/produtos/'.$p['imagem_capa'] : 'https://via.placeholder.com/200x120?text=IMG';
                // Preço da variação ou do produto pai
                $precoShow = !empty($p['var_preco']) ? $p['var_preco'] : $p['preco'];
                $searchData = strtolower($p['nome'].' '.$p['marca'].' '.$p['cor'].' '.$p['tamanho']);
            ?>
            <div class="item-card" data-tipo="produto" data-search="<?php echo $searchData; ?>" 
                 onclick="addItem({
                     id: '<?php echo $p['id']; ?>', 
                     varId: '<?php echo $p['var_id']; ?>', 
                     nome: '<?php echo addslashes($p['nome']); ?>', 
                     meta: '<?php echo addslashes($p['cor'].' '.$p['tamanho']); ?>', 
                     preco: <?php echo $precoShow ?: 0; ?>, 
                     estoque: <?php echo $p['estoque']; ?>, 
                     tipo: 'produto'
                 })">
                <img src="<?php echo $img; ?>" class="card-img">
                <div class="card-body">
                    <div class="card-title"><?php echo htmlspecialchars($p['nome']); ?></div>
                    <div class="card-sub"><?php echo htmlspecialchars($p['marca'].' - '.$p['cor'].' '.$p['tamanho']); ?></div>
                    <div class="card-footer">
                        <span class="price-tag">R$ <?php echo number_format($precoShow, 2, ',', '.'); ?></span>
                        <span class="stock-badge"><?php echo $p['estoque']; ?> un</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach($servicos as $s): 
                $img = !empty($s['imagem']) ? '../../assets/uploads/servicos/'.$s['imagem'] : 'https://via.placeholder.com/200x120?text=Serviço';
                $searchData = strtolower($s['nome'].' '.$s['categoria']);
            ?>
            <div class="item-card" data-tipo="servico" data-search="<?php echo $searchData; ?>"
                 onclick="addItem({
                     id: '<?php echo $s['id']; ?>', 
                     varId: null, 
                     nome: '<?php echo addslashes($s['nome']); ?>', 
                     meta: '<?php echo addslashes($s['categoria']); ?>', 
                     preco: <?php echo $s['preco'] ?: 0; ?>, 
                     estoque: 9999, 
                     tipo: 'servico'
                 })">
                <img src="<?php echo $img; ?>" class="card-img">
                <div class="card-body">
                    <div class="card-title"><?php echo htmlspecialchars($s['nome']); ?></div>
                    <div class="card-sub">Serviço • <?php echo htmlspecialchars($s['categoria']); ?></div>
                    <div class="card-footer">
                        <span class="price-tag">R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?></span>
                        <span class="stock-badge" style="background:#e0e7ff; color:#4338ca;">Infinito</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <form class="cart-panel" method="POST" id="formVenda" onsubmit="return validarVenda()">
        <input type="hidden" name="acao" value="finalizar_venda">
        <input type="hidden" name="carrinho_json" id="inputCarrinho">
        <input type="hidden" name="forma_pagamento" id="inputPagamento" value="dinheiro">

        <div class="cart-header">
            <div class="client-selector">
                <select name="cliente_id" class="client-select-box" onchange="toggleManual(this)">
                    <option value="">Cliente Balcão (Não Identificado)</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                    <?php endforeach; ?>
                    <option value="manual">+ Digitar Nome/CPF Manualmente...</option>
                </select>
            </div>
            <div class="manual-client-inputs" id="manualInputs">
                <input type="text" name="nome_cliente_manual" class="client-select-box" placeholder="Nome Completo">
                <input type="text" name="cpf_cliente_manual" class="client-select-box" placeholder="CPF">
            </div>
        </div>

        <div class="cart-items-container">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Item</th>
                        <th style="width: 25%; text-align: center;">Qtd</th>
                        <th style="width: 25%; text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody id="cartBody">
                    <tr><td colspan="3" style="text-align:center; padding:30px; color:#94a3b8;">Carrinho vazio</td></tr>
                </tbody>
            </table>
        </div>

        <div class="checkout-footer">
            <div style="font-size:0.85rem; font-weight:600; margin-bottom:8px; color:var(--text-muted);">FORMA DE PAGAMENTO</div>
            <div class="payment-methods">
                <div class="pay-btn active" onclick="selectPayment('dinheiro', this)">
                    <i class="bi bi-cash-coin"></i> Dinheiro
                </div>
                <div class="pay-btn" onclick="selectPayment('cartao_credito', this)">
                    <i class="bi bi-credit-card"></i> Crédito
                </div>
                <div class="pay-btn" onclick="selectPayment('cartao_debito', this)">
                    <i class="bi bi-credit-card-2-front"></i> Débito
                </div>
                <div class="pay-btn" onclick="selectPayment('pix', this)">
                    <i class="bi bi-qr-code"></i> PIX
                </div>
            </div>

            <div class="money-input-group" id="moneyGroup" style="display:block;">
                <label class="money-label">VALOR RECEBIDO PELO CLIENTE</label>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <input type="number" step="0.01" name="valor_recebido" id="inpRecebido" class="money-field" placeholder="0,00" oninput="calcTroco()">
                    <div style="margin-left:15px; text-align:right;">
                        <span style="font-size:0.75rem; color:#166534;">TROCO</span>
                        <div id="displayTroco" style="font-weight:700; color:#166534;">R$ 0,00</div>
                    </div>
                </div>
            </div>

            <div class="total-row">
                <div class="total-label">TOTAL A PAGAR</div>
                <div class="total-amount" id="displayTotal">R$ 0,00</div>
            </div>

            <button type="submit" class="btn-finish">
                <span>CONCLUIR VENDA</span>
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </form>
</main>

<script>
    // --- ESTADO DO CARRINHO ---
    let cart = [];

    // --- FUNÇÕES DE CARRINHO ---
    function addItem(data) {
        // Cria ID único (ProdutoID + VariacaoID)
        const uid = data.tipo + '-' + data.id + '-' + (data.varId || '0');
        const existing = cart.find(i => i.uid === uid);

        if(existing) {
            // Verifica estoque apenas para produtos
            if(data.tipo === 'produto' && existing.quantidade >= data.estoque) {
                alert('Estoque máximo atingido para este item!');
                return;
            }
            existing.quantidade++;
        } else {
            cart.push({
                uid: uid,
                produto_id: data.tipo === 'produto' ? data.id : null,
                variacao_id: data.varId,
                servico_id: data.tipo === 'servico' ? data.id : null,
                nome_produto: data.nome,
                cor: data.meta, // Usamos 'cor' como metadado geral (cor/tamanho ou categoria)
                tamanho: '', 
                preco_unit: parseFloat(data.preco),
                quantidade: 1,
                tipo: data.tipo
            });
        }
        renderCart();
    }

    function updateQtd(idx, delta) {
        const item = cart[idx];
        const newQtd = item.quantidade + delta;
        if(newQtd < 1) return; // Mínimo 1
        
        // Verifica estoque se for produto
        /* Nota: Para verificar estoque ao aumentar aqui, precisaríamos ter o estoque salvo no objeto do carrinho.
           Por simplificação, deixamos livre aqui, mas o ideal é passar o 'estoqueMax' no push inicial.
        */
        item.quantidade = newQtd;
        renderCart();
    }

    function removeItem(idx) {
        cart.splice(idx, 1);
        renderCart();
    }

    function renderCart() {
        const tbody = document.getElementById('cartBody');
        tbody.innerHTML = '';
        let total = 0;

        if(cart.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:30px; color:#94a3b8;">Carrinho vazio</td></tr>';
        } else {
            cart.forEach((item, idx) => {
                const subtotal = item.quantidade * item.preco_unit;
                total += subtotal;

                const tr = document.createElement('tr');
                tr.className = 'cart-row';
                tr.innerHTML = `
                    <td>
                        <div class="c-info">
                            <span class="c-name">${item.nome_produto}</span>
                            <span class="c-meta">${item.cor}</span>
                            <span style="font-size:0.75rem; color:#4f46e5;">R$ ${item.preco_unit.toFixed(2).replace('.',',')}</span>
                        </div>
                    </td>
                    <td align="center">
                        <div class="qty-control">
                            <button type="button" class="btn-qty" onclick="updateQtd(${idx}, -1)">-</button>
                            <input class="input-qty" value="${item.quantidade}" readonly>
                            <button type="button" class="btn-qty" onclick="updateQtd(${idx}, 1)">+</button>
                        </div>
                    </td>
                    <td align="right">
                        <div style="font-weight:600; font-size:0.9rem;">R$ ${subtotal.toFixed(2).replace('.',',')}</div>
                        <div style="display:flex; justify-content:flex-end; margin-top:4px;">
                            <button type="button" class="btn-trash" onclick="removeItem(${idx})"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Atualiza Totais
        const totalFmt = total.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
        document.getElementById('displayTotal').innerText = totalFmt;
        document.getElementById('inputCarrinho').value = JSON.stringify(cart);
        
        calcTroco(total);
    }

    // --- PAGAMENTO E TROCO ---
    function selectPayment(method, btn) {
        // Atualiza input hidden
        document.getElementById('inputPagamento').value = method;
        
        // Atualiza visual dos botões
        document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Mostra/Esconde campo de dinheiro
        const moneyGroup = document.getElementById('moneyGroup');
        if(method === 'dinheiro') {
            moneyGroup.style.display = 'block';
        } else {
            moneyGroup.style.display = 'none';
            document.getElementById('inpRecebido').value = '';
            document.getElementById('displayTroco').innerText = 'R$ 0,00';
        }
    }

    function calcTroco(totalOverride) {
        // Calcula total atual do carrinho
        let total = 0;
        cart.forEach(i => total += (i.quantidade * i.preco_unit));
        
        const recebido = parseFloat(document.getElementById('inpRecebido').value.replace(',','.') || 0);
        const troco = recebido - total;

        const el = document.getElementById('displayTroco');
        if(troco > 0) {
            el.innerText = troco.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
            el.style.color = 'var(--success)';
        } else {
            el.innerText = 'R$ 0,00';
            el.style.color = '#166534'; // mantém verde escuro ou neutro
        }
    }

    // --- FILTROS E BUSCA ---
    function filtrarCatalogo() {
        const termo = document.getElementById('searchBox').value.toLowerCase();
        document.querySelectorAll('.item-card').forEach(card => {
            const txt = card.getAttribute('data-search');
            // Verifica também se o tipo está visível (tabs)
            const tipo = card.getAttribute('data-tipo');
            const isVisibleType = currentFilter === 'todos' || currentFilter === tipo;
            
            if(txt.includes(termo) && isVisibleType) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    let currentFilter = 'todos';
    function filtrarTipo(tipo, btn) {
        currentFilter = tipo;
        
        // Atualiza botões
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Re-aplica filtros
        filtrarCatalogo();
    }

    function toggleManual(select) {
        const div = document.getElementById('manualInputs');
        if(select.value === 'manual') {
            div.style.display = 'grid';
        } else {
            div.style.display = 'none';
        }
    }

    function validarVenda() {
        if(cart.length === 0) { alert('Adicione itens ao carrinho!'); return false; }
        
        const method = document.getElementById('inputPagamento').value;
        if(method === 'dinheiro') {
            let total = 0;
            cart.forEach(i => total += (i.quantidade * i.preco_unit));
            const rec = parseFloat(document.getElementById('inpRecebido').value.replace(',','.') || 0);
            
            if(rec < total) {
                alert('Valor recebido é menor que o total da venda!');
                return false;
            }
        }
        return true;
    }
</script>

</body>
</html>