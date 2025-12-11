<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: admin-system.php');
    exit();
}
require_once 'includes/banco-dados/init-db.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Store</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" href="../img/favicon.ico">
</head>
<body>

    <div class="main-wrapper">
        
        <div class="visual-side">
            <div class="visual-content">
                <h1>Gerencie tudo<br>em um só lugar.</h1>
                <p>Controle de estoque, PDV e produção com a eficiência que o seu negócio merece.</p>
            </div>
        </div>

        <div class="login-side">
            <div class="login-container">
                
                <div class="mobile-logo">ERP SYSTEM</div>

                <div class="login-header">
                    <div class="login-title">Bem-vindo</div>
                    <div class="login-subtitle">Insira suas credenciais para continuar</div>
                </div>

                <form class="login-form" method="POST" action="login.php">
                    <div class="input-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="nome@empresa.com" required autocomplete="username">
                    </div>
                    
                    <div class="input-group">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="login-btn">Entrar</button>
                    
                    <a href="#" class="forgot-link">Esqueci minha senha</a>
                </form>

                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $email = $_POST['email'] ?? '';
                    $senha = $_POST['senha'] ?? '';
                    
                    // Conexão e verificação (Lógica mantida)
                    $dbPath = __DIR__ . '/includes/banco-dados/loja.db';
                    $dsn    = 'sqlite:' . $dbPath;

                    try {
                        $pdo = new PDO($dsn);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $stmt = $pdo->prepare('SELECT id, nome, permissao FROM usuarios WHERE email = :email AND senha = :senha');
                        $stmt->bindValue(':email', $email);
                        $stmt->bindValue(':senha', $senha);
                        $stmt->execute();
                        
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_nome'] = $user['nome'];
                            $_SESSION['user_permissao'] = $user['permissao'];
                            header('Location: admin-system.php');
                            exit();
                        } else {
                            echo '<div class="error-msg">Acesso negado. Verifique seus dados.</div>';
                        }
                    } catch (PDOException $e) {
                        echo '<div class="error-msg">Erro interno: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

</body>
</html>