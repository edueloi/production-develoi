
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';
$owner_id = $_SESSION['user_id'];

// Criar tabela comandas se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS comandas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero TEXT NOT NULL,
    status TEXT DEFAULT 'aberta',
    responsavel TEXT,
    data_abertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_fechamento DATETIME,
    owner_id INTEGER NOT NULL
)");

// Excluir comanda
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $pdo->prepare("DELETE FROM comandas WHERE id = ? AND owner_id = ?")->execute([$id, $owner_id]);
    header('Location: index.php');
    exit;
}

// Cadastrar nova comanda
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['numero'])) {
    $numero = trim($_POST['numero']);
    $responsavel = trim($_POST['responsavel']);
    if ($numero) {
        $stmt = $pdo->prepare("INSERT INTO comandas (numero, responsavel, owner_id) VALUES (?, ?, ?)");
        $stmt->execute([$numero, $responsavel, $owner_id]);
        $msg = 'Comanda criada!';
    } else {
        $msg = 'Informe o número da comanda.';
    }
}

// Listar comandas do usuário
$stmt = $pdo->prepare("SELECT * FROM comandas WHERE owner_id = ? ORDER BY status DESC, data_abertura DESC");
$stmt->execute([$owner_id]);
$comandas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comandas / Mesas</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .comandas-table { width:100%; border-collapse:collapse; margin-top:24px; }
        .comandas-table th, .comandas-table td { padding:10px; border-bottom:1px solid #e5e7eb; }
        .comandas-table th { background:#f3f4f6; text-align:left; }
        .status-aberta { color:#10b981; font-weight:600; }
        .status-fechada { color:#ef4444; font-weight:600; }
        .btn-del { color:#ef4444; border:none; background:none; cursor:pointer; }
        .form-comanda { display:flex; gap:10px; margin-top:20px; }
        .form-comanda input { padding:8px; border:1px solid #e5e7eb; border-radius:6px; }
        .form-comanda button { padding:8px 18px; background:#6366f1; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; }
    </style>
</head>
<body>
<main style="padding:32px; max-width:900px; margin:auto;">
    <h1>Comandas / Mesas</h1>
    <p>Gerencie comandas e mesas do estabelecimento.</p>

    <?php if($msg): ?>
        <div style="margin:10px 0; color:#6366f1; font-weight:600;"> <?php echo $msg; ?> </div>
    <?php endif; ?>

    <form method="POST" class="form-comanda">
        <input type="text" name="numero" placeholder="Nº da Comanda ou Mesa" required>
        <input type="text" name="responsavel" placeholder="Responsável (opcional)">
        <button type="submit">Abrir Comanda</button>
    </form>

    <table class="comandas-table">
        <thead>
            <tr>
                <th>Nº</th>
                <th>Status</th>
                <th>Responsável</th>
                <th>Abertura</th>
                <th>Fechamento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($comandas as $c): ?>
            <tr>
                <td><?php echo htmlspecialchars($c['numero']); ?></td>
                <td class="status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></td>
                <td><?php echo htmlspecialchars($c['responsavel']); ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($c['data_abertura'])); ?></td>
                <td><?php echo $c['data_fechamento'] ? date('d/m/Y H:i', strtotime($c['data_fechamento'])) : '-'; ?></td>
                <td>
                    <form method="GET" style="display:inline;">
                        <input type="hidden" name="del" value="<?php echo $c['id']; ?>">
                        <button type="submit" class="btn-del" title="Excluir" onclick="return confirm('Excluir esta comanda?')">Excluir</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
