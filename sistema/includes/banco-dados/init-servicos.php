<?php
require_once __DIR__ . '/db.php';

// Criação da tabela servicos se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS servicos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    descricao TEXT,
    preco REAL DEFAULT 0,
    ativo INTEGER DEFAULT 1,
    mostrar_site INTEGER DEFAULT 1,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

echo "Tabela servicos criada/ajustada com sucesso!";
