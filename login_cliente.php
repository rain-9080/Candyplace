<?php
include 'db_connect.php';
session_start(); // Inicia a sessão para armazenar o status de login

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ds_email = $mysqli->real_escape_string($_POST['email']);
    $ds_senha_crua = $_POST['senha'];

    // 1. Prepared Statement para buscar o cliente pelo email (chave de login)
    $sql = "SELECT cd_cliente, nm_cliente, ds_senha FROM cadastro_cliente WHERE ds_email = ?";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $ds_email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $cliente = $resultado->fetch_assoc();
        $ds_senha_hash = $cliente['ds_senha'];

        // 2. Verifica a Senha (Usa password_verify para comparar o hash)
        if (password_verify($ds_senha_crua, $ds_senha_hash)) {
            // Sucesso no Login
            $_SESSION['logado'] = true;
            $_SESSION['tipo_usuario'] = 'cliente';
            $_SESSION['cd_usuario'] = $cliente['cd_cliente'];
            $_SESSION['nm_usuario'] = $cliente['nm_cliente'];
            
            // =========================================================
            // LÓGICA DE RECUPERAÇÃO DO CARRINHO (PEDIDO ATIVO)
            // =========================================================
            $cd_cliente_logado = $cliente['cd_cliente'];
            
            // Procura por um pedido com status 'Carrinho' para este cliente
            $sql_carrinho_ativo = "SELECT cd_pedido FROM pedido WHERE cd_cliente = ? AND ds_status_pedido = 'Carrinho' LIMIT 1";
            $stmt_carrinho = $mysqli->prepare($sql_carrinho_ativo);
            $stmt_carrinho->bind_param("i", $cd_cliente_logado);
            $stmt_carrinho->execute();
            $resultado_carrinho = $stmt_carrinho->get_result();
            
            if ($resultado_carrinho->num_rows === 1) {
                // Encontrou um carrinho pendente, carrega seu ID na sessão
                $pedido_ativo = $resultado_carrinho->fetch_assoc();
                $_SESSION['cd_pedido_ativo'] = $pedido_ativo['cd_pedido'];
                
                // MENSAGEM ATUALIZADA COM CLASSE CSS
                $mensagem = "<p class='msg-sucesso'>✅ Login bem-sucedido! Carrinho recuperado. Redirecionando...</p>";
            } else {
                // MENSAGEM ATUALIZADA COM CLASSE CSS
                $mensagem = "<p class='msg-sucesso'>✅ Login bem-sucedido! Redirecionando...</p>";
            }
            $stmt_carrinho->close();
            // =========================================================
            
            // Redireciona para a página principal ou painel do cliente
            header("Location: index.php"); 
            exit();
        } else {
            // MENSAGEM ATUALIZADA COM CLASSE CSS
            $mensagem = "<p class='msg-erro'>❌ Senha incorreta.</p>";
        }
    } else {
        // MENSAGEM ATUALIZADA COM CLASSE CSS
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
    <title>Login de Cliente - CandyPlace</title>
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
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0;
            background-color: var(--cor-principal-fundo);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Garante que o container fique no centro vertical */
        }
        
        /* Container Principal do Formulário */
        .login-container {
            width: 90%;
            max-width: 400px; /* Limita a largura máxima */
            padding: 30px;
            background-color: var(--cor-container-fundo);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
            text-align: left;
        }
        .msg-sucesso { 
            color: var(--cor-verde-sucesso); 
            font-weight: bold; 
            background: #E8FDE8; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #D1F8D1; 
            margin-bottom: 15px;
            text-align: left;
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
        
        input[type="email"], input[type="password"] {
            width: 100%; 
            padding: 12px; 
            box-sizing: border-box; 
            border: 1px solid var(--cor-borda); 
            border-radius: 4px; 
            transition: border-color 0.2s;
            margin-bottom: 10px;
        }
        input[type="email"]:focus, input[type="password"]:focus {
            border-color: var(--cor-marrom-acao);
            outline: none;
        }

        /* Botão Principal */
        .btn-login {
            display: block; /* Ocupa a largura total */
            width: 100%;
            padding: 15px; 
            background-color: var(--cor-marrom-acao); 
            color: white; 
            text-decoration: none; 
            border: none; 
            border-radius: 4px; 
            margin-top: 30px; 
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: bold;
            font-size: 1.1em;
        }
        .btn-login:hover {
            background-color: var(--cor-marrom-escuro); /* Tom mais escuro */
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
            text-decoration: underline;
        }
        
    </style>
</head>
<body>
    <div class="login-container">
        <h2>🛍️ Login de Cliente</h2>
        
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

            <input type="submit" value="Entrar" class="btn-login">
        </form>
        
        <div class="nav-links">
            <p>
                Não tem conta? 
                <a href="cadastro_cliente.php">Cadastre-se aqui</a>.
            </p>
            <p>
                É lojista? 
                <a href="login_loja.php">Faça login aqui</a>.
            </p>
            <p><a href="index.php">Voltar para a Página Inicial</a></p>
        </div>
    </div>
</body>
</html>