<?php
// ==========================================
// 1. CONFIGURAÇÕES, BANCO E UPLOAD
// ==========================================
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';

// Pasta para salvar as imagens dos serviços
$uploadDir = '../../assets/uploads/servicos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Cria tabela atualizada (com imagem e flag de site)
$pdo->exec("CREATE TABLE IF NOT EXISTS servicos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    categoria TEXT,
    descricao TEXT,
    duracao_minutos INTEGER,
    preco REAL,
    imagem TEXT,
    mostrar_site INTEGER DEFAULT 0,
    ativo INTEGER DEFAULT 1,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$msgSucesso = null;
$msgErro = null;

// ==========================================
// 2. PROCESSAR AÇÕES (SALVAR / EXCLUIR)
// ==========================================

// --- SALVAR (CRIAR OU EDITAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_servico') {
    try {
        $id          = !empty($_POST['id_servico']) ? (int)$_POST['id_servico'] : null;
        $nome        = trim($_POST['nome'] ?? '');
        $categoria   = trim($_POST['categoria'] ?? '');
        $descricao   = trim($_POST['descricao'] ?? '');
        $duracao     = !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : null;
        $preco       = !empty($_POST['preco']) ? (float)str_replace(',', '.', $_POST['preco']) : 0;
        $ativo       = isset($_POST['ativo']) ? 1 : 0;
        $mostrarSite = isset($_POST['mostrar_site']) ? 1 : 0;

        if ($nome === '') throw new Exception('O nome do serviço é obrigatório.');

        // Upload de Imagem
        $nomeImagem = null;
        $sqlImagem = ""; 
        
        if (!empty($_FILES['imagem']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $novoNome = uniqid('srv_') . '.' . $ext;
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $uploadDir . $novoNome)) {
                    $nomeImagem = $novoNome;
                }
            }
        }

        if ($id) {
            // UPDATE
            $sql = "UPDATE servicos SET nome=?, categoria=?, descricao=?, duracao_minutos=?, preco=?, ativo=?, mostrar_site=?";
            $params = [$nome, $categoria, $descricao, $duracao, $preco, $ativo, $mostrarSite];
            
            if ($nomeImagem) {
                // Se enviou nova imagem, atualiza e pode apagar a antiga se quiser (opcional)
                $sql .= ", imagem=?";
                $params[] = $nomeImagem;
            }
            
            $sql .= " WHERE id=?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $msgSucesso = "Serviço atualizado com sucesso!";
        } else {
            // INSERT
            $stmt = $pdo->prepare("INSERT INTO servicos (nome, categoria, descricao, duracao_minutos, preco, ativo, mostrar_site, imagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $categoria, $descricao, $duracao, $preco, $ativo, $mostrarSite, $nomeImagem]);
            $msgSucesso = "Serviço criado com sucesso!";
        }

    } catch (Exception $e) {
        $msgErro = "Erro: " . $e->getMessage();
    }
}

// --- EXCLUIR ---
if (isset($_GET['excluir'])) {
    try {
        $id = (int)$_GET['excluir'];
        // Pega imagem para deletar arquivo
        $stmt = $pdo->prepare("SELECT imagem FROM servicos WHERE id = ?");
        $stmt->execute([$id]);
        $foto = $stmt->fetchColumn();
        
        if($foto && file_exists($uploadDir.$foto)) unlink($uploadDir.$foto);

        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([$id]);
        $msgSucesso = "Serviço removido!";
    } catch (Exception $e) {
        $msgErro = "Erro ao excluir.";
    }
}

// ==========================================
// 3. BUSCAR DADOS
// ==========================================
$busca = trim($_GET['busca'] ?? '');
$sql = "SELECT * FROM servicos WHERE 1=1";
$params = [];

if ($busca) {
    $sql .= " AND (nome LIKE ? OR categoria LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " ORDER BY nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Serviços</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f8fafc; /* Fundo claro */
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 12px;
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding-bottom: 40px;
        }

        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* HEADER */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .page-header p {
            margin: 5px 0 0;
            color: var(--text-muted);
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        /* ALERTS */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* CARD PRINCIPAL */
        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        /* BARRA DE FILTRO */
        .filter-bar {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 10px;
            background: #fcfcfc;
        }
        .search-input {
            flex: 1;
            max-width: 400px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            color: var(--text-main);
        }
        .search-input:focus { border-color: var(--primary); }

        /* TABELA */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th {
            background: #f8fafc;
            padding: 16px;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #fdfdfd; }

        /* ELEMENTOS VISUAIS */
        .thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
            background: #f1f5f9;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-site { background: #e0e7ff; color: #4338ca; } /* Indigo */
        .badge-no-site { background: #f1f5f9; color: #64748b; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }

        .btn-icon {
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 6px; border: 1px solid transparent;
            background: transparent; color: var(--text-muted);
            cursor: pointer; transition: 0.2s;
        }
        .btn-icon:hover { background: #f1f5f9; color: var(--primary); }
        .btn-icon.del:hover { background: #fef2f2; color: #ef4444; }

        /* MODAL */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(3px);
            display: none; align-items: center; justify-content: center;
            z-index: 9999;
        }
        .modal-overlay.open { display: flex; animation: fadeIn 0.2s ease; }
        
        .modal-box {
            background: white; width: 95%; max-width: 600px;
            border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            display: flex; flex-direction: column; max-height: 90vh;
        }
        .modal-header {
            padding: 20px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 1.25rem; color: var(--text-main); }
        
        .modal-body { padding: 24px; overflow-y: auto; }
        
        .modal-footer {
            padding: 20px; border-top: 1px solid var(--border); background: #fcfcfc;
            display: flex; justify-content: flex-end; gap: 10px; border-radius: 0 0 16px 16px;
        }

        /* FORM */
        .form-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;
        }
        .col-full { grid-column: span 2; }
        
        label { display: block; font-size: 0.85rem; font-weight: 500; color: var(--text-main); margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 0.95rem; outline: none; box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px #e0e7ff; }
        
        textarea.form-control { resize: vertical; min-height: 80px; }

        /* UPLOAD PREVIEW */
        .upload-area {
            border: 2px dashed var(--border); border-radius: 8px; padding: 20px;
            text-align: center; cursor: pointer; transition: 0.2s; position: relative;
        }
        .upload-area:hover { border-color: var(--primary); background: #f8fafc; }
        .preview-img {
            max-width: 100%; max-height: 150px; border-radius: 8px; display: none; margin: 10px auto; object-fit: contain;
        }

        .switch-label {
            display: flex; align-items: center; gap: 10px; cursor: pointer;
            padding: 10px; border: 1px solid var(--border); border-radius: 8px;
        }
        input[type="checkbox"] { accent-color: var(--primary); width: 16px; height: 16px; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @media(max-width: 600px) { .form-row { grid-template-columns: 1fr; } .col-full { grid-column: span 1; } }
    </style>
</head>
<body>

<main>
    <div class="page-header">
        <div>
            <h1>Serviços e Procedimentos</h1>
            <p>Cadastre o que a sua empresa oferece (Barba, Cabelo, Manutenção, etc.)</p>
        </div>
        <button class="btn-primary" onclick="abrirModal()">
            <i class="bi bi-plus-lg"></i> Novo Serviço
        </button>
    </div>

    <?php if($msgSucesso): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $msgSucesso; ?></div>
    <?php endif; ?>
    <?php if($msgErro): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-triangle"></i> <?php echo $msgErro; ?></div>
    <?php endif; ?>

    <div class="card">
        <form class="filter-bar">
            <div style="flex:1; display:flex; gap:10px;">
                <input type="text" name="busca" class="search-input" placeholder="Buscar serviço..." value="<?php echo htmlspecialchars($busca); ?>">
                <button type="submit" class="btn-primary" style="padding: 10px 16px;">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th width="70">Img</th>
                        <th>Nome / Descrição</th>
                        <th>Categoria</th>
                        <th>Preço / Duração</th>
                        <th>Visibilidade</th>
                        <th>Status</th>
                        <th style="text-align:right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($servicos)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">
                                Nenhum serviço encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($servicos as $s): 
                            $imgUrl = !empty($s['imagem']) ? $uploadDir.$s['imagem'] : 'https://via.placeholder.com/100x100?text=Serviço';
                        ?>
                        <tr>
                            <td><img src="<?php echo $imgUrl; ?>" class="thumb"></td>
                            <td>
                                <div style="font-weight:600; color:var(--text-main);"><?php echo htmlspecialchars($s['nome']); ?></div>
                                <div style="font-size:0.8rem; color:var(--text-muted); max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?php echo htmlspecialchars($s['descricao']); ?>
                                </div>
                            </td>
                            <td>
                                <span style="background:#f1f5f9; padding:4px 8px; border-radius:6px; font-size:0.8rem; font-weight:500;">
                                    <?php echo htmlspecialchars($s['categoria'] ?: 'Geral'); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:600;">R$ <?php echo number_format($s['preco'], 2, ',', '.'); ?></div>
                                <small style="color:var(--text-muted);"><?php echo $s['duracao_minutos'] ? $s['duracao_minutos'].' min' : '-'; ?></small>
                            </td>
                            <td>
                                <?php if($s['mostrar_site']): ?>
                                    <span class="badge badge-site"><i class="bi bi-globe"></i> No Site</span>
                                <?php else: ?>
                                    <span class="badge badge-no-site"><i class="bi bi-eye-slash"></i> Interno</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($s['ativo']): ?>
                                    <span class="badge badge-active">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <button class="btn-icon" onclick='editar(<?php echo json_encode($s); ?>)' title="Editar"><i class="bi bi-pencil"></i></button>
                                <button class="btn-icon del" onclick="excluir(<?php echo $s['id']; ?>)" title="Excluir"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalServico">
    <form class="modal-box" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="acao" value="salvar_servico">
        <input type="hidden" name="id_servico" id="inpId">

        <div class="modal-header">
            <h3 id="modalTitle">Novo Serviço</h3>
            <button type="button" class="btn-icon" onclick="fecharModal()"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="modal-body">
            <div class="form-row">
                <div class="col-full">
                    <label>Nome do Serviço *</label>
                    <input type="text" name="nome" id="inpNome" class="form-control" required placeholder="Ex: Corte Degradê, Manutenção PC...">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Categoria</label>
                    <input type="text" name="categoria" id="inpCategoria" class="form-control" placeholder="Ex: Cabelo, Informática...">
                </div>
                <div>
                    <label>Preço (R$)</label>
                    <input type="number" step="0.01" name="preco" id="inpPreco" class="form-control" placeholder="0,00">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Duração (minutos)</label>
                    <input type="number" name="duracao_minutos" id="inpDuracao" class="form-control" placeholder="Ex: 30">
                </div>
            </div>

            <div class="form-row">
                <div class="col-full">
                    <label>Descrição Detalhada</label>
                    <textarea name="descricao" id="inpDescricao" class="form-control" placeholder="Descreva o que está incluso no serviço..."></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="col-full">
                    <label>Imagem do Serviço (Capa)</label>
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="bi bi-cloud-arrow-up" style="font-size:1.5rem; color:var(--primary);"></i>
                        <p style="margin:5px 0; font-size:0.85rem; color:var(--text-muted);">Clique para enviar uma foto (JPG, PNG)</p>
                        <input type="file" name="imagem" id="fileInput" accept="image/*" style="display:none" onchange="previewImage(this)">
                        <img id="imgPreview" class="preview-img">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-full" style="display:flex; gap:20px;">
                    <label class="switch-label">
                        <input type="checkbox" name="ativo" id="chkAtivo" checked>
                        Disponível / Ativo
                    </label>
                    <label class="switch-label" style="background:#f0fdf4; border-color:#86efac;">
                        <input type="checkbox" name="mostrar_site" id="chkSite">
                        <i class="bi bi-globe"></i> Mostrar no Site
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-icon" style="width:auto; padding:0 15px; border:1px solid var(--border);" onclick="fecharModal()">Cancelar</button>
            <button type="submit" class="btn-primary">Salvar Serviço</button>
        </div>
    </form>
</div>

<script>
    const modal = document.getElementById('modalServico');
    const uploadPath = '<?php echo $uploadDir; ?>';

    function abrirModal() {
        document.querySelector('form').reset();
        document.getElementById('inpId').value = '';
        document.getElementById('modalTitle').innerText = 'Novo Serviço';
        document.getElementById('imgPreview').style.display = 'none';
        document.getElementById('chkAtivo').checked = true;
        document.getElementById('chkSite').checked = false;
        
        modal.classList.add('open');
    }

    function fecharModal() {
        modal.classList.remove('open');
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('imgPreview');
                img.src = e.target.result;
                img.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function editar(serv) {
        document.getElementById('inpId').value = serv.id;
        document.getElementById('inpNome').value = serv.nome;
        document.getElementById('inpCategoria').value = serv.categoria || '';
        document.getElementById('inpPreco').value = serv.preco || '';
        document.getElementById('inpDuracao').value = serv.duracao_minutos || '';
        document.getElementById('inpDescricao').value = serv.descricao || '';
        
        document.getElementById('chkAtivo').checked = (serv.ativo == 1);
        document.getElementById('chkSite').checked = (serv.mostrar_site == 1);

        const img = document.getElementById('imgPreview');
        if(serv.imagem) {
            img.src = uploadPath + serv.imagem;
            img.style.display = 'block';
        } else {
            img.style.display = 'none';
        }

        document.getElementById('modalTitle').innerText = 'Editar Serviço';
        modal.classList.add('open');
    }

    function excluir(id) {
        if(confirm('Tem certeza que deseja excluir este serviço?')) {
            window.location.href = '?excluir=' + id;
        }
    }

    // Fechar ao clicar fora
    modal.addEventListener('click', (e) => {
        if(e.target === modal) fecharModal();
    });
</script>

</body>
</html>