<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_permissao'] !== 'Admin') {
	header('Location: login.php');
	exit();
}
require_once 'includes/banco-dados/init-db.php';
$db = new SQLite3('includes/banco-dados/loja.db');
$admin_id = $_SESSION['user_id'];

// Cadastro de novo usuário
$msg = '';
if (isset($_POST['novo_usuario'])) {
	$nome = $_POST['nome'] ?? '';
	$email = $_POST['email'] ?? '';
	$senha = $_POST['senha'] ?? '';
	$permissao = $_POST['permissao'] ?? 'Usuario';
	$tempo_limite = $_POST['tempo_limite'] ?? 30;
	// Verifica limite de 5 usuários
	$count = $db->querySingle("SELECT COUNT(*) FROM usuarios WHERE id != $admin_id AND permissao != 'Admin' AND ativo = 1");
	if ($count >= 5) {
		$msg = '<div style="color:#e57373;">Limite de 5 usuários atingido.</div>';
	} else {
		$stmt = $db->prepare('INSERT INTO usuarios (nome, email, senha, permissao, tempo_limite) VALUES (:nome, :email, :senha, :permissao, :tempo_limite)');
		$stmt->bindValue(':nome', $nome, SQLITE3_TEXT);
		$stmt->bindValue(':email', $email, SQLITE3_TEXT);
		$stmt->bindValue(':senha', $senha, SQLITE3_TEXT);
		$stmt->bindValue(':permissao', $permissao, SQLITE3_TEXT);
		$stmt->bindValue(':tempo_limite', $tempo_limite, SQLITE3_INTEGER);
		if ($stmt->execute()) {
			$msg = '<div style="color:#43a047;">Usuário cadastrado com sucesso!</div>';
		} else {
			$msg = '<div style="color:#e57373;">Erro ao cadastrar usuário.</div>';
		}
	}
}

// Ativar/Inativar usuário
if (isset($_POST['toggle_ativo'])) {
	$uid = $_POST['uid'];
	$ativo = $_POST['ativo'] ? 0 : 1;
	$db->exec("UPDATE usuarios SET ativo = $ativo WHERE id = $uid");
}

// Histórico de acessos (simples: data de cadastro)
$usuarios = $db->query("SELECT * FROM usuarios WHERE id != $admin_id AND permissao != 'Admin'");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Painel Admin - Sistema Loja</title>
	<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
	<div class="header">
		Painel do Administrador
		<div>
			<span><?php echo $_SESSION['user_nome']; ?> (Admin)</span>
			<a href="logout.php" class="btn" style="margin-left:18px;">Logout</a>
		</div>
	</div>
	<div class="menu">
		<a href="#" class="active">Usuários</a>
		<a href="#">Configurações</a>
		<a href="#">Histórico</a>
	</div>
	<div class="container">
		<h2 style="color:#90caf9;">Cadastrar Novo Usuário</h2>
		<?php echo $msg; ?>
		<form method="POST" style="margin-bottom:32px;">
			<div class="input-group">
				<label for="nome">Nome</label>
				<input type="text" name="nome" id="nome" required>
			</div>
			<div class="input-group">
				<label for="email">Email</label>
				<input type="email" name="email" id="email" required>
			</div>
			<div class="input-group">
				<label for="senha">Senha</label>
				<input type="password" name="senha" id="senha" required>
			</div>
			<div class="input-group">
				<label for="permissao">Permissão</label>
				<select name="permissao" id="permissao">
					<option value="Usuario">Usuário</option>
					<option value="Subadmin">Subadmin</option>
				</select>
			</div>
			<div class="input-group">
				<label for="tempo_limite">Tempo de acesso (dias)</label>
				<input type="number" name="tempo_limite" id="tempo_limite" min="1" max="60" value="30">
			</div>
			<button type="submit" name="novo_usuario" class="btn">Cadastrar</button>
		</form>
		<h2 style="color:#90caf9;">Usuários Cadastrados</h2>
		<table class="table">
			<tr>
				<th>Nome</th>
				<th>Email</th>
				<th>Permissão</th>
				<th>Data Cadastro</th>
				<th>Tempo Limite</th>
				<th>Status</th>
				<th>Ações</th>
			</tr>
			<?php while ($u = $usuarios->fetchArray(SQLITE3_ASSOC)) { ?>
			<tr>
				<td><?php echo htmlspecialchars($u['nome']); ?></td>
				<td><?php echo htmlspecialchars($u['email']); ?></td>
				<td><?php echo $u['permissao']; ?></td>
				<td><?php echo date('d/m/Y', strtotime($u['data_cadastro'])); ?></td>
				<td><?php echo $u['tempo_limite']; ?> dias</td>
				<td class="<?php echo $u['ativo'] ? 'status-active' : 'status-inactive'; ?>">
					<?php echo $u['ativo'] ? 'Ativo' : 'Inativo'; ?>
				</td>
				<td>
					<form method="POST" style="display:inline;">
						<input type="hidden" name="uid" value="<?php echo $u['id']; ?>">
						<input type="hidden" name="ativo" value="<?php echo $u['ativo']; ?>">
						<button type="submit" name="toggle_ativo" class="btn" style="padding:4px 12px; font-size:0.9rem;">
							<?php echo $u['ativo'] ? 'Inativar' : 'Ativar'; ?>
						</button>
					</form>
				</td>
			</tr>
			<?php } ?>
		</table>
	</div>
</body>
</html>
