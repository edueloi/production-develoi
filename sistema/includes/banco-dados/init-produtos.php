<?php
require_once __DIR__ . '/db.php';

// 1. Tabela Principal de Produtos
$pdo->exec("CREATE TABLE IF NOT EXISTS produtos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT NOT NULL,
    marca TEXT,
    modelo TEXT,
    descricao TEXT,
    tipo_produto TEXT,
    codigo_barras TEXT,
    estoque_minimo INTEGER DEFAULT 5,
    tem_validade INTEGER DEFAULT 0,
    data_validade DATE,
    ativo INTEGER DEFAULT 1,
    auto_desativar INTEGER DEFAULT 0,
    imagem_capa TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 2. Tabela de Variações
$pdo->exec("CREATE TABLE IF NOT EXISTS produtos_variacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    produto_id INTEGER,
    cor TEXT,
    tamanho TEXT,
    peso REAL,
    estoque INTEGER DEFAULT 0,
    FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
)");

// 3. Tabela de Galeria
$pdo->exec("CREATE TABLE IF NOT EXISTS produtos_galeria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    produto_id INTEGER,
    caminho_imagem TEXT,
    FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
)");

echo "Tabelas de produtos criadas com sucesso!";
