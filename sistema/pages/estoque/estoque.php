<?php
// ==========================================
// ESTOQUE - LÓGICA PHP (BACKEND)
// ==========================================
require_once '../../includes/banco-dados/db.php';
$owner_id = $_SESSION['user_id'];
include_once '../../includes/menu.php'; // Menu lateral

$uploadDir = '../../assets/uploads/produtos/';

// ---------------- API: DETALHES DO PRODUTO (AJAX) ----------------
if (isset($_GET['api_acao']) && $_GET['api_acao'] === 'detalhes' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_GET['id'];

    // Produto
    $stmt = $pdo->prepare("SELECT *, (SELECT SUM(estoque) FROM produtos_variacoes WHERE produto_id = produtos.id AND owner_id = ?) AS estoque_total FROM produtos WHERE id = ? AND owner_id = ?");
    $stmt->execute([$owner_id, $id, $owner_id]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    // Variações
    $stmtVar = $pdo->prepare("SELECT * FROM produtos_variacoes WHERE produto_id = ? AND owner_id = ?");
    $stmtVar->execute([$id, $owner_id]);
    $variacoes = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['produto' => $produto, 'variacoes' => $variacoes]);
    exit;
}

// ---------------- DADOS GERAIS ----------------
$sql = "SELECT p.*, (SELECT SUM(estoque) FROM produtos_variacoes WHERE produto_id = p.id AND owner_id = :owner_id) AS estoque_total FROM produtos p WHERE p.owner_id = :owner_id ORDER BY p.nome ASC";
$stmtList = $pdo->prepare($sql);
$stmtList->execute([':owner_id' => $owner_id]);
$produtos = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Cálculos
$totalItens = 0;
$totalProdutos = count($produtos);
$abaixoMinimo = 0;
$inativos = 0;
$porCategoria = [];
$statusEstoque = ['OK' => 0, 'Baixo' => 0, 'Zerado' => 0];

foreach ($produtos as $p) {
    $estoque = (int)($p['estoque_total'] ?? 0);
    $minimo  = (int)$p['estoque_minimo'];
    $totalItens += $estoque;

    if ((int)$p['ativo'] === 0) $inativos++;

    // Categoria
    $cat = $p['tipo_produto'] ?: 'Geral';
    if (!isset($porCategoria[$cat])) $porCategoria[$cat] = 0;
    $porCategoria[$cat] += $estoque;

    // Status
    if ($estoque <= 0) $statusEstoque['Zerado']++;
    elseif ($estoque <= $minimo) { $statusEstoque['Baixo']++; $abaixoMinimo++; }
    else $statusEstoque['OK']++;
}

// Dados para JS
$catLabels = array_keys($porCategoria);
$catValues = array_values($porCategoria);
$statusLabels = array_keys($statusEstoque);
$statusValues = array_values($statusEstoque);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Estoque</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            /* Padding ajustado pelo menu.php, mas garantimos aqui */
            padding-bottom: 40px; 
        }

        main {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* HEADER */
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
        }
        .page-header p {
            color: var(--text-muted);
            margin-top: 5px;
        }

        /* KPI CARDS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: var(--surface);
            padding: 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-3px); }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .icon-blue { background: #dbeafe; color: #2563eb; }
        .icon-purple { background: #f3e8ff; color: #9333ea; }
        .icon-orange { background: #ffedd5; color: #ea580c; }
        .icon-red { background: #fee2e2; color: #dc2626; }

        .kpi-info h3 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .kpi-info span { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }

        /* CHARTS SECTION */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-box {
            background: var(--surface);
            padding: 24px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .chart-box h2 { font-size: 1.1rem; margin: 0 0 20px; color: var(--text-main); }

        /* LISTA & FILTROS */
        .content-panel {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background: #f8fafc;
        }

        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-top: 15px;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        .search-input input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: 0.2s;
        }
        .search-input i {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted);
        }
        .search-input input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }

        .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text-main);
            outline: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        /* TABELA MODERNA */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .styled-table {
            width: 100%;
            border-collapse: collapse;
        }
        .styled-table th {
            text-align: left;
            padding: 16px 20px;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        .styled-table td {
            padding: 16px 20px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            font-size: 0.9rem;
        }
        .styled-table tr:last-child td { border-bottom: none; }
        .styled-table tr:hover { background-color: #f8fafc; }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .product-thumb {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
        }

        /* BADGES */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-badge.ok { background: #dcfce7; color: #166534; }
        .status-badge.warning { background: #ffedd5; color: #9a3412; }
        .status-badge.danger { background: #fee2e2; color: #991b1b; }
        .status-badge.neutral { background: #f1f5f9; color: #475569; }

        /* BUTTONS */
        .btn-action {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: var(--text-muted);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-action:hover { background: var(--primary-light); color: var(--primary); }

        /* --- RESPONSIVIDADE AVANÇADA (CARD VIEW) --- */
        @media (max-width: 900px) {
            .charts-section { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            main { padding: 15px; }
            .panel-header h2 { font-size: 1.1rem; }
            
            /* Tabela vira Cards */
            .styled-table thead { display: none; }
            .styled-table, .styled-table tbody, .styled-table tr, .styled-table td {
                display: block;
                width: 100%;
            }
            .styled-table tr {
                margin-bottom: 15px;
                border: 1px solid var(--border);
                border-radius: 12px;
                background: white;
                box-shadow: 0 2px 5px rgba(0,0,0,0.03);
                overflow: hidden;
            }
            .styled-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 15px;
                text-align: right;
                border-bottom: 1px solid #f1f5f9;
            }
            .styled-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-muted);
                font-size: 0.8rem;
                text-transform: uppercase;
                margin-right: 10px;
                text-align: left;
            }
            .product-cell { flex-direction: row-reverse; } /* Ajuste visual */
        }

        /* MODAL */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center; z-index: 5000;
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s; }
        
        .modal-content {
            background: white; width: 95%; max-width: 600px; border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden;
            max-height: 90vh; display: flex; flex-direction: column;
        }
        .modal-body { padding: 24px; overflow-y: auto; }
        
        .modal-product-header {
            display: flex; gap: 16px; align-items: center; margin-bottom: 24px;
            padding-bottom: 20px; border-bottom: 1px solid #f1f5f9;
        }
        .modal-thumb { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; }
        
        .var-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .var-table th { text-align: left; font-size: 0.75rem; color: var(--text-muted); padding: 8px; background: #f8fafc; }
        .var-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<main>
    <div class="page-header">
        <h1>Dashboard de Estoque</h1>
        <p>Visão geral de inventário, alertas e métricas.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon icon-blue"><i class="bi bi-box-seam"></i></div>
            <div class="kpi-info">
                <h3><?php echo $totalItens; ?></h3>
                <span>Total de Itens</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon icon-purple"><i class="bi bi-tags"></i></div>
            <div class="kpi-info">
                <h3><?php echo $totalProdutos; ?></h3>
                <span>Produtos Únicos</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon icon-orange"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="kpi-info">
                <h3><?php echo $abaixoMinimo; ?></h3>
                <span>Reposição</span>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon icon-red"><i class="bi bi-slash-circle"></i></div>
            <div class="kpi-info">
                <h3><?php echo $inativos; ?></h3>
                <span>Inativos</span>
            </div>
        </div>
    </div>

    <div class="charts-section">
        <div class="chart-box">
            <h2>Estoque por Categoria</h2>
            <div style="height: 250px;">
                <canvas id="chartCat"></canvas>
            </div>
        </div>
        <div class="chart-box">
            <h2>Status do Estoque</h2>
            <div style="height: 250px; display:flex; justify-content:center;">
                <canvas id="chartStatus"></canvas>
            </div>
        </div>
    </div>

    <div class="content-panel">
        <div class="panel-header">
            <h2 style="margin:0; font-size:1.1rem;">Inventário Detalhado</h2>
            
            <div class="filters-container">
                <div class="search-input">
                    <i class="bi bi-search"></i>
                    <input type="text" id="inputBusca" placeholder="Buscar por nome, marca...">
                </div>
                
                <select id="selCategoria" class="filter-select">
                    <option value="">Todas Categorias</option>
                    <?php foreach ($porCategoria as $cat => $qtd): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="selStatus" class="filter-select">
                    <option value="">Status: Todos</option>
                    <option value="ok">Normal</option>
                    <option value="baixo">Baixo Estoque</option>
                    <option value="zerado">Sem Estoque</option>
                    <option value="inativo">Inativos</option>
                </select>
                
                <div style="font-size:0.85rem; color:var(--text-muted); margin-left:auto;">
                    <span id="contador">0</span> produtos visíveis
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="styled-table" id="tabelaPrincipal">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Estoque Atual</th>
                        <th>Mínimo</th>
                        <th>Status</th>
                        <th style="text-align:right">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): 
                        $estoque = (int)($p['estoque_total'] ?? 0);
                        $min = (int)$p['estoque_minimo'];
                        $img = !empty($p['imagem_capa']) ? $uploadDir.$p['imagem_capa'] : 'https://via.placeholder.com/80?text=IMG';
                        
                        // Lógica Status
                        $statusKey = 'ok';
                        $badgeClass = 'ok';
                        $badgeIcon = 'bi-check-circle';
                        $badgeLabel = 'Normal';

                        if($estoque <= 0) {
                            $statusKey = 'zerado'; $badgeClass = 'danger'; $badgeLabel = 'Zerado'; $badgeIcon = 'bi-x-circle';
                        } elseif ($estoque <= $min) {
                            $statusKey = 'baixo'; $badgeClass = 'warning'; $badgeLabel = 'Baixo'; $badgeIcon = 'bi-exclamation-circle';
                        }
                        if($p['ativo'] == 0) {
                            $statusKey = 'inativo'; $badgeClass = 'neutral'; $badgeLabel = 'Inativo'; $badgeIcon = 'bi-dash-circle';
                        }
                    ?>
                    <tr class="item-row" 
                        data-nome="<?php echo strtolower($p['nome'].' '.$p['marca']); ?>"
                        data-cat="<?php echo htmlspecialchars($p['tipo_produto'] ?: 'Geral'); ?>"
                        data-status="<?php echo $statusKey; ?>">
                        
                        <td data-label="Produto">
                            <div class="product-cell">
                                <img src="<?php echo $img; ?>" class="product-thumb">
                                <div>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($p['nome']); ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($p['marca']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Categoria"><?php echo htmlspecialchars($p['tipo_produto'] ?: 'Geral'); ?></td>
                        <td data-label="Estoque">
                            <span style="font-weight:700; <?php echo ($statusKey=='baixo'||$statusKey=='zerado')?'color:#dc2626':''; ?>">
                                <?php echo $estoque; ?>
                            </span> un.
                        </td>
                        <td data-label="Mínimo"><?php echo $min; ?> un.</td>
                        <td data-label="Status">
                            <span class="status-badge <?php echo $badgeClass; ?>">
                                <i class="bi <?php echo $badgeIcon; ?>"></i> <?php echo $badgeLabel; ?>
                            </span>
                        </td>
                        <td data-label="Ações" style="text-align:right">
                            <button class="btn-action" onclick="verDetalhes(<?php echo $p['id']; ?>)">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="msgVazio" style="display:none; padding:40px; text-align:center; color:var(--text-muted);">
                <i class="bi bi-search" style="font-size:2rem; display:block; margin-bottom:10px; opacity:0.5;"></i>
                Nenhum produto encontrado com estes filtros.
            </div>
        </div>
    </div>
</main>

<div class="modal-overlay" id="modalDetalhes">
    <div class="modal-content">
        <div style="padding:16px 24px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem;">Ficha Técnica</h3>
            <button onclick="fecharModal()" style="border:none; background:none; font-size:1.5rem; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-product-header">
                <img id="mImg" src="" class="modal-thumb">
                <div>
                    <h4 id="mNome" style="margin:0; font-size:1.1rem;">-</h4>
                    <span id="mMarca" style="color:var(--text-muted); font-size:0.9rem;">-</span>
                    <div id="mStatus" style="margin-top:6px;"></div>
                </div>
            </div>

            <div style="background:#f8fafc; padding:12px; border-radius:8px; margin-bottom:20px;">
                <small style="text-transform:uppercase; color:var(--text-muted); font-weight:700; font-size:0.7rem;">Descrição</small>
                <p id="mDesc" style="margin:4px 0 0; font-size:0.9rem; color:var(--text-main);">-</p>
            </div>

            <h5 style="margin:0 0 10px;">Distribuição de Estoque</h5>
            <div style="border:1px solid var(--border); border-radius:8px; overflow:hidden;">
                <table class="var-table">
                    <thead>
                        <tr>
                            <th>Cor</th>
                            <th>Tamanho</th>
                            <th>Peso</th>
                            <th style="text-align:right;">Qtd.</th>
                        </tr>
                    </thead>
                    <tbody id="mBodyVar"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. FILTROS DA TABELA
    const inputBusca = document.getElementById('inputBusca');
    const selCat = document.getElementById('selCategoria');
    const selStatus = document.getElementById('selStatus');
    const rows = document.querySelectorAll('.item-row');
    const contador = document.getElementById('contador');
    const msgVazio = document.getElementById('msgVazio');

    function filtrar() {
        const term = inputBusca.value.toLowerCase();
        const cat = selCat.value;
        const st = selStatus.value;
        let visiveis = 0;

        rows.forEach(row => {
            const rNome = row.dataset.nome;
            const rCat = row.dataset.cat;
            const rSt = row.dataset.status;

            let show = true;
            if (term && !rNome.includes(term)) show = false;
            if (cat && rCat !== cat) show = false;
            if (st && rSt !== st) show = false;

            row.style.display = show ? '' : 'none';
            if(show) visiveis++;
        });

        contador.innerText = visiveis;
        msgVazio.style.display = (visiveis === 0) ? 'block' : 'none';
        document.getElementById('tabelaPrincipal').style.display = (visiveis === 0) ? 'none' : 'table';
    }

    [inputBusca, selCat, selStatus].forEach(el => el.addEventListener('input', filtrar));
    filtrar(); // Init

    // 2. MODAL DETALHES
    const modal = document.getElementById('modalDetalhes');
    const uploadPath = '<?php echo $uploadDir; ?>';

    async function verDetalhes(id) {
        try {
            const res = await fetch(`?api_acao=detalhes&id=${id}`);
            const data = await res.json();
            const p = data.produto;
            
            // Preencher
            document.getElementById('mNome').innerText = p.nome;
            document.getElementById('mMarca').innerText = (p.marca || '') + ' ' + (p.modelo || '');
            document.getElementById('mDesc').innerText = p.descricao || 'Sem descrição.';
            document.getElementById('mImg').src = p.imagem_capa ? uploadPath + p.imagem_capa : 'https://via.placeholder.com/80';
            
            // Status Badge no modal
            const est = parseInt(p.estoque_total || 0);
            const min = parseInt(p.estoque_minimo || 0);
            let badge = `<span class="status-badge ok">Estoque Normal (${est})</span>`;
            if(est <= min) badge = `<span class="status-badge warning">Baixo Estoque (${est})</span>`;
            if(est <= 0) badge = `<span class="status-badge danger">Esgotado</span>`;
            document.getElementById('mStatus').innerHTML = badge;

            // Variações
            const tbody = document.getElementById('mBodyVar');
            tbody.innerHTML = '';
            if(data.variacoes.length > 0) {
                data.variacoes.forEach(v => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${v.cor}</td>
                            <td>${v.tamanho || '-'}</td>
                            <td>${v.peso ? v.peso+'kg' : '-'}</td>
                            <td style="text-align:right; font-weight:700;">${v.estoque}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#64748b; padding:15px;">Sem variações cadastradas.</td></tr>';
            }

            modal.classList.add('active');
        } catch(e) {
            console.error(e);
            alert('Erro ao carregar detalhes.');
        }
    }

    function fecharModal() {
        modal.classList.remove('active');
    }
    
    // Fechar ao clicar fora
    modal.addEventListener('click', (e) => {
        if(e.target === modal) fecharModal();
    });

    // 3. GRÁFICOS (Chart.js)
    const ctxCat = document.getElementById('chartCat');
    const ctxSt = document.getElementById('chartStatus');

    if(ctxCat) {
        new Chart(ctxCat, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($catLabels); ?>,
                datasets: [{
                    label: 'Itens',
                    data: <?php echo json_encode($catValues); ?>,
                    backgroundColor: '#6366f1',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { borderDash: [2, 4] } }, x: { grid: { display: false } } }
            }
        });
    }

    if(ctxSt) {
        new Chart(ctxSt, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusValues); ?>,
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'right', labels: { usePointStyle: true } } }
            }
        });
    }
</script>

</body>
</html>