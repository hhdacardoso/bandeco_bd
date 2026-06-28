<?php
session_start();

// Segurança: Se não estiver logado ou não tiver tipos na sessão, volta pro login
if (!isset($_SESSION["carteirinha"]) || !isset($_SESSION["tipos"])) {
    header("Location: login.php");
    exit;
}

// Se o usuário clicar em um dos perfis, define o tipo ativo e redireciona
if (isset($_GET["escolha"])) {
    $escolha = strtolower($_GET["escolha"]);
    
    // Valida se o perfil escolhido realmente pertence ao usuário
    if (in_array($escolha, $_SESSION["tipos"], true)) {
        $_SESSION["tipo"] = $escolha; // Define o perfil ativo para esta sessão
        
        switch ($escolha) {
            case "aluno": header("Location: painel_aluno.php"); break;
            case "servidor": header("Location: painel_servidor.php"); break;
            case "nutricionista": header("Location: painel_nutricionista.php"); break;
            case "admin": header("Location: painel_admin.php"); break;
        }
        exit;
    }
}

// Dicionário para deixar os nomes dos botões bonitos na tela
$nomes_papeis = [
    "admin" => "Administrador",
    "nutricionista" => "Nutricionista",
    "servidor" => "Servidor Técnico/Docente",
    "aluno" => "Aluno / Estudante"
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Bandejão - Selecionar Perfil</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f4f4; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 2.5rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; width: 100%; max-width: 400px; border-top: 4px solid #2e2769; }
        h1 { color: #2e2769; font-size: 1.5rem; margin-top: 0; margin-bottom: 0.5rem; }
        p { color: #555; font-size: 0.95rem; margin-bottom: 2rem; }
        .btn-perfil { display: block; background: #2e2769; color: white; text-decoration: none; padding: 1rem; margin-bottom: 1rem; border-radius: 6px; font-weight: bold; font-size: 1.05rem; transition: background 0.2s; border: none; }
        .btn-perfil:hover { background: #2e2769; }
        .btn-logout { display: inline-block; margin-top: 1.5rem; color: #b3261e; text-decoration: none; font-size: 0.9rem; font-weight: bold; }
        .btn-logout:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="box">
    <h1>Múltiplos Perfis Encontrados</h1>
    <p>Olá, <b><?= htmlspecialchars($_SESSION["nome"]) ?></b>.<br>Como você deseja acessar o sistema hoje?</p>

    <div class="lista-perfis">
        <?php foreach ($_SESSION["tipos"] as $tipo): ?>
            <?php 
                // Pega o nome amigável ou usa o próprio termo se não achar no dicionário
                $label = $nomes_papeis[$tipo] ?? ucfirst($tipo); 
            ?>
            <a href="selecionar_perfil.php?escolha=<?= urlencode($tipo) ?>" class="btn-perfil">
                Acessar como <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <a href="logout.php" class="btn-logout">Cancelar e Sair</a>
</div>

</body>
</html>