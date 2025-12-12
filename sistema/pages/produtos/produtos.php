<?php
// ==========================================
// 1. LÓGICA DE BACKEND (API + BANCO)
// ==========================================
// Inicia buffer para garantir que nenhum HTML "vaze" antes do JSON da API
ob_start();


// Evita avisos de variável indefinida
$msgSucesso = '';
$msgErro = '';

require_once '../../includes/banco-dados/db.php';

// Diretório de uploads
$uploadDir = '../../assets/uploads/produtos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// --- API JSON (Processamento Silencioso) ---
if (isset($_GET['api_acao'])) {
    // Limpa qualquer saída anterior (como espaços ou HTML de includes acidentais)
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        // A. BUSCAR DADOS
        if ($_GET['api_acao'] === 'get_produto') {
            $id = (int)$_GET['id'];
            $prod = $pdo->query("SELECT * FROM produtos WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            
            if (!$prod) throw new Exception("Produto não encontrado.");

            $vars = $pdo->query("SELECT * FROM produtos_variacoes WHERE produto_id = $id")->fetchAll(PDO::FETCH_ASSOC);
            $imgs = $pdo->query("SELECT * FROM produtos_galeria WHERE produto_id = $id")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'produto' => $prod, 'variacoes' => $vars, 'galeria' => $imgs]);
            exit;
        }

        // B. EXCLUIR IMAGEM
        if ($_GET['api_acao'] === 'del_imagem') {
            $id = (int)$_GET['id'];
            $img = $pdo->query("SELECT caminho_imagem FROM produtos_galeria WHERE id = $id")->fetchColumn();
            if ($img && file_exists($uploadDir.$img)) @unlink($uploadDir.$img);
            $pdo->query("DELETE FROM produtos_galeria WHERE id = $id");
            echo json_encode(['success' => true]);
            exit;
        }

        // C. EXCLUIR PRODUTO
        if ($_GET['api_acao'] === 'del_produto') {
            $id = (int)$_GET['id'];
            $capa = $pdo->query("SELECT imagem_capa FROM produtos WHERE id = $id")->fetchColumn();
            if($capa && file_exists($uploadDir.$capa)) @unlink($uploadDir.$capa);
            
            $galeria = $pdo->query("SELECT caminho_imagem FROM produtos_galeria WHERE produto_id = $id")->fetchAll(PDO::FETCH_COLUMN);
            foreach($galeria as $f) { if(file_exists($uploadDir.$f)) @unlink($uploadDir.$f); }

            $pdo->query("DELETE FROM produtos WHERE id = $id");
            echo json_encode(['success' => true]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    exit;
}

// --- HTML E INTERFACE COMEÇAM AQUI ---
// Só incluímos o menu DEPOIS de garantir que não é uma chamada de API
include_once '../../includes/menu.php';

// AUTO-CORREÇÃO DE TABELAS (Prevenção de erros)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        marca TEXT, modelo TEXT, tipo_produto TEXT, codigo_barras TEXT,
        estoque_minimo INTEGER DEFAULT 5,
        preco_custo REAL DEFAULT 0, preco_venda REAL DEFAULT 0,
        tem_validade INTEGER DEFAULT 0, data_validade DATE,
        ativo INTEGER DEFAULT 1, auto_desativar INTEGER DEFAULT 0,
        descricao TEXT, imagem_capa TEXT, data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Updates de segurança para colunas novas
    @$pdo->exec("ALTER TABLE produtos ADD COLUMN preco_custo REAL DEFAULT 0");
    @$pdo->exec("ALTER TABLE produtos ADD COLUMN preco_venda REAL DEFAULT 0");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_variacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto_id INTEGER, cor TEXT, tamanho TEXT, peso REAL,
        estoque INTEGER DEFAULT 0, preco_venda REAL DEFAULT 0,
        FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    )");
    @$pdo->exec("ALTER TABLE produtos_variacoes ADD COLUMN preco_venda REAL DEFAULT 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_galeria (
        id INTEGER PRIMARY KEY AUTOINCREMENT, produto_id INTEGER, caminho_imagem TEXT,
        FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {}

// --- SALVAR (POST FORM) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    try {
        $pdo->beginTransaction();
        $id = !empty($_POST['id_produto']) ? (int)$_POST['id_produto'] : null;

        function limpaVal($v) { return (float)str_replace(['.',','], ['','.'], $v); } // Ex: 1.200,00 -> 1200.00

        // Campos
        $nome       = $_POST['nome'];
        $marca      = $_POST['marca'] ?? '';
        $modelo     = $_POST['modelo'] ?? '';
        $tipo       = $_POST['tipo'] ?? 'fisico';
        $cod        = $_POST['cod_barras'] ?? '';
        $est_min    = (int)($_POST['est_min'] ?? 0);
        $custo      = limpaVal($_POST['preco_custo'] ?? '0');
        $venda      = limpaVal($_POST['preco_venda'] ?? '0');
        $validade   = isset($_POST['tem_validade']) ? 1 : 0;
        $dt_val     = $_POST['data_validade'] ?: null;
        $ativo      = isset($_POST['ativo']) ? 1 : 0;
        $auto_des   = isset($_POST['auto_desativar']) ? 1 : 0;
        $desc       = $_POST['descricao'] ?? '';

        // Query Base
        $sqlCapa = "";
        $params = [$nome, $marca, $modelo, $tipo, $cod, $est_min, $custo, $venda, $validade, $dt_val, $ativo, $auto_des, $desc];

        // Upload Capa
        if (!empty($_FILES['imagem_capa']['name'])) {
            $ext = pathinfo($_FILES['imagem_capa']['name'], PATHINFO_EXTENSION);
            $novoNome = uniqid('capa_') . '.' . $ext;
            if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $uploadDir . $novoNome)) {
                if ($id) {
                    $sqlCapa = ", imagem_capa=?";
                    $params[] = $novoNome;
                    // Apagar antiga
                    $old = $pdo->query("SELECT imagem_capa FROM produtos WHERE id=$id")->fetchColumn();
                    if($old && file_exists($uploadDir.$old)) @unlink($uploadDir.$old);
                } else {
                    $params[] = $novoNome;
                }
            }
        } else {
            if (!$id) $params[] = null; // Insert sem capa
        }

        if ($id) {
            $params[] = $id;
            $pdo->prepare("UPDATE produtos SET nome=?, marca=?, modelo=?, tipo_produto=?, codigo_barras=?, estoque_minimo=?, preco_custo=?, preco_venda=?, tem_validade=?, data_validade=?, ativo=?, auto_desativar=?, descricao=? $sqlCapa WHERE id=?")->execute($params);
            $produtoId = $id;
            $pdo->prepare("DELETE FROM produtos_variacoes WHERE produto_id=?")->execute([$id]);
        } else {
            // Ajusta query para insert dependendo se teve capa ou não
            if(count($params) > 13) 
                $sql = "INSERT INTO produtos (nome,marca,modelo,tipo_produto,codigo_barras,estoque_minimo,preco_custo,preco_venda,tem_validade,data_validade,ativo,auto_desativar,descricao,imagem_capa) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            else 
                $sql = "INSERT INTO produtos (nome,marca,modelo,tipo_produto,codigo_barras,estoque_minimo,preco_custo,preco_venda,tem_validade,data_validade,ativo,auto_desativar,descricao) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            
            $pdo->prepare($sql)->execute($params);
            $produtoId = $pdo->lastInsertId();
        }

        // Variações
        if (isset($_POST['var_cor'])) {
            $stmtV = $pdo->prepare("INSERT INTO produtos_variacoes (produto_id, cor, tamanho, peso, estoque, preco_venda) VALUES (?,?,?,?,?,?)");
            foreach ($_POST['var_cor'] as $k => $cor) {
                $vp = !empty($_POST['var_preco'][$k]) ? limpaVal($_POST['var_preco'][$k]) : $venda;
                $stmtV->execute([$produtoId, $cor, $_POST['var_tam'][$k], $_POST['var_peso'][$k], (int)$_POST['var_qtd'][$k], $vp]);
            }
        }

        // Galeria
        if (!empty($_FILES['galeria']['name'][0])) {
            $stmtG = $pdo->prepare("INSERT INTO produtos_galeria (produto_id, caminho_imagem) VALUES (?,?)");
            $total = count($_FILES['galeria']['name']);
            for ($i=0; $i<$total; $i++) {
                $ext = pathinfo($_FILES['galeria']['name'][$i], PATHINFO_EXTENSION);
                $nomeG = uniqid('gal_').'.'.$ext;
                if(move_uploaded_file($_FILES['galeria']['tmp_name'][$i], $uploadDir.$nomeG)){
                    $stmtG->execute([$produtoId, $nomeG]);
                }
            }
        }

        $pdo->commit();
        $msgSucesso = "Produto salvo com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msgErro = $e->getMessage();
    }
}

// --- DADOS DASHBOARD ---
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$totalItens = 0; $valorTotal = 0; $maiorEstoque = ['nome'=>'-','qtd'=>0];

foreach($produtos as $k => $p) {
    $soma = $pdo->query("SELECT SUM(estoque) as q, SUM(estoque * preco_venda) as v FROM produtos_variacoes WHERE produto_id = {$p['id']}")->fetch();
    $q = (int)$soma['q']; 
    $v = (float)($soma['v'] ?? ($q * $p['preco_venda']));
    
    $produtos[$k]['estoque_total'] = $q;
    $totalItens += $q;
    $valorTotal += $v;
    if($q > $maiorEstoque['qtd']) $maiorEstoque = ['nome'=>$p['nome'], 'qtd'=>$q];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Produtos - Lumina ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* ESTILO LUMINA ERP (Fundo claro, sidebar escura que vem do menu.php) */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f3f4f6; /* Cinza claro de fundo */
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --radius: 12px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            padding-bottom: 40px;
        }

        main {
            max-width: 1400px;
            margin: 32px auto;
            padding: 0 24px;
        }

        /* HEADER */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        .page-header h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: #0f172a; }
        .page-header p { margin: 4px 0 0; color: var(--text-muted); font-size: 0.95rem; }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(99,102,241,0.25);
            transition: 0.2s;
        }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }

        /* KPI CARDS (Estilo Dashboard) */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            background: var(--card-bg);
            padding: 24px;
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .kpi-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 8px;
        }
        .kpi-title { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; color: #0f172a; letter-spacing: -0.5px; }

        /* TABELA DE PRODUTOS */
        .content-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            overflow: hidden;
        }

        .filter-bar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: #fcfcfc;
        }
        .search-input {
            width: 100%;
            max-width: 350px;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            outline: none;
            font-size: 0.9rem;
            color: var(--text-main);
            background: white;
            transition: 0.2s;
        }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 20px; background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; border-bottom: 1px solid var(--border); }
        td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #334155; font-size: 0.9rem; }
        tr:hover { background: #fdfdfd; }
        
        .thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .st-ativo { background: #dcfce7; color: #166534; }
        .st-inativo { background: #fee2e2; color: #991b1b; }

        .btn-action {
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 6px; border: 1px solid transparent;
            color: #64748b; cursor: pointer; transition: 0.2s; background: transparent;
        }
        .btn-action:hover { background: #f1f5f9; color: var(--primary); }
        .btn-action.del:hover { background: #fef2f2; color: #ef4444; }

        /* MODAL */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 2000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; animation: fadeUp 0.2s ease-out; }
        
        .modal-box { background: white; width: 95%; max-width: 750px; border-radius: 16px; display: flex; flex-direction: column; max-height: 90vh; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 1.2rem; color: #0f172a; }
        
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; background: #f8fafc; border-radius: 0 0 16px 16px; }

        /* WIZARD NO MODAL */
        .steps { display: flex; gap: 20px; padding: 12px 24px; border-bottom: 1px solid var(--border); background: #fbfbfc; }
        .step-item { font-size: 0.85rem; font-weight: 600; color: #94a3b8; cursor: pointer; padding-bottom: 8px; border-bottom: 2px solid transparent; transition: 0.2s; }
        .step-item.active { color: var(--primary); border-bottom-color: var(--primary); }
        
        .step-content { display: none; }
        .step-content.active { display: block; animation: fadeUp 0.3s; }

        /* FORMS */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .full { grid-column: span 2; }
        label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .input-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 0.95rem; box-sizing: border-box; }
        .input-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px #e0e7ff; }

        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<main>
    <div class="page-header">
        <div>
            <h1>Produtos</h1>
            <p>Gerencie inventário, preços e variações.</p>
        </div>
        <button class="btn-primary" onclick="openModalNovo()">
            <i class="bi bi-plus-lg"></i> Novo Produto
        </button>
    </div>

    <?php if($msgSucesso): ?>
        <div style="padding:15px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:20px; border:1px solid #bbf7d0;">
            <i class="bi bi-check-circle-fill"></i> <?php echo $msgSucesso; ?>
        </div>
    <?php endif; ?>
    <?php if($msgErro): ?>
        <div style="padding:15px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca;">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $msgErro; ?>
        </div>
    <?php endif; ?>

    <div class="kpi-container">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#e0e7ff; color:var(--primary)"><i class="bi bi-box-seam"></i></div>
            <div class="kpi-title">Total de Itens</div>
            <div class="kpi-value"><?php echo $totalItens; ?> <small style="font-size:1rem; font-weight:500">un</small></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dcfce7; color:#166534"><i class="bi bi-currency-dollar"></i></div>
            <div class="kpi-title">Valor em Estoque</div>
            <div class="kpi-value">R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fff7ed; color:#c2410c"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="kpi-title">Maior Estoque</div>
            <div class="kpi-value" style="font-size:1.4rem"><?php echo $maiorEstoque['nome']; ?></div>
        </div>
    </div>

    <div class="content-card">
        <div class="filter-bar">
            <input type="text" id="busca" class="search-input" placeholder="Pesquisar produto..." onkeyup="filtrar()">
        </div>
        <div style="overflow-x:auto;">
            <table id="tblProd">
                <thead>
                    <tr>
                        <th width="70">Capa</th>
                        <th>Produto</th>
                        <th>Preço Venda</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th style="text-align:right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($produtos as $p): 
                        $img = !empty($p['imagem_capa']) ? $uploadDir.$p['imagem_capa'] : 'https://via.placeholder.com/60?text=IMG';
                        $status = $p['ativo'] ? ['Ativo','st-ativo'] : ['Inativo','st-inativo'];
                    ?>
                    <tr>
                        <td><img src="<?php echo $img; ?>" class="thumb"></td>
                        <td>
                            <div style="font-weight:600"><?php echo htmlspecialchars($p['nome']); ?></div>
                            <div style="font-size:0.8rem; color:#94a3b8;"><?php echo htmlspecialchars($p['marca'].' '.$p['modelo']); ?></div>
                        </td>
                        <td>
                            R$ <?php echo number_format($p['preco_venda'], 2, ',', '.'); ?>
                            <?php if($p['preco_custo'] > 0): ?>
                                <div style="font-size:0.75rem; color:#94a3b8;">Custo: <?php echo number_format($p['preco_custo'], 2, ',', '.'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo $p['estoque_total']; ?></strong> un</td>
                        <td><span class="status-badge <?php echo $status[1]; ?>"><?php echo $status[0]; ?></span></td>
                        <td style="text-align:right">
                            <button class="btn-action" onclick="editar(<?php echo $p['id']; ?>)"><i class="bi bi-pencil"></i></button>
                            <button class="btn-action del" onclick="confirmarDel(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalProd">
    <form class="modal-box" method="POST" enctype="multipart/form-data" id="formP">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id_produto" id="inpId">

        <div class="modal-header">
            <h3 id="mTitle">Novo Produto</h3>
            <button type="button" class="btn-action" onclick="fecharModal()" style="font-size:1.5rem;">&times;</button>
        </div>

        <div class="steps">
            <div class="step-item active" onclick="goStep(1)" id="st1">1. Dados Básicos</div>
            <div class="step-item" onclick="goStep(2)" id="st2">2. Preço & Estoque</div>
            <div class="step-item" onclick="goStep(3)" id="st3">3. Imagens</div>
        </div>

        <div class="modal-body">
            <div class="step-content active" id="conteudo1">
                <div class="form-grid">
                    <div class="full">
                        <label>Nome do Produto *</label>
                        <input type="text" name="nome" id="inpNome" class="input-control" required placeholder="Ex: Camiseta Básica">
                    </div>
                    <div><label>Marca</label><input type="text" name="marca" id="inpMarca" class="input-control"></div>
                    <div><label>Modelo</label><input type="text" name="modelo" id="inpModelo" class="input-control"></div>
                    <div><label>Cód. Barras</label><input type="text" name="cod_barras" id="inpCod" class="input-control"></div>
                    <div>
                        <label>Tipo</label>
                        <select name="tipo" id="inpTipo" class="input-control">
                            <option value="fisico">Produto Físico</option>
                            <option value="servico">Serviço</option>
                        </select>
                    </div>
                    <div class="full">
                        <label>Descrição</label>
                        <textarea name="descricao" id="inpDesc" class="input-control" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <div class="step-content" id="conteudo2">
                <div class="form-grid" style="background:#f8fafc; padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid var(--border);">
                    <div><label>Preço Custo (R$)</label><input type="text" name="preco_custo" id="inpCusto" class="input-control" placeholder="0,00"></div>
                    <div><label>Preço Venda (R$)</label><input type="text" name="preco_venda" id="inpVenda" class="input-control" placeholder="0,00"></div>
                    <div><label>Estoque Mínimo</label><input type="number" name="est_min" id="inpMin" class="input-control" value="5"></div>
                </div>
                
                <h4 style="margin:0 0 10px 0; font-size:1rem;">Variações (Cor, Tamanho)</h4>
                <div id="boxVars"></div>
                <button type="button" onclick="addVar()" style="width:100%; padding:10px; border:1px dashed var(--primary); background:white; color:var(--primary); border-radius:8px; cursor:pointer; font-weight:600; margin-top:10px;">
                    <i class="bi bi-plus"></i> Adicionar Variação
                </button>
            </div>

            <div class="step-content" id="conteudo3">
                <label>Foto de Capa</label>
                <input type="file" name="imagem_capa" class="input-control" accept="image/*" style="margin-bottom:15px;">
                <div id="previewCapa" style="margin-bottom:20px;"></div>

                <label>Galeria (Várias fotos)</label>
                <input type="file" name="galeria[]" class="input-control" multiple accept="image/*">
                <div id="boxGaleria" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:15px;"></div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-primary" style="background:white; color:#64748b; border:1px solid #cbd5e1;" onclick="nav(-1)" id="btnVoltar">Voltar</button>
            <div>
                <button type="button" class="btn-primary" id="btnAvancar" onclick="nav(1)">Próximo</button>
                <button type="submit" class="btn-primary" id="btnSalvar" style="display:none; background:#10b981;">Salvar Produto</button>
            </div>
        </div>
    </form>
</div>

<div class="modal-overlay" id="modalDel">
    <div class="modal-box" style="max-width:400px; height:auto;">
        <div class="modal-header"><h3>Excluir Produto</h3></div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir <b id="delName"></b>?</p>
            <small style="color:red">Esta ação é irreversível.</small>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" style="background:white; color:#333; border:1px solid #ddd;" onclick="document.getElementById('modalDel').classList.remove('open')">Cancelar</button>
            <button type="button" class="btn-primary" style="background:#ef4444;" onclick="execDelete()">Excluir</button>
        </div>
    </div>
</div>

<script>
    let step = 1;
    let delId = null;
    const modal = document.getElementById('modalProd');
    const uploadPath = '<?php echo $uploadDir; ?>';

    function openModalNovo() {
        document.getElementById('formP').reset();
        document.getElementById('inpId').value = '';
        document.getElementById('boxVars').innerHTML = '';
        document.getElementById('boxGaleria').innerHTML = '';
        document.getElementById('previewCapa').innerHTML = '';
        document.getElementById('mTitle').innerText = 'Novo Produto';
        addVar(); // Adiciona linha vazia
        goStep(1);
        modal.classList.add('open');
    }

    function fecharModal() { modal.classList.remove('open'); }

    // WIZARD
    function nav(dir) { goStep(step + dir); }
    function goStep(n) {
        if(n<1 || n>3) return;
        step = n;
        
        // Atualiza abas
        document.querySelectorAll('.step-item').forEach((el, i) => {
            el.classList.toggle('active', (i+1)===step);
        });
        // Atualiza conteúdo
        document.querySelectorAll('.step-content').forEach((el, i) => {
            el.classList.toggle('active', (i+1)===step);
        });

        // Botões
        document.getElementById('btnVoltar').style.visibility = step === 1 ? 'hidden' : 'visible';
        if(step === 3) {
            document.getElementById('btnAvancar').style.display = 'none';
            document.getElementById('btnSalvar').style.display = 'flex';
        } else {
            document.getElementById('btnAvancar').style.display = 'flex';
            document.getElementById('btnSalvar').style.display = 'none';
        }
    }

    // VARIAÇÕES
    function addVar(cor='', tam='', peso='', qtd='', pr='') {
        const div = document.createElement('div');
        div.style.cssText = "display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 40px; gap:8px; align-items:center; margin-bottom:8px;";
        div.innerHTML = `
            <input name="var_cor[]" value="${cor}" placeholder="Cor" class="input-control">
            <input name="var_tam[]" value="${tam}" placeholder="Tam" class="input-control">
            <input name="var_peso[]" value="${peso}" placeholder="Kg" class="input-control">
            <input name="var_qtd[]" value="${qtd}" placeholder="Qtd" type="number" class="input-control">
            <input name="var_preco[]" value="${pr}" placeholder="R$" class="input-control">
            <button type="button" onclick="this.parentElement.remove()" style="color:red; border:none; background:none; cursor:pointer; font-size:1.2rem;">&times;</button>
        `;
        document.getElementById('boxVars').appendChild(div);
    }

    // EDITAR
    async function editar(id) {
        // Limpa form antes de abrir
        document.getElementById('formP').reset();
        document.getElementById('boxVars').innerHTML = '';
        document.getElementById('previewCapa').innerHTML = '';
        document.getElementById('boxGaleria').innerHTML = '';
        
        try {
            const req = await fetch('?api_acao=get_produto&id='+id);
            const res = await req.json();
            
            if(!res.success) {
                alert('Erro: ' + (res.error || 'Falha ao buscar dados'));
                return;
            }
            
            const p = res.produto;
            document.getElementById('inpId').value = p.id;
            document.getElementById('mTitle').innerText = 'Editar: ' + p.nome;
            document.getElementById('inpNome').value = p.nome;
            document.getElementById('inpMarca').value = p.marca;
            document.getElementById('inpModelo').value = p.modelo;
            document.getElementById('inpCod').value = p.codigo_barras;
            document.getElementById('inpDesc').value = p.descricao;
            document.getElementById('inpCusto').value = p.preco_custo;
            document.getElementById('inpVenda').value = p.preco_venda;
            document.getElementById('inpMin').value = p.estoque_minimo;

            // Capa
            if(p.imagem_capa) {
                document.getElementById('previewCapa').innerHTML = `<img src="${uploadPath}${p.imagem_capa}" style="height:100px; border-radius:8px; border:1px solid #ddd;">`;
            }

            // Variações
            if(res.variacoes.length) {
                res.variacoes.forEach(v => addVar(v.cor, v.tamanho, v.peso, v.estoque, v.preco_venda));
            } else {
                addVar();
            }

            // Galeria
            const boxG = document.getElementById('boxGaleria');
            res.galeria.forEach(g => {
                const d = document.createElement('div');
                d.style.position = 'relative';
                d.innerHTML = `
                    <img src="${uploadPath}${g.caminho_imagem}" style="width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid #ddd;">
                    <button type="button" onclick="delImg(${g.id}, this)" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:10px;">X</button>
                `;
                boxG.appendChild(d);
            });
            
            goStep(1);
            modal.classList.add('open');

        } catch(e) {
            console.error(e);
            alert('Erro de conexão com o servidor.');
        }
    }

    async function delImg(id, el) {
        if(!confirm('Apagar foto?')) return;
        await fetch('?api_acao=del_imagem&id='+id);
        el.parentElement.remove();
    }

    function confirmarDel(id, nome) {
        delId = id;
        document.getElementById('delName').innerText = nome;
        document.getElementById('modalDel').classList.add('open');
    }

    async function execDelete() {
        if(!delId) return;
        const req = await fetch('?api_acao=del_produto&id='+delId);
        const res = await req.json();
        if(res.success) location.reload();
        else alert('Erro ao excluir');
    }

    function filtrar() {
        const term = document.getElementById('busca').value.toLowerCase();
        document.querySelectorAll('#tblProd tbody tr').forEach(r => {
            r.style.display = r.innerText.toLowerCase().includes(term) ? '' : 'none';
        });
    }
</script>

</body>
</html>