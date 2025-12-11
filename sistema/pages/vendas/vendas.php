<?php
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';

$sess_status = session_status();
if ($sess_status === PHP_SESSION_NONE) session_start();
$owner_id = $_SESSION['user_id'] ?? null;

// =======================
// API para detalhes (AJAX)
// =======================
if (isset($_GET['acao']) && $_GET['acao'] === 'itens' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $idVenda = (int)$_GET['id'];

    $stmtVenda = $pdo->prepare("SELECT * FROM vendas WHERE id = :id");
    $stmtVenda->execute([':id' => $idVenda]);
    $venda = $stmtVenda->fetch(PDO::FETCH_ASSOC);

    $stmtItens = $pdo->prepare("SELECT * FROM vendas_itens WHERE venda_id = :id");
    $stmtItens->execute([':id' => $idVenda]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['venda' => $venda, 'itens' => $itens]);
    exit;
}

// =======================
// FILTROS (GET)
// =======================
$busca        = trim($_GET['busca'] ?? '');
$data_ini     = $_GET['data_ini'] ?? '';
$data_fim     = $_GET['data_fim'] ?? '';
$forma        = $_GET['forma'] ?? '';
$limite       = 100;

$where  = "1=1";
$params = [];

if ($owner_id) {
    $where .= " AND owner_id = :owner_id";
    $params[':owner_id'] = $owner_id;
}

if ($busca !== '') {
    $where .= " AND (nome_cliente LIKE :busca OR cpf_cliente LIKE :busca OR CAST(id AS TEXT) LIKE :busca)";
    $params[':busca'] = "%{$busca}%";
}

if ($data_ini !== '') {
    $where .= " AND DATE(data_venda) >= :data_ini";
    $params[':data_ini'] = $data_ini;
}
if ($data_fim !== '') {
    $where .= " AND DATE(data_venda) <= :data_fim";
    $params[':data_fim'] = $data_fim;
}

if ($forma !== '') {
    $where .= " AND forma_pagamento = :forma";
    $params[':forma'] = $forma;
}

$sql = "SELECT * FROM vendas WHERE $where ORDER BY data_venda DESC LIMIT $limite";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pequenos totais (com base no filtro atual)
$totalVendas = count($vendas);
$totalValor  = array_sum(array_column($vendas, 'valor_total'));
$ticketMedio = $totalVendas > 0 ? $totalValor / $totalVendas : 0;

// Hoje
$hoje = date('Y-m-d');
$sqlHoje = "SELECT SUM(valor_total) as total_hoje, COUNT(*) as qtd_hoje
            FROM vendas
            WHERE DATE(data_venda) = :hoje" . ($owner_id ? " AND owner_id = :owner_id" : "");
$paramsHoje = [':hoje' => $hoje];
if ($owner_id) $paramsHoje[':owner_id'] = $owner_id;
$stmtHoje = $pdo->prepare($sqlHoje);
$stmtHoje->execute($paramsHoje);
$dadosHoje = $stmtHoje->fetch(PDO::FETCH_ASSOC);
$totalHoje = $dadosHoje['total_hoje'] ?? 0;
$qtdHoje   = $dadosHoje['qtd_hoje'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Vendas</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-soft: #eef2ff;
            --danger: #ef4444;
            --success: #10b981;
            --muted: #6b7280;
            --border: #e5e7eb;
            --surface: #ffffff;
            --bg: #f3f4f6;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        body {
            background: var(--bg);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
        }

        main {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            margin-bottom:20px;
        }

        .page-header h1 {
            margin:0;
            font-size:1.6rem;
            color:#111827;
        }
        .page-header p { margin:4px 0 0; color:var(--muted); }

        /* Cards resumo */
        .summary-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
            gap:12px;
            margin-bottom:20px;
        }
        .summary-card {
            background:var(--surface);
            border-radius:var(--radius);
            padding:14px 16px;
            box-shadow:var(--shadow);
            display:flex;
            align-items:center;
            justify-content:space-between;
            border:1px solid var(--border);
        }
        .summary-label {
            font-size:0.8rem;
            text-transform:uppercase;
            letter-spacing:0.05em;
            color:var(--muted);
            margin-bottom:4px;
        }
        .summary-value {
            font-size:1.2rem;
            font-weight:700;
            color:#111827;
        }
        .summary-pill {
            padding:4px 8px;
            border-radius:999px;
            font-size:0.75rem;
            font-weight:600;
        }
        .pill-green { background:#ecfdf5; color:#166534; }
        .pill-blue  { background:#eff6ff; color:#1d4ed8; }
        .pill-gray  { background:#f3f4f6; color:#374151; }

        /* Filtros */
        .filters-card {
            background:var(--surface);
            border-radius:var(--radius);
            padding:14px 16px;
            box-shadow:var(--shadow);
            border:1px solid var(--border);
            margin-bottom:16px;
        }
        .filters-row {
            display:grid;
            grid-template-columns: 1.5fr repeat(2, 1fr) 1fr auto;
            gap:10px;
            align-items:end;
        }
        .filter-group label {
            display:block;
            font-size:0.8rem;
            color:var(--muted);
            margin-bottom:4px;
        }
        .filter-control {
            width:100%;
            padding:8px 10px;
            border-radius:8px;
            border:1px solid var(--border);
            font-size:0.9rem;
            box-sizing:border-box;
        }
        .filter-control:focus {
            outline:none;
            border-color:var(--primary);
            box-shadow:0 0 0 2px var(--primary-soft);
        }
        .btn-primary {
            border:none;
            background:var(--primary);
            color:#fff;
            padding:9px 14px;
            border-radius:8px;
            font-size:0.9rem;
            font-weight:600;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:6px;
        }
        .btn-secondary {
            border:1px solid var(--border);
            background:#fff;
            color:#374151;
            padding:9px 14px;
            border-radius:8px;
            font-size:0.85rem;
            font-weight:500;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:6px;
        }

        /* Tabela */
        .card-table {
            background:var(--surface);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            border:1px solid var(--border);
            overflow:hidden;
        }
        table {
            width:100%;
            border-collapse:collapse;
        }
        thead tr {
            background:#f9fafb;
        }
        th, td {
            padding:10px 12px;
            font-size:0.9rem;
            text-align:left;
            border-bottom:1px solid #f1f5f9;
        }
        th {
            text-transform:uppercase;
            letter-spacing:0.04em;
            font-size:0.75rem;
            color:var(--muted);
        }
        tbody tr:hover {
            background:#f9fafb;
        }
        .badge {
            padding:3px 8px;
            border-radius:999px;
            font-size:0.7rem;
            font-weight:600;
        }
        .badge-pix      { background:#ecfdf3; color:#047857; }
        .badge-cred     { background:#eff6ff; color:#1d4ed8; }
        .badge-deb      { background:#fef9c3; color:#854d0e; }
        .badge-din      { background:#fee2e2; color:#b91c1c; }
        .badge-outro    { background:#e5e7eb; color:#374151; }

        .badge-origem   { background:#eef2ff; color:#4338ca; }

        .actions-cell {
            display:flex;
            justify-content:flex-end;
            gap:8px;
        }
        .btn-sm {
            border:none;
            padding:6px 10px;
            border-radius:999px;
            font-size:0.8rem;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:4px;
        }
        .btn-outline {
            background:#fff;
            border:1px solid var(--border);
            color:#374151;
        }
        .btn-outline-green {
            border-color:#22c55e;
            color:#15803d;
        }

        .no-data {
            text-align:center;
            padding:24px;
            color:var(--muted);
            font-size:0.9rem;
        }

        /* Modal detalhes */
        .modal-overlay {
            position:fixed;
            inset:0;
            background:rgba(15,23,42,0.55);
            display:none;
            align-items:center;
            justify-content:center;
            z-index:3000;
        }
        .modal-overlay.open { display:flex; }
        .modal-box {
            background:#fff;
            width:95%;
            max-width:700px;
            max-height:90vh;
            border-radius:16px;
            box-shadow:0 20px 25px -5px rgba(0,0,0,0.25);
            display:flex;
            flex-direction:column;
            overflow:hidden;
        }
        .modal-header {
            padding:14px 18px;
            border-bottom:1px solid var(--border);
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .modal-body {
            padding:14px 18px;
            overflow-y:auto;
        }
        .modal-footer {
            padding:12px 18px;
            border-top:1px solid var(--border);
            display:flex;
            justify-content:flex-end;
            gap:10px;
            background:#f9fafb;
        }

        .modal-header h3 {
            margin:0;
            font-size:1rem;
        }
        .close-btn {
            background:none;
            border:none;
            font-size:1.2rem;
            cursor:pointer;
            color:#6b7280;
        }

        .detail-grid {
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap:10px;
            margin-bottom:14px;
        }
        .detail-item label {
            font-size:0.75rem;
            color:var(--muted);
            text-transform:uppercase;
            letter-spacing:0.05em;
        }
        .detail-item span {
            display:block;
            font-size:0.9rem;
            font-weight:500;
            color:#111827;
        }

        @media(max-width: 900px) {
            .filters-row {
                grid-template-columns: 1fr 1fr;
                grid-auto-rows:auto;
            }
        }
        @media(max-width: 640px) {
            .filters-row {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction:column;
                gap:6px;
            }
        }
    </style>
</head>
<body>
<main style="padding:32px 16px 32px;">
    <div class="page-header">
        <div>
            <h1>Histórico de Vendas</h1>
            <p>Acompanhe as vendas feitas no PDV, comandas ou vendas diretas.</p>
        </div>
    </div>

    <!-- Cards Resumo -->
    <div class="summary-grid">
        <div class="summary-card">
            <div>
                <div class="summary-label">Total no Período</div>
                <div class="summary-value">
                    R$ <?php echo number_format($totalValor, 2, ',', '.'); ?>
                </div>
            </div>
            <span class="summary-pill pill-blue"><?php echo $totalVendas; ?> vendas</span>
        </div>

        <div class="summary-card">
            <div>
                <div class="summary-label">Ticket Médio</div>
                <div class="summary-value">
                    R$ <?php echo number_format($ticketMedio, 2, ',', '.'); ?>
                </div>
            </div>
            <span class="summary-pill pill-gray">por venda</span>
        </div>

        <div class="summary-card">
            <div>
                <div class="summary-label">Hoje (<?php echo date('d/m'); ?>)</div>
                <div class="summary-value">
                    R$ <?php echo number_format($totalHoje, 2, ',', '.'); ?>
                </div>
            </div>
            <span class="summary-pill pill-green"><?php echo $qtdHoje; ?> vendas hoje</span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-card">
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label>Buscar (cliente, CPF, nº venda)</label>
                <input type="text" name="busca" value="<?php echo htmlspecialchars($busca); ?>" class="filter-control" placeholder="Ex: Maria, 000.000.000-00, 123">
            </div>

            <div class="filter-group">
                <label>Data inicial</label>
                <input type="date" name="data_ini" value="<?php echo htmlspecialchars($data_ini); ?>" class="filter-control">
            </div>

            <div class="filter-group">
                <label>Data final</label>
                <input type="date" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" class="filter-control">
            </div>

            <div class="filter-group">
                <label>Forma de pagamento</label>
                <select name="forma" class="filter-control">
                    <option value="">Todas</option>
                    <option value="dinheiro" <?php if($forma==='dinheiro') echo 'selected'; ?>>Dinheiro</option>
                    <option value="cartao_credito" <?php if($forma==='cartao_credito') echo 'selected'; ?>>Crédito</option>
                    <option value="cartao_debito" <?php if($forma==='cartao_debito') echo 'selected'; ?>>Débito</option>
                    <option value="pix" <?php if($forma==='pix') echo 'selected'; ?>>PIX</option>
                </select>
            </div>

            <div style="display:flex; gap:6px; justify-content:flex-end;">
                <button type="submit" class="btn-primary">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a href="historico-vendas.php" class="btn-secondary">
                    <i class="bi bi-x-circle"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="card-table">
        <table>
            <thead>
                <tr>
                    <th># Venda</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>CPF</th>
                    <th>Origem</th>
                    <th>Pagamento</th>
                    <th>Total</th>
                    <th style="text-align:right;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($vendas)): ?>
                <tr>
                    <td colspan="8" class="no-data">
                        <i class="bi bi-receipt"></i> Nenhuma venda encontrada com os filtros atuais.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($vendas as $v): 
                    $idFormat = str_pad($v['id'], 6, '0', STR_PAD_LEFT);
                    $dataFmt  = date('d/m/Y H:i', strtotime($v['data_venda']));
                    $cliente  = $v['nome_cliente'] ?: 'Cliente avulso';
                    $cpf      = $v['cpf_cliente'] ?: '-';

                    // origem simples (futuramente você pode colocar campo na tabela)
                    $origem = 'PDV / Loja';
                    if (!empty($v['origem'])) {
                        $origem = $v['origem']; // se um dia criar esse campo
                    }

                    // badge pagamento
                    switch($v['forma_pagamento']) {
                        case 'pix':
                            $badgePagto = '<span class="badge badge-pix">PIX</span>';
                            break;
                        case 'cartao_credito':
                            $badgePagto = '<span class="badge badge-cred">Crédito</span>';
                            break;
                        case 'cartao_debito':
                            $badgePagto = '<span class="badge badge-deb">Débito</span>';
                            break;
                        case 'dinheiro':
                            $badgePagto = '<span class="badge badge-din">Dinheiro</span>';
                            break;
                        default:
                            $badgePagto = '<span class="badge badge-outro">'.htmlspecialchars($v['forma_pagamento']).'</span>';
                    }
                ?>
                <tr>
                    <td>#<?php echo $idFormat; ?></td>
                    <td><?php echo $dataFmt; ?></td>
                    <td><?php echo htmlspecialchars($cliente); ?></td>
                    <td><?php echo htmlspecialchars($cpf); ?></td>
                    <td><span class="badge badge-origem"><?php echo htmlspecialchars($origem); ?></span></td>
                    <td><?php echo $badgePagto; ?></td>
                    <td>R$ <?php echo number_format($v['valor_total'],2,',','.'); ?></td>
                    <td>
                        <div class="actions-cell">
                            <button type="button" class="btn-sm btn-outline" onclick="verDetalhes(<?php echo $v['id']; ?>)">
                                <i class="bi bi-eye"></i> Detalhes
                            </button>
                            <a href="../pdv/pdv_nota.php?id=<?php echo $v['id']; ?>" target="_blank" class="btn-sm btn-outline btn-outline-green">
                                <i class="bi bi-printer"></i> Nota
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- MODAL DETALHES -->
<div class="modal-overlay" id="detalhesModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="detalhesTitulo">Venda</h3>
            <button class="close-btn" onclick="fecharModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Data / Hora</label>
                    <span id="detalhesData">--</span>
                </div>
                <div class="detail-item">
                    <label>Cliente</label>
                    <span id="detalhesCliente">--</span>
                </div>
                <div class="detail-item">
                    <label>CPF</label>
                    <span id="detalhesCpf">--</span>
                </div>
                <div class="detail-item">
                    <label>Forma de Pagamento</label>
                    <span id="detalhesPagto">--</span>
                </div>
                <div class="detail-item">
                    <label>Valor Total</label>
                    <span id="detalhesTotal">--</span>
                </div>
                <div class="detail-item">
                    <label>Troco</label>
                    <span id="detalhesTroco">--</span>
                </div>
            </div>

            <div class="detail-item" style="margin-bottom:12px;">
                <label>Observações</label>
                <span id="detalhesObs">--</span>
            </div>

            <h4 style="margin:8px 0 6px; font-size:0.95rem;">Itens da venda</h4>
            <div style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="padding:8px; font-size:0.8rem;">Produto</th>
                            <th style="padding:8px; font-size:0.8rem;">Variação</th>
                            <th style="padding:8px; font-size:0.8rem;">Qtd</th>
                            <th style="padding:8px; font-size:0.8rem;">Preço</th>
                            <th style="padding:8px; font-size:0.8rem;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="detalhesItensBody">
                        <tr>
                            <td colspan="5" style="text-align:center; padding:10px; font-size:0.85rem; color:#9ca3af;">
                                Nenhum item encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="fecharModal()">
                Fechar
            </button>
            <a href="#" id="btnNotaModal" target="_blank" class="btn-primary">
                <i class="bi bi-printer"></i> Imprimir Nota
            </a>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('detalhesModal');

    function abrirModal() {
        modal.classList.add('open');
    }
    function fecharModal() {
        modal.classList.remove('open');
    }

    async function verDetalhes(idVenda) {
        try {
            const resp = await fetch(`historico-vendas.php?acao=itens&id=${idVenda}`);
            const data = await resp.json();
            const v = data.venda;
            const itens = data.itens || [];

            document.getElementById('detalhesTitulo').innerText = `Venda #${String(idVenda).padStart(6,'0')}`;
            document.getElementById('detalhesData').innerText   = formatarData(v.data_venda);
            document.getElementById('detalhesCliente').innerText= v.nome_cliente || 'Cliente avulso';
            document.getElementById('detalhesCpf').innerText    = v.cpf_cliente || '-';
            document.getElementById('detalhesPagto').innerText  = mapPagto(v.forma_pagamento);
            document.getElementById('detalhesTotal').innerText  = formatarMoeda(v.valor_total);
            document.getElementById('detalhesTroco').innerText  = formatarMoeda(v.troco ?? 0);
            document.getElementById('detalhesObs').innerText    = v.observacoes || '-';

            const tbody = document.getElementById('detalhesItensBody');
            tbody.innerHTML = '';

            if (itens.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:10px; font-size:0.85rem; color:#9ca3af;">Nenhum item encontrado.</td></tr>';
            } else {
                itens.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="padding:8px; font-size:0.85rem;">${item.nome_produto}</td>
                        <td style="padding:8px; font-size:0.85rem;">${(item.cor||'') + (item.tamanho ? ' - '+item.tamanho : '')}</td>
                        <td style="padding:8px; font-size:0.85rem;">${item.quantidade}</td>
                        <td style="padding:8px; font-size:0.85rem;">${formatarMoeda(item.preco_unit)}</td>
                        <td style="padding:8px; font-size:0.85rem;">${formatarMoeda(item.subtotal)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            const btnNota = document.getElementById('btnNotaModal');
            btnNota.href = `../pdv/pdv_nota.php?id=${idVenda}`;

            abrirModal();
        } catch (e) {
            console.error(e);
            alert('Erro ao carregar detalhes da venda.');
        }
    }

    function formatarData(str) {
        if (!str) return '-';
        const d = new Date(str.replace(' ', 'T')); // gambizinha pro JS
        if (isNaN(d.getTime())) return str;
        return d.toLocaleString('pt-BR');
    }

    function formatarMoeda(val) {
        const num = parseFloat(val || 0);
        return num.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
    }

    function mapPagto(cod) {
        switch(cod) {
            case 'pix': return 'PIX';
            case 'dinheiro': return 'Dinheiro';
            case 'cartao_credito': return 'Cartão Crédito';
            case 'cartao_debito': return 'Cartão Débito';
            default: return cod || '-';
        }
    }

    // Fecha modal clicando fora
    modal.addEventListener('click', (e) => {
        if (e.target === modal) fecharModal();
    });
</script>
</body>
</html>
