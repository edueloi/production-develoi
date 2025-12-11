<?php
// ==========================================
// CLIENTES - LÓGICA PHP (BACKEND)
// ==========================================
require_once '../../includes/banco-dados/db.php';
$owner_id = $_SESSION['user_id'];
include_once '../../includes/menu.php';

// 1. GARANTIR TABELAS
$pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    apelido TEXT,
    telefone TEXT,
    whatsapp TEXT,
    email TEXT,
    cpf TEXT,
    rg TEXT,
    data_nascimento DATE,
    cep TEXT,
    logradouro TEXT,
    numero TEXT,
    complemento TEXT,
    bairro TEXT,
    cidade TEXT,
    uf TEXT,
    observacoes TEXT,
    ativo INTEGER DEFAULT 1,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS clientes_compras (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cliente_id INTEGER NOT NULL,
    data_compra DATE NOT NULL,
    descricao TEXT,
    valor_total REAL DEFAULT 0,
    canal TEXT,
    FOREIGN KEY(cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
)");

// 2. API JSON (AJAX)
if (isset($_GET['api_acao'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Detalhes + Compras
    if ($_GET['api_acao'] === 'get_cliente' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("SELECT c.*, 
            (SELECT COUNT(*) FROM clientes_compras cc WHERE cc.cliente_id = c.id) AS qtd_compras,
            (SELECT COALESCE(SUM(valor_total), 0) FROM clientes_compras cc WHERE cc.cliente_id = c.id) AS total_gasto,
            (SELECT MAX(data_compra) FROM clientes_compras cc WHERE cc.cliente_id = c.id) AS ultima_compra
            FROM clientes c WHERE c.id = ? AND c.owner_id = ?");
        $stmt->execute([$id, $owner_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmtC = $pdo->prepare("SELECT * FROM clientes_compras WHERE cliente_id = ? ORDER BY data_compra DESC LIMIT 50");
        $stmtC->execute([$id]);
        $compras = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['cliente' => $cliente, 'compras' => $compras]);
        exit;
    }

    // Excluir
    if ($_GET['api_acao'] === 'del_cliente' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $pdo->prepare("DELETE FROM clientes WHERE id = ? AND owner_id = ?")->execute([$id, $owner_id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// 3. SALVAR (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $dados = [
        $_POST['nome'], $_POST['apelido'], $_POST['telefone'], $_POST['whatsapp'], $_POST['email'],
        $_POST['cpf'], $_POST['rg'], $_POST['data_nascimento'] ?: null, 
        $_POST['cep'], $_POST['logradouro'], $_POST['numero'], $_POST['complemento'],
        $_POST['bairro'], $_POST['cidade'], $_POST['uf'], $_POST['observacoes'],
        isset($_POST['ativo']) ? 1 : 0
    ];

    if ($id) {
        $sql = "UPDATE clientes SET nome=?, apelido=?, telefone=?, whatsapp=?, email=?, cpf=?, rg=?, data_nascimento=?, cep=?, logradouro=?, numero=?, complemento=?, bairro=?, cidade=?, uf=?, observacoes=?, ativo=? WHERE id=? AND owner_id=?";
        $dados[] = $id;
        $dados[] = $owner_id;
        $pdo->prepare($sql)->execute($dados);
    } else {
        $sql = "INSERT INTO clientes (nome, apelido, telefone, whatsapp, email, cpf, rg, data_nascimento, cep, logradouro, numero, complemento, bairro, cidade, uf, observacoes, ativo, owner_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $dados[] = $owner_id;
        $pdo->prepare($sql)->execute($dados);
    }
    // Refresh simples para limpar POST
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// 4. LISTAGEM DASHBOARD
$sql = "SELECT c.*, (SELECT MAX(data_compra) FROM clientes_compras WHERE cliente_id = c.id) as ultima_compra FROM clientes c WHERE c.owner_id = :owner_id ORDER BY c.nome ASC";
$stmtList = $pdo->prepare($sql);
$stmtList->execute([':owner_id' => $owner_id]);
$clientes = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Métricas
$total = count($clientes);
$ativos = 0; $inativos = 0; $aniversariantes = 0;
$mesAtual = date('m');

foreach($clientes as $c) {
    if($c['ativo']) $ativos++; else $inativos++;
    if($c['data_nascimento'] && date('m', strtotime($c['data_nascimento'])) == $mesAtual) $aniversariantes++;
}

// Helper para Iniciais do Avatar
function getInitials($nome) {
    $words = explode(' ', trim($nome));
    $in = mb_substr($words[0], 0, 1);
    if(count($words) > 1) $in .= mb_substr(end($words), 0, 1);
    return strtoupper($in);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Clientes</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #e0e7ff;
            --surface: #ffffff;
            --bg-body: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            padding-bottom: 40px;
        }

        main { padding: 30px; max-width: 1400px; margin: 0 auto; }

        /* HEADER */
        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; flex-wrap: wrap; gap: 15px;
        }
        .page-header h1 { margin: 0; font-size: 1.75rem; font-weight: 700; }
        .page-header p { margin: 5px 0 0; color: var(--text-muted); }

        .btn-primary {
            background: var(--primary); color: white; border: none;
            padding: 10px 20px; border-radius: 99px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; gap: 8px;
            transition: 0.2s; box-shadow: 0 4px 12px rgba(99,102,241,0.25);
        }
        .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }

        /* KPI CARDS */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .kpi-card {
            background: var(--surface); padding: 20px; border-radius: var(--radius);
            box-shadow: var(--shadow); border: 1px solid var(--border);
            display: flex; align-items: center; gap: 15px;
        }
        .kpi-icon {
            width: 46px; height: 46px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .icon-blue { background: #dbeafe; color: #2563eb; }
        .icon-green { background: #dcfce7; color: #166534; }
        .icon-gray { background: #f1f5f9; color: #64748b; }
        .icon-pink { background: #fce7f3; color: #be185d; }

        .kpi-info h3 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .kpi-info span { font-size: 0.85rem; color: var(--text-muted); }

        /* CONTENT PANEL */
        .content-panel {
            background: var(--surface); border-radius: var(--radius);
            border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden;
        }

        .panel-header { padding: 20px; background: #f8fafc; border-bottom: 1px solid var(--border); }
        
        .filters {
            display: flex; gap: 12px; flex-wrap: wrap; margin-top: 15px;
        }
        .search-box {
            flex: 1; min-width: 250px; position: relative;
        }
        .search-box input {
            width: 100%; padding: 10px 12px 10px 38px; border-radius: 8px;
            border: 1px solid var(--border); outline: none;
        }
        .search-box i {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);
        }
        .filter-select {
            padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border);
            background: white; cursor: pointer;
        }

        /* TABLE */
        .table-responsive { width: 100%; overflow-x: auto; }
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table th {
            text-align: left; padding: 16px 20px; color: var(--text-muted);
            font-size: 0.75rem; text-transform: uppercase; font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        .styled-table td {
            padding: 16px 20px; border-bottom: 1px solid var(--border);
            color: var(--text-main); font-size: 0.9rem; vertical-align: middle;
        }
        .styled-table tr:hover { background: #f8fafc; }

        .client-info { display: flex; align-items: center; gap: 12px; }
        .avatar-circle {
            width: 40px; height: 40px; border-radius: 50%; background: var(--primary-light);
            color: var(--primary); font-weight: 700; display: flex;
            align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0;
        }
        
        .badge {
            padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
        }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.inactive { background: #f1f5f9; color: #64748b; }

        .btn-icon {
            background: none; border: none; cursor: pointer; color: var(--text-muted);
            width: 32px; height: 32px; border-radius: 6px; transition: 0.2s;
        }
        .btn-icon:hover { background: var(--bg-body); color: var(--primary); }
        .btn-icon.del:hover { background: #fee2e2; color: #dc2626; }

        /* RESPONSIVE CARD VIEW */
        @media (max-width: 768px) {
            .styled-table thead { display: none; }
            .styled-table, tbody, tr, td { display: block; width: 100%; }
            .styled-table tr {
                margin-bottom: 15px; border: 1px solid var(--border);
                border-radius: 12px; box-shadow: var(--shadow);
            }
            .styled-table td {
                display: flex; justify-content: space-between; align-items: center;
                padding: 12px 15px; text-align: right;
            }
            .styled-table td::before {
                content: attr(data-label); font-weight: 600; color: var(--text-muted);
                font-size: 0.8rem; text-transform: uppercase;
            }
            .client-info { flex-direction: row-reverse; }
        }

        /* MODAL */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center; z-index: 5000;
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s; }
        .modal-content {
            background: white; width: 95%; max-width: 700px; border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25); display: flex; flex-direction: column;
            max-height: 90vh;
        }
        .modal-header {
            padding: 20px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-body { padding: 24px; overflow-y: auto; }
        .modal-footer {
            padding: 16px 24px; border-top: 1px solid var(--border); background: #f8fafc;
            display: flex; justify-content: flex-end; gap: 10px; border-radius: 0 0 16px 16px;
        }

        .form-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;
        }
        .col-full { grid-column: span 2; }
        
        .form-label { display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px; }
        .form-input {
            width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;
            outline: none; font-family: inherit;
        }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<main>
    <div class="page-header">
        <div>
            <h1>Clientes</h1>
            <p>Gerencie sua base de contactos e histórico.</p>
        </div>
        <button class="btn-primary" onclick="abrirModal()">
            <i class="bi bi-plus-lg"></i> Novo Cliente
        </button>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon icon-blue"><i class="bi bi-people-fill"></i></div>
            <div class="kpi-info"><h3><?php echo $total; ?></h3><span>Total Clientes</span></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon icon-green"><i class="bi bi-person-check-fill"></i></div>
            <div class="kpi-info"><h3><?php echo $ativos; ?></h3><span>Ativos</span></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon icon-gray"><i class="bi bi-person-dash-fill"></i></div>
            <div class="kpi-info"><h3><?php echo $inativos; ?></h3><span>Inativos</span></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon icon-pink"><i class="bi bi-cake2-fill"></i></div>
            <div class="kpi-info"><h3><?php echo $aniversariantes; ?></h3><span>Aniv. Mês</span></div>
        </div>
    </div>

    <div class="content-panel">
        <div class="panel-header">
            <div style="font-weight:600; font-size:1.1rem; margin-bottom:10px;">Base de Dados</div>
            <div class="filters">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="inputBusca" placeholder="Buscar por nome, telefone ou CPF...">
                </div>
                <select id="filtroStatus" class="filter-select">
                    <option value="">Status: Todos</option>
                    <option value="ativo">Ativos</option>
                    <option value="inativo">Inativos</option>
                </select>
                <div style="margin-left:auto; align-self:center; font-size:0.85rem; color:var(--text-muted);">
                    <span id="contador"><?php echo $total; ?></span> registros
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="styled-table" id="tabelaClientes">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contatos</th>
                        <th>Documento</th>
                        <th>Última Compra</th>
                        <th>Status</th>
                        <th style="text-align:right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($clientes as $c): 
                        $status = $c['ativo'] ? 'ativo' : 'inativo';
                        $statusLabel = $c['ativo'] ? 'Ativo' : 'Inativo';
                        $doc = $c['cpf'] ?: ($c['rg'] ?: '-');
                        $lastBuy = $c['ultima_compra'] ? date('d/m/Y', strtotime($c['ultima_compra'])) : '-';
                        $searchData = strtolower($c['nome'] . ' ' . $c['telefone'] . ' ' . $c['cpf']);
                    ?>
                    <tr class="item-row" data-search="<?php echo $searchData; ?>" data-status="<?php echo $status; ?>">
                        <td data-label="Cliente">
                            <div class="client-info">
                                <div class="avatar-circle"><?php echo getInitials($c['nome']); ?></div>
                                <div>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($c['nome']); ?></div>
                                    <small style="color:var(--text-muted);"><?php echo htmlspecialchars($c['apelido'] ?? ''); ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Contatos">
                            <div style="display:flex; flex-direction:column; gap:2px; font-size:0.85rem;">
                                <?php if($c['whatsapp']): ?>
                                    <span style="color:var(--text-muted)"><i class="bi bi-whatsapp"></i> <?php echo $c['whatsapp']; ?></span>
                                <?php elseif($c['telefone']): ?>
                                    <span style="color:var(--text-muted)"><i class="bi bi-telephone"></i> <?php echo $c['telefone']; ?></span>
                                <?php endif; ?>
                                
                                <?php if($c['email']): ?>
                                    <span style="color:var(--text-muted)"><i class="bi bi-envelope"></i> <?php echo $c['email']; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Documento"><?php echo htmlspecialchars($doc); ?></td>
                        <td data-label="Última Compra"><?php echo $lastBuy; ?></td>
                        <td data-label="Status">
                            <span class="badge <?php echo $status; ?>"><?php echo $statusLabel; ?></span>
                        </td>
                        <td data-label="Ações" style="text-align:right;">
                            <button class="btn-icon" title="Ver Detalhes" onclick="verDetalhes(<?php echo $c['id']; ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn-icon" title="Editar" onclick="editar(<?php echo $c['id']; ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-icon del" title="Excluir" onclick="excluir(<?php echo $c['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="noResults" style="display:none; padding:40px; text-align:center; color:var(--text-muted);">
                <i class="bi bi-search" style="font-size:2rem; opacity:0.5; margin-bottom:10px; display:block;"></i>
                Nenhum cliente encontrado.
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalForm">
    <form class="modal-content" method="POST">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" id="inpId">
        
        <div class="modal-header">
            <h3 id="modalTitle" style="margin:0;">Novo Cliente</h3>
            <button type="button" class="btn-icon" onclick="fecharModal('modalForm')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="col-full">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="nome" id="inpNome" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Apelido</label>
                    <input type="text" name="apelido" id="inpApelido" class="form-input">
                </div>
                <div>
                    <label class="form-label">CPF / CNPJ</label>
                    <input type="text" name="cpf" id="inpCpf" class="form-input">
                </div>
                <div>
                    <label class="form-label">WhatsApp</label>
                    <input type="text" name="whatsapp" id="inpWhats" class="form-input">
                </div>
                <div>
                    <label class="form-label">Telefone Fixo</label>
                    <input type="text" name="telefone" id="inpTel" class="form-input">
                </div>
                <div class="col-full">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" id="inpEmail" class="form-input">
                </div>
                <div class="col-full" style="margin-top:10px; border-top:1px solid #f1f5f9; padding-top:10px;">
                    <strong style="font-size:0.9rem;">Endereço & Detalhes</strong>
                </div>
                <div>
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep" id="inpCep" class="form-input">
                </div>
                <div>
                    <label class="form-label">Cidade / UF</label>
                    <input type="text" name="cidade" id="inpCidade" class="form-input" placeholder="Ex: São Paulo - SP">
                </div>
                <div class="col-full">
                    <label class="form-label">Endereço Completo</label>
                    <input type="text" name="logradouro" id="inpLogradouro" class="form-input" placeholder="Rua, Número, Bairro...">
                </div>
                <div>
                    <label class="form-label">Data Nascimento</label>
                    <input type="date" name="data_nascimento" id="inpNasc" class="form-input">
                </div>
                <div style="display:flex; align-items:flex-end;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="ativo" id="inpAtivo" checked> Cliente Ativo
                    </label>
                </div>
                <div class="col-full">
                    <label class="form-label">Observações</label>
                    <textarea name="observacoes" id="inpObs" class="form-input" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-icon" style="width:auto; padding:0 15px; border:1px solid var(--border);" onclick="fecharModal('modalForm')">Cancelar</button>
            <button type="submit" class="btn-primary">Salvar Cliente</button>
        </div>
    </form>
</div>

<div class="modal-overlay" id="modalView">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0;">Ficha do Cliente</h3>
            <button type="button" class="btn-icon" onclick="fecharModal('modalView')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:flex; align-items:center; gap:15px; margin-bottom:20px;">
                <div class="avatar-circle" id="viewAvatar" style="width:64px; height:64px; font-size:1.5rem;"></div>
                <div>
                    <h2 id="viewNome" style="margin:0; font-size:1.3rem;"></h2>
                    <span id="viewDoc" style="color:var(--text-muted); font-size:0.9rem;"></span>
                    <div id="viewBadge" style="margin-top:5px;"></div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; background:#f8fafc; padding:15px; border-radius:12px; border:1px solid var(--border);">
                <div>
                    <small style="color:var(--text-muted); display:block;">Contato</small>
                    <div id="viewContact" style="font-size:0.95rem; font-weight:500;"></div>
                </div>
                <div>
                    <small style="color:var(--text-muted); display:block;">Localização</small>
                    <div id="viewAddress" style="font-size:0.95rem; font-weight:500;"></div>
                </div>
                <div>
                    <small style="color:var(--text-muted); display:block;">Financeiro</small>
                    <div id="viewFinance" style="font-size:0.95rem; font-weight:500;"></div>
                </div>
            </div>

            <h4 style="margin:20px 0 10px;">Histórico de Compras</h4>
            <div class="table-responsive">
                <table class="styled-table" style="font-size:0.85rem;">
                    <thead><tr><th>Data</th><th>Descrição</th><th style="text-align:right">Valor</th></tr></thead>
                    <tbody id="viewHistory"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // --- FILTROS DE PESQUISA ---
    const inputBusca = document.getElementById('inputBusca');
    const filtroStatus = document.getElementById('filtroStatus');
    const rows = document.querySelectorAll('.item-row');
    const contador = document.getElementById('contador');
    const noResults = document.getElementById('noResults');

    function filtrar() {
        const term = inputBusca.value.toLowerCase();
        const st = filtroStatus.value;
        let visiveis = 0;

        rows.forEach(row => {
            const dataSearch = row.dataset.search;
            const dataStatus = row.dataset.status;
            let show = true;

            if(term && !dataSearch.includes(term)) show = false;
            if(st && dataStatus !== st) show = false;

            row.style.display = show ? '' : 'none';
            if(show) visiveis++;
        });
        
        contador.innerText = visiveis;
        noResults.style.display = visiveis === 0 ? 'block' : 'none';
        document.getElementById('tabelaClientes').style.display = visiveis === 0 ? 'none' : 'table';
    }

    inputBusca.addEventListener('input', filtrar);
    filtroStatus.addEventListener('change', filtrar);

    // --- MODAIS ---
    function abrirModal() {
        document.querySelector('form').reset();
        document.getElementById('inpId').value = '';
        document.getElementById('modalTitle').innerText = 'Novo Cliente';
        document.getElementById('modalForm').classList.add('active');
    }

    function fecharModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    // --- EDITAR (AJAX) ---
    async function editar(id) {
        const res = await fetch(`?api_acao=get_cliente&id=${id}`);
        const data = await res.json();
        const c = data.cliente;

        document.getElementById('inpId').value = c.id;
        document.getElementById('inpNome').value = c.nome;
        document.getElementById('inpApelido').value = c.apelido || '';
        document.getElementById('inpCpf').value = c.cpf || '';
        document.getElementById('inpWhats').value = c.whatsapp || '';
        document.getElementById('inpTel').value = c.telefone || '';
        document.getElementById('inpEmail').value = c.email || '';
        document.getElementById('inpCep').value = c.cep || '';
        document.getElementById('inpLogradouro').value = c.logradouro || '';
        document.getElementById('inpCidade').value = (c.cidade || '') + (c.uf ? ' - '+c.uf : '');
        document.getElementById('inpNasc').value = c.data_nascimento || '';
        document.getElementById('inpObs').value = c.observacoes || '';
        document.getElementById('inpAtivo').checked = (c.ativo == 1);

        document.getElementById('modalTitle').innerText = 'Editar Cliente';
        document.getElementById('modalForm').classList.add('active');
    }

    // --- DETALHES (AJAX) ---
    async function verDetalhes(id) {
        const res = await fetch(`?api_acao=get_cliente&id=${id}`);
        const data = await res.json();
        const c = data.cliente;

        // Header
        document.getElementById('viewNome').innerText = c.nome;
        document.getElementById('viewDoc').innerText = c.cpf || 'Sem documento';
        document.getElementById('viewBadge').innerHTML = c.ativo == 1 
            ? '<span class="badge active">Cliente Ativo</span>' 
            : '<span class="badge inactive">Inativo</span>';
        
        // Avatar
        const initials = c.nome.split(' ').map((n,i,a)=> i==0 || i==a.length-1 ? n[0] : '').join('').toUpperCase();
        document.getElementById('viewAvatar').innerText = initials;

        // Info Blocks
        let contact = '';
        if(c.whatsapp) contact += `<div><i class="bi bi-whatsapp"></i> ${c.whatsapp}</div>`;
        if(c.email) contact += `<div><i class="bi bi-envelope"></i> ${c.email}</div>`;
        if(!contact) contact = '<span style="color:#999">Sem contato</span>';
        document.getElementById('viewContact').innerHTML = contact;

        let addr = '';
        if(c.cidade) addr += `<div>${c.cidade}/${c.uf}</div>`;
        if(c.logradouro) addr += `<div style="font-size:0.85rem; color:#666">${c.logradouro}</div>`;
        if(!addr) addr = '<span style="color:#999">Endereço não informado</span>';
        document.getElementById('viewAddress').innerHTML = addr;

        let fin = `<div>Total Gasto: <strong>R$ ${parseFloat(c.total_gasto||0).toFixed(2)}</strong></div>`;
        fin += `<div>Compras: ${c.qtd_compras}</div>`;
        document.getElementById('viewFinance').innerHTML = fin;

        // Tabela Histórico
        const tbody = document.getElementById('viewHistory');
        tbody.innerHTML = '';
        if(data.compras.length > 0) {
            data.compras.forEach(buy => {
                tbody.innerHTML += `
                    <tr>
                        <td>${new Date(buy.data_compra).toLocaleDateString('pt-BR')}</td>
                        <td>${buy.descricao || 'Compra'}</td>
                        <td style="text-align:right">R$ ${parseFloat(buy.valor_total).toFixed(2)}</td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#999;">Nenhuma compra registrada.</td></tr>';
        }

        document.getElementById('modalView').classList.add('active');
    }

    // --- EXCLUIR ---
    async function excluir(id) {
        if(confirm('Tem certeza? Isso apagará o histórico deste cliente também.')) {
            await fetch(`?api_acao=del_cliente&id=${id}`);
            location.reload();
        }
    }

    // Fechar ao clicar fora
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if(e.target === overlay) overlay.classList.remove('active');
        });
    });
</script>

</body>
</html>