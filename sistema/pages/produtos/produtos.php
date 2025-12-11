<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// ==========================================
// 1. LÓGICA PHP (CRUD COMPLETO)
// ==========================================

// Banco de dados primeiro (para API não vazar HTML)
require_once '../../includes/banco-dados/db.php';
$owner_id = $_SESSION['user_id'];

// Diretório de upload de imagens dos produtos
$uploadDir = '../../assets/uploads/produtos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// ------------------------------------------
// 1.1 API INTERNA (JSON para o JavaScript)
// ------------------------------------------
if (isset($_GET['api_acao'])) {
    header('Content-Type: application/json; charset=utf-8');

    // A. Buscar dados para edição
    if ($_GET['api_acao'] === 'get_produto' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Produto

        // Produto
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND owner_id = ?");
        $stmt->execute([$id, $owner_id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        // Variações
        $stmtVar = $pdo->prepare("SELECT * FROM produtos_variacoes WHERE produto_id = ? AND owner_id = ?");
        $stmtVar->execute([$id, $owner_id]);
        $vars = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

        // Galeria
        $stmtGal = $pdo->prepare("SELECT * FROM produtos_galeria WHERE produto_id = ?");
        $stmtGal->execute([$id]);
        $galeria = $stmtGal->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'produto'   => $prod,
            'variacoes' => $vars,
            'galeria'   => $galeria
        ]);
        exit;
    }

    // B. Excluir imagem da galeria
    if ($_GET['api_acao'] === 'del_imagem' && isset($_GET['id'])) {
        $idImg = (int)$_GET['id'];

        $stmt = $pdo->prepare("SELECT caminho_imagem FROM produtos_galeria WHERE id = ?");
        $stmt->execute([$idImg]);
        $img = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($img) {
            $file = $uploadDir . $img['caminho_imagem'];
            if (file_exists($file)) @unlink($file);

            $pdo->prepare("DELETE FROM produtos_galeria WHERE id = ?")->execute([$idImg]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    // C. Excluir produto (capa + galeria + variações)
    if ($_GET['api_acao'] === 'del_produto' && isset($_GET['id'])) {
        $idProd = (int)$_GET['id'];

        try {
            $pdo->beginTransaction();

            // Apagar capa
            $stmt = $pdo->prepare("SELECT imagem_capa FROM produtos WHERE id = ?");
            $stmt->execute([$idProd]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($p && !empty($p['imagem_capa'])) {
                $capaFile = $uploadDir . $p['imagem_capa'];
                if (file_exists($capaFile)) @unlink($capaFile);
            }

            // Apagar imagens da galeria (arquivos)
            $stmtGal = $pdo->prepare("SELECT caminho_imagem FROM produtos_galeria WHERE produto_id = ?");
            $stmtGal->execute([$idProd]);
            $gals = $stmtGal->fetchAll(PDO::FETCH_ASSOC);

            foreach ($gals as $g) {
                $file = $uploadDir . $g['caminho_imagem'];
                if (file_exists($file)) @unlink($file);
            }

            // Banco: ON DELETE CASCADE já remove variações/galeria
            $stmtDel = $pdo->prepare("DELETE FROM produtos WHERE id = ? AND owner_id = ?");
            $stmtDel->execute([$idProd, $owner_id]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Qualquer outra ação não reconhecida
    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    exit;
}


// ------------------------------------------
// 1.2 PÁGINA NORMAL (HTML + MENU)
// ------------------------------------------
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php
include_once '../../includes/menu.php';

// --- PROCESSAR FORMULÁRIO (SALVAR / ATUALIZAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    try {
        $pdo->beginTransaction();

        $id = !empty($_POST['id_produto']) ? (int)$_POST['id_produto'] : null;

        // Dados gerais
        $nome         = $_POST['nome'];
        $marca        = $_POST['marca'];
        $modelo       = $_POST['modelo'];
        $tipo         = $_POST['tipo'];
        $cod_barras   = $_POST['cod_barras'];
        $est_min      = (int)$_POST['est_min'];
        $tem_validade = isset($_POST['tem_validade']) ? 1 : 0;
        $dt_validade  = $_POST['data_validade'] ?: null;
        $ativo        = isset($_POST['ativo']) ? 1 : 0;
        $auto_des     = isset($_POST['auto_desativar']) ? 1 : 0;
        $desc         = $_POST['descricao'];

        // Upload Capa (se enviada)
        $capaSql = "";
        $params = [
            $nome, $marca, $modelo, $tipo, $cod_barras,
            $est_min, $tem_validade, $dt_validade, $ativo, $auto_des, $desc
        ];

        if (!empty($_FILES['imagem_capa']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagem_capa']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $novoNome = uniqid('capa_') . '.' . $ext;
                if (move_uploaded_file($_FILES['imagem_capa']['tmp_name'], $uploadDir . $novoNome)) {
                    if ($id) {
                        // update com nova capa
                        $capaSql = ", imagem_capa = ?";
                        $params[] = $novoNome;

                        // apaga capa antiga
                        $stmtOld = $pdo->prepare("SELECT imagem_capa FROM produtos WHERE id = ?");
                        $stmtOld->execute([$id]);
                        $old = $stmtOld->fetch(PDO::FETCH_ASSOC);
                        if ($old && !empty($old['imagem_capa'])) {
                            $oldPath = $uploadDir . $old['imagem_capa'];
                            if (file_exists($oldPath)) @unlink($oldPath);
                        }
                    } else {
                        // insert com capa
                        $params[] = $novoNome;
                    }
                }
            }
        } else {
            // insert sem capa
            if (!$id) $params[] = null;
        }


        if ($id) {
            // --- UPDATE ---
            $sql = "UPDATE produtos 
                    SET nome=?, marca=?, modelo=?, tipo_produto=?, codigo_barras=?, estoque_minimo=?, 
                        tem_validade=?, data_validade=?, ativo=?, auto_desativar=?, descricao=? 
                        $capaSql 
                    WHERE id=? AND owner_id=?";
            $params[] = $id;
            $params[] = $owner_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $produtoId = $id;

            // Limpa variações antigas (estratégia simples)
            $pdo->prepare("DELETE FROM produtos_variacoes WHERE produto_id = ? AND owner_id = ?")->execute([$id, $owner_id]);
        } else {
            // --- INSERT ---
            $stmt = $pdo->prepare("INSERT INTO produtos 
                (nome, marca, modelo, tipo_produto, codigo_barras, estoque_minimo, tem_validade, data_validade, ativo, auto_desativar, descricao, imagem_capa, owner_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $params[] = $owner_id;
            $stmt->execute($params);
            $produtoId = $pdo->lastInsertId();
        }

        // Salvar variações
        if (isset($_POST['var_cor'])) {
            $stmtVar = $pdo->prepare("INSERT INTO produtos_variacoes (produto_id, cor, tamanho, peso, preco, estoque, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['var_cor'] as $key => $cor) {
                $tam   = $_POST['var_tam'][$key];
                $peso  = $_POST['var_peso'][$key];
                $qtd   = $_POST['var_qtd'][$key];
                $preco = $_POST['var_preco'][$key];

                if ($cor && $qtd !== '' && $preco !== '') {
                    $stmtVar->execute([$produtoId, $cor, $tam, $peso, $preco, $qtd, $owner_id]);
                }
            }
        }

        // Salvar novas imagens da galeria (máx 10)
        if (!empty($_FILES['galeria']['name'][0])) {
            $stmtGal = $pdo->prepare("INSERT INTO produtos_galeria (produto_id, caminho_imagem) VALUES (?, ?)");

            // conta quantas já existem
            $stmtCount = $pdo->prepare("SELECT COUNT(*) AS total FROM produtos_galeria WHERE produto_id = ?");
            $stmtCount->execute([$produtoId]);
            $jaTem = (int)$stmtCount->fetchColumn();
            $limite = 10 - $jaTem;

            $total = count($_FILES['galeria']['name']);
            for ($i = 0; $i < $total && $i < $limite; $i++) {
                $tmp  = $_FILES['galeria']['tmp_name'][$i];
                $name = $_FILES['galeria']['name'][$i];
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $nomeGal = uniqid('gal_') . '.' . $ext;
                    if (move_uploaded_file($tmp, $uploadDir . $nomeGal)) {
                        $stmtGal->execute([$produtoId, $nomeGal]);
                    }
                }
            }
        }

        $pdo->commit();
        $msgSucesso = "Produto salvo com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msgErro = "Erro ao salvar: " . $e->getMessage();
    }
}

// --- LISTAGEM ---
$sql = "SELECT p.*, 
            (SELECT SUM(estoque) FROM produtos_variacoes WHERE produto_id = p.id AND owner_id = :owner_id) as estoque_total 
        FROM produtos p 
        WHERE p.owner_id = :owner_id 
        ORDER BY p.id DESC";
$stmtLista = $pdo->prepare($sql);
$stmtLista->execute([':owner_id' => $owner_id]);
$listaProdutos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    :root { --primary-color: #6366f1; --bg-glass: rgba(255,255,255,0.9); }

    .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding:0 10px; }

    .card-panel { background:white; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); border:1px solid #e2e8f0; overflow:hidden; }

    .table-custom { width:100%; border-collapse:collapse; }
    .table-custom th { text-align:left; padding:16px; background:#f8fafc; color:#64748b; font-size:0.85rem; font-weight:600; text-transform:uppercase; }
    .table-custom td { padding:16px; border-bottom:1px solid #f1f5f9; color:#334155; font-size:0.95rem; vertical-align:middle; }
    .table-custom tr:last-child td { border-bottom:none; }

    .thumb-img { width:48px; height:48px; border-radius:8px; object-fit:cover; background:#f1f5f9; border:1px solid #e2e8f0; }

    .badge { padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:600; }
    .badge-success { background:#dcfce7; color:#166534; }
    .badge-warning { background:#fef9c3; color:#854d0e; }
    .badge-danger  { background:#fee2e2; color:#991b1b; }
    .badge-neutral { background:#f1f5f9; color:#475569; }

    .modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:2000; display:none; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }

    .modal-box { background:white; width:90%; max-width:800px; max-height:90vh; border-radius:16px; display:flex; flex-direction:column; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); }
    .modal-header { padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
    .modal-body { padding:20px; overflow-y:auto; flex:1; }
    .modal-footer { padding:20px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; background:#f8fafc; border-radius:0 0 16px 16px; }

    .tabs { display:flex; gap:20px; border-bottom:2px solid #f1f5f9; margin-bottom:20px; }
    .tab-btn { background:none; border:none; padding:10px 0; cursor:pointer; color:#64748b; font-weight:600; border-bottom:2px solid transparent; transition:0.2s; }
    .tab-btn.active { color:var(--primary-color); border-bottom-color:var(--primary-color); }
    .tab-content { display:none; }
    .tab-content.active { display:block; animation:fadeIn 0.3s; }

    .form-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:15px; }
    .full-width { grid-column:span 2; }

    .input-group label { display:block; font-size:0.85rem; color:#475569; margin-bottom:6px; font-weight:500; }
    .input-control { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; font-size:0.95rem; transition:0.2s; }
    .input-control:focus { outline:none; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(99,102,241,0.1); }

    .upload-zone { border:2px dashed #cbd5e1; border-radius:12px; padding:30px; text-align:center; cursor:pointer; transition:0.2s; background:#f8fafc; }
    .upload-zone:hover { border-color:var(--primary-color); background:#eff6ff; }

    .preview-gallery { display:flex; gap:10px; margin-top:15px; flex-wrap:wrap; }
    .preview-item { width:80px; height:80px; border-radius:8px; object-fit:cover; border:1px solid #e2e8f0; }

    .var-row { display:grid; grid-template-columns:2fr 1fr 1fr 1fr 40px; gap:10px; margin-bottom:10px; align-items:center; }

    @keyframes fadeIn { from{opacity:0; transform:translateY(5px);} to{opacity:1; transform:translateY(0);} }

    /* MOBILE – transforma tabela em cards */
    @media (max-width: 768px) {
        .table-custom thead { display:none; }

        .table-custom tr {
            display:block;
            margin:8px 12px;
            background:#fff;
            border-radius:12px;
            box-shadow:0 4px 6px -1px rgba(0,0,0,0.06);
        }

        .table-custom td {
            display:flex;
            justify-content:space-between;
            align-items:center;
            border-bottom:none;
            padding:8px 14px;
            font-size:0.9rem;
        }

        .table-custom td:first-child {
            justify-content:flex-start;
            gap:10px;
        }

        .table-custom td:last-child {
            justify-content:flex-end;
            padding-bottom:12px;
        }
    }
</style>

<main style="padding: 32px; max-width: 1200px; margin: 0 auto;">
    <div class="page-header">
        <div>
            <h1 style="margin:0; font-size:1.5rem; color:#1e293b;">Produtos</h1>
            <p style="margin:4px 0 0; color:#64748b;">Gerencie inventário, preços e variações.</p>
        </div>
        <button onclick="openModalNovo()" style="background:var(--primary-color); color:white; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px;">
            <i class="bi bi-plus-lg"></i> Novo Produto
        </button>
    </div>

    <?php if(isset($msgSucesso)): ?>
        <div style="padding:15px; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:20px; border:1px solid #bbf7d0;">
            <i class="bi bi-check-circle-fill"></i> <?php echo $msgSucesso; ?>
        </div>
    <?php endif; ?>
    <?php if(isset($msgErro)): ?>
        <div style="padding:15px; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca;">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $msgErro; ?>
        </div>
    <?php endif; ?>

    <div class="card-panel">
        <table class="table-custom">
            <thead>
            <tr>
                <th style="width: 80px;">Capa</th>
                <th>Produto</th>
                <th>Variações / Estoque</th>
                <th>Status</th>
                <th>Validade</th>
                <th style="text-align:right;">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($listaProdutos as $prod):
                $imgUrl = !empty($prod['imagem_capa'])
                    ? '../../assets/uploads/produtos/'.$prod['imagem_capa']
                    : 'https://via.placeholder.com/150?text=Sem+Foto';

                // Validade
                $validadeHtml = '<span class="badge badge-neutral">-</span>';
                if ($prod['tem_validade']) {
                    $hoje = date('Y-m-d');
                    if ($prod['data_validade'] < $hoje) {
                        $validadeHtml = '<span class="badge badge-danger">Vencido</span>';
                    } elseif ($prod['data_validade'] < date('Y-m-d', strtotime('+30 days'))) {
                        $validadeHtml = '<span class="badge badge-warning">Vence em breve</span>';
                    } else {
                        $validadeHtml = '<span class="badge badge-success">'.date('d/m/Y', strtotime($prod['data_validade'])).'</span>';
                    }
                }

                $estoque = $prod['estoque_total'] ?: 0;
                $estoqueClass = ($estoque <= $prod['estoque_minimo']) ? 'text-danger fw-bold' : '';
                ?>
                <tr>
                    <td><img src="<?php echo $imgUrl; ?>" class="thumb-img"></td>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($prod['nome']); ?></div>
                        <div style="font-size:0.8rem; color:#94a3b8;"><?php echo htmlspecialchars($prod['marca'] . ' - ' . $prod['modelo']); ?></div>
                    </td>
                    <td>
                        <div class="<?php echo $estoqueClass; ?>">
                            <?php echo $estoque; ?> un.
                        </div>
                        <small style="color:#94a3b8;">Total (todas variações)</small>
                    </td>
                    <td>
                        <?php if($prod['ativo']): ?>
                            <span class="badge badge-success">Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $validadeHtml; ?></td>
                    <td style="text-align:right;">
                        <button onclick="editarProduto(<?php echo $prod['id']; ?>)" style="background:none; border:none; cursor:pointer; color:#6366f1; margin-right:8px;">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button onclick="openDeleteModal(<?php echo $prod['id']; ?>, '<?php echo addslashes(htmlspecialchars($prod['nome'])); ?>')" style="background:none; border:none; cursor:pointer; color:#ef4444;">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if(empty($listaProdutos)): ?>
                <tr><td colspan="6" style="text-align:center; padding:30px;">Nenhum produto cadastrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- MODAL DE CADASTRO/EDIÇÃO -->
<div class="modal-overlay" id="productModal">
    <form class="modal-box" method="POST" enctype="multipart/form-data" id="productForm">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id_produto" id="inputIdProduto" value="">

        <div class="modal-header">
            <h3 style="margin:0;">Novo Produto</h3>
            <button type="button" onclick="closeModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>

        <div class="modal-body">
            <div class="tabs">
                <button type="button" class="tab-btn active" onclick="switchTab(event, 'tab-geral')">1. Dados Gerais</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-var')">2. Estoque & Variações</button>
                <button type="button" class="tab-btn" onclick="switchTab(event, 'tab-img')">3. Imagens</button>
            </div>

            <div id="tab-geral" class="tab-content active">
                <div class="form-grid">
                    <div class="input-group full-width">
                        <label>Nome do Produto</label>
                        <input type="text" name="nome" class="input-control" required placeholder="Ex: Camiseta Básica Algodão">
                    </div>

                    <div class="input-group">
                        <label>Marca</label>
                        <input type="text" name="marca" class="input-control" placeholder="Ex: Nike">
                    </div>
                    <div class="input-group">
                        <label>Modelo</label>
                        <input type="text" name="modelo" class="input-control" placeholder="Ex: Slim Fit">
                    </div>

                    <div class="input-group">
                        <label>Código de Barras (EAN)</label>
                        <input type="text" name="cod_barras" class="input-control">
                    </div>
                    <div class="input-group">
                        <label>Tipo</label>
                        <select name="tipo" class="input-control">
                            <option value="produto">Produto Físico</option>
                            <option value="servico">Serviço</option>
                            <option value="kit">Kit / Combo</option>
                        </select>
                    </div>

                    <div class="input-group full-width">
                        <label>Descrição Detalhada</label>
                        <textarea name="descricao" class="input-control" rows="3"></textarea>
                    </div>

                    <div class="input-group" style="display:flex; align-items:center; gap:10px; margin-top:10px;">
                        <input type="checkbox" id="checkValidade" name="tem_validade" onchange="toggleValidade()">
                        <label for="checkValidade" style="margin:0; cursor:pointer;">Este produto tem validade?</label>
                    </div>
                    <div class="input-group" id="divDataValidade" style="display:none;">
                        <label>Data de Validade</label>
                        <input type="date" name="data_validade" class="input-control">
                    </div>

                    <div class="full-width" style="border-top:1px solid #f1f5f9; margin-top:10px; padding-top:10px;">
                        <div style="display:flex; gap:20px;">
                            <label style="display:flex; align-items:center; gap:8px;">
                                <input type="checkbox" name="ativo" checked> Produto Ativo no Site
                            </label>
                            <label style="display:flex; align-items:center; gap:8px;">
                                <input type="checkbox" name="auto_desativar"> Desativar se estoque zerar
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-var" class="tab-content">
                <div style="background:#f8fafc; padding:15px; border-radius:8px; margin-bottom:15px;">
                    <p style="margin:0; font-size:0.9rem; color:#64748b;">
                        Cadastre as variações (Ex: Preta P, Preta M). O estoque total será a soma destas quantidades.
                    </p>
                    <div class="input-group" style="margin-top:10px;">
                        <label>Alerta de Estoque Mínimo (Geral)</label>
                        <input type="number" name="est_min" value="5" class="input-control" style="width:100px;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 40px; gap:10px; font-size:0.9rem; margin-bottom:8px; color:#64748b;">
                    <span>Cor</span><span>Tamanho</span><span>Peso</span><span>Qtd</span><span>Preço</span><span></span>
                </div>
                <div id="variacoes-container"></div>
                <button type="button" onclick="addVariationRow()" style="margin-top:10px; background:none; border:1px dashed #6366f1; color:#6366f1; width:100%; padding:10px; border-radius:8px; cursor:pointer;">
                    + Adicionar Variação
                </button>
            </div>

            <div id="tab-img" class="tab-content">
                <div class="input-group full-width">
                    <label>Foto de Capa (Principal)</label>
                    <input type="file" name="imagem_capa" class="input-control" accept="image/png, image/jpeg">
                </div>

                <div id="galeria-existente" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;"></div>

                <div style="margin-top:20px;">
                    <label style="display:block; margin-bottom:10px;">Galeria (Até 10 imagens)</label>
                    <div class="upload-zone" id="dropZone">
                        <i class="bi bi-cloud-arrow-up" style="font-size:2rem; color:#cbd5e1;"></i>
                        <p style="margin:10px 0; color:#64748b;">Arraste fotos aqui ou clique para selecionar</p>
                        <input type="file" name="galeria[]" id="galeriaInput" multiple accept="image/png, image/jpeg" style="display:none;">
                    </div>
                    <div class="preview-gallery" id="previewGallery"></div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="closeModal()" style="padding:10px 20px; border:1px solid #cbd5e1; background:white; border-radius:8px; cursor:pointer;">Cancelar</button>
            <button type="submit" style="padding:10px 20px; background:var(--primary-color); color:white; border:none; border-radius:8px; cursor:pointer;">Salvar Produto</button>
        </div>
    </form>
</div>

<!-- MODAL DE CONFIRMAÇÃO DE EXCLUSÃO -->
<div class="modal-overlay" id="confirmDeleteModal">
    <div class="modal-box" style="max-width: 420px;">
        <div class="modal-header">
            <h3 style="margin:0; font-size:1.1rem;">Excluir produto</h3>
            <button type="button" onclick="closeDeleteModal()"
                    style="background:none; border:none; font-size:1.5rem; cursor:pointer;">
                &times;
            </button>
        </div>
        <div class="modal-body">
            <p style="margin:0 0 10px; color:#475569;">
                Tem certeza que deseja excluir o produto abaixo? Essa ação não pode ser desfeita.
            </p>
            <p style="margin:0; font-weight:600; color:#111827;" id="deleteProductName"></p>
        </div>
        <div class="modal-footer" style="justify-content:flex-end;">
            <button type="button" onclick="closeDeleteModal()"
                    style="padding:8px 18px; border:1px solid #cbd5e1; background:white; border-radius:8px; cursor:pointer;">
                Cancelar
            </button>
            <button type="button" onclick="confirmDelete()"
                    style="padding:8px 18px; background:#ef4444; color:white; border:none; border-radius:8px; cursor:pointer;">
                Excluir
            </button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('productModal');
    const form  = document.getElementById('productForm');

    const confirmDeleteModal = document.getElementById('confirmDeleteModal');
    const deleteProductName  = document.getElementById('deleteProductName');
    let deleteId = null;

    function resetForm() {
        form.reset();
        document.getElementById('inputIdProduto').value = '';
        document.getElementById('variacoes-container').innerHTML = '';
        document.getElementById('previewGallery').innerHTML = '';
        document.getElementById('galeria-existente').innerHTML = '';
        toggleValidade(); // esconde campo se necessário
        switchTab(null, 'tab-geral', true);
    }

    function openModalNovo() {
        resetForm();
        document.querySelector('.modal-header h3').innerText = "Novo Produto";
        addVariationRow();
        modal.classList.add('open');
    }

    function openModal() {
        modal.classList.add('open');
    }

    function closeModal() {
        modal.classList.remove('open');
    }

    // Abas
    function switchTab(evt, tabId, fromReset = false) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');

        if (!fromReset && evt) {
            evt.target.classList.add('active');
        } else {
            const firstBtn = document.querySelector('.tab-btn');
            if (firstBtn) firstBtn.classList.add('active');
        }
    }

    // Validade
    function toggleValidade() {
        const check = document.getElementById('checkValidade');
        const div = document.getElementById('divDataValidade');
        if (!check) return;
        div.style.display = check.checked ? 'block' : 'none';
    }

    // Variações dinâmicas
    function addVariationRow(cor = '', tam = '', peso = '', qtd = '', preco = '') {
        const container = document.getElementById('variacoes-container');
        const div = document.createElement('div');
        div.className = 'var-row';
        div.innerHTML = `
            <input type="text" name="var_cor[]" value="${cor}" placeholder="Cor (Ex: Preto)" class="input-control" required>
            <input type="text" name="var_tam[]" value="${tam}" placeholder="Tam (P, M)" class="input-control">
            <input type="number" name="var_peso[]" value="${peso}" placeholder="Kg" class="input-control" step="0.01">
            <input type="number" name="var_qtd[]" value="${qtd}" placeholder="Qtd" class="input-control" min="0" required>
            <input type="number" name="var_preco[]" value="${preco}" placeholder="Preço" class="input-control" min="0" step="0.01" required>
            <button type="button" onclick="this.parentElement.remove()" style="color:red; border:none; background:none; cursor:pointer;"><i class="bi bi-trash"></i></button>
        `;
        container.appendChild(div);
    }

    // EDITAR PRODUTO (AJAX)
    async function editarProduto(id) {
        resetForm();
        document.getElementById('inputIdProduto').value = id;
        document.querySelector('.modal-header h3').innerText = "Editar Produto #" + id;

        try {
            const response = await fetch(`?api_acao=get_produto&id=${id}`);
            const data = await response.json();
            const p = data.produto;

            document.querySelector('[name="nome"]').value        = p.nome || '';
            document.querySelector('[name="marca"]').value       = p.marca || '';
            document.querySelector('[name="modelo"]').value      = p.modelo || '';
            document.querySelector('[name="cod_barras"]').value  = p.codigo_barras || '';
            document.querySelector('[name="est_min"]').value     = p.estoque_minimo || 0;
            document.querySelector('[name="descricao"]').value   = p.descricao || '';
            document.querySelector('[name="tipo"]').value        = p.tipo_produto || 'produto';

            document.querySelector('[name="ativo"]').checked          = (p.ativo == 1);
            document.querySelector('[name="auto_desativar"]').checked = (p.auto_desativar == 1);

            const checkVal = document.getElementById('checkValidade');
            checkVal.checked = (p.tem_validade == 1);
            toggleValidade();
            if (p.tem_validade == 1 && p.data_validade) {
                document.querySelector('[name="data_validade"]').value = p.data_validade;
            }

            // Variações
            if (data.variacoes.length > 0) {
                data.variacoes.forEach(v => {
                    addVariationRow(v.cor, v.tamanho, v.peso, v.estoque);
                });
            } else {
                addVariationRow();
            }

            // Galeria existente
            const galeriaContainer = document.getElementById('galeria-existente');
            if (data.galeria.length > 0) {
                galeriaContainer.innerHTML = '<p style="width:100%; font-size:0.85rem; color:#64748b; margin-bottom:5px;">Imagens salvas (clique no lixo para apagar):</p>';
                data.galeria.forEach(img => {
                    const div = document.createElement('div');
                    div.style.position = 'relative';
                    div.style.width = '80px';
                    div.style.height = '80px';
                    div.innerHTML = `
                        <img src="../../assets/uploads/produtos/${img.caminho_imagem}" style="width:100%; height:100%; object-fit:cover; border-radius:8px; border:1px solid #e2e8f0;">
                        <button type="button" onclick="deletarImagem(${img.id}, this)" style="position:absolute; top:-5px; right:-5px; background:red; color:white; border:none; border-radius:50%; width:24px; height:24px; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 5px rgba(0,0,0,0.2);">
                            <i class="bi bi-trash-fill" style="font-size:0.7rem;"></i>
                        </button>
                    `;
                    galeriaContainer.appendChild(div);
                });
            }

            openModal();
        } catch (error) {
            console.error('Erro ao buscar produto:', error);
            alert('Erro ao carregar dados do produto.');
        }
    }

    // MODAL DE EXCLUSÃO
    function openDeleteModal(id, nome) {
        deleteId = id;
        deleteProductName.textContent = nome;
        confirmDeleteModal.classList.add('open');
    }

    function closeDeleteModal() {
        confirmDeleteModal.classList.remove('open');
        deleteId = null;
    }

    async function confirmDelete() {
        if (!deleteId) return;

        try {
            const res = await fetch(`?api_acao=del_produto&id=${deleteId}`);
            const data = await res.json();

            if (data.success) {
                closeDeleteModal();
                location.reload();
            } else {
                closeDeleteModal();
                alert('Erro ao excluir produto.');
            }
        } catch (e) {
            console.error(e);
            closeDeleteModal();
            alert('Erro ao excluir produto.');
        }
    }

    // Deletar imagem da galeria
    async function deletarImagem(idImg, btnElement) {
        if(!confirm('Quer mesmo apagar esta foto?')) return;
        try {
            const res = await fetch(`?api_acao=del_imagem&id=${idImg}`);
            const data = await res.json();
            if(data.success) {
                btnElement.parentElement.remove();
            } else {
                alert('Erro ao apagar imagem.');
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Drag and drop galeria
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('galeriaInput');
    const previewArea = document.getElementById('previewGallery');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#6366f1';
        dropZone.style.background = '#e0e7ff';
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = '#cbd5e1';
        dropZone.style.background = '#f8fafc';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#cbd5e1';
        dropZone.style.background = '#f8fafc';

        const files = e.dataTransfer.files;
        handleFiles(files);
        fileInput.files = files;
    });

    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
    });

    function handleFiles(files) {
        previewArea.innerHTML = '';
        if (files.length > 10) {
            alert('Máximo de 10 imagens permitidas!');
            fileInput.value = '';
            return;
        }

        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-item';
                    previewArea.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        });
    }

</script>
</body>
</html>
