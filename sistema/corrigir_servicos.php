<?php
// Arquivo: sistema/corrigir_servicos.php
// Objetivo: Resetar a tabela de serviços para corrigir o erro de coluna faltante

require_once 'includes/banco-dados/db.php';

try {
    echo "<h2>Reparando Tabela de Serviços...</h2>";

    // 1. Apagar a tabela antiga (que está com defeito)
    $pdo->exec("DROP TABLE IF EXISTS servicos");
    echo "<p style='color:orange'>🗑️ Tabela antiga removida.</p>";

    // 2. Criar a tabela nova com TODAS as colunas certas (Light & Clean)
    $sql = "CREATE TABLE servicos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        categoria TEXT,
        descricao TEXT,
        duracao_minutos INTEGER,
        preco REAL,
        imagem TEXT,
        mostrar_site INTEGER DEFAULT 0,
        ativo INTEGER DEFAULT 1,
        data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✅ Nova tabela criada com as colunas corretas (mostrar_site, imagem, etc).</p>";

    // 3. Inserir um serviço de exemplo para testar
    $stmt = $pdo->prepare("INSERT INTO servicos (nome, categoria, descricao, preco, mostrar_site) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Corte de Cabelo', 'Cabelo', 'Corte masculino moderno com acabamento.', 50.00, 1]);
    echo "<p style='color:blue'>📝 Serviço de teste inserido.</p>";

    echo "<hr>";
    echo "<h3 style='color:green'>Tudo pronto! O erro sumiu.</h3>";
    echo "<a href='pages/servicos/index.php' style='background:#4f46e5; color:white; padding:10px 20px; text-decoration:none; border-radius:8px;'>Voltar para Meus Serviços</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Erro Crítico: " . $e->getMessage() . "</h3>";
    echo "<p>Verifique se o arquivo do banco de dados (loja.db) tem permissão de escrita.</p>";
}
?>