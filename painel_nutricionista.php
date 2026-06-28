<?php
session_start();
// Segurança: Garante que só NUTRI entra aqui
if (!isset($_SESSION["carteirinha"]) || $_SESSION["tipo"] !== "nutricionista") {
    header("Location: login.php");
    exit;
}

$dsn  = "pgsql:host=localhost;port=5432;dbname=bandeco";
$user = "postgres";
$pass = "teste123";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. Contagem de refeições servidas no Almoço hoje
    $q_almoco = $pdo->query("SELECT COUNT(*) FROM ACESSO WHERE Data = CURRENT_DATE AND Hora BETWEEN '11:00:00' AND '14:30:00'");
    $total_almoco_hoje = $q_almoco->fetchColumn();

    // 2. Contagem de refeições servidas na Janta hoje
    $q_janta = $pdo->query("SELECT COUNT(*) FROM ACESSO WHERE Data = CURRENT_DATE AND Hora BETWEEN '17:00:00' AND '20:30:00'");
    $total_janta_hoje = $q_janta->fetchColumn();

    // 3. Histórico de fluxo de acessos ordenado pelos mais recentes (Mural de Atendimento)
    $q_fluxo = $pdo->query("
        SELECT a.Hora, a.Data, u.Nome_Usuario, t.Nome_Tipo_Usuario
        FROM ACESSO a
        INNER JOIN USUARIO u ON a.Num_Carteirinha = u.Num_Carteirinha
        INNER JOIN TIPIFICA_USUARIO tu ON u.Num_Carteirinha = tu.Num_Carteirinha
        INNER JOIN TIPO_USUARIO t ON tu.Id_Tipo = t.Id_Tipo
        ORDER BY a.Data DESC, a.Hora DESC LIMIT 10
    ");
    $fluxo_refeitorio = $q_fluxo->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erro no painel da nutri: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Bandejão - Nutricionista</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f6f6f9; margin: 0; padding: 2rem; }
        .container { max-width: 850px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #2e437d; padding-bottom: 1rem; margin-bottom: 2rem; }
        .header h1 { margin: 0; color: #2e437d; }
        .btn-logout { background: #c00a0a; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; }
        .grid-turnos { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .card-turno { background: #bbbfe7; padding: 1.5rem; border-radius: 8px; text-align: center; border: 1px solid #bbbfe7; }
        .card-turno h3 { margin: 0; color: #2e437d; text-transform: uppercase; font-size: 0.9rem; letter-spacing: 0.5px; }
        .card-turno .contador { font-size: 2.5rem; font-weight: bold; color: #2e437d; margin: 0.5rem 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dddddd; }
        th { background: #bbbfe7; color: #2e437d; }
        h2 { color: #2e437d; margin-top: 2rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Painel de Controle Nutricionista</h1>
            <small>Nutricionista: <?= htmlspecialchars($_SESSION["nome"]) ?></small>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if (count($_SESSION["tipos"]) > 1): ?>
                <a href="selecionar_perfil.php" class="btn-alternar">Alternar Perfil</a>
            <?php endif; ?>
            
            <a href="logout.php" class="btn-logout">Sair</a>
        </div>
    </div>

    <h2>Consumo de Refeições (Hoje)</h2>
    <div class="grid-turnos">
        <div class="card-turno">
            <h3>Turno do Almoço</h3>
            <div class="contador"><?= $total_almoco_hoje ?></div>
            <small style="color: #666;">Janela: 11:00h às 14:30h</small>
        </div>
        <div class="card-turno">
            <h3>Turno da Janta</h3>
            <div class="contador"><?= $total_janta_hoje ?></div>
            <small style="color: #666;">Janela: 17:00h às 20:30h</small>
        </div>
    </div>

    <h2>Fluxo em Tempo Real (Últimos 10 Acessos)</h2>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Hora de Entrada</th>
                <th>Nome do Usuário</th>
                <th>Tipo de Vínculo</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($fluxo_refeitorio)): ?>
                <tr><td colspan="4" style="color: #777; text-align: center;">Nenhum fluxo registrado na roleta ainda.</td></tr>
            <?php else: ?>
                <?php foreach ($fluxo_refeitorio as $f): ?>
                <tr>
                    <td><?= date("d/m/Y", strtotime($f["data"])) ?></td>
                    <td style="font-weight: bold; color: #333;"><?= $f["hora"] ?></td>
                    <td><?= htmlspecialchars($f["nome_usuario"]) ?></td>
                    <td><?= $f["nome_tipo_usuario"] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>