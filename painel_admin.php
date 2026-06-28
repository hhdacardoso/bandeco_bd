<?php
session_start();

if (!isset($_SESSION["carteirinha"]) || $_SESSION["tipo"] !== "admin") {
    header("Location: login.php");
    exit;
}

$dsn  = "pgsql:host=localhost;port=5432;dbname=bandeco";
$user = "postgres";
$pass = "teste123";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Lista Geral de Usuários e Perfis
    $q_usuarios = $pdo->query("
        SELECT u.Num_Carteirinha, u.Nome_Usuario, t.Nome_Tipo_Usuario, u.SALDO
        FROM USUARIO u
        INNER JOIN TIPIFICA_USUARIO tu ON u.Num_Carteirinha = tu.Num_Carteirinha
        INNER JOIN TIPO_USUARIO t ON tu.Id_Tipo = t.Id_Tipo
        ORDER BY u.Nome_Usuario
    ");
    $relatorio_usuarios = $q_usuarios->fetchAll(PDO::FETCH_ASSOC);

    // Total acumulado gasto por usuário que já comeu
    $q_gastos = $pdo->query("
        SELECT u.Nome_Usuario, COUNT(a.Id_Refeicao) as Qtd_Refeicoes, SUM(a.Valor) as Total_Gasto
        FROM USUARIO u
        INNER JOIN ACESSO a ON u.Num_Carteirinha = a.Num_Carteirinha
        GROUP BY u.Num_Carteirinha, u.Nome_Usuario
        HAVING SUM(a.Valor) >= 0.00
        ORDER BY Total_Gasto DESC
    ");
    $relatorio_gastos = $q_gastos->fetchAll(PDO::FETCH_ASSOC);

    // Balanço Geral do Sistema
    $total_saldo = $pdo->query("SELECT SUM(SALDO) FROM USUARIO")->fetchColumn();
    $total_recargas = $pdo->query("SELECT SUM(Valor) FROM RECARGAS")->fetchColumn();
    $total_refeicoes = $pdo->query("SELECT COUNT(*) FROM ACESSO")->fetchColumn();

} catch (PDOException $e) {
    echo "Erro no painel administrativo: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Bandejão - Painel Admin</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f6f9; margin: 0; padding: 2rem; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #2e2769; padding-bottom: 1rem; margin-bottom: 2rem; }
        .header h1 { margin: 0; color: #2e2769; }
        .btn-logout { background: #b3261e; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; }
        .grid-metricas { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
        .card-metrica { background: #e8eaf6; padding: 1rem; border-radius: 6px; text-align: center; border-top: 4px solid #2e2769; }
        .card-metrica h3 { margin: 0; color: #555; font-size: 0.9rem; }
        .card-metrica p { margin: 0.5rem 0 0 0; font-size: 1.6rem; font-weight: bold; color: #2e2769; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; font-size: 0.95rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; color: #333; }
        h2 { color: #2e2769; border-left: 4px solid #2e2769; padding-left: 0.5rem; margin-top: 1.5rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Painel de Controle Administrativo</h1>
            <small>Logado como: <?= htmlspecialchars($_SESSION["nome"]) ?> (ID: <?= $_SESSION["carteirinha"] ?>)</small>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if (isset($_SESSION["tipos"]) && count($_SESSION["tipos"]) > 1): ?>
                <a href="selecionar_perfil.php" class="btn-alternar">Alternar Perfil</a>
            <?php endif; ?>
            
            <a href="logout.php" class="btn-logout">Sair do Sistema</a>
        </div>
    </div>

    <div class="grid-metricas">
        <div class="card-metrica">
            <h3>Custódia de Saldos (Usuários)</h3>
            <p>R$ <?= number_format($total_saldo ?: 0, 2, ',', '.') ?></p>
        </div>
        <div class="card-metrica">
            <h3>Total Injetado (Recargas)</h3>
            <p>R$ <?= number_format($total_recargas ?: 0, 2, ',', '.') ?></p>
        </div>
        <div class="card-metrica">
            <h3>Giro de Refeições Servidas</h3>
            <p><?= $total_refeicoes ?> un.</p>
        </div>
    </div>

    <h2>Auditoria Geral de Contas</h2>
    <table>
        <thead>
            <tr>
                <th>Carteirinha</th>
                <th>Nome do Usuário</th>
                <th>Vínculo Ativo</th>
                <th>Saldo Atual</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($relatorio_usuarios as $user): ?>
            <tr>
                <td><?= $user["num_carteirinha"] ?></td>
                <td><?= htmlspecialchars($user["nome_usuario"]) ?></td>
                <td><span style="background: #e0e0e0; padding: 2px 6px; border-radius: 4px; font-size: 0.85rem;"><?= $user["nome_tipo_usuario"] ?></span></td>
                <td style="font-weight: bold; color: <?= $user["saldo"] >= 0 ? '#2e437d' : '#b3261e' ?>">
                    R$ <?= number_format($user["saldo"], 2, ',', '.') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Faturamento Acumulado por Consumidor</h2>
    <table>
        <thead>
            <tr>
                <th>Nome do Consumidor</th>
                <th>Refeições Consumidas</th>
                <th>Total já Pago ao RU</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($relatorio_gastos)): ?>
                <tr><td colspan="3" style="color: #777;">Nenhuma refeição registrada até o momento.</td></tr>
            <?php else: ?>
                <?php foreach ($relatorio_gastos as $gasto): ?>
                <tr>
                    <td><?= htmlspecialchars($gasto["nome_usuario"]) ?></td>
                    <td><?= $gasto["qtd_refeicoes"] ?> refeições</td>
                    <td style="font-weight: bold; color: #2e2769;">R$ <?= number_format($gasto["total_gasto"], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>