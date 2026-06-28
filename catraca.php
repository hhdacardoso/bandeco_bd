<?php
$dsn  = "pgsql:host=localhost;port=5432;dbname=bandeco";
$user = "postgres";
$pass = "teste123";

$erro = null;
$sucesso = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $carteirinha = trim($_POST["carteirinha"] ?? "");

    if (empty($carteirinha)) {
        $erro = "Por favor, aproxime ou digite uma carteirinha válida.";
    } else {
        try {
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Insere na tabela ACESSO. O trigger 'tg_calcula_e_desconta_refeicao' descobre a tarifa,
            // desconta do saldo e o trigger 'tg_valida_limite_saldo' barra se estourar o limite devedor
            $sql = "INSERT INTO ACESSO (Num_Carteirinha) VALUES (:carteirinha)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["carteirinha" => $carteirinha]);

            // Busca o nome e o valor que o trigger acabou de calcular para exibir na tela da catraca
            $stmt_info = $pdo->prepare("
                SELECT u.Nome_Usuario, a.Valor 
                FROM ACESSO a 
                JOIN USUARIO u ON a.Num_Carteirinha = u.Num_Carteirinha 
                WHERE a.Num_Carteirinha = :carteirinha 
                ORDER BY a.Id_Refeicao DESC LIMIT 1
            ");
            $stmt_info->execute(["carteirinha" => $carteirinha]);
            $resultado = $stmt_info->fetch(PDO::FETCH_ASSOC);

            $sucesso = "✅ ACESSO LIBERADO!<br><b>" . htmlspecialchars($resultado["nome_usuario"]) . "</b><br>Valor debitado: R$ " . number_format($resultado["valor"], 2, ',', '.');

        } catch (PDOException $e) {
            // Captura os erros customizados dos triggers do Postgres (P0001) ou se a carteirinha não existir (23503)
            if ($e->getCode() == 'P0001') {
                $erro = "❌ ACESSO NEGADO:<br>" . preg_replace('/.*EXCEPTION:/', '', $e->getMessage());
            } elseif ($e->getCode() == '23503') {
                $erro = "❌ ACESSO NEGADO:<br>Carteirinha não cadastrada no sistema.";
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
    <title>Simulador de Catraca - RU</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0b0f19; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .catraca-box { background: #131a2e; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 25px rgba(0,0,0,0.6); text-align: center; width: 100%; max-width: 380px; border: 2px solid #1e294b; }
        h1 { color: #4dadff; margin-top: 0; font-size: 1.6rem; text-transform: uppercase; letter-spacing: 1px; }
        .status-tela { min-height: 80px; display: flex; justify-content: center; align-items: center; border: 2px dashed #253562; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; background: #090d16; font-size: 1rem; line-height: 1.4; }
        .msg-erro { color: #ff5252; } 
        .msg-sucesso { color: #64ffda; } 
        input[type="number"] { width: 100%; padding: 0.8rem; border: 2px solid #253562; background: #1b2440; color: #fff; border-radius: 6px; box-sizing: border-box; font-size: 1.2rem; text-align: center; font-weight: bold; }
        input[type="number"]:focus { border-color: #4dadff; outline: none; box-shadow: 0 0 8px rgba(77, 173, 255, 0.4); }
        button { background: #2563eb; color: #ffffff; border: none; padding: 0.9rem; width: 100%; margin-top: 1rem; cursor: pointer; border-radius: 6px; font-size: 1.1rem; font-weight: bold; text-transform: uppercase; transition: background 0.2s; }
        button:hover { background: #1d4ed8; }
        .instrucao { color: #64748b; font-size: 0.85rem; margin-top: 1.5rem; }
    </style>
</head>
<body>

<div class="catraca-box">
    <h1>Catraca Eletrônica</h1>
    
    <div class="status-tela">
        <?php if ($erro): ?>
            <div class="msg-erro"><?= $erro ?></div>
        <?php elseif ($sucesso): ?>
            <div class="msg-sucesso"><?= $sucesso ?></div>
        <?php else: ?>
            <div style="color: #aaa;">AGUARDANDO CARTEIRINHA...</div>
        <?php endif; ?>
    </div>

    <form method="post">
        <input type="number" name="carteirinha" placeholder="Insira o número" autofocus required>
        <button type="submit">Passar na Roleta</button>
    </form>

    <div class="instrucao">Simulador físico do terminal de entrada do refeitório.</div>
</div>

</body>
</html>