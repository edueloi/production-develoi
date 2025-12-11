<?php
require_once __DIR__ . '/db.php';

// Verifica se a coluna preco já existe
$colCheck = $pdo->query("PRAGMA table_info(produtos_variacoes)");
$colExists = false;
foreach ($colCheck as $col) {
    if ($col['name'] === 'preco') {
        $colExists = true;
        break;
    }
}
if (!$colExists) {
    $pdo->exec("ALTER TABLE produtos_variacoes ADD COLUMN preco REAL DEFAULT 0");
    echo "Coluna preco adicionada em produtos_variacoes!\n";
} else {
    echo "Coluna preco já existe em produtos_variacoes.\n";
}
