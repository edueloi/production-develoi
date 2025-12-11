<?php
// ==========================================
// 1. CONFIGURAÇÕES E CONEXÃO
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

// ==========================================
// 2. PROCESSAR FINALIZAÇÃO (POST)
// ==========================================
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

        $totalVenda = 0;
        foreach ($carrinho as $item) {
            $totalVenda += ($item['quantidade'] * $item['preco_unit']);
        }

        $troco = 0;
        if ($pagamento === 'dinheiro') {
            if ($valorRecebido < $totalVenda) throw new Exception("Valor recebido é menor que o total.");
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

        // Salvar Venda
        $stmtVenda = $pdo->prepare("INSERT INTO vendas (cliente_id, nome_cliente, cpf_cliente, valor_total, forma_pagamento, valor_recebido, troco, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtVenda->execute([$idCliente, $nomeFinal, $cpfFinal, $totalVenda, $pagamento, $valorRecebido, $troco, $obs]);
        $ultimaVendaId = $pdo->lastInsertId();

        // Salvar Itens e Baixar Estoque
        $stmtItem = $pdo->prepare("INSERT INTO vendas_itens (venda_id, produto_id, variacao_id, nome_produto, cor, tamanho, quantidade, preco_unit, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtEstoque = $pdo->prepare("UPDATE produtos_variacoes SET estoque = estoque - ? WHERE id = ?");

        foreach ($carrinho as $item) {
            $subtotal = $item['quantidade'] * $item['preco_unit'];
            $stmtItem->execute([$ultimaVendaId, $item['produto_id'], $item['variacao_id'], $item['nome_produto'], $item['cor'], $item['tamanho'], $item['quantidade'], $item['preco_unit'], $subtotal]);

            if (!empty($item['variacao_id'])) {
                $stmtEstoque->execute([$item['quantidade'], $item['variacao_id']]);
            }
        }

        $pdo->commit();
        $msgSucesso = "Venda #$ultimaVendaId finalizada!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $msgErro = $e->getMessage();
    }
}

// ==========================================
// 3. DADOS INICIAIS
// ==========================================
// Filtrar produtos e clientes pelo owner_id do usuário logado
$owner_id = $_SESSION['user_id'];

$sqlProd = "SELECT p.id, p.nome, p.marca, p.modelo, p.imagem_capa, 
            v.id as var_id, v.cor, v.tamanho, v.estoque 
            FROM produtos p 
            JOIN produtos_variacoes v ON v.produto_id = p.id 
            WHERE p.ativo = 1 AND v.estoque > 0 AND p.owner_id = :owner_id
            ORDER BY p.nome";
$stmtProd = $pdo->prepare($sqlProd);
$stmtProd->execute([':owner_id' => $owner_id]);
$produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

$stmtCli = $pdo->prepare("SELECT id, nome, cpf FROM clientes WHERE owner_id = :owner_id ORDER BY nome");
$stmtCli->execute([':owner_id' => $owner_id]);
$clientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>PDV Profissional</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #f3f4f6;
            --surface: #ffffff;
            --primary: #4f46e5;       /* Indigo */
            --primary-hover: #4338ca;
            --text-main: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --danger: #ef4444;
            --success: #10b981;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding-bottom: 20px;
        }

        .pdv-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            max-width: 1600px;
            margin: 20px auto;
            padding: 0 20px;
            height: calc(100vh - 100px); /* Ajuste conforme altura do menu */
        }

        /* --- COLUNA ESQUERDA (PRODUTOS) --- */
        .left-col {
            display: flex;
            flex-direction: column;
            gap: 15px;
            height: 100%;
        }

        .search-container {
            background: var(--surface);
            padding: 15px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            border: none;
            font-size: 1.1rem;
            outline: none;
            color: var(--text-main);
        }
        .search-icon { color: var(--text-muted); font-size: 1.2rem; }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            overflow-y: auto;
            padding-right: 5px;
            padding-bottom: 20px;
        }

        .product-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .p-image {
            height: 120px;
            width: 100%;
            object-fit: cover;
            background: #f9fafb;
            border-bottom: 1px solid var(--border);
        }

        .p-info {
            padding: 12px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .p-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; line-height: 1.3; }
        .p-desc { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 8px; }
        .p-stock { 
            margin-top: auto; 
            font-size: 0.75rem; 
            font-weight: 700; 
            color: var(--success); 
            background: #ecfdf5; 
            padding: 3px 8px; 
            border-radius: 99px; 
            align-self: flex-start; 
        }

        /* --- COLUNA DIREITA (CHECKOUT) --- */
        .right-col {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 100%;
        }

        /* Header Carrinho */
        .cart-header {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
        }

        /* Lista Carrinho */
        .cart-list {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            transition: 0.1s;
        }
        .cart-item:hover { background: #f9fafb; }

        .item-info { flex: 1; }
        .item-name { font-weight: 600; font-size: 0.9rem; display: block; }
        .item-var { font-size: 0.8rem; color: var(--text-muted); }
        
        .item-actions { display: flex; align-items: center; gap: 10px; }
        .qty-input {
            width: 50px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 4px;
            font-size: 0.9rem;
        }
        .price-input {
            width: 70px;
            text-align: right;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 4px;
            font-size: 0.9rem;
        }
        .btn-del { color: var(--danger); background: none; border: none; cursor: pointer; font-size: 1.1rem; }

        /* Footer Checkout */
        .checkout-area {
            padding: 20px;
            background: #ffffff;
            border-top: 1px solid var(--border);
            box-shadow: 0 -4px 6px -1px rgba(0,0,0,0.02);
        }

        .total-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .total-label { font-size: 1.2rem; font-weight: 600; color: var(--text-muted); }
        .total-value { font-size: 2rem; font-weight: 800; color: var(--primary); }

        .form-row { margin-bottom: 12px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-muted); margin-bottom: 5px; }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            transition: 0.2s;
            box-sizing: border-box; /* Garante que padding não estoure */
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px #e0e7ff; }

        .btn-checkout {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-checkout:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Mensagens */
        .alert-box {
            padding: 15px; border-radius: 8px; margin-bottom: 15px; font-weight: 500; display: flex; justify-content: space-between; align-items: center;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .btn-nota {
            background: #fff; color: #166534; padding: 5px 12px; border-radius: 6px; 
            text-decoration: none; font-size: 0.9rem; border: 1px solid #86efac; font-weight: 600;
        }
        .btn-nota:hover { background: #f0fdf4; }

        /* Responsividade */
        @media (max-width: 1000px) {
            .pdv-layout { grid-template-columns: 1fr; height: auto; }
            .right-col { height: auto; max-height: 600px; }
            .products-grid { max-height: 400px; }
        }
    </style>
</head>
<body>

<main>
    <div class="pdv-layout">
        
        <div class="left-col">
            <h2 style="margin:0; font-size:1.5rem;">Frente de Caixa</h2>
            
            <?php if($msgSucesso): ?>
                <div class="alert-box alert-success">
                    <span><i class="bi bi-check-circle-fill"></i> <?php echo $msgSucesso; ?></span>
                    <?php if($ultimaVendaId): ?>
                        <a href="pdv_nota.php?id=<?php echo $ultimaVendaId; ?>" target="_blank" class="btn-nota">
                            <i class="bi bi-printer"></i> Imprimir Nota
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if($msgErro): ?>
                <div class="alert-box alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $msgErro; ?>
                </div>
            <?php endif; ?>

            <div class="search-container">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="searchBox" class="search-input" placeholder="Buscar produto (nome, cor, código)..." onkeyup="filtrarProdutos()">
            </div>

            <div class="products-grid">
                <?php foreach($produtos as $p): 
                    $img = !empty($p['imagem_capa']) ? '../../assets/uploads/produtos/'.$p['imagem_capa'] : 'https://via.placeholder.com/200x120?text=Sem+Foto';
                    $dataSearch = strtolower($p['nome'].' '.$p['marca'].' '.$p['cor'].' '.$p['tamanho']);
                ?>
                <div class="product-card" data-search="<?php echo $dataSearch; ?>" onclick="addItem(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                    <img src="<?php echo $img; ?>" class="p-image">
                    <div class="p-info">
                        <div class="p-name"><?php echo htmlspecialchars($p['nome']); ?></div>
                        <div class="p-desc"><?php echo htmlspecialchars($p['marca'] . ' - ' . $p['cor'] . ' ' . $p['tamanho']); ?></div>
                        <div class="p-stock">Estoque: <?php echo $p['estoque']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="right-col">
            <div class="cart-header">
                <span>Carrinho de Compras</span>
                <span id="cartCount">0 itens</span>
            </div>

            <ul class="cart-list" id="cartList">
                <li style="padding:30px; text-align:center; color:#9ca3af;">
                    <i class="bi bi-cart3" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                    Seu carrinho está vazio.
                </li>
            </ul>

            <form method="POST" class="checkout-area" onsubmit="return validarVenda()">
                <input type="hidden" name="acao" value="finalizar_venda">
                <input type="hidden" name="carrinho_json" id="inputCarrinho">

                <div class="total-display">
                    <div class="total-label">Total</div>
                    <div class="total-value" id="displayTotal">R$ 0,00</div>
                </div>

                <div class="form-row">
                    <label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-control" onchange="toggleManual(this)">
                        <option value="">Cliente Avulso (Balcão)</option>
                        <?php foreach($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row" id="manualInputs" style="display:none; grid-template-columns: 2fr 1fr; gap:10px;">
                    <input type="text" name="nome_cliente_manual" class="form-control" placeholder="Nome Cliente">
                    <input type="text" name="cpf_cliente_manual" class="form-control" placeholder="CPF">
                </div>

                <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div>
                        <label class="form-label">Pagamento</label>
                        <select name="forma_pagamento" id="selPagto" class="form-control" onchange="checkPagto()">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao_credito">Crédito</option>
                            <option value="cartao_debito">Débito</option>
                            <option value="pix">PIX</option>
                        </select>
                    </div>
                    <div id="divRecebido">
                        <label class="form-label">Valor Recebido</label>
                        <input type="number" step="0.01" name="valor_recebido" id="inpRecebido" class="form-control" oninput="calcTroco()">
                    </div>
                </div>

                <div id="divTroco" style="display:flex; justify-content:space-between; margin-bottom:15px; color:var(--success); font-weight:700;">
                    <span>Troco:</span>
                    <span id="displayTroco">R$ 0,00</span>
                </div>

                <div class="form-row">
                    <input type="text" name="observacoes" class="form-control" placeholder="Observações (opcional)">
                </div>

                <button type="submit" class="btn-checkout">
                    <i class="bi bi-check2-circle"></i> Finalizar Venda
                </button>
            </form>
        </div>
    </div>
</main>

<script>
    let cart = [];

    // --- LÓGICA DO CARRINHO ---
    function addItem(p) {
        const idUniq = p.id + '-' + p.var_id;
        const exists = cart.find(i => i.uid === idUniq);

        if(exists) {
            if(exists.quantidade < p.estoque) {
                exists.quantidade++;
            } else {
                alert('Estoque máximo atingido!');
            }
        } else {
            cart.push({
                uid: idUniq,
                produto_id: p.id,
                variacao_id: p.var_id,
                nome_produto: p.nome,
                cor: p.cor,
                tamanho: p.tamanho,
                preco_unit: 0, // Definir preço aqui se vier do banco
                quantidade: 1
            });
        }
        renderCart();
    }

    function removeItem(idx) {
        cart.splice(idx, 1);
        renderCart();
    }

    function updateQtd(idx, val) {
        let v = parseInt(val);
        if(v < 1) v = 1;
        cart[idx].quantidade = v;
        renderCart();
    }

    function updatePrice(idx, val) {
        let v = parseFloat(val);
        if(isNaN(v)) v = 0;
        cart[idx].preco_unit = v;
        renderCart();
    }

    function renderCart() {
        const ul = document.getElementById('cartList');
        const count = document.getElementById('cartCount');
        let total = 0;
        
        ul.innerHTML = '';
        
        if(cart.length === 0) {
            ul.innerHTML = '<li style="padding:30px; text-align:center; color:#9ca3af;"><i class="bi bi-cart3" style="font-size:2rem; display:block; margin-bottom:10px;"></i>Carrinho vazio.</li>';
            count.innerText = '0 itens';
        } else {
            cart.forEach((item, idx) => {
                total += (item.quantidade * item.preco_unit);
                
                const li = document.createElement('li');
                li.className = 'cart-item';
                li.innerHTML = `
                    <div class="item-info">
                        <span class="item-name">${item.nome_produto}</span>
                        <span class="item-var">${item.cor} ${item.tamanho ? '- '+item.tamanho : ''}</span>
                    </div>
                    <div class="item-actions">
                        <input type="number" class="qty-input" value="${item.quantidade}" onchange="updateQtd(${idx}, this.value)">
                        <input type="number" step="0.01" class="price-input" value="${item.preco_unit}" placeholder="R$" onchange="updatePrice(${idx}, this.value)">
                        <button class="btn-del" onclick="removeItem(${idx})"><i class="bi bi-trash"></i></button>
                    </div>
                `;
                ul.appendChild(li);
            });
            count.innerText = cart.length + ' itens';
        }

        document.getElementById('displayTotal').innerText = total.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
        document.getElementById('inputCarrinho').value = JSON.stringify(cart);
        calcTroco();
    }

    // --- LÓGICA DE PAGAMENTO ---
    function checkPagto() {
        const tipo = document.getElementById('selPagto').value;
        const divRec = document.getElementById('divRecebido');
        const divTro = document.getElementById('divTroco');
        
        if(tipo === 'dinheiro') {
            divRec.style.visibility = 'visible';
            divTro.style.visibility = 'visible';
        } else {
            divRec.style.visibility = 'hidden';
            divTro.style.visibility = 'hidden';
            document.getElementById('inpRecebido').value = '';
        }
    }

    function calcTroco() {
        const total = cart.reduce((acc, i) => acc + (i.quantidade * i.preco_unit), 0);
        const rec = parseFloat(document.getElementById('inpRecebido').value || 0);
        const troco = rec - total;
        
        const el = document.getElementById('displayTroco');
        if(troco > 0) {
            el.innerText = troco.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
            el.style.color = 'var(--success)';
        } else {
            el.innerText = 'R$ 0,00';
            el.style.color = 'var(--text-muted)';
        }
    }

    function toggleManual(sel) {
        const div = document.getElementById('manualInputs');
        // Se quiser lógica para mostrar input manual, pode ser aqui.
        // Por padrão deixei fixo abaixo para simplificar, mas pode mostrar/esconder.
        div.style.display = 'grid'; 
    }

    function filtrarProdutos() {
        const term = document.getElementById('searchBox').value.toLowerCase();
        document.querySelectorAll('.product-card').forEach(card => {
            const txt = card.getAttribute('data-search');
            card.style.display = txt.includes(term) ? 'flex' : 'none';
        });
    }

    function validarVenda() {
        if(cart.length === 0) { alert('Carrinho vazio!'); return false; }
        const semPreco = cart.some(i => i.preco_unit <= 0);
        if(semPreco) { alert('Defina o preço de todos os itens.'); return false; }
        
        // Validar dinheiro
        const tipo = document.getElementById('selPagto').value;
        if(tipo === 'dinheiro') {
            const total = cart.reduce((acc, i) => acc + (i.quantidade * i.preco_unit), 0);
            const rec = parseFloat(document.getElementById('inpRecebido').value || 0);
            if(rec < total) {
                alert('Valor recebido menor que o total!');
                return false;
            }
        }
        return true;
    }

    checkPagto();
</script>

</body>
</html>