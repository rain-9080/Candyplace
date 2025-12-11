<?php
include 'db_connect.php'; // Inclui o arquivo de conexão

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    //  Coleta e sanitiza os dados do formulário
    $nm_cliente = $mysqli->real_escape_string($_POST['nome']);
    $cd_cpf  = $mysqli->real_escape_string($_POST['cpf']);
    $ds_email = $mysqli->real_escape_string($_POST['email']);
    $nr_telefone= $mysqli->real_escape_string($_POST['telefone']);
    $ds_senha_crua = $_POST['senha'];

    //  Hashing da Senha (Criptografia para a senha)
    $ds_senha_hash = password_hash($ds_senha_crua, PASSWORD_DEFAULT);

    //  Prepared Statement para inserção segura (recurso do banco de dados que permite compilar uma consulta SQl para previnir SQL Injection (Os pontos de interrogação))
    $sql = "INSERT INTO cadastro_cliente (nm_cliente, cd_cpf, ds_email, nr_telefone, ds_senha) VALUES (?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    
    // Liga as variáveis aos placeholders
    $stmt->bind_param("sssss", $nm_cliente, $cd_cpf, $ds_email, $nr_telefone, $ds_senha_hash);

    if ($stmt->execute()) {
        $mensagem = "<p class='msg-sucesso'>✅ Cadastro de cliente realizado com sucesso! Você pode <a href='login_cliente.php'>fazer login</a> agora.</p>";
    } else {
        if ($mysqli->errno == 1062) {
             $mensagem = "<p class='msg-erro'>❌ Erro: CPF ou E-mail já cadastrado.</p>";
        } else {
             $mensagem = "<p class='msg-erro'>❌ Erro ao cadastrar: " . $stmt->error . "</p>";
        }
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
    <title>Cadastro de Cliente - CandyPlace</title>
    <style>

        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D;
            --cor-marrom-escuro: #6B4423;   
            --cor-borda: #E0D4C5;
            --cor-verde-sucesso: #28A745;
            --cor-vermelho-erro: #DC3545;
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px 0;
            background-color: var(--cor-principal-fundo);
            display: flex;
            justify-content: center;
            align-items: flex-start; 
            min-height: 100vh;
        }

        /* Container Principal do Cadastro */
        .cadastro-container {
            width: 90%;
            max-width: 500px; 
            background-color: var(--cor-container-fundo); 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            padding: 30px;
            text-align: center;
        }

        .cadastro-container h2 {
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
        
        input[type="text"], 
        input[type="email"], 
        input[type="password"] { 
            width: 100%; 
            padding: 10px; 
            box-sizing: border-box; 
            border: 1px solid var(--cor-borda); 
            border-radius: 4px; 
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: var(--cor-marrom-acao);
            outline: none;
        }

        /* Campo de Mostrar Senha */
        .show-password-container {
            text-align: left;
            margin-top: 5px;
            font-size: 0.9em;
            color: var(--cor-marrom-escuro);
        }
        .show-password-container label {
            display: inline;
            font-weight: normal;
            margin-top: 0;
            cursor: pointer;
        }
        .show-password-container input[type="checkbox"] {
            width: auto;
            margin-right: 5px;
        }


        /* Botão Principal */
        .btn-cadastro {
            width: 100%;
            padding: 12px;
            background-color: var(--cor-marrom-acao); 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            margin-top: 25px;
            transition: background-color 0.3s;
        }
        .btn-cadastro:hover {
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
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="cadastro-container">
        <h2>📝 Cadastro de Novo Cliente</h2>
        
        <?php echo $mensagem; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="nome">Nome Completo:</label>
                <input type="text" id="nome" name="nome" required>
            </div>

            <div class="form-group">
                <label for="cpf">CPF (apenas números):</label>
                <input type="text" id="cpf" name="cpf" maxlength="11" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="telefone">Telefone (Ex: 13 1111-1111):</label>
                <input type="text" id="telefone" name="telefone" maxlength="14" required>
            </div>

            <div class="form-group">
                <label for="senha">Crie sua Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <div class="show-password-container">
                <input type="checkbox" onclick="myFunction()" id="show-password-check">
                <label for="show-password-check">Mostrar senha</label>
            </div>

            <input type="submit" value="Cadastrar Cliente" class="btn-cadastro">
        </form>
        
        <div class="nav-links">
            <p>
                Já tem conta? 
                <a href="login_cliente.php" class="link-login">Faça login aqui</a>.
            </p>
            <p><a href="index.php">Voltar para a Página Inicial</a></p>
        </div>
    </div>
    
    <script>

        /*
         Função JavaScript para alternar a visibilidade da senha.
         */

        function myFunction() {
            var x = document.getElementById("senha");
            if (x.type === "password") {
                x.type = "text";
            } else {
                x.type = "password";
            }
        } 
    </script>
</body>
</html>