<?php
session_start();

// Limpa todas as variáveis da sessão
$_SESSION = [];

// Destroi a sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Volta pro login
header('Location: login.php');
exit();
