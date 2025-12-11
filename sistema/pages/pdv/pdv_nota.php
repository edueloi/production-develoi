<?php
// ==========================================
// RECIBO DE VENDA (CUPOM NÃO FISCAL)
// ==========================================
require_once '../../includes/banco-dados/db.php';

if (!isset($_GET['id'])) die("Venda não especificada.");
$id = (int)$_GET['id'];

// Buscar Dados da Venda
$stmt = $pdo->prepare("SELECT * FROM vendas WHERE id = ?");
$stmt->execute([$id]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venda) die("Venda não encontrada.");

// Buscar Itens
$stmtItens = $pdo->prepare("SELECT * FROM vendas_itens WHERE venda_id = ?");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

// Configurações da Loja (Pode vir do banco futuramente)
$lojaNome = "Lumina Store";
$lojaEnd  = "Rua Exemplo, 123 - Centro, SP";
$lojaTel  = "(11) 99999-9999";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Recibo #<?php echo str_pad($id, 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; background: #eee; padding: 20px; }
        
        .cupom-wrapper {
            background: #fff;
            width: 300px; /* Largura padrão de impressora térmica */
            margin: 0 auto;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-bottom: 1px dashed #000; margin: 10px 0; }
        
        .header h2 { margin: 0; font-size: 1.2rem; }
        .header p { margin: 2px 0; font-size: 0.8rem; }

        .info-venda { font-size: 0.85rem; margin: 10px 0; }
        
        table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        td { padding: 4px 0; vertical-align: top; }
        
        .totais { font-size: 0.9rem; margin-top: 10px; }
        .totais .row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .grande { font-size: 1.1rem; font-weight: bold; }

        .footer { margin-top: 20px; text-align: center; font-size: 0.75rem; }

        /* Botão de imprimir (some na impressão) */
        .btn-print {
            display: block; width: 100%; padding: 10px; 
            background: #6366f1; color: white; text-align: center; 
            text-decoration: none; border-radius: 5px; margin-top: 20px;
            font-family: sans-serif; font-weight: bold;
        }

        @media print {
            body { background: white; padding: 0; }
            .cupom-wrapper { box-shadow: none; margin: 0; width: 100%; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="cupom-wrapper">
        <div class="header text-center">
            <h2><?php echo $lojaNome; ?></h2>
            <p><?php echo $lojaEnd; ?></p>
            <p>Tel: <?php echo $lojaTel; ?></p>
        </div>

        <div class="line"></div>

        <div class="info-venda">
            <div><strong>Venda:</strong> #<?php echo str_pad($venda['id'], 6, '0', STR_PAD_LEFT); ?></div>
            <div><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($venda['data_venda'])); ?></div>
            <?php if($venda['nome_cliente']): ?>
                <div><strong>Cliente:</strong> <?php echo $venda['nome_cliente']; ?></div>
            <?php endif; ?>
            <?php if($venda['cpf_cliente']): ?>
                <div><strong>CPF:</strong> <?php echo $venda['cpf_cliente']; ?></div>
            <?php endif; ?>
        </div>

        <div class="line"></div>

        <table>
            <thead>
                <tr style="text-align:left;">
                    <th colspan="2">Item</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($itens as $item): ?>
                <tr>
                    <td colspan="3">
                        <?php echo $item['nome_produto']; ?>
                        <br>
                        <small><?php echo $item['cor'].' '.$item['tamanho']; ?></small>
                    </td>
                </tr>
                <tr>
                    <td><?php echo $item['quantidade']; ?>x</td>
                    <td>R$ <?php echo number_format($item['preco_unit'], 2, ',', '.'); ?></td>
                    <td class="text-right">R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="line"></div>

        <div class="totais">
            <div class="row">
                <span>Forma Pagto:</span>
                <span><?php echo ucfirst(str_replace('_', ' ', $venda['forma_pagamento'])); ?></span>
            </div>
            <?php if($venda['forma_pagamento'] == 'dinheiro'): ?>
                <div class="row">
                    <span>Recebido:</span>
                    <span>R$ <?php echo number_format($venda['valor_recebido'], 2, ',', '.'); ?></span>
                </div>
                <div class="row">
                    <span>Troco:</span>
                    <span>R$ <?php echo number_format($venda['troco'], 2, ',', '.'); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="line"></div>
            
            <div class="row grande">
                <span>TOTAL A PAGAR</span>
                <span>R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></span>
            </div>
        </div>

        <div class="footer">
            <p>Obrigado pela preferência!</p>
            <p>Volte sempre.</p>
            <br>
            <small>Documento sem valor fiscal</small>
        </div>

        <a href="#" onclick="window.print(); return false;" class="btn-print">IMPRIMIR CUPOM</a>
    </div>

</body>
</html>