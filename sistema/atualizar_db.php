<?php
// Arquivo: sistema/atualizar_db.php
require_once 'includes/banco-dados/db.php';

try {
    echo "<h2>Atualizando Banco de Dados...</h2>";

    // 1. Adicionar coluna 'mostrar_site'
    try {
        $pdo->exec("ALTER TABLE servicos ADD COLUMN mostrar_site INTEGER DEFAULT 0");
        echo "<p style='color:green'>✔ Coluna 'mostrar_site' adicionada.</p>";
    } catch (Exception $e) {
        echo "<p style='color:gray'>• Coluna 'mostrar_site' já existia.</p>";
    }

    // 2. Adicionar coluna 'imagem'
    try {
        $pdo->exec("ALTER TABLE servicos ADD COLUMN imagem TEXT");
        echo "<p style='color:green'>✔ Coluna 'imagem' adicionada.</p>";
    } catch (Exception $e) {
        echo "<p style='color:gray'>• Coluna 'imagem' já existia.</p>";
    }

    // 3. Adicionar coluna 'categoria'
    try {
        $pdo->exec("ALTER TABLE servicos ADD COLUMN categoria TEXT");
        echo "<p style='color:green'>✔ Coluna 'categoria' adicionada.</p>";
    } catch (Exception $e) {
        echo "<p style='color:gray'>• Coluna 'categoria' já existia.</p>";
    }

    // 4. Adicionar coluna 'duracao_minutos'
    try {
        $pdo->exec("ALTER TABLE servicos ADD COLUMN duracao_minutos INTEGER");
        echo "<p style='color:green'>✔ Coluna 'duracao_minutos' adicionada.</p>";
    } catch (Exception $e) {
        echo "<p style='color:gray'>• Coluna 'duracao_minutos' já existia.</p>";
    }

    echo "<h3>Atualização concluída! Pode voltar ao sistema.</h3>";
    echo "<a href='pages/servicos/index.php'>Voltar para Serviços</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Erro Crítico: " . $e->getMessage() . "</h3>";
}
?>