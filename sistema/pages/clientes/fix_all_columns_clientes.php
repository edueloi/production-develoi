<?php
// Script para garantir que todas as colunas necessárias existem na tabela clientes
try {
    $dbPath = __DIR__ . '/../../includes/banco-dados/loja.db';
    if (!file_exists($dbPath)) {
        $dbPath = __DIR__ . '/../../banco-dados/loja.db'; // fallback
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Lista completa das colunas usadas no sistema
    $colunas = [
        'apelido' => 'TEXT',
        'telefone' => 'TEXT',
        'whatsapp' => 'TEXT',
        'email' => 'TEXT',
        'cpf' => 'TEXT',
        'rg' => 'TEXT',
        'data_nascimento' => 'DATE',
        'cep' => 'TEXT',
        'logradouro' => 'TEXT',
        'numero' => 'TEXT',
        'complemento' => 'TEXT',
        'bairro' => 'TEXT',
        'cidade' => 'TEXT',
        'uf' => 'TEXT',
        'observacoes' => 'TEXT',
        'ativo' => 'INTEGER DEFAULT 1',
        'owner_id' => 'INTEGER',
        'data_cadastro' => 'DATETIME DEFAULT CURRENT_TIMESTAMP'
    ];
    $result = $pdo->query("PRAGMA table_info(clientes)");
    $existentes = array();
    foreach ($result as $col) {
        $existentes[] = strtolower($col['name']);
    }
    $alteradas = [];
    foreach ($colunas as $coluna => $tipo) {
        if (!in_array(strtolower($coluna), $existentes)) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN $coluna $tipo");
            $alteradas[] = $coluna;
        }
    }
    if ($alteradas) {
        echo "Colunas adicionadas: " . implode(', ', $alteradas) . "\n";
    } else {
        echo "Todas as colunas já existem.\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
