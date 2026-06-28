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
$formas_pagamento = [];

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Busca as formas de pagamento para listar no formulário
    $stmt = $pdo->query("SELECT Id_Forma_Pagamento, Nome_Forma_Pagamento FROM FORMA_PAGAMENTO");
    $formas_pagamento = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $forma = $_POST["forma_pagamento"] ?? "";
        $valor = floatval($_POST["valor"] ?? 0);
        $carteirinha = $_SESSION["carteirinha"];

        if (empty($forma) || $valor <= 0) {
            $erro = "Por favor, insira um valor válido maior que zero.";
        } else {
            // O PHP faz apenas o INSERT. O trigger 'tg_atualiza_saldo_recarga' soma o saldo
            // O trigger 'tg_valida_tipo_recarga' impede se o usuário for Admin/Nutri
            $sql = "INSERT INTO RECARGAS (Id_Tipo_Pagamento, Valor, Num_Carteirinha) VALUES (:forma, :valor, :carteirinha)";
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->execute([
                "forma" => $forma,
                "valor" => $valor,
                "carteirinha" => $carteirinha
            ]);

            $sucesso = "Recarga de R$ " . number_format($valor, 2, ',', '.') . " realizada com sucesso!";
        }
    }
} catch (PDOException $e) {
    // Captura as exceções personalizadas disparadas pelos Triggers (SQLSTATE P0001)
    if ($e->getCode() == 'P0001') {
        $erro = preg_replace('/.*EXCEPTION:/', '', $e->getMessage());
    } else {
        $erro = "Erro no sistema: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Bandejão - Recarga de Saldo</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f4; padding: 2rem; }
        .box { background: white; padding: 2rem; max-width: 400px; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #2e2769; margin-top: 0; }
        .erro { background: #fdecea; color: #b3261e; padding: 0.7rem; margin-bottom: 1rem; border-radius: 6px; font-size: 0.9rem; }
        .sucesso { background: #e8f5e9; color: #2e2769; padding: 0.7rem; margin-bottom: 1rem; border-radius: 6px; font-size: 0.9rem; }
        label { display: block; margin-top: 1rem; font-weight: bold; color: #555; }
        select, input[type="number"] { width: 100%; padding: 0.7rem; margin-top: 0.4rem; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
        button { background: #2e2769; color: white; border: none; padding: 0.8rem; width: 100%; margin-top: 1.5rem; cursor: pointer; border-radius: 6px; font-size: 1rem; font-weight: bold; }
        button:hover { background: #2e2769; }
        .back-link { display: block; text-align: center; margin-top: 1rem; color: #666; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="box">
    <h2>Efetuar Recarga</h2>
    <p>Adicione créditos à sua carteirinha institucional.</p>

    <?php if ($erro): ?> <div class="erro"><?= htmlspecialchars($erro) ?></div> <?php endif; ?>
    <?php if ($sucesso): ?> <div class="sucesso"><?= htmlspecialchars($sucesso) ?></div> <?php endif; ?>

    <form method="post">
        <label for="forma_pagamento">Forma de Pagamento:</label>
        <select name="forma_pagamento" id="forma_pagamento" required>
            <option value="">Selecione...</option>
            <?php foreach ($formas_pagamento as $fp): ?>
                <option value="<?= $fp["id_forma_pagamento"] ?>"><?= htmlspecialchars($fp["nome_forma_pagamento"]) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="valor">Valor da Recarga (R$):</label>
        <input type="number" name="valor" id="valor" step="0.01" min="0.01" placeholder="0,00" required>

        <button type="submit">Confirmar Pagamento</button>
    </form>

    <a href="painel_aluno.php" class="back-link">← Voltar ao Painel</a>
</div>

</body>
</html>