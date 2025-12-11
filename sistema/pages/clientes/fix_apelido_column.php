<?php
// Script para adicionar a coluna 'apelido' na tabela clientes, se não existir
try {
    $dbPath = __DIR__ . '/../../includes/banco-dados/loja.db';
    if (!file_exists($dbPath)) {
        $dbPath = __DIR__ . '/../../banco-dados/loja.db'; // fallback
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verifica se a coluna já existe
    $result = $pdo->query("PRAGMA table_info(clientes)");
    $colExists = false;
    foreach ($result as $col) {
        if (strtolower($col['name']) === 'apelido') {
            $colExists = true;
            break;
        }
    }
    if (!$colExists) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN apelido TEXT");
        echo "Coluna 'apelido' adicionada com sucesso!\n";
    } else {
        echo "Coluna 'apelido' já existe.\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
