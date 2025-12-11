<?php
// Adiciona preco e desconto na tabela produtos se não existirem
require_once __DIR__ . '/db.php';

$colunas = [
    'preco' => 'REAL DEFAULT 0',
    'desconto' => 'REAL DEFAULT 0'
];

$result = $pdo->query("PRAGMA table_info(produtos)");
$existentes = array();
foreach ($result as $col) {
    $existentes[] = strtolower($col['name']);
}
$alteradas = [];
foreach ($colunas as $coluna => $tipo) {
    if (!in_array(strtolower($coluna), $existentes)) {
        $pdo->exec("ALTER TABLE produtos ADD COLUMN $coluna $tipo");
        $alteradas[] = $coluna;
    }
}
if ($alteradas) {
    echo "Colunas adicionadas: " . implode(', ', $alteradas) . "\n";
} else {
    echo "Todas as colunas já existem.\n";
}
