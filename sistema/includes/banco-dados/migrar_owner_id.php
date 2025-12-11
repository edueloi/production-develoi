<?php
// MIGRAÇÃO: Adiciona owner_id nas tabelas principais para multi-tenant
require_once __DIR__ . '/db.php';

function addColumnIfNotExists($pdo, $table, $column, $type) {
    $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        if ($col['name'] === $column) return;
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
}

addColumnIfNotExists($pdo, 'produtos', 'owner_id', 'INTEGER NOT NULL DEFAULT 1');
addColumnIfNotExists($pdo, 'produtos_variacoes', 'owner_id', 'INTEGER NOT NULL DEFAULT 1');
addColumnIfNotExists($pdo, 'clientes', 'owner_id', 'INTEGER NOT NULL DEFAULT 1');
addColumnIfNotExists($pdo, 'vendas', 'owner_id', 'INTEGER NOT NULL DEFAULT 1');
addColumnIfNotExists($pdo, 'vendas_itens', 'owner_id', 'INTEGER NOT NULL DEFAULT 1');
addColumnIfNotExists($pdo, 'servicos', 'owner_id', 'INTEGER NOT NULL DEFAULT 1');

// Atualiza registros existentes para owner_id = 1 (ou outro valor padrão)
$tables = ['produtos','produtos_variacoes','clientes','vendas','vendas_itens','servicos'];
foreach ($tables as $t) {
    $pdo->exec("UPDATE $t SET owner_id = 1 WHERE owner_id IS NULL OR owner_id = ''");
}

echo "Migração concluída. owner_id adicionado e populado.";
