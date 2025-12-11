<?php
session_start();
require_once __DIR__ . '/../../includes/banco-dados/init-db.php';

// 1) SEGURANÇA BÁSICA
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Se for ADMIN, não deixa usar este painel – manda pro painel de Admin
if (isset($_SESSION['user_permissao']) && $_SESSION['user_permissao'] === 'Admin') {
    header('Location: ../../admin-system.php');
    exit();
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_nome'] ?? 'Usuário';
$firstName = explode(' ', trim($userName))[0] ?? $userName;

// 2) INFO DO PLANO / VALIDADE
$validadeConta = null;
$diasRestantes = null;

try {
    $stmtUser = $pdo->prepare("SELECT validade_conta FROM usuarios WHERE id = :id LIMIT 1");
    $stmtUser->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmtUser->execute();
    $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($rowUser && !empty($rowUser['validade_conta'])) {
        $validadeConta = $rowUser['validade_conta'];
        $hojeTs        = strtotime(date('Y-m-d'));
        $validaTs      = strtotime($validadeConta);
        $diffDias      = ($validaTs - $hojeTs) / 86400;

        $diasRestantes = (int)ceil($diffDias);
        if ($diasRestantes < 0) {
            $diasRestantes = 0;
        }
    }
} catch (PDOException $e) {
    // Se der erro, só não mostra. Evita quebrar o painel.
    $validadeConta = null;
    $diasRestantes = null;
}

// 3) MÉTRICAS BÁSICAS (por enquanto, mock/0 – você conecta depois nas tabelas)
$totalVendasHoje      = 0;
$faturamentoHoje      = 0.00;
$totalProdutosEstoque = 0;
$produtosBaixoEstoque = 0;

// Aqui depois você pode trocar por SELECTs reais por owner_id
// try { ... } catch (PDOException $e) { ... }

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel da Loja</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS global do sistema -->
    <link rel="stylesheet" href="../../assets/css/admin.css">

    <style>
        /* =========================
           ESTILOS ESPECÍFICOS DO PAINEL
           ========================== */
        :root {
            --panel-bg: #f3f4f6;
            --panel-card-bg: #ffffff;
            --panel-border: #e5e7eb;
            --panel-radius: 18px;
            --panel-shadow: 0 12px 40px rgba(15,23,42,0.08);
            --primary: #6366f1;
            --primary-soft: #eef2ff;
            --text-main: #0f172a;
            --text-sub: #6b7280;
            --danger: #ef4444;
            --success: #22c55e;
        }

        body {
            background: var(--panel-bg);
        }

        .main-shell {
            padding: 90px 18px 24px;
            max-width: 1180px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 18px;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--text-main);
            letter-spacing: -0.03em;
        }

        .page-subtitle {
            font-size: 0.88rem;
            color: var(--text-sub);
            margin-top: 4px;
        }

        .plan-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .plan-pill span.badge {
            padding: 3px 7px;
            border-radius: 999px;
            background: #0ea5e9;
            color: #f0f9ff;
            font-size: 0.72rem;
        }

        .hero-card {
            background: radial-gradient(circle at top left, #e0f2fe, #eef2ff 45%, #ffffff 75%);
            border-radius: var(--panel-radius);
            padding: 20px 22px;
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1.5fr);
            gap: 18px;
            box-shadow: var(--panel-shadow);
            border: 1px solid rgba(148,163,184,0.25);
            margin-bottom: 22px;
        }

        .hero-main-title {
            font-size: 1.35rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }

        .hero-text {
            font-size: 0.9rem;
            color: #4b5563;
            margin-bottom: 14px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-primary {
            border-radius: 999px;
            border: none;
            padding: 9px 18px;
            font-size: 0.88rem;
            font-weight: 600;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #ffffff;
            box-shadow: 0 10px 30px rgba(79,70,229,0.35);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease, filter 0.12s ease;
        }

        .btn-primary:hover {
            filter: brightness(1.03);
            transform: translateY(-1px);
            box-shadow: 0 12px 34px rgba(79,70,229,0.45);
        }

        .btn-secondary {
            border-radius: 999px;
            border: 1px solid rgba(148,163,184,0.6);
            padding: 8px 16px;
            font-size: 0.86rem;
            font-weight: 500;
            background: rgba(255,255,255,0.8);
            color: #0f172a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            backdrop-filter: blur(4px);
            transition: 0.12s ease;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .hero-side {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
        }

        .hero-mini {
            padding: 10px 12px;
            border-radius: 16px;
            background: rgba(15,23,42,0.9);
            color: #e5e7eb;
            font-size: 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .hero-mini strong {
            font-size: 0.9rem;
        }

        .hero-mini span.chip {
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(34,197,94,0.18);
            color: #bbf7d0;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .hero-list {
            list-style: none;
            padding: 0;
            margin: 2px 0 0;
            font-size: 0.78rem;
            color: #9ca3af;
        }

        .hero-list li {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .hero-dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #22c55e;
        }

        /* GRID DE CARDS DE MÉTRICAS */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .metric-card {
            background: var(--panel-card-bg);
            border-radius: 16px;
            padding: 12px 14px;
            border: 1px solid var(--panel-border);
            box-shadow: 0 4px 14px rgba(15,23,42,0.05);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .metric-label {
            font-size: 0.78rem;
            color: var(--text-sub);
        }

        .metric-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .metric-foot {
            font-size: 0.75rem;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .metric-pill-up {
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(34,197,94,0.12);
            color: #16a34a;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .metric-pill-down {
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(248,113,113,0.12);
            color: #ef4444;
            font-size: 0.72rem;
            font-weight: 600;
        }

        /* ATALHOS RÁPIDOS */
        .main-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1.5fr);
            gap: 18px;
        }

        .card-block {
            background: var(--panel-card-bg);
            border-radius: 16px;
            padding: 16px 18px;
            border: 1px solid var(--panel-border);
            box-shadow: 0 6px 18px rgba(15,23,42,0.05);
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .card-sub {
            font-size: 0.8rem;
            color: var(--text-sub);
            margin-bottom: 12px;
        }

        .shortcut-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
        }

        .shortcut-item {
            border-radius: 14px;
            padding: 10px 11px;
            border: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #f9fafb, #f3f4f6);
            cursor: pointer;
            text-decoration: none;
            color: #111827;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 0.8rem;
            transition: 0.14s ease;
        }

        .shortcut-item strong {
            font-size: 0.85rem;
        }

        .shortcut-item span.desc {
            color: #6b7280;
        }

        .shortcut-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(15,23,42,0.12);
            border-color: #c7d2fe;
        }

        .shortcut-badge {
            align-self: flex-start;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #eef2ff;
            color: #4f46e5;
        }

        /* STATUS DA CONTA */
        .status-row {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 0.8rem;
            color: var(--text-sub);
        }

        .status-row div {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .status-label {
            font-weight: 500;
        }

        .status-value {
            font-weight: 600;
            color: var(--text-main);
        }

        .status-chip-ok {
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(34,197,94,0.12);
            color: #16a34a;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-chip-exp {
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(248,113,113,0.12);
            color: #ef4444;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .next-steps {
            list-style: none;
            margin: 0;
            padding-left: 0;
            font-size: 0.8rem;
        }

        .next-steps li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 6px;
            color: #4b5563;
        }

        .next-steps span.bullet {
            margin-top: 3px;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #a855f7;
            flex-shrink: 0;
        }

        @media (max-width: 900px) {
            .hero-card {
                grid-template-columns: 1fr;
            }
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .main-shell {
                padding: 80px 12px 20px;
            }
            .hero-card {
                padding: 16px 15px;
            }
        }
    </style>
</head>
<body>

<?php
// MENU GLOBAL (navbar + sidebar)
include __DIR__ . '/../../includes/menu.php';
?>

<div class="main-shell">
    <!-- CABEÇALHO -->
    <header class="page-header">
        <div>
            <h1 class="page-title">Painel da loja</h1>
            <p class="page-subtitle">
                Olá, <?php echo htmlspecialchars($firstName); ?>. Aqui você acompanha o resumo do seu negócio:
                vendas, estoque e atalhos rápidos para o dia a dia.
            </p>
        </div>

        <div class="plan-pill">
            <span>Licença ativa</span>
            <?php if ($diasRestantes !== null): ?>
                <span class="badge">
                    <?php echo $diasRestantes > 0 ? "{$diasRestantes} dias restantes" : "vence hoje ou vencida"; ?>
                </span>
            <?php else: ?>
                <span class="badge">Sem validade definida</span>
            <?php endif; ?>
        </div>
    </header>

    <!-- HERO / BOAS-VINDAS -->
    <section class="hero-card">
        <div>
            <h2 class="hero-main-title">Bem-vindo ao painel de vendas</h2>
            <p class="hero-text">
                Use este painel para controlar seu estoque, registrar vendas rápidas,
                acessar o PDV e acompanhar os resultados da sua loja física ou online.
            </p>

            <div class="hero-actions">
                <a href="../comandas/comandas.php" class="btn-primary">
                    Abrir PDV
                </a>
                <a href="../estoque/estoque.php" class="btn-secondary">
                    Ver estoque
                </a>
            </div>
        </div>

        <div class="hero-side">
            <div class="hero-mini">
                <div>
                    <strong>Visão do dia</strong>
                    <ul class="hero-list">
                        <li><span class="hero-dot"></span> Registre vendas e comandas em poucos cliques</li>
                        <li><span class="hero-dot"></span> Atualize estoque automaticamente a cada venda</li>
                        <li><span class="hero-dot"></span> Acompanhe o desempenho da equipe</li>
                    </ul>
                </div>
                <span class="chip">
                    <?php echo date('d/m'); ?>
                </span>
            </div>
        </div>
    </section>

    <!-- MÉTRICAS RÁPIDAS -->
    <section class="metrics-grid">
        <article class="metric-card">
            <span class="metric-label">Vendas hoje</span>
            <span class="metric-value"><?php echo $totalVendasHoje; ?></span>
            <div class="metric-foot">
                <span>Tickets emitidos no dia</span>
                <span class="metric-pill-up">tempo real</span>
            </div>
        </article>

        <article class="metric-card">
            <span class="metric-label">Faturamento de hoje</span>
            <span class="metric-value">R$ <?php echo number_format($faturamentoHoje, 2, ',', '.'); ?></span>
            <div class="metric-foot">
                <span>Inclui vendas de PDV e comandas</span>
                <span class="metric-pill-up">+0% vs. ontem</span>
            </div>
        </article>

        <article class="metric-card">
            <span class="metric-label">Itens em estoque</span>
            <span class="metric-value"><?php echo $totalProdutosEstoque; ?></span>
            <div class="metric-foot">
                <span>Produtos cadastrados</span>
                <span class="metric-pill-down">
                    <?php echo $produtosBaixoEstoque; ?> em atenção
                </span>
            </div>
        </article>

        <article class="metric-card">
            <span class="metric-label">Status da conta</span>
            <span class="metric-value">
                <?php
                if ($diasRestantes === null) {
                    echo 'Em análise';
                } elseif ($diasRestantes === 0) {
                    echo 'Vencida';
                } else {
                    echo 'Ativa';
                }
                ?>
            </span>
            <div class="metric-foot">
                <span>Controle de licença e acesso</span>
                <?php if ($diasRestantes === null || $diasRestantes > 7): ?>
                    <span class="status-chip-ok">regular</span>
                <?php else: ?>
                    <span class="status-chip-exp">atenção</span>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <!-- GRID PRINCIPAL: ATALHOS + STATUS -->
    <section class="main-grid">
        <!-- Atalhos rápidos -->
        <section class="card-block">
            <h3 class="card-title">Atalhos rápidos</h3>
            <p class="card-sub">
                Comece por aqui para agilizar sua rotina. Todos os módulos
                são integrados ao estoque e às vendas.
            </p>

            <div class="shortcut-grid">
                <a href="../comandas/comandas.php" class="shortcut-item">
                    <span class="shortcut-badge">PDV</span>
                    <strong>Abrir PDV / Comandas</strong>
                    <span class="desc">Registre vendas rápidas e comandas em atendimento.</span>
                </a>

                <a href="../clientes/clientes.php" class="shortcut-item">
                    <span class="shortcut-badge">Clientes</span>
                    <strong>Cadastrar clientes</strong>
                    <span class="desc">Organize sua base de clientes e histórico de compras.</span>
                </a>

                <a href="../estoque/estoque.php" class="shortcut-item">
                    <span class="shortcut-badge">Estoque</span>
                    <strong>Gerenciar produtos</strong>
                    <span class="desc">Atualize quantidades, preços e controle mínimo.</span>
                </a>

                <a href="../calcular-servico/calcular-servico.php" class="shortcut-item">
                    <span class="shortcut-badge">Custos</span>
                    <strong>Calcular serviço</strong>
                    <span class="desc">Simule custos e precificação para não ter prejuízo.</span>
                </a>
            </div>
        </section>

        <!-- Status da conta / próximos passos -->
        <section class="card-block">
            <h3 class="card-title">Status da conta</h3>
            <p class="card-sub">
                Acompanhe sua licença e tenha uma visão clara do que falta configurar.
            </p>

            <div class="status-row">
                <div>
                    <span class="status-label">Validade da licença</span>
                    <span class="status-value">
                        <?php
                        if ($validadeConta) {
                            echo date('d/m/Y', strtotime($validadeConta));
                        } else {
                            echo 'Sem data definida';
                        }
                        ?>
                    </span>
                </div>
                <div>
                    <span class="status-label">Dias restantes</span>
                    <span class="status-value">
                        <?php
                        if ($diasRestantes === null) {
                            echo '-';
                        } else {
                            echo $diasRestantes . ' dia(s)';
                        }
                        ?>
                    </span>
                </div>
                <div>
                    <span class="status-label">Situação</span>
                    <span class="status-value">
                        <?php
                        if ($diasRestantes === null) {
                            echo 'Em acompanhamento';
                        } elseif ($diasRestantes <= 0) {
                            echo 'Licença vencida';
                        } elseif ($diasRestantes <= 7) {
                            echo 'Próximo do vencimento';
                        } else {
                            echo 'Regular';
                        }
                        ?>
                    </span>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid #e5e7eb;margin:12px 0;">

            <h4 class="card-title" style="font-size:0.88rem;margin-bottom:6px;">Próximos passos sugeridos</h4>
            <ul class="next-steps">
                <li>
                    <span class="bullet"></span>
                    <span>Finalize o cadastro dos seus produtos principais no módulo de estoque.</span>
                </li>
                <li>
                    <span class="bullet"></span>
                    <span>Teste o fluxo completo: abra o PDV, registre uma venda e confira se o estoque foi atualizado.</span>
                </li>
                <li>
                    <span class="bullet"></span>
                    <span>Cadaste seus clientes recorrentes para acompanhar o histórico de compras.</span>
                </li>
            </ul>
        </section>
    </section>
</div>

</body>
</html>
