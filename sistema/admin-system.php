<?php
session_start();
require_once 'includes/banco-dados/init-db.php'; // aqui dentro você já cria $pdo e a tabela usuarios

// SEGURANÇA: só entra aqui se for Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_permissao'] !== 'Admin') {
    header('Location: login.php');
    exit();
}

$msg = '';
$editLoja = null;

// -------------------------------------------------------
// MODO EDIÇÃO: se vier ?edit=ID na URL, carrega dados da loja
// -------------------------------------------------------
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT id, nome, email, validade_conta
        FROM usuarios
        WHERE id = :id AND permissao = 'Dono'
    ");
    $stmt->bindValue(':id', $editId, PDO::PARAM_INT);
    $stmt->execute();
    $editLoja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editLoja) {
        // Se não achar, só volta pro admin-system sem edição
        header('Location: admin-system.php');
        exit();
    }
}

// -------------------------------------------------------
// CRIAR NOVA LOJA (DONO)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_loja'])) {
    $nome  = trim($_POST['nome']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $dias  = (int)($_POST['dias'] ?? 30);

    if ($nome === '' || $email === '' || $senha === '' || $dias <= 0) {
        $msg = "<div class='alert error'>Preencha todos os campos corretamente.</div>";
    } else {
        $validade = date('Y-m-d', strtotime("+$dias days"));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, email, senha, permissao, validade_conta, ativo)
                VALUES (:nome, :email, :senha, 'Dono', :validade, 1)
            ");
            $stmt->bindValue(':nome',     $nome);
            $stmt->bindValue(':email',    $email);
            $stmt->bindValue(':senha',    $senha);
            $stmt->bindValue(':validade', $validade);
            $stmt->execute();

            $msg = "<div class='alert success'>
                        Loja/cliente criado com sucesso! Licença até "
                        . date('d/m/Y', strtotime($validade)) .
                    "</div>";
        } catch (PDOException $e) {
            $msg = "<div class='alert error'>Erro ao criar: e-mail já está em uso.</div>";
        }
    }
}

// -------------------------------------------------------
// EDITAR LOJA (DONO)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_loja'])) {
    $uid      = (int)$_POST['uid'];
    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $validade = trim($_POST['validade'] ?? '');

    if ($nome === '' || $email === '') {
        $msg = "<div class='alert error'>Nome e e-mail são obrigatórios.</div>";
    } else {
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET nome = :nome,
                email = :email,
                validade_conta = :validade
            WHERE id = :id
              AND permissao = 'Dono'
        ");
        $stmt->bindValue(':nome',  $nome);
        $stmt->bindValue(':email', $email);
        // se vier vazio, grava NULL na validade
        if ($validade !== '') {
            $stmt->bindValue(':validade', $validade);
        } else {
            $stmt->bindValue(':validade', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':id',    $uid, PDO::PARAM_INT);
        $stmt->execute();

        header('Location: admin-system.php');
        exit();
    }
}

// -------------------------------------------------------
// DELETAR LOJA (DONO)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deletar_loja'])) {
    $uid = (int)$_POST['uid'];

    $stmt = $pdo->prepare("
        DELETE FROM usuarios
        WHERE id = :id AND permissao = 'Dono'
    ");
    $stmt->bindValue(':id', $uid, PDO::PARAM_INT);
    $stmt->execute();

    header('Location: admin-system.php');
    exit();
}

// -------------------------------------------------------
// ATIVAR / BLOQUEAR LOJA (DONO)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_ativo'])) {
    $uid   = (int)$_POST['uid'];
    $ativo = (int)$_POST['ativo'] === 1 ? 0 : 1;

    $stmt = $pdo->prepare("
        UPDATE usuarios
        SET ativo = :ativo
        WHERE id = :id AND permissao = 'Dono'
    ");
    $stmt->bindValue(':ativo', $ativo, PDO::PARAM_INT);
    $stmt->bindValue(':id',    $uid,   PDO::PARAM_INT);
    $stmt->execute();

    header('Location: admin-system.php');
    exit();
}

// -------------------------------------------------------
// BUSCAR TODAS AS LOJAS (DONOS)
// -------------------------------------------------------
$lojasStmt = $pdo->prepare("
    SELECT id, nome, email, validade_conta, ativo, data_cadastro
    FROM usuarios
    WHERE permissao = 'Dono'
    ORDER BY data_cadastro DESC
");
$lojasStmt->execute();
$lojas = $lojasStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel Super Admin | ERP Loja</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="icon" href="img/favicon.ico">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>

<header class="top-bar">
    <div class="user-info">
        <div class="avatar-circle">
            <?php echo strtoupper(substr($_SESSION['user_nome'], 0, 1)); ?>
        </div>
        <div class="text-info">
            <span class="welcome-text">
                Olá, <?php echo htmlspecialchars($_SESSION['user_nome']); ?>
            </span>
            <span class="role-badge admin">SUPER ADMIN</span>
        </div>
    </div>

    <div class="system-status">
        <div class="badge-status">
            <span class="dot"></span>
            Sistema operacional
        </div>
        <a href="logout.php" class="btn-logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            Sair
        </a>
    </div>
</header>

<main class="container">
    <h2 class="section-title">Gestão de clientes (lojas)</h2>

    <?php echo $msg; ?>

    <!-- Cards de visão rápida -->
    <section class="stats-grid">
        <article class="stat-card">
            <div class="stat-icon bg-blue">
                <i class="fa-solid fa-store"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($lojas); ?></h3>
                <p>Lojas cadastradas</p>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon bg-green">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="stat-info">
                <h3>
                    <?php
                    $ativas = array_filter($lojas, fn($l) => (int)$l['ativo'] === 1);
                    echo count($ativas);
                    ?>
                </h3>
                <p>Lojas ativas</p>
            </div>
        </article>

        <article class="stat-card">
            <div class="stat-icon bg-purple">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo date('d/m'); ?></h3>
                <p>Hoje</p>
            </div>
        </article>
    </section>

    <div class="section-divider"></div>

    <section class="content-split">

        <!-- FORMULÁRIO: cadastrar ou editar loja -->
        <section class="card form-card">
            <header class="card-header">
                <h3>
                    <i class="fa-solid fa-plus-circle"></i>
                    <?php echo $editLoja ? 'Editar cliente (dono de loja)' : 'Cadastrar novo cliente (dono de loja)'; ?>
                </h3>
            </header>

            <?php if ($editLoja): ?>
                <!-- FORMULÁRIO DE EDIÇÃO -->
                <form method="POST">
                    <input type="hidden" name="uid" value="<?php echo (int)$editLoja['id']; ?>">
                    <div class="form-row">
                        <div class="input-group">
                            <label>Nome do responsável</label>
                            <input type="text" name="nome" class="input-field"
                                   value="<?php echo htmlspecialchars($editLoja['nome']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>E-mail de acesso</label>
                            <input type="email" name="email" class="input-field"
                                   value="<?php echo htmlspecialchars($editLoja['email']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>Data de vencimento</label>
                            <input type="date" name="validade" class="input-field"
                                   value="<?php echo $editLoja['validade_conta'] ?: ''; ?>">
                        </div>
                    </div>

                    <button type="submit" name="editar_loja" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar alterações
                    </button>

                    <div style="margin-top:10px;">
                        <a href="admin-system.php" class="btn-secondary" style="display:inline-block;padding:10px 18px;">
                            Cancelar edição
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <!-- FORMULÁRIO DE CRIAÇÃO -->
                <form method="POST">
                    <div class="form-row">
                        <div class="input-group">
                            <label>Nome do responsável</label>
                            <input type="text" name="nome" class="input-field" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>E-mail de acesso</label>
                            <input type="email" name="email" class="input-field" required>
                        </div>
                        <div class="input-group">
                            <label>Senha provisória</label>
                            <input type="text" name="senha" value="123456" class="input-field" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>Dias de licença</label>
                            <input type="number" name="dias" value="30" min="1" class="input-field">
                        </div>
                    </div>

                    <button type="submit" name="criar_loja" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Criar loja
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <!-- TABELA: lojas cadastradas -->
        <section class="card">
            <header class="card-header">
                <h3>
                    <i class="fa-solid fa-list"></i>
                    Lojas cadastradas
                </h3>
            </header>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Cliente / Loja</th>
                        <th>E-mail</th>
                        <th>Cadastro</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($lojas)) : ?>
                        <tr>
                            <td colspan="6">Nenhuma loja cadastrada ainda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lojas as $loja): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="mini-avatar">
                                            <?php echo strtoupper(substr($loja['nome'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($loja['nome']); ?></strong>
                                            <div class="tag">Dono</div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($loja['email']); ?></td>
                                <td>
                                    <?php
                                    echo $loja['data_cadastro']
                                        ? date('d/m/Y', strtotime($loja['data_cadastro']))
                                        : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo $loja['validade_conta']
                                        ? date('d/m/Y', strtotime($loja['validade_conta']))
                                        : 'Sem limite';
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $loja['ativo'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $loja['ativo'] ? 'Ativa' : 'Bloqueada'; ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <!-- Ativar / Bloquear -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="uid"   value="<?php echo $loja['id']; ?>">
                                        <input type="hidden" name="ativo" value="<?php echo $loja['ativo']; ?>">
                                        <button type="submit" name="toggle_ativo"
                                                class="icon-btn <?php echo $loja['ativo'] ? 'danger' : 'success'; ?>"
                                                title="<?php echo $loja['ativo'] ? 'Bloquear' : 'Ativar'; ?>">
                                            <?php if ($loja['ativo']) : ?>
                                                <i class="fa-solid fa-ban"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-unlock"></i>
                                            <?php endif; ?>
                                        </button>
                                    </form>

                                    <!-- Editar -->
                                    <a href="admin-system.php?edit=<?php echo $loja['id']; ?>"
                                       class="icon-btn"
                                       title="Editar"
                                       style="background:#eff6ff;color:#1d4ed8;margin-left:4px;">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>

                                    <!-- Deletar -->
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Tem certeza que deseja deletar esta loja?');">
                                        <input type="hidden" name="uid" value="<?php echo $loja['id']; ?>">
                                        <button type="submit" name="deletar_loja"
                                                class="icon-btn danger" title="Deletar" style="margin-left:4px;">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </section>
</main>

</body>
</html>
