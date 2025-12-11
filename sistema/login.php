<?php
session_start();
require_once 'includes/banco-dados/init-db.php';

// Se já estiver logado, redireciona direto conforme a permissão
if (isset($_SESSION['user_id']) && isset($_SESSION['user_permissao'])) {
    if ($_SESSION['user_permissao'] === 'Admin') {
        header('Location: admin-system.php');
        exit();
    } else {
        header('Location: pages/painel/index.php');
        exit();
    }
}

$errorMsg = '';
$email = '';
$senha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    // ==== LOGIN FIXO PARA SUPER ADMIN ====
    if ($email === 'admin@admin.com' && $senha === '123456') {
        $_SESSION['user_id']        = 1;
        $_SESSION['user_nome']      = 'Admin';
        $_SESSION['user_permissao'] = 'Admin';
        $_SESSION['owner_id']       = null;

        header('Location: admin-system.php');
        exit();
    }

    // ==== LOGIN NORMAL (Dono / Vendedor) ====
    try {
        // $pdo vem do init-db.php
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = :email AND senha = :senha');
        $stmt->execute([
            ':email' => $email,
            ':senha' => $senha
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Conta inativa
            if ((int)$user['ativo'] === 0) {
                $errorMsg = 'Conta inativa. Entre em contato com o suporte ou administrador.';
            }
            // Licença vencida (não vale para Admin do banco, se algum dia existir)
            elseif ($user['permissao'] !== 'Admin'
                && !empty($user['validade_conta'])
                && strtotime($user['validade_conta']) < time()
            ) {
                $errorMsg = 'Sua licença expirou em ' .
                    date('d/m/Y', strtotime($user['validade_conta'])) . '.';
            } else {
                // Login OK
                $_SESSION['user_id']        = (int)$user['id'];
                $_SESSION['user_nome']      = $user['nome'];
                $_SESSION['user_permissao'] = $user['permissao']; // Dono ou Vendedor
                $_SESSION['owner_id']       = $user['owner_id'];

                // Todo mundo que não é o admin fixo vai para o painel padrão
                header('Location: pages/painel/index.php');
                exit();
            }
        } else {
            $errorMsg = 'E-mail ou senha incorretos.';
        }
    } catch (PDOException $e) {
        // Se quiser logar o erro em arquivo depois, aqui é o ponto
        $errorMsg = 'Erro interno ao tentar fazer login. Tente novamente mais tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Acesso Seguro | ERP Loja</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" href="img/favicon.ico">
</head>
<body>

<div class="main-wrapper">
    <!-- Lado visual (desktop) -->
    <div class="visual-side">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>

        <div class="visual-content">
            <h1>O futuro da sua<br>loja começa aqui.</h1>
            <p>Sistema moderno de gestão, controle de estoque, vendas e PDV em um único painel.</p>
        </div>
    </div>

    <!-- Lado do login -->
    <div class="login-side">
        <div class="login-container">
            <div class="mobile-logo">ERP LOJA</div>

            <div class="login-header">
                <h2 class="login-title">Bem-vindo</h2>
                <p class="login-subtitle">Acesse com seu e-mail e senha cadastrados</p>
            </div>

            <form class="login-form" method="POST" action="login.php">
                <div class="input-group">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="input-field"
                        placeholder=" "
                        required
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($email); ?>"
                    >
                    <label for="email" class="input-label">E-mail corporativo</label>
                </div>

                <div class="input-group">
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        class="input-field"
                        placeholder=" "
                        required
                        autocomplete="current-password"
                    >
                    <label for="senha" class="input-label">Sua senha</label>

                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>

                <button type="submit" class="login-btn">Acessar painel</button>

                <a href="#" class="forgot-link">Esqueci minha senha</a>
            </form>

            <?php if (!empty($errorMsg)) : ?>
                <div class="error-msg">
                    <?php echo htmlspecialchars($errorMsg); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function togglePassword() {
        const senhaInput = document.getElementById('senha');
        const eyeIcon = document.getElementById('eye-icon');

        if (senhaInput.type === 'password') {
            senhaInput.type = 'text';
            eyeIcon.style.color = '#3b82f6';
        } else {
            senhaInput.type = 'password';
            eyeIcon.style.color = '#94a3b8';
        }
    }
</script>
</body>
</html>
