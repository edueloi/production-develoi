<?php
// =========================================================
// 1. CONFIGURAÇÕES & SEGURANÇA
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica login
if (!isset($_SESSION['user_id'])) {
    header('Location: /karen_site/loja/sistema/login.php'); 
    exit();
}

// =========================================================
// 2. DADOS DO USUÁRIO
// =========================================================
$userName     = $_SESSION['user_nome']      ?? 'Usuário';
$userPerm     = $_SESSION['user_permissao'] ?? 'Visitante';
$partesNome   = explode(' ', trim($userName));
$firstName    = $partesNome[0];

// Iniciais
$primeiraLetra = mb_substr($firstName, 0, 1);
$ultimaLetra   = (count($partesNome) > 1) ? mb_substr(end($partesNome), 0, 1) : '';
$iniciais      = strtoupper($primeiraLetra . $ultimaLetra);

// Cargos e Cores
switch ($userPerm) {
    case 'Admin': $userRole = 'Super Admin'; $roleBadge = 'bg-danger'; break;
    case 'Dono':  $userRole = 'CEO / Dono';  $roleBadge = 'bg-warning'; break;
    default:      $userRole = 'Colaborador'; $roleBadge = 'bg-success'; break;
}

// =========================================================
// 3. HELPER DE NAVEGAÇÃO
// =========================================================
$baseUrl = '/karen_site/loja/sistema'; 

function isActive($paths) {
    $current = $_SERVER['PHP_SELF'];
    if (!is_array($paths)) $paths = [$paths];
    foreach ($paths as $path) {
        if (strpos($current, $path) !== false) return 'active';
    }
    return '';
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        /* Dimensões */
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 70px;
        --header-height: 64px;
        
        /* Cores */
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --sidebar-bg: #0f172a;       /* Dark Blue/Slate */
        --navbar-bg: rgba(255, 255, 255, 0.9);
        --text-sidebar: #94a3b8;
        --text-sidebar-hover: #f8fafc;
        --body-bg: #f1f5f9;
        
        /* Transição Suave */
        --transition-speed: 0.3s;
    }

    /* Reset Body para layout */
    body {
        margin: 0;
        padding-top: var(--header-height);
        padding-left: var(--sidebar-width);
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: var(--body-bg);
        transition: padding-left var(--transition-speed) ease;
    }

    /* Quando menu fechado */
    body.menu-collapsed {
        padding-left: var(--sidebar-collapsed-width);
    }

    /* =========================================
       1. SIDEBAR (Lateral)
       ========================================= */
    .app-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        color: var(--text-sidebar);
        z-index: 1050;
        display: flex;
        flex-direction: column;
        transition: width var(--transition-speed) ease;
        box-shadow: 4px 0 24px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .app-sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    /* Header da Sidebar (Logo) */
    .sidebar-header {
        height: var(--header-height);
        display: flex;
        align-items: center;
        padding: 0 20px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        white-space: nowrap;
    }

    .logo-area {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .logo-icon {
        min-width: 32px;
        height: 32px;
        background: linear-gradient(135deg, var(--primary), #818cf8);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.1rem;
        box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
    }

    .logo-text {
        font-weight: 700;
        font-size: 1.1rem;
        color: white;
        opacity: 1;
        transition: opacity 0.2s;
    }

    .app-sidebar.collapsed .logo-text {
        opacity: 0;
        pointer-events: none;
        display: none;
    }

    /* Área de Scroll Personalizada */
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 16px 10px;
    }
    
    /* Scrollbar Bonito (Fino e Escuro) */
    .sidebar-content::-webkit-scrollbar { width: 5px; }
    .sidebar-content::-webkit-scrollbar-track { background: transparent; }
    .sidebar-content::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    .sidebar-content::-webkit-scrollbar-thumb:hover { background: #475569; }

    /* Links do Menu */
    .menu-category {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        margin: 20px 0 8px 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .app-sidebar.collapsed .menu-category { display: none; }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 11px 14px;
        color: var(--text-sidebar);
        text-decoration: none;
        border-radius: 10px;
        margin-bottom: 4px;
        transition: all 0.2s;
        position: relative;
        white-space: nowrap;
    }

    .nav-link i {
        font-size: 1.25rem;
        min-width: 24px;
        display: flex;
        justify-content: center;
    }

    .nav-link span {
        margin-left: 12px;
        font-weight: 500;
        transition: opacity 0.2s;
    }
    .app-sidebar.collapsed .nav-link span { opacity: 0; display: none; }

    /* Hover & Active */
    .nav-link:hover {
        background: rgba(255,255,255,0.08);
        color: var(--text-sidebar-hover);
    }
    .nav-link.active {
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }
    .nav-link.active i { color: white; }

    /* Tooltip no Hover (quando fechado) */
    .app-sidebar.collapsed .nav-link:hover::after {
        content: attr(data-title);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: #1e293b;
        color: white;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        white-space: nowrap;
        margin-left: 10px;
        z-index: 2000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    /* =========================================
       2. NAVBAR SUPERIOR (Topo)
       ========================================= */
    .app-header {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        height: var(--header-height);
        background: var(--navbar-bg);
        backdrop-filter: blur(8px);
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 24px;
        z-index: 1040;
        transition: left var(--transition-speed) ease;
    }

    body.menu-collapsed .app-header {
        left: var(--sidebar-collapsed-width);
    }

    /* Botão Toggle (Hambúrguer/Seta) */
    .header-toggle-btn {
        background: transparent;
        border: none;
        font-size: 1.4rem;
        color: #475569;
        cursor: pointer;
        padding: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
    }
    .header-toggle-btn:hover { color: var(--primary); }

    /* Área do Usuário */
    .user-dropdown {
        position: relative;
    }

    .user-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        background: transparent;
        border: none;
        padding: 5px;
        border-radius: 50px;
        transition: 0.2s;
    }
    .user-btn:hover { background: #f1f5f9; }

    .user-info {
        text-align: right;
    }
    .user-info h6 { margin: 0; font-size: 0.9rem; color: #334155; font-weight: 600; }
    .user-info span { font-size: 0.75rem; color: #64748b; display: block; }

    .avatar-circle {
        width: 38px;
        height: 38px;
        background: #e0e7ff;
        color: var(--primary);
        font-weight: 700;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Dropdown Content */
    .dropdown-content {
        position: absolute;
        top: 120%;
        right: 0;
        width: 200px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1);
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 2000;
    }

    .dropdown-content.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .drop-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        color: #475569;
        text-decoration: none;
        font-size: 0.9rem;
        transition: 0.2s;
    }
    .drop-item:hover { background: #f8fafc; color: var(--primary); }
    .drop-item.danger { color: #ef4444; border-top: 1px solid #f1f5f9; }
    .drop-item.danger:hover { background: #fef2f2; }

    /* =========================================
       3. RESPONSIVIDADE (Mobile)
       ========================================= */
    .backdrop {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);
        z-index: 1045; opacity: 0; visibility: hidden; transition: 0.3s;
    }
    .backdrop.show { opacity: 1; visibility: visible; }

    @media (max-width: 768px) {
        body { padding-left: 0 !important; }
        .app-header { left: 0 !important; }
        .user-info { display: none; } /* Esconde texto no mobile */
        
        /* Sidebar sai da tela */
        .app-sidebar { transform: translateX(-100%); width: var(--sidebar-width) !important; }
        .app-sidebar.mobile-open { transform: translateX(0); }
    }
</style>

<div class="backdrop" id="backdrop"></div>

<aside class="app-sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo $baseUrl; ?>/pages/painel/index.php" class="logo-area">
            <div class="logo-icon"><i class="bi bi-box-seam-fill"></i></div>
            <span class="logo-text">Sistema ERP</span>
        </a>
    </div>

    <div class="sidebar-content">
        <div class="menu-category">Visão Geral</div>
        <a href="<?php echo $baseUrl; ?>/pages/painel/index.php" 
           class="nav-link <?php echo isActive(['painel', 'index.php']); ?>" 
           data-title="Dashboard">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <div class="menu-category">Vendas & Caixa</div>
        <a href="<?php echo $baseUrl; ?>/pages/pdv/pdv.php" 
           class="nav-link <?php echo isActive('pdv'); ?>" 
           data-title="Abrir PDV">
            <i class="bi bi-shop"></i>
            <span>Frente de Caixa</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/pages/comandas/index.php" 
           class="nav-link <?php echo isActive('comandas'); ?>" 
           data-title="Comandas">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Comandas / Mesas</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/pages/vendas/vendas.php" 
           class="nav-link <?php echo isActive('vendas'); ?>" 
           data-title="Histórico">
            <i class="bi bi-clock-history"></i>
            <span>Histórico Vendas</span>
        </a>

        <div class="menu-category">Serviços & Custos</div>
        <a href="<?php echo $baseUrl; ?>/pages/calcular-servico/index.php" 
           class="nav-link <?php echo isActive('calcular-servico'); ?>" 
           data-title="Calculadora Custos">
            <i class="bi bi-calculator-fill"></i>
            <span>Calc. de Custos</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/pages/servicos/index.php" 
           class="nav-link <?php echo isActive('servicos'); ?>" 
           data-title="Meus Serviços">
            <i class="bi bi-scissors"></i>
            <span>Meus Serviços</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/pages/pacotes/index.php" 
           class="nav-link <?php echo isActive('pacotes'); ?>" 
           data-title="Pacotes">
            <i class="bi bi-gift-fill"></i>
            <span>Pacotes</span>
        </a>

        <div class="menu-category">Gestão</div>
        <a href="<?php echo $baseUrl; ?>/pages/produtos/produtos.php" 
           class="nav-link <?php echo isActive('produtos'); ?>" 
           data-title="Produtos">
            <i class="bi bi-tags-fill"></i>
            <span>Produtos</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/pages/estoque/estoque.php" 
           class="nav-link <?php echo isActive('estoque'); ?>" 
           data-title="Estoque">
            <i class="bi bi-box2-fill"></i>
            <span>Estoque</span>
        </a>
        <a href="<?php echo $baseUrl; ?>/pages/clientes/index.php" 
           class="nav-link <?php echo isActive('clientes'); ?>" 
           data-title="Clientes">
            <i class="bi bi-people-fill"></i>
            <span>Clientes</span>
        </a>

        <?php if ($userPerm === 'Dono' || $userPerm === 'Admin'): ?>
            <div class="menu-category">Admin</div>
            <a href="<?php echo $baseUrl; ?>/pages/relatorios/relatorios.php" 
               class="nav-link <?php echo isActive('relatorios'); ?>" 
               data-title="Relatórios">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>Relatórios</span>
            </a>
            <a href="<?php echo $baseUrl; ?>/pages/usuarios/usuarios.php" 
               class="nav-link <?php echo isActive('usuarios'); ?>" 
               data-title="Usuários">
                <i class="bi bi-person-badge-fill"></i>
                <span>Usuários</span>
            </a>
        <?php endif; ?>
        
        <div style="height: 50px;"></div>
    </div>
</aside>

<header class="app-header">
    <div style="display:flex; align-items:center; gap:15px;">
        <button class="header-toggle-btn" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <h4 style="margin:0; font-size:1.1rem; font-weight:600; color:#1e293b; display:none;" id="pageTitle">
            </h4>
    </div>

    <div class="user-dropdown">
        <button class="user-btn" id="userMenuBtn">
            <div class="user-info">
                <h6><?php echo htmlspecialchars($firstName); ?></h6>
                <span><?php echo $userRole; ?></span>
            </div>
            <div class="avatar-circle">
                <?php echo $iniciais; ?>
            </div>
            <i class="bi bi-chevron-down" style="font-size:0.8rem; color:#94a3b8;"></i>
        </button>

        <div class="dropdown-content" id="userMenuContent">
            <div style="padding:15px; border-bottom:1px solid #f1f5f9;">
                <span style="font-size:0.85rem; color:#94a3b8;">Conta logada</span>
                <div style="font-weight:600; color:#334155;"><?php echo htmlspecialchars($userName); ?></div>
            </div>
            
            <a href="<?php echo $baseUrl; ?>/pages/perfil/index.php" class="drop-item">
                <i class="bi bi-person"></i> Meu Perfil
            </a>
            <a href="<?php echo $baseUrl; ?>/pages/configuracoes/index.php" class="drop-item">
                <i class="bi bi-gear"></i> Configurações
            </a>
            
            <a href="<?php echo $baseUrl; ?>/logout.php" class="drop-item danger">
                <i class="bi bi-box-arrow-right"></i> Sair do Sistema
            </a>
        </div>
    </div>
</header>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Elementos
    const sidebar       = document.getElementById('sidebar');
    const toggleBtn     = document.getElementById('sidebarToggle');
    const body          = document.body;
    const userBtn       = document.getElementById('userMenuBtn');
    const userContent   = document.getElementById('userMenuContent');
    const backdrop      = document.getElementById('backdrop');

    // --- 1. LÓGICA DE COLAPSAR MENU (DESKTOP) ---
    // Verifica memória
    const isCollapsed = localStorage.getItem('erp_menu_collapsed') === 'true';
    
    // Aplica estado inicial (apenas se não for mobile)
    if (isCollapsed && window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        body.classList.add('menu-collapsed');
    }

    toggleBtn.addEventListener('click', () => {
        // Se for Mobile, abre o menu "overlay"
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('mobile-open');
            backdrop.classList.toggle('show');
        } else {
            // Se for Desktop, colapsa
            sidebar.classList.toggle('collapsed');
            body.classList.toggle('menu-collapsed');
            
            // Salva preferência
            const state = sidebar.classList.contains('collapsed');
            localStorage.setItem('erp_menu_collapsed', state);
        }
    });

    // Fechar menu mobile ao clicar no fundo
    backdrop.addEventListener('click', () => {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('show');
    });

    // --- 2. DROPDOWN DO USUÁRIO ---
    userBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userContent.classList.toggle('show');
    });

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', (e) => {
        if (!userContent.contains(e.target) && !userBtn.contains(e.target)) {
            userContent.classList.remove('show');
        }
    });
});
</script>