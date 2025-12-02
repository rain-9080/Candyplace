<?php
include 'db_connect.php';
session_start(); // Inicia a sessão

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ds_email = $mysqli->real_escape_string($_POST['email']);
    $ds_senha_crua = $_POST['senha'];

    // 1. Prepared Statement para buscar a loja pelo email
    $sql = "SELECT cd_loja, nm_loja, ds_senha, status_loja FROM cadastro_loja WHERE ds_email = ?";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $ds_email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $loja = $resultado->fetch_assoc();
        $ds_senha_hash = $loja['ds_senha'];

        // 2. Verifica o status da loja
        if ($loja['status_loja'] !== 'Ativa') {
            // MENSAGEM ADAPTADA AO CSS
            $mensagem = "<p class='msg-aviso'>⚠️ Sua conta está " . $loja['status_loja'] . ". Por favor, entre em contato com o suporte.</p>";
        }
        // 3. Verifica a Senha (Usa password_verify para comparar o hash)
        else if (password_verify($ds_senha_crua, $ds_senha_hash)) {
            // Sucesso no Login
            $_SESSION['logado'] = true;
            $_SESSION['tipo_usuario'] = 'loja';
            $_SESSION['cd_usuario'] = $loja['cd_loja'];
            $_SESSION['nm_usuario'] = $loja['nm_loja'];
            
            // Redireciona para o painel da loja
            header("Location: painel_loja.php"); 
            exit();
        } else {
            // MENSAGEM ADAPTADA AO CSS
            $mensagem = "<p class='msg-erro'>❌ Senha incorreta.</p>";
        }
    } else {
        // MENSAGEM ADAPTADA AO CSS
        $mensagem = "<p class='msg-erro'>❌ E-mail não encontrado.</p>";
    }
    $stmt->close();
}
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login de Loja - CandyPlace</title>
    <style>
        /* Paleta de Cores (Consistente com as demais páginas) */
        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D;     /* Ação principal */
            --cor-marrom-escuro: #6B4423;   /* Textos/Títulos */
            --cor-borda: #E0D4C5;
            --cor-verde-sucesso: #28A745;
            --cor-vermelho-erro: #DC3545;
            --cor-laranja-aviso: #FFC107;
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0;
            background-color: var(--cor-principal-fundo);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Container Principal do Login */
        .login-container {
            width: 90%;
            max-width: 400px;
            background-color: var(--cor-container-fundo); 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            padding: 30px;
            text-align: center;
        }

        .login-container h2 {
            color: var(--cor-marrom-acao); 
            border-bottom: 2px solid var(--cor-borda); 
            padding-bottom: 10px; 
            margin-top: 0; 
            margin-bottom: 25px;
            font-size: 1.8em;
        }

        /* Mensagens de feedback */
        .msg-erro { 
            color: var(--cor-vermelho-erro); 
            font-weight: bold; 
            background: #FDE8E9; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #F8D1D5; 
            margin-bottom: 15px;
        }
        .msg-sucesso { 
            color: var(--cor-verde-sucesso); 
            font-weight: bold; 
            background: #E8FDE8; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #D1F8D1; 
            margin-bottom: 15px;
        }
        .msg-aviso {
            color: #856404; 
            font-weight: bold; 
            background: #FFF3CD; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #FFE0A0; 
            margin-bottom: 15px;
        }

        /* Formulario */
        form {
            text-align: left;
        }

        .form-group { 
            margin-bottom: 15px; 
        }
        .form-group label { 
            display: block; 
            font-weight: bold; 
            margin-bottom: 5px; 
            color: var(--cor-marrom-escuro);
            font-size: 0.95em;
        }
        .form-group input { 
            width: 100%; 
            padding: 10px; 
            box-sizing: border-box; 
            border: 1px solid var(--cor-borda); 
            border-radius: 4px; 
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            border-color: var(--cor-marrom-acao);
            outline: none;
        }

        /* Botão Principal */
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: var(--cor-marrom-acao); 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            margin-top: 15px;
            transition: background-color 0.3s;
        }
        .btn-login:hover {
            background-color: var(--cor-marrom-escuro); 
        }

        /* Links de Navegação */
        .nav-links {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid var(--cor-borda);
        }
        .nav-links p {
            margin: 8px 0;
            font-size: 0.95em;
        }
        .nav-links a {
            color: var(--cor-marrom-acao);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s;
        }
        .nav-links a:hover {
            color: var(--cor-marrom-escuro);
        }

    </style>
</head>
<body>
    <div class="login-container">
        <h2>🔑 Acesso para Lojistas</h2>
        
        <?php echo $mensagem; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <input type="submit" value="Entrar como Loja" class="btn-login">
        </form>

        <div class="nav-links">
            <p>Não tem conta? <a href="cadastro_loja.php">Cadastre sua loja aqui</a>.</p>
            <p>É cliente? <a href="login_cliente.php">Faça login aqui</a>.</p>
            <p><a href="index.php">Voltar para a Página Inicial</a></p>
        </div>
    </div>
</body>
</html>