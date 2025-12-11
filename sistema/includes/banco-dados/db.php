<?php
// Conexão (ajuste o caminho se necessário)
$dbPath = __DIR__ . '/loja.db'; 
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON;');

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
    tem_validade INTEGER DEFAULT 0, -- 0: Não, 1: Sim
    data_validade DATE,
    ativo INTEGER DEFAULT 1,
    auto_desativar INTEGER DEFAULT 0, -- Se estoque acabar, desativa
    imagem_capa TEXT,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 2. Tabela de Variações (Ex: Camiseta Preta M, Camiseta Branca G)
$pdo->exec("CREATE TABLE IF NOT EXISTS produtos_variacoes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    produto_id INTEGER,
    cor TEXT,
    tamanho TEXT,
    peso REAL,
    estoque INTEGER DEFAULT 0,
    FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
)");

// 3. Tabela de Galeria de Imagens
$pdo->exec("CREATE TABLE IF NOT EXISTS produtos_galeria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    produto_id INTEGER,
    caminho_imagem TEXT,
    FOREIGN KEY(produto_id) REFERENCES produtos(id) ON DELETE CASCADE
)");



// 4. Tabela de Serviços (garante owner_id)
$pdo->exec("CREATE TABLE IF NOT EXISTS servicos (
    id INTEGER PRIMARY KEY AUTOINCREMENT
    -- outras colunas serão adicionadas conforme necessário
)");
// Só adiciona a coluna owner_id se ela não existir
$colunasServicos = $pdo->query("PRAGMA table_info(servicos)")->fetchAll(PDO::FETCH_ASSOC);
$temOwnerId = false;
foreach ($colunasServicos as $col) {
    if (strtolower($col['name']) === 'owner_id') {
        $temOwnerId = true;
        break;
    }
}
if (!$temOwnerId) {
    $pdo->exec("ALTER TABLE servicos ADD COLUMN owner_id INTEGER");
}

?>