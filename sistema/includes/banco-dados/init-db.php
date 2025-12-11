<?php
// Define o caminho do banco de dados (na mesma pasta deste arquivo)
$dbPath = __DIR__ . '/loja.db';

try {
    // Conecta ao SQLite (cria o arquivo se não existir)
    $pdo = new PDO('sqlite:' . $dbPath);
    
    // Ativa o modo de erros para avisar se algo der errado
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


    // --- CRIAÇÃO DA TABELA DE USUÁRIOS (ESTRUTURA SAAS) ---
    // owner_id: Define quem é o chefe (NULL = Super Admin ou Dono de Loja)
    // validade_conta: Data que o sistema bloqueia o acesso (para cobrar mensalidade)
    $query = "CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        owner_id INTEGER DEFAULT NULL,
        nome TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        senha TEXT NOT NULL,
        permissao TEXT NOT NULL,
        ativo INTEGER DEFAULT 1,
        data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($query);

    // --- MIGRAÇÃO: Adiciona coluna validade_conta se não existir ---
    $colCheck = $pdo->query("PRAGMA table_info(usuarios)");
    $colExists = false;
    foreach ($colCheck as $col) {
        if ($col['name'] === 'validade_conta') {
            $colExists = true;
            break;
        }
    }
    if (!$colExists) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN validade_conta DATE DEFAULT NULL");
    }

    // --- CRIA O SUPER ADMIN (VOCÊ) SE NÃO EXISTIR ---
    // Verifica se já existe algum Admin no sistema
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE permissao = 'Admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        // Se não existir, cria o Admin Padrão
        // Login: admin@admin.com
        // Senha: 123456
        $pdo->exec("INSERT INTO usuarios (nome, email, senha, permissao, ativo) 
                    VALUES ('Administrador', 'admin@admin.com', '123456', 'Admin', 1)");
    }

} catch (PDOException $e) {
    // Se der erro, para tudo e mostra a mensagem
    die("Erro crítico ao iniciar banco de dados: " . $e->getMessage());
}
?>