<?php
session_start();

// --- Conexão com o banco (ajuste dbname, user, pass conforme seu ambiente) ---
$dsn  = "pgsql:host=localhost;port=5432;dbname=bandeco"; // troque "refeitorio" pelo nome real do seu banco
$user = "postgres";
$pass = "teste123"; // a mesma senha definida com ALTER USER / CREATE USER

$erro = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $carteirinha = trim($_POST["carteirinha"] ?? "");

    if ($carteirinha === "") {
        $erro = "Informe o número da carteirinha.";
    } else {
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $sql = "SELECT u.Num_Carteirinha, u.Nome_Usuario, u.Saldo,
                           t.Id_Tipo, t.Nome_Tipo_Usuario, t.Valor_Refeicao
                    FROM USUARIO u
                    JOIN TIPIFICA_USUARIO tu ON tu.Num_Carteirinha = u.Num_Carteirinha
                    JOIN TIPO_USUARIO t      ON t.Id_Tipo = tu.Id_Tipo
                    WHERE u.Num_Carteirinha = :carteirinha";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(["carteirinha" => $carteirinha]);
            $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$linhas) {
                $erro = "Carteirinha não encontrada.";
            } else {
                // um usuário pode ter mais de um tipo (N:N via TIPIFICA_USUARIO)
                // define qual tipo "vence" quando há mais de um
                $prioridade = ["admin", "nutricionista", "servidor", "aluno"];
                $tipos_do_usuario = array_map(
                    fn($l) => strtolower($l["Nome_Tipo_Usuario"]),
                    $linhas
                );

                $tipo_escolhido = null;
                foreach ($prioridade as $candidato) {
                    if (in_array($candidato, $tipos_do_usuario, true)) {
                        $tipo_escolhido = $candidato;
                        break;
                    }
                }

                $usuario = $linhas[0]; // dados básicos são iguais em todas as linhas

                // grava dados na sessão
                $_SESSION["carteirinha"] = $usuario["Num_Carteirinha"];
                $_SESSION["nome"]        = $usuario["Nome_Usuario"];
                $_SESSION["saldo"]       = $usuario["Saldo"];
                $_SESSION["tipo"]        = $tipo_escolhido;
                $_SESSION["tipos"]       = $tipos_do_usuario; // guarda todos, caso precise depois

                // redireciona conforme o tipo de usuário (maior privilégio)
                switch ($tipo_escolhido) {
                    case "aluno":
                        header("Location: painel_aluno.php");
                        break;
                    case "servidor":
                        header("Location: painel_servidor.php");
                        break;
                    case "nutricionista":
                        header("Location: painel_nutricionista.php");
                        break;
                    case "admin":
                        header("Location: painel_admin.php");
                        break;
                    default:
                        header("Location: painel.php");
                }
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao conectar ao banco de dados: " . $e->getMessage(); // TEMPORÁRIO - reverter depois do debug
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Refeitório - Login</title>
<style>
    body {
        font-family: system-ui, sans-serif;
        background: #f4f4f4;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
        margin: 0;
    }
    .card {
        background: #fff;
        padding: 2.5rem 2rem;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        width: 320px;
        text-align: center;
    }
    .card h1 {
        font-size: 1.3rem;
        margin-bottom: 0.3rem;
    }
    .card p.sub {
        color: #666;
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
    }
    input[type="text"] {
        width: 100%;
        padding: 0.7rem;
        font-size: 1.1rem;
        text-align: center;
        border: 1px solid #ccc;
        border-radius: 6px;
        box-sizing: border-box;
        margin-bottom: 1rem;
    }
    button {
        width: 100%;
        padding: 0.7rem;
        font-size: 1rem;
        background: #2e7d32;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    button:hover { background: #27692a; }
    .erro {
        background: #fdecea;
        color: #b3261e;
        padding: 0.6rem;
        border-radius: 6px;
        font-size: 0.85rem;
        margin-bottom: 1rem;
    }
</style>
</head>
<body>
    <div class="card">
        <h1>Restaurante Universitário</h1>
        <p class="sub">Digite o número da sua carteirinha para entrar</p>

        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <input
                type="text"
                name="carteirinha"
                placeholder="Número da carteirinha"
                autofocus
                required
            >
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>