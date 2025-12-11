<?php
// Arquivo: sistema/atualizar_tudo.php
require_once 'includes/banco-dados/db.php';

try {
    echo "<h2>🛠️ Manutenção do Banco de Dados...</h2>";

    // 1. CORREÇÃO DOS SERVIÇOS (Reset para garantir estrutura nova)
    $pdo->exec("DROP TABLE IF EXISTS servicos");
    $pdo->exec("CREATE TABLE servicos (
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
    )");
    echo "<p style='color:green'>✅ Tabela 'servicos' recriada com sucesso (Erro resolvido).</p>";

    // 2. ATUALIZAÇÃO DOS PRODUTOS (Adicionar Preços)
    // Vamos tentar adicionar as colunas. Se já existirem, o 'catch' ignora.
    $colunasParaAdicionar = [
        "ALTER TABLE produtos ADD COLUMN preco_venda REAL DEFAULT 0",
        "ALTER TABLE produtos ADD COLUMN preco_custo REAL DEFAULT 0",
        "ALTER TABLE produtos_variacoes ADD COLUMN preco_venda REAL DEFAULT 0",
        "ALTER TABLE produtos_variacoes ADD COLUMN preco_custo REAL DEFAULT 0"
    ];

    foreach ($colunasParaAdicionar as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color:blue'>🔹 Coluna de preço adicionada em Produtos.</p>";
        } catch (Exception $e) {
            // Ignora se já existir
        }
    }

    echo "<hr><h3>Tudo pronto! Pode voltar ao painel.</h3>";
    echo "<a href='pages/produtos/produtos.php' style='padding:10px 20px; background:#6366f1; color:white; text-decoration:none; border-radius:5px;'>Ir para Produtos</a>";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>