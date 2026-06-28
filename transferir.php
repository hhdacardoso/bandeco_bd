<?php
session_start();
if (!isset($_SESSION["carteirinha"])) {
    header("Location: login.php");
    exit;
}

$dsn  = "pgsql:host=localhost;port=5432;dbname=bandeco";
$user = "postgres";
$pass = "teste123";

$erro = null;
$sucesso = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $destinatario = trim($_POST["destinatario"] ?? "");
    $valor = floatval($_POST["valor"] ?? 0);
    $remetente = $_SESSION["carteirinha"];

    if (empty($destinatario) || $valor <= 0) {
        $erro = "Preencha os campos com valores válidos.";
    } elseif ($destinatario == $remetente) {
        $erro = "Não é possível transferir valores para a sua própria carteirinha.";
    } else {
        try {
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // O INSERT dispara o trigger 'tg_gerencia_transferencia', que faz toda a validação de saldo,
            // checa o limite de 1 transação por dia e faz os UPDATES de balanço financeiro
            $sql = "INSERT INTO TRANSFERENCIAS (Num_Remetente, Num_Destinatario, Valor) VALUES (:remetente, :destinatario, :valor)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                "remetente" => $remetente,
                "destinatario" => $destinatario,
                "valor" => $valor
            ]);

            $sucesso = "Transferência de R$ " . number_format($valor, 2, ',', '.') . " realizada com sucesso!";
        } catch (PDOException $e) {
            // Captura os erros customizados dos triggers do Postgres (P0001) ou erros de chave estrangeira (23503)
            if ($e->getCode() == 'P0001') {
                $erro = preg_replace('/.*EXCEPTION:/', '', $e->getMessage());
            } elseif ($e->getCode() == '23503') {
                $erro = "A carteirinha de destino (" . htmlspecialchars($destinatario) . ") não foi encontrada no sistema.";
            } else {
                $erro = "Erro no sistema: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Bandejão - Transferência de Saldo</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f4; padding: 2rem; }
        .box { background: white; padding: 2rem; max-width: 400px; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #2e2769; margin-top: 0; }
        .erro { background: #fdecea; color: #b3261e; padding: 0.7rem; margin-bottom: 1rem; border-radius: 6px; font-size: 0.9rem; }
        .sucesso { background: #e8f5e9; color: #2e2769; padding: 0.7rem; margin-bottom: 1rem; border-radius: 6px; font-size: 0.9rem; }
        label { display: block; margin-top: 1rem; font-weight: bold; color: #555; }
        input[type="number"] { width: 100%; padding: 0.7rem; margin-top: 0.4rem; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        button { background: #2e2769; color: white; border: none; padding: 0.8rem; width: 100%; margin-top: 1.5rem; cursor: pointer; border-radius: 6px; font-size: 1rem; font-weight: bold; }
        button:hover { background: #2e2769; }
        .back-link { display: block; text-align: center; margin-top: 1rem; color: #666; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="box">
    <h2>Transferir Saldo</h2>
    <p>Envie créditos instantaneamente para outro usuário.</p>

    <?php if ($erro): ?> <div class="erro"><?= htmlspecialchars($erro) ?></div> <?php endif; ?>
    <?php if ($sucesso): ?> <div class="sucesso"><?= htmlspecialchars($sucesso) ?></div> <?php endif; ?>

    <form method="post">
        <label for="destinatario">Carteirinha do Destinatário:</label>
        <input type="number" name="destinatario" id="destinatario" placeholder="Ex: 1005" required>

        <label for="valor">Valor a Transferir (R$):</label>
        <input type="number" name="valor" id="valor" step="0.01" min="0.01" placeholder="0,00" required>

        <button type="submit">Confirmar Transferência</button>
    </form>

    <a href="painel_aluno.php" class="back-link">← Voltar ao Painel</a>
</div>

</body>
</html>