

<?php
require_once '../../includes/banco-dados/db.php';
include_once '../../includes/menu.php';
$owner_id = $_SESSION['user_id'];

$sql = "SELECT * FROM vendas WHERE owner_id = :owner_id ORDER BY data_venda DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute([':owner_id' => $owner_id]);
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Vendas</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<main style="padding:32px;">
	<h1>Histórico de Vendas</h1>
	<p>Consulte aqui todas as vendas realizadas.</p>

    <table style="width:100%; border-collapse:collapse; margin-top:24px;">
        <thead>
            <tr style="background:#f3f4f6;">
                <th style="padding:8px; text-align:left;">#</th>
                <th style="padding:8px; text-align:left;">Data</th>
                <th style="padding:8px; text-align:left;">Cliente</th>
                <th style="padding:8px; text-align:left;">Total</th>
                <th style="padding:8px; text-align:left;">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($vendas as $v): ?>
            <tr>
                <td style="padding:8px;">#<?php echo str_pad($v['id'], 6, '0', STR_PAD_LEFT); ?></td>
                <td style="padding:8px;"><?php echo date('d/m/Y H:i', strtotime($v['data_venda'])); ?></td>
                <td style="padding:8px;"><?php echo htmlspecialchars($v['nome_cliente']); ?></td>
                <td style="padding:8px;">R$ <?php echo number_format($v['valor_total'],2,',','.'); ?></td>
                <td style="padding:8px;"><a href="../pdv/pdv_nota.php?id=<?php echo $v['id']; ?>" target="_blank">Ver Recibo</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
