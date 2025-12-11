<?php
// ==========================================
// 1. CONFIG E LÓGICA PHP
// ==========================================
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';

$uploadDir = '../../assets/uploads/produtos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// --- AUTO-CORREÇÃO DO BANCO (Garante que as colunas existem) ---
try {

    // Tenta criar tabela produtos se não existir
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        marca TEXT,
        modelo TEXT,
        tipo_produto TEXT,
        codigo_barras TEXT,
        estoque_minimo INTEGER DEFAULT 5,
        preco_custo REAL DEFAULT 0,
        preco_venda REAL DEFAULT 0,
        tem_validade INTEGER DEFAULT 0,
        data_validade DATE,
        ativo INTEGER DEFAULT 1,
        auto_desativar INTEGER DEFAULT 0,
        descricao TEXT,
        imagem_capa TEXT,
        data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Tenta adicionar colunas de preço caso a tabela seja antiga
    // O '@' suprime o erro se a coluna já existir
    @$pdo->exec("ALTER TABLE produtos ADD COLUMN preco_custo REAL DEFAULT 0");
    @$pdo->exec("ALTER TABLE produtos ADD COLUMN preco_venda REAL DEFAULT 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_variacoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto_id INTEGER,
        cor TEXT,
        tamanho TEXT,
        peso REAL,
        estoque INTEGER DEFAULT 0,
        preco_venda REAL DEFAULT 0,
        FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    )");
    @$pdo->exec("ALTER TABLE produtos_variacoes ADD COLUMN preco_venda REAL DEFAULT 0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos_galeria (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        produto_id INTEGER,
        caminho_imagem TEXT,
        FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
    )");

    // --- AUTO-CORREÇÃO PARA owner_id EM servicos ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS servicos (
        id INTEGER PRIMARY KEY AUTOINCREMENT
        -- outras colunas serão adicionadas conforme necessário
    )");
    // Adiciona a coluna owner_id se não existir
    @$pdo->exec("ALTER TABLE servicos ADD COLUMN owner_id INTEGER");

} catch (Exception $e) {
    // Ignora erros de coluna existente
}

// --- API INTERNA (JSON PARA O JAVASCRIPT) ---
if (isset($_GET['api_acao'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Buscar dados para editar
    if ($_GET['api_acao'] === 'get_produto') {
        $id = (int)$_GET['id'];
        $prod = $pdo->query("SELECT * FROM produtos WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $vars = $pdo->query("SELECT * FROM produtos_variacoes WHERE produto_id = $id")->fetchAll(PDO::FETCH_ASSOC);
        $imgs = $pdo->query("SELECT * FROM produtos_galeria WHERE produto_id = $id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['produto' => $prod, 'variacoes' => $vars, 'galeria' => $imgs]);
        exit;
    }

    // Excluir imagem da galeria
    if ($_GET['api_acao'] === 'del_imagem') {
        $id = (int)$_GET['id'];
        $img = $pdo->query("SELECT caminho_imagem FROM produtos_galeria WHERE id = $id")->fetchColumn();
        if ($img && file_exists($uploadDir.$img)) unlink($uploadDir.$img);
        $pdo->query("DELETE FROM produtos_galeria WHERE id = $id");
        echo json_encode(['success' => true]);
        exit;
    }

    // Excluir Produto Inteiro
    if ($_GET['api_acao'] === 'del_produto') {
        $id = (int)$_GET['id'];
        
        // Apaga capa
        $capa = $pdo->query("SELECT imagem_capa FROM produtos WHERE id = $id")->fetchColumn();
        if($capa && file_exists($uploadDir.$capa)) unlink($uploadDir.$capa);

        // Apaga galeria
        $galeria = $pdo->query("SELECT caminho_imagem FROM produtos_galeria WHERE produto_id = $id")->fetchAll(PDO::FETCH_COLUMN);
        foreach($galeria as $foto) {
            if(file_exists($uploadDir.$foto)) unlink($uploadDir.$foto);
        }

        // Remove do banco (Cascade remove variações e galeria, mas garantimos o delete do produto)
        $pdo->query("DELETE FROM produtos WHERE id = $id");
        echo json_encode(['success' => true]);
        exit;
    }
    exit;
}

// --- PROCESSAR SALVAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    try {
        $pdo->beginTransaction();
        $id = !empty($_POST['id_produto']) ? (int)$_POST['id_produto'] : null;

        // Limpa valor monetário
        function limpaMoeda($val) {
            if(empty($val)) return 0;
            return (float)str_replace(',', '.', str_replace('.', '', $val));
        }

        // Dados do form
        $nome       = $_POST['nome'] ?? 'Sem Nome';
        $marca      = $_POST['marca'] ?? '';
        $modelo     = $_POST['modelo'] ?? '';
        $tipo       = $_POST['tipo'] ?? 'fisico';
        $cod        = $_POST['cod_barras'] ?? '';
        $est_min    = (int)($_POST['est_min'] ?? 0);
        $preco_custo= limpaMoeda($_POST['preco_custo']);
        $preco_venda= limpaMoeda($_POST['preco_venda']);
        $validade   = isset($_POST['tem_validade']) ? 1 : 0;
        $dt_val     = $_POST['data_validade'] ?: null;
        $ativo      = isset($_POST['ativo']) ? 1 : 0;
        $auto_des   = isset($_POST['auto_desativar']) ? 1 : 0;
        $desc       = $_POST['descricao'] ?? '';

        // Capa
        $capaSql = ""; 
        $params = [$nome, $marca, $modelo, $tipo, $cod, $est_min, $preco_custo, $preco_venda, $validade, $dt_val, $ativo, $auto_des, $desc];

        if (!empty($_FILES['imagem_capa']['name'])) {
            $ext = pathinfo($_FILES['imagem_capa']['name'], PATHINFO_EXTENSION);
            $novoNome = uniqid('capa_') . '.' . $ext;
            if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $uploadDir . $novoNome)) {
                if ($id) { $capaSql = ", imagem_capa = ?"; $params[] = $novoNome; }
                else { $params[] = $novoNome; }
            }
        } else {
            if (!$id) $params[] = null;
        }

        if ($id) {
            $sql = "UPDATE produtos SET nome=?, marca=?, modelo=?, tipo_produto=?, codigo_barras=?, estoque_minimo=?, preco_custo=?, preco_venda=?, tem_validade=?, data_validade=?, ativo=?, auto_desativar=?, descricao=? $capaSql WHERE id=?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            $produtoId = $id;
            // Limpa variações para recriar
            $pdo->prepare("DELETE FROM produtos_variacoes WHERE produto_id = ?")->execute([$id]);
        } else {
            $sql = "INSERT INTO produtos (nome, marca, modelo, tipo_produto, codigo_barras, estoque_minimo, preco_custo, preco_venda, tem_validade, data_validade, ativo, auto_desativar, descricao, imagem_capa) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute($params);
            $produtoId = $pdo->lastInsertId();
        }

        // Salvar Variações
        if (isset($_POST['var_cor'])) {
            $stmtV = $pdo->prepare("INSERT INTO produtos_variacoes (produto_id, cor, tamanho, peso, estoque, preco_venda) VALUES (?,?,?,?,?,?)");
            foreach ($_POST['var_cor'] as $k => $cor) {
                // Se não preencheu preço na variação, usa o geral
                $vPreco = !empty($_POST['var_preco'][$k]) ? limpaMoeda($_POST['var_preco'][$k]) : $preco_venda;
                $stmtV->execute([
                    $produtoId, 
                    $cor, 
                    $_POST['var_tam'][$k], 
                    $_POST['var_peso'][$k], 
                    (int)$_POST['var_qtd'][$k],
                    $vPreco
                ]);
            }
        }

        // Salvar Galeria
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

// --- DADOS DO DASHBOARD ---
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$totalItens = 0;
$valorTotalEstoque = 0;
$prodMaisEstoque = ['nome' => '-', 'qtd' => 0];

foreach($produtos as $key => $p) {
    // Busca soma das variações
    $soma = $pdo->query("SELECT SUM(estoque) as qtd, SUM(estoque * preco_venda) as val FROM produtos_variacoes WHERE produto_id = {$p['id']}")->fetch(PDO::FETCH_ASSOC);
    
    $qtd = (int)($soma['qtd'] ?? 0);
    // Se não tiver variação, assume estoque 0 ou implementa lógica para estoque pai se quiseres
    // Aqui assumimos que o estoque está sempre nas variações (como pedido antes)
    
    // Se quiseres permitir produto sem variação com estoque:
    // if($qtd == 0 && empty($soma['qtd'])) { $qtd = ...; } 

    $val = (float)($soma['val'] ?? ($qtd * $p['preco_venda'])); 

    $produtos[$key]['estoque_total'] = $qtd;
    
    $totalItens += $qtd;
    $valorTotalEstoque += $val;

    if($qtd > $prodMaisEstoque['qtd']) {
        $prodMaisEstoque = ['nome' => $p['nome'], 'qtd' => $qtd];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Produtos</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --primary: #6366f1;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); margin:0; padding-bottom:40px; }
        
        main { max-width: 1300px; margin: 30px auto; padding: 0 20px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h1 { font-size: 1.8rem; font-weight: 700; margin: 0; }
        
        .btn-primary { 
            background: var(--primary); color: white; padding: 12px 24px; 
            border-radius: 8px; border: none; font-weight: 600; cursor: pointer; 
            display: flex; gap: 8px; align-items: center; transition: 0.2s; 
        }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .kpi-label { font-size: 0.85rem; color: var(--muted); font-weight: 500; margin-bottom: 5px; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; color: var(--text); }
        .kpi-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 15px; }
        
        /* Tabela */
        .card-table { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -2px rgba(0,0,0,0.03); }
        .filters { padding: 20px; border-bottom: 1px solid var(--border); }
        .search-input { width: 100%; max-width: 300px; padding: 10px 15px; border: 1px solid var(--border); border-radius: 8px; outline: none; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 20px; background: #f9fafb; color: var(--muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 700; border-bottom: 1px solid var(--border); }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:hover { background: #fcfcfc; }
        .prod-img { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border); background: #eee; }
        
        /* Modais */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(2px); }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; width: 95%; max-width: 800px; border-radius: 16px; display: flex; flex-direction: column; max-height: 90vh; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        
        .modal-header { padding: 20px 30px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 30px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 20px 30px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; background: #fff; border-radius: 0 0 16px 16px; }

        .wizard-steps { display: flex; gap: 30px; padding: 15px 30px; background: #f9fafb; border-bottom: 1px solid var(--border); }
        .step-btn { background: none; border: none; font-weight: 600; color: var(--muted); cursor: pointer; padding-bottom: 5px; border-bottom: 2px solid transparent; transition: 0.2s; }
        .step-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .step-content { display: none; animation: fadeIn 0.3s; }
        .step-content.active { display: block; }

        /* Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full { grid-column: span 2; }
        label { display: block; margin-bottom: 6px; font-size: 0.9rem; font-weight: 500; color: var(--text); }
        .input-control { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; outline: none; box-sizing: border-box; }
        .input-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px #e0e7ff; }
        
        .btn-nav { padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border); background: white; cursor: pointer; font-weight: 600; color: var(--text); }
        .btn-nav:hover { background: #f8fafc; }
        .btn-save { background: var(--primary); color: white; border: none; }
        .btn-del { border: 1px solid #fee2e2; background: #fff; color: #ef4444; border-radius: 6px; padding: 5px 10px; cursor: pointer; }
        .btn-del:hover { background: #fef2f2; }

        .var-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 40px; gap: 10px; align-items: center; margin-bottom: 10px; }
        
        @keyframes fadeIn { from { opacity:0; transform: translateY(5px); } to { opacity:1; transform: translateY(0); } }
    </style>
</head>
<body>

<main>
    <div class="header">
        <div>
            <h1>Produtos</h1>
            <p style="color:var(--muted)">Gestão completa de estoque e preços</p>
        </div>
        <button class="btn-primary" onclick="openModalNovo()">+ Novo Produto</button>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#e0e7ff; color:#4f46e5"><i class="bi bi-box-seam"></i></div>
            <div class="kpi-label">Total de Itens</div>
            <div class="kpi-value"><?php echo $totalItens; ?> <small style="font-size:1rem">un</small></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dcfce7; color:#166534"><i class="bi bi-currency-dollar"></i></div>
            <div class="kpi-label">Valor em Estoque</div>
            <div class="kpi-value">R$ <?php echo number_format($valorTotalEstoque, 2, ',', '.'); ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef9c3; color:#854d0e"><i class="bi bi-star"></i></div>
            <div class="kpi-label">Maior Estoque</div>
            <div class="kpi-value" style="font-size:1.4rem"><?php echo $prodMaisEstoque['nome']; ?></div>
        </div>
    </div>

    <div class="card-table">
        <div class="filters">
            <input type="text" id="filtro" class="search-input" placeholder="Pesquisar produto..." onkeyup="filtrarTabela()">
        </div>
        <div style="overflow-x:auto">
            <table id="tabelaProdutos">
                <thead>
                    <tr>
                        <th width="70">Img</th>
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
                        $status = $p['ativo'] ? ['Ativo','success'] : ['Inativo','danger'];
                    ?>
                    <tr>
                        <td><img src="<?php echo $img; ?>" class="prod-img"></td>
                        <td>
                            <div style="font-weight:600"><?php echo htmlspecialchars($p['nome']); ?></div>
                            <div style="font-size:0.8rem; color:var(--muted)"><?php echo htmlspecialchars($p['marca']); ?></div>
                        </td>
                        <td>
                            R$ <?php echo number_format($p['preco_venda'], 2, ',', '.'); ?>
                            <?php if($p['preco_custo'] > 0): ?>
                                <div style="font-size:0.75rem; color:var(--muted)">Custo: <?php echo number_format($p['preco_custo'], 2, ',', '.'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:700"><?php echo $p['estoque_total']; ?> un</div>
                        </td>
                        <td><span style="padding:4px 8px; border-radius:20px; font-size:0.75rem; background:var(--bs-<?php echo $status[1]; ?>-bg-subtle); color:var(--bs-<?php echo $status[1]; ?>)"><?php echo $status[0]; ?></span></td>
                        <td style="text-align:right">
                            <button onclick="editar(<?php echo $p['id']; ?>)" style="border:1px solid #ddd; background:white; border-radius:6px; width:32px; height:32px; cursor:pointer;"><i class="bi bi-pencil"></i></button>
                            <button onclick="abrirModalDelete(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')" style="border:1px solid #fee2e2; background:white; border-radius:6px; width:32px; height:32px; cursor:pointer; color:#ef4444; margin-left:5px;"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalProduto">
    <form class="modal-box" method="POST" enctype="multipart/form-data" id="formProd">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id_produto" id="inpId">

        <div class="modal-header">
            <h3 id="modalTitle">Novo Produto</h3>
            <button type="button" onclick="fecharModal('modalProduto')" style="border:none; background:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>

        <div class="wizard-steps">
            <button type="button" class="step-btn active" onclick="goStep(1)">1. Dados Básicos</button>
            <button type="button" class="step-btn" onclick="goStep(2)">2. Preços e Estoque</button>
            <button type="button" class="step-btn" onclick="goStep(3)">3. Fotos</button>
        </div>

        <div class="modal-body">
            <div class="step-content active" id="step1">
                <div class="form-grid">
                    <div class="full">
                        <label>Nome do Produto (Obrigatório)</label>
                        <input type="text" name="nome" id="inpNome" class="input-control" placeholder="Ex: Camiseta Nike Branca" required>
                    </div>
                    <div>
                        <label>Marca</label>
                        <input type="text" name="marca" id="inpMarca" class="input-control">
                    </div>
                    <div>
                        <label>Modelo</label>
                        <input type="text" name="modelo" id="inpModelo" class="input-control">
                    </div>
                    <div>
                        <label>Código Barras</label>
                        <input type="text" name="cod_barras" id="inpCod" class="input-control">
                    </div>
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

            <div class="step-content" id="step2">
                <div class="form-grid" style="margin-bottom:20px; background:#f9fafb; padding:15px; border-radius:8px;">
                    <div>
                        <label>Preço de Custo (R$)</label>
                        <input type="text" name="preco_custo" id="inpCusto" class="input-control" placeholder="0,00">
                    </div>
                    <div>
                        <label>Preço de Venda (R$)</label>
                        <input type="text" name="preco_venda" id="inpVenda" class="input-control" placeholder="0,00">
                    </div>
                    <div>
                        <label>Estoque Mínimo (Alerta)</label>
                        <input type="number" name="est_min" id="inpMin" class="input-control" value="5">
                    </div>
                </div>

                <label>Variações (Cor, Tamanho, etc)</label>
                <div id="containerVariacoes"></div>
                <button type="button" onclick="addVarRow()" style="margin-top:10px; background:white; border:1px dashed var(--primary); color:var(--primary); width:100%; padding:10px; border-radius:8px; cursor:pointer;">+ Adicionar Variação</button>
            </div>

            <div class="step-content" id="step3">
                <label>Foto de Capa</label>
                <input type="file" name="imagem_capa" class="input-control" accept="image/*" style="margin-bottom:20px;">
                
                <label>Galeria (Mais fotos)</label>
                <input type="file" name="galeria[]" class="input-control" multiple accept="image/*">
                
                <div id="galeriaExistente" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:20px;"></div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-nav" id="btnVoltar" onclick="nav(-1)" style="visibility:hidden">Voltar</button>
            <div>
                <button type="button" class="btn-nav" id="btnAvancar" onclick="nav(1)">Próximo</button>
                <button type="submit" class="btn-nav btn-save" id="btnSalvar" style="display:none">Salvar Produto</button>
            </div>
        </div>
    </form>
</div>

<div class="modal-overlay" id="modalDelete">
    <div class="modal-box" style="max-width:400px;">
        <div class="modal-header">
            <h3>Excluir Produto</h3>
            <button type="button" onclick="fecharModal('modalDelete')" style="border:none; background:none; font-size:1.5rem; cursor:pointer">&times;</button>
        </div>
        <div class="modal-body">
            <p>Tem certeza que deseja excluir o produto abaixo?</p>
            <h4 id="delProdName" style="margin:10px 0; color:var(--primary);"></h4>
            <small style="color:red">Essa ação é irreversível.</small>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-nav" onclick="fecharModal('modalDelete')">Cancelar</button>
            <button type="button" class="btn-nav btn-del" style="background:#ef4444; color:white; border:none;" onclick="confirmarExclusao()">Excluir</button>
        </div>
    </div>
</div>

<script>
    let currentStep = 1;
    const totalSteps = 3;
    let deleteId = null;

    // --- MODAIS ---
    function openModalNovo() {
        resetForm();
        document.getElementById('modalTitle').innerText = 'Novo Produto';
        document.getElementById('modalProduto').classList.add('open');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // --- WIZARD ---
    function resetForm() {
        document.getElementById('formProd').reset();
        document.getElementById('inpId').value = '';
        document.getElementById('containerVariacoes').innerHTML = '';
        document.getElementById('galeriaExistente').innerHTML = '';
        addVarRow(); // adiciona uma linha vazia por padrão
        currentStep = 1;
        updateWizard();
    }

    function nav(dir) {
        currentStep += dir;
        updateWizard();
    }
    function goStep(n) {
        currentStep = n;
        updateWizard();
    }

    function updateWizard() {
        for(let i=1; i<=totalSteps; i++) {
            document.getElementById('step'+i).classList.remove('active');
            document.querySelectorAll('.step-btn')[i-1].classList.remove('active');
        }
        document.getElementById('step'+currentStep).classList.add('active');
        document.querySelectorAll('.step-btn')[currentStep-1].classList.add('active');

        document.getElementById('btnVoltar').style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        
        if(currentStep === totalSteps) {
            document.getElementById('btnAvancar').style.display = 'none';
            document.getElementById('btnSalvar').style.display = 'block';
        } else {
            document.getElementById('btnAvancar').style.display = 'block';
            document.getElementById('btnSalvar').style.display = 'none';
        }
    }

    // --- VARIAÇÕES ---
    function addVarRow(cor='', tam='', peso='', qtd='', preco='') {
        const div = document.createElement('div');
        div.className = 'var-row';
        div.innerHTML = `
            <input type="text" name="var_cor[]" class="input-control" placeholder="Cor (Ex: Azul)" value="${cor}">
            <input type="text" name="var_tam[]" class="input-control" placeholder="Tam" value="${tam}">
            <input type="text" name="var_peso[]" class="input-control" placeholder="Peso" value="${peso}">
            <input type="number" name="var_qtd[]" class="input-control" placeholder="Qtd" value="${qtd}">
            <input type="text" name="var_preco[]" class="input-control" placeholder="Preço (Opc)" value="${preco}">
            <button type="button" onclick="this.parentElement.remove()" style="color:red; border:none; background:none; cursor:pointer;"><i class="bi bi-trash"></i></button>
        `;
        document.getElementById('containerVariacoes').appendChild(div);
    }

    // --- EDITAR ---

    async function editar(id) {
        resetForm();
        document.getElementById('containerVariacoes').innerHTML = '';
        try {
            const res = await fetch(`?api_acao=get_produto&id=${id}`);
            if (!res.ok) throw new Error('Erro ao buscar produto');
            const data = await res.json();
            const p = data.produto;
            if (!p) throw new Error('Produto não encontrado');

            document.getElementById('inpId').value = p.id;
            document.getElementById('inpNome').value = p.nome;
            document.getElementById('inpMarca').value = p.marca;
            document.getElementById('inpModelo').value = p.modelo;
            document.getElementById('inpCod').value = p.codigo_barras;
            document.getElementById('inpDesc').value = p.descricao;
            document.getElementById('inpCusto').value = p.preco_custo;
            document.getElementById('inpVenda').value = p.preco_venda;
            document.getElementById('inpMin').value = p.estoque_minimo;

            if(data.variacoes && data.variacoes.length > 0) {
                data.variacoes.forEach(v => addVarRow(v.cor, v.tamanho, v.peso, v.estoque, v.preco_venda));
            } else {
                addVarRow();
            }

            const galDiv = document.getElementById('galeriaExistente');
            if(data.galeria && data.galeria.length > 0) {
                data.galeria.forEach(g => {
                    galDiv.innerHTML += `
                        <div style="position:relative;">
                            <img src="../../assets/uploads/produtos/${g.caminho_imagem}" style="width:60px; height:60px; border-radius:6px; object-fit:cover;">
                            <button type="button" onclick="delImg(${g.id}, this)" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:10px;">X</button>
                        </div>
                    `;
                });
            }

            document.getElementById('modalTitle').innerText = 'Editar Produto #' + id;
            document.getElementById('modalProduto').classList.add('open');
        } catch(e) {
            alert('Erro ao carregar dados do produto para edição.');
            console.error(e);
        }
    }

    async function delImg(id, btn) {
        if(confirm('Apagar foto?')) {
            await fetch(`?api_acao=del_imagem&id=${id}`);
            btn.parentElement.remove();
        }
    }

    // --- EXCLUIR ---
    function abrirModalDelete(id, nome) {
        deleteId = id;
        document.getElementById('delProdName').innerText = nome;
        document.getElementById('modalDelete').classList.add('open');
    }

    async function confirmarExclusao() {
        if(!deleteId) return;
        const res = await fetch(`?api_acao=del_produto&id=${deleteId}`);
        const data = await res.json();
        if(data.success) location.reload();
        else alert('Erro ao excluir');
    }

    function filtrarTabela() {
        const termo = document.getElementById('filtro').value.toLowerCase();
        const rows = document.querySelectorAll('#tabelaProdutos tbody tr');
        rows.forEach(r => {
            r.style.display = r.innerText.toLowerCase().includes(termo) ? '' : 'none';
        });
    }
</script>

</body>
</html>