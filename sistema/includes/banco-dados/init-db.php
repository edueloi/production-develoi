<?php

// Caminho absoluto para o arquivo do banco
$dbPath = __DIR__ . '/loja.db';
$dsn    = 'sqlite:' . $dbPath;

try {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cria tabela de usuários, se ainda não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            senha TEXT NOT NULL,
            permissao TEXT NOT NULL DEFAULT 'admin'
        );
    ");

    // Usuário padrão (só se não tiver nenhum usuário ainda)
    $count = (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nome, email, senha, permissao)
            VALUES (:nome, :email, :senha, :permissao)
        ");

        $stmt->execute([
            ':nome'      => 'Administrador',
            ':email'     => 'admin@admin.com',
            ':senha'     => '123456', // depois troca pra hash!
            ':permissao' => 'admin',
        ]);
    }

} catch (PDOException $e) {
    die('Erro ao inicializar o banco de dados: ' . $e->getMessage());
}
