<?php
// Script para criar a tabela clientes com owner_id para multi-tenant
require_once __DIR__ . '/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id INTEGER NOT NULL,
    nome TEXT NOT NULL,
    cpf TEXT,
    email TEXT,
    telefone TEXT,
    endereco TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

echo "Tabela clientes criada/ajustada com sucesso!";
