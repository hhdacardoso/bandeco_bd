<?php
session_start();

// Se não houver sessão ativa, volta para o login
if (!isset($_SESSION["carteirinha"])) {
    header("Location: login.php");
    exit;
}

$dsn  = "pgsql:host=localhost;port=5432;dbname=bandeco";
$user = "postgres";
$pass = "teste123";

$carteirinha = $_SESSION["carteirinha"];
$nome = $_SESSION["nome"];
$saldo_atual = 0.00;

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Busca o saldo em tempo real direto do banco de dados
    $stmt = $pdo->prepare("SELECT SALDO FROM USUARIO WHERE Num_Carteirinha = :carteirinha");
    $stmt->execute(["carteirinha" => $carteirinha]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $saldo_atual = $usuario["saldo"];
    }

    // Busca o histórico de acessos (refeições consumidas) do aluno
    $stmt_acesso = $pdo->prepare("SELECT Data, Hora, Valor FROM ACESSO WHERE Num_Carteirinha = :carteirinha ORDER BY Data DESC, Hora DESC LIMIT 5");
    $stmt_acesso->execute(["carteirinha" => $carteirinha]);
    $historico_acessos = $stmt_acesso->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erro técnico: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Bandejão - Painel do Aluno</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f4; margin: 0; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #2e437d; padding-bottom: 1rem; margin-bottom: 2rem; }
        .header h1 { margin: 0; color: #2e3096; font-size: 1.5rem; }
        .btn-logout { background: #b3261e; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; }
        .cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: #f9f9f9; padding: 1.5rem; border-radius: 6px; border-left: 5px solid #2e437d; }
        .card h2 { margin-top: 0; font-size: 1.1rem; color: #555; }
        .saldo-valor { font-size: 2rem; font-weight: bold; color: #2e437d; margin: 0.5rem 0; }
        .actions { display: flex; gap: 1rem; }
        .btn-action { flex: 1; text-align: center; background: #2e437d; color: white; padding: 0.7rem; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .btn-action:hover { background: #2e2769; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #eee; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Olá, <?= htmlspecialchars($nome) ?>!</h1>
            <small>Carteirinha: <?= htmlspecialchars($carteirinha) ?> | Perfil: <?= ucfirst($_SESSION["tipo"]) ?></small>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
        <?php if (count($_SESSION["tipos"]) > 1): ?>
            <a href="selecionar_perfil.php" class="btn-alternar">Alternar Perfil</a>
        <?php endif; ?>
        
        <a href="logout.php" class="btn-logout">Sair</a>
    </div>
    
</div>
    

    <div class="cards-grid">
        <div class="card">
            <h2>Saldo Disponível</h2>
            <div class="saldo-valor">R$ <?= number_format($saldo_atual, 2, ',', '.') ?></div>
        </div>
        <div class="card">
            <h2>Ações Rápidas</h2>
            <div class="actions" style="margin-top: 1rem;">
                <a href="recarregar.php" class="btn-action">Fazer Recarga</a>
                <a href="transferir.php" class="btn-action">Transferir Saldo</a>
            </div>
        </div>
    </div>

    <h2>Últimos Acessos ao Refeitório</h2>
    <?php if (empty($historico_acessos)): ?>
        <p style="color: #777;">Nenhum registro de refeição encontrado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Valor Debitado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historico_acessos as $acesso): ?>
                    <tr>
                        <td><?= date("d/m/Y", strtotime($acesso["data"])) ?></td>
                        <td><?= $acesso["hora"] ?></td>
                        <td>R$ <?= number_format($acesso["valor"], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>