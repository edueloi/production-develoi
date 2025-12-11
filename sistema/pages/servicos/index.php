<?php
// ==========================================
// 1. CONEXÃO E TABELA
// ==========================================
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';

// Cria tabela de serviços (se ainda não existir)
$pdo->exec("CREATE TABLE IF NOT EXISTS servicos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    categoria TEXT,
    descricao TEXT,
    duracao_minutos INTEGER,
    preco REAL,
    ativo INTEGER DEFAULT 1,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$msgSucesso = null;
$msgErro = null;

// ==========================================
// 2. PROCESSAR FORM (CRIAR / EDITAR / EXCLUIR)
// ==========================================

// Salvar (insert/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_servico') {
    try {
        $id        = !empty($_POST['id_servico']) ? (int)$_POST['id_servico'] : null;
        $nome      = trim($_POST['nome'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $duracao   = isset($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : null;
        $preco     = isset($_POST['preco']) ? (float)str_replace(',', '.', $_POST['preco']) : 0;
        $ativo     = isset($_POST['ativo']) ? 1 : 0;

        if ($nome === '') {
            throw new Exception('O nome do serviço é obrigatório.');
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE servicos 
                SET nome = ?, categoria = ?, descricao = ?, duracao_minutos = ?, preco = ?, ativo = ? 
                WHERE id = ?");
            $stmt->execute([$nome, $categoria, $descricao, $duracao, $preco, $ativo, $id]);
            $msgSucesso = "Serviço atualizado com sucesso!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO servicos (nome, categoria, descricao, duracao_minutos, preco, ativo)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $categoria, $descricao, $duracao, $preco, $ativo]);
            $msgSucesso = "Serviço cadastrado com sucesso!";
        }
    } catch (Exception $e) {
        $msgErro = "Erro ao salvar serviço: " . $e->getMessage();
    }
}

// Excluir
if (isset($_GET['excluir']) && $_GET['excluir'] !== '') {
    try {
        $idDel = (int)$_GET['excluir'];
        $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ?");
        $stmt->execute([$idDel]);
        $msgSucesso = "Serviço excluído com sucesso!";
    } catch (Exception $e) {
        $msgErro = "Erro ao excluir serviço: " . $e->getMessage();
    }
}

// ==========================================
// 3. BUSCAR LISTA DE SERVIÇOS
// ==========================================
$busca = trim($_GET['busca'] ?? '');
$params = [];
$sql = "SELECT * FROM servicos";

if ($busca !== '') {
    $sql .= " WHERE nome LIKE :busca OR categoria LIKE :busca";
    $params[':busca'] = "%{$busca}%";
}

$sql .= " ORDER BY ativo DESC, nome ASC";

$stmtLista = $pdo->prepare($sql);
$stmtLista->execute($params);
$listaServicos = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Serviços</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        :root {
            --primary-color: #6366f1;
        }

        body {
            background: #0f172a;
        }

        main.servicos-main {
            padding: 24px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:20px;
            color:#e5e7eb;
        }

        .page-header h1 {
            margin:0;
            font-size:1.5rem;
        }

        .page-header p {
            margin:4px 0 0;
            color:#9ca3af;
        }

        .btn-primary {
            background:linear-gradient(135deg,#4f46e5,#7c3aed);
            color:#f9fafb;
            border:none;
            padding:10px 20px;
            border-radius:999px;
            font-weight:600;
            cursor:pointer;
            display:flex;
            align-items:center;
            gap:8px;
            box-shadow:0 10px 25px rgba(79,70,229,0.4);
        }

        .btn-primary:active {
            transform:translateY(1px);
            box-shadow:0 4px 15px rgba(79,70,229,0.4);
        }

        .msg-sucesso, .msg-erro {
            border-radius:12px;
            padding:10px 12px;
            font-size:0.9rem;
            margin-bottom:12px;
        }

        .msg-sucesso {
            background:rgba(22,163,74,0.15);
            border:1px solid rgba(22,163,74,0.5);
            color:#bbf7d0;
        }

        .msg-erro {
            background:rgba(239,68,68,0.15);
            border:1px solid rgba(239,68,68,0.6);
            color:#fecaca;
        }

        .card-panel {
            background:#020617;
            border-radius:16px;
            border:1px solid rgba(148,163,184,0.35);
            padding:16px;
            color:#e5e7eb;
        }

        .filter-bar {
            display:flex;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:12px;
        }

        .filter-bar input {
            flex:1;
            min-width:200px;
            padding:8px 10px;
            border-radius:999px;
            border:1px solid #1f2937;
            background:#020617;
            color:#e5e7eb;
            font-size:0.9rem;
        }

        .filter-bar button {
            border-radius:999px;
            padding:8px 14px;
            border:none;
            background:#111827;
            color:#e5e7eb;
            font-size:0.85rem;
            cursor:pointer;
        }

        table.servicos-table {
            width:100%;
            border-collapse:collapse;
            margin-top:8px;
        }

        table.servicos-table th,
        table.servicos-table td {
            padding:10px 8px;
            font-size:0.85rem;
            border-bottom:1px solid #111827;
        }

        table.servicos-table th {
            text-align:left;
            color:#9ca3af;
            font-weight:500;
            text-transform:uppercase;
            font-size:0.75rem;
        }

        table.servicos-table tr:last-child td {
            border-bottom:none;
        }

        .badge {
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            padding:3px 8px;
            font-size:0.75rem;
            font-weight:500;
        }

        .badge-ativo {
            background:rgba(22,163,74,0.2);
            color:#bbf7d0;
        }

        .badge-inativo {
            background:rgba(239,68,68,0.2);
            color:#fecaca;
        }

        .actions {
            display:flex;
            justify-content:flex-end;
            gap:6px;
        }

        .btn-sm {
            border:none;
            border-radius:999px;
            padding:5px 10px;
            font-size:0.75rem;
            cursor:pointer;
        }

        .btn-edit {
            background:rgba(59,130,246,0.15);
            color:#bfdbfe;
        }

        .btn-delete {
            background:rgba(239,68,68,0.15);
            color:#fecaca;
        }

        /* Modal */
        .modal-overlay {
            position:fixed;
            top:0; left:0;
            width:100%; height:100%;
            background:rgba(15,23,42,0.75);
            backdrop-filter:blur(4px);
            display:none;
            align-items:center;
            justify-content:center;
            z-index:2000;
        }

        .modal-overlay.open {
            display:flex;
        }

        .modal-box {
            background:#020617;
            border-radius:16px;
            border:1px solid rgba(148,163,184,0.4);
            width:95%;
            max-width:560px;
            color:#e5e7eb;
            max-height:90vh;
            display:flex;
            flex-direction:column;
        }

        .modal-header {
            padding:14px 18px;
            border-bottom:1px solid #111827;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .modal-header h3 {
            margin:0;
            font-size:1rem;
        }

        .modal-body {
            padding:16px 18px;
            overflow-y:auto;
        }

        .modal-footer {
            padding:12px 18px;
            border-top:1px solid #111827;
            display:flex;
            justify-content:flex-end;
            gap:10px;
            background:#020617;
            border-radius:0 0 16px 16px;
        }

        .form-grid {
            display:grid;
            grid-template-columns:repeat(2, 1fr);
            gap:12px;
        }

        .full-width {
            grid-column:span 2;
        }

        .input-group label {
            display:block;
            font-size:0.8rem;
            margin-bottom:4px;
            color:#9ca3af;
        }

        .input-control,
        .input-control-textarea {
            width:100%;
            padding:8px 9px;
            border-radius:10px;
            border:1px solid #1f2937;
            background:#020617;
            color:#e5e7eb;
            font-size:0.9rem;
        }

        .input-control-textarea {
            resize:vertical;
            min-height:70px;
        }

        .input-control:focus,
        .input-control-textarea:focus {
            outline:none;
            border-color:var(--primary-color);
            box-shadow:0 0 0 1px rgba(99,102,241,0.4);
        }

        .checkbox-inline {
            display:flex;
            align-items:center;
            gap:6px;
            margin-top:4px;
            font-size:0.8rem;
            color:#9ca3af;
        }

        .btn-secondary {
            padding:8px 14px;
            border-radius:999px;
            border:1px solid #374151;
            background:#020617;
            color:#e5e7eb;
            font-size:0.85rem;
            cursor:pointer;
        }

        @media (max-width: 720px) {
            main.servicos-main {
                padding:16px;
            }
            .form-grid {
                grid-template-columns:1fr;
            }
            .full-width {
                grid-column:span 1;
            }
        }
    </style>
</head>
<body>
<main class="servicos-main">
    <div class="page-header">
        <div>
            <h1>Meus Serviços</h1>
            <p>Cadastre, edite e controle os serviços oferecidos na sua loja/consultório.</p>
        </div>
        <button class="btn-primary" type="button" onclick="abrirModalNovo()">
            <span>+</span> Novo Serviço
        </button>
    </div>

    <?php if ($msgSucesso): ?>
        <div class="msg-sucesso"><?php echo htmlspecialchars($msgSucesso); ?></div>
    <?php endif; ?>

    <?php if ($msgErro): ?>
        <div class="msg-erro"><?php echo htmlspecialchars($msgErro); ?></div>
    <?php endif; ?>

    <div class="card-panel">
        <form method="GET" class="filter-bar">
            <input type="text" name="busca" placeholder="Buscar serviço por nome ou categoria..." value="<?php echo htmlspecialchars($busca); ?>">
            <button type="submit">Filtrar</button>
        </form>

        <table class="servicos-table">
            <thead>
            <tr>
                <th>Serviço</th>
                <th>Categoria</th>
                <th>Duração</th>
                <th>Preço</th>
                <th>Status</th>
                <th style="text-align:right;">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($listaServicos)): ?>
                <tr>
                    <td colspan="6" style="text-align:center; color:#6b7280; padding:20px 0;">
                        Nenhum serviço cadastrado ainda.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($listaServicos as $serv): ?>
                    <?php
                        $duracaoStr = $serv['duracao_minutos'] ? $serv['duracao_minutos'] . ' min' : '-';
                        $precoStr   = $serv['preco'] !== null
                            ? 'R$ ' . number_format($serv['preco'], 2, ',', '.')
                            : '-';
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600; font-size:0.9rem;">
                                <?php echo htmlspecialchars($serv['nome']); ?>
                            </div>
                            <?php if (!empty($serv['descricao'])): ?>
                                <div style="font-size:0.75rem; color:#9ca3af; max-width:320px;">
                                    <?php echo htmlspecialchars($serv['descricao']); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem; color:#e5e7eb;">
                            <?php echo $serv['categoria'] ? htmlspecialchars($serv['categoria']) : '-'; ?>
                        </td>
                        <td style="font-size:0.8rem; color:#e5e7eb;">
                            <?php echo $duracaoStr; ?>
                        </td>
                        <td style="font-size:0.8rem; color:#e5e7eb;">
                            <?php echo $precoStr; ?>
                        </td>
                        <td>
                            <?php if ($serv['ativo']): ?>
                                <span class="badge badge-ativo">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-inativo">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <button type="button"
                                    class="btn-sm btn-edit"
                                    onclick='abrirModalEditar(<?php echo json_encode($serv, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                    Editar
                                </button>
                                <button type="button"
                                    class="btn-sm btn-delete"
                                    onclick="confirmarExclusao(<?php echo (int)$serv['id']; ?>)">
                                    Excluir
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- MODAL SERVIÇO -->
<div class="modal-overlay" id="modalServico">
    <form class="modal-box" method="POST">
        <input type="hidden" name="acao" value="salvar_servico">
        <input type="hidden" name="id_servico" id="id_servico">

        <div class="modal-header">
            <h3 id="modalTitulo">Novo Serviço</h3>
            <button type="button" onclick="fecharModal()" style="background:none; border:none; color:#9ca3af; font-size:1.4rem; cursor:pointer;">&times;</button>
        </div>

        <div class="modal-body">
            <div class="form-grid">
                <div class="input-group full-width">
                    <label>Nome do Serviço *</label>
                    <input type="text" name="nome" id="nome" class="input-control" required placeholder="Ex: Corte de Cabelo, Sessão de Terapia, Manicure...">
                </div>

                <div class="input-group">
                    <label>Categoria</label>
                    <input type="text" name="categoria" id="categoria" class="input-control" placeholder="Ex: Cabelo, Estética, Terapia, Consulta...">
                </div>

                <div class="input-group">
                    <label>Duração (minutos)</label>
                    <input type="number" name="duracao_minutos" id="duracao_minutos" class="input-control" placeholder="Ex: 30, 50, 60">
                </div>

                <div class="input-group">
                    <label>Preço (R$)</label>
                    <input type="number" step="0.01" name="preco" id="preco" class="input-control" placeholder="Ex: 80, 150">
                </div>

                <div class="input-group full-width">
                    <label>Descrição</label>
                    <textarea name="descricao" id="descricao" class="input-control-textarea" placeholder="Detalhe o serviço, o que está incluso, orientações, etc."></textarea>
                </div>

                <div class="input-group full-width">
                    <label>Status</label>
                    <div class="checkbox-inline">
                        <input type="checkbox" name="ativo" id="ativo" checked>
                        <span>Serviço ativo e disponível no sistema</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="fecharModal()">Cancelar</button>
            <button type="submit" class="btn-primary">Salvar</button>
        </div>
    </form>
</div>

<script>
    const modal = document.getElementById('modalServico');
    const idInput = document.getElementById('id_servico');
    const tituloModal = document.getElementById('modalTitulo');

    function limparForm() {
        idInput.value = '';
        document.getElementById('nome').value = '';
        document.getElementById('categoria').value = '';
        document.getElementById('duracao_minutos').value = '';
        document.getElementById('preco').value = '';
        document.getElementById('descricao').value = '';
        document.getElementById('ativo').checked = true;
    }

    function abrirModalNovo() {
        limparForm();
        tituloModal.innerText = 'Novo Serviço';
        modal.classList.add('open');
    }

    function abrirModalEditar(servico) {
        limparForm();
        tituloModal.innerText = 'Editar Serviço #' + servico.id;

        idInput.value = servico.id;
        document.getElementById('nome').value = servico.nome || '';
        document.getElementById('categoria').value = servico.categoria || '';
        document.getElementById('duracao_minutos').value = servico.duracao_minutos || '';
        document.getElementById('preco').value = servico.preco || '';
        document.getElementById('descricao').value = servico.descricao || '';
        document.getElementById('ativo').checked = (servico.ativo == 1);

        modal.classList.add('open');
    }

    function fecharModal() {
        modal.classList.remove('open');
    }

    function confirmarExclusao(id) {
        if (confirm('Deseja realmente excluir este serviço?')) {
            window.location.href = '?excluir=' + id;
        }
    }
</script>
</body>
</html>
