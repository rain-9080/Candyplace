<?php
include 'db_connect.php'; // Inclui o arquivo de conexão

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Coleta e sanitiza os dados do formulário
    $cd_cnpj = $mysqli->real_escape_string($_POST['cnpj']);
    $nm_razao_social = $mysqli->real_escape_string($_POST['razao_social']);
    $nm_loja = $mysqli->real_escape_string($_POST['nome_fantasia']);
    $ds_email = $mysqli->real_escape_string($_POST['email']);
    $nr_telefone = $mysqli->real_escape_string($_POST['telefone']);
    $ds_senha_crua = $_POST['senha'];
    $sg_estado = $mysqli->real_escape_string($_POST['estado']);
    $nm_cidade = $mysqli->real_escape_string($_POST['cidade']);
    $nm_bairro = $mysqli->real_escape_string($_POST['bairro']);
    $ds_endereco = $mysqli->real_escape_string($_POST['endereco']);
    $cd_cep = $mysqli->real_escape_string($_POST['cep']);
    
    // Converte 'sim' para 1 e 'não' para 0 (BOOLEAN)
    $franquia = ($_POST['franquia'] == 'sim') ? 1 : 0; 
    
    // Status Padrão para nova loja
    $status_loja = 'Ativa';

    // 2. Hashing da Senha
    $ds_senha_hash = password_hash($ds_senha_crua, PASSWORD_DEFAULT);

    // 3. Prepared Statement para inserção
    $sql = "INSERT INTO cadastro_loja (cd_cnpj, nm_razao_social, nm_loja, ds_email, nr_telefone, ds_senha, sg_estado, nm_cidade, nm_bairro, ds_endereco, cd_cep, franquia, status_loja) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    
    // Treze strings (s)
    $stmt->bind_param("sssssssssssss", 
        $cd_cnpj, $nm_razao_social, $nm_loja, $ds_email, $nr_telefone, $ds_senha_hash, 
        $sg_estado, $nm_cidade, $nm_bairro, $ds_endereco, $cd_cep, $franquia, $status_loja
    );

    if ($stmt->execute()) {
        $mensagem = "<p class='msg-sucesso'>✅ Cadastro de Loja realizado com sucesso! Você já pode fazer login.</p>";
    } else {
        if ($mysqli->errno == 1062) {
             $mensagem = "<p class='msg-erro'>❌ Erro: CNPJ ou E-mail já cadastrado.</p>";
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
    <title>Cadastro de Loja - CandyPlace</title>
    <style>
        /* Paleta de Cores (Consistente com as demais páginas) */
        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D;     /* Ação principal/Botão */
            --cor-marrom-escuro: #6B4423;   /* Textos/Títulos */
            --cor-borda: #E0D4C5;
            --cor-verde-sucesso: #28A745;
            --cor-vermelho-erro: #DC3545;
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px 0; /* Padding vertical para formulários longos */
            background-color: var(--cor-principal-fundo);
            display: flex;
            justify-content: center;
            align-items: flex-start; /* Alinha no topo */
            min-height: 100vh;
        }

        /* Container Principal do Cadastro (Um pouco maior que o login) */
        .cadastro-container {
            width: 90%;
            max-width: 600px; /* Maior largura para acomodar o formulário */
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
        
        .cadastro-container h3 {
            color: var(--cor-marrom-escuro); 
            margin-top: 30px;
            margin-bottom: 15px;
            border-left: 3px solid var(--cor-marrom-acao);
            padding-left: 10px;
            text-align: left;
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
        .form-group input[type="text"], 
        .form-group input[type="email"], 
        .form-group input[type="password"], 
        .form-group select { 
            width: 100%; 
            padding: 10px; 
            box-sizing: border-box; 
            border: 1px solid var(--cor-borda); 
            border-radius: 4px; 
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--cor-marrom-acao);
            outline: none;
        }
        /* Layout de grid para campos de endereço/contato */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 500px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
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
        }
    </style>
</head>
<body>
    <div class="cadastro-container">
        <h2>🛍️ Cadastro de Loja Parceira</h2>
        
        <?php echo $mensagem; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            
            <h3>Dados da Empresa</h3>
            <div class="form-group">
                <label for="cnpj">CNPJ (apenas números):</label>
                <input type="text" id="cnpj" name="cnpj" maxlength="14" required>
            </div>

            <div class="form-group">
                <label for="razao_social">Razão Social:</label>
                <input type="text" id="razao_social" name="razao_social" required>
            </div>

            <div class="form-group">
                <label for="nome_fantasia">Nome da Loja/Nome Fantasia:</label>
                <input type="text" id="nome_fantasia" name="nome_fantasia" required>
            </div>

            <div class="form-group">
                <label for="franquia">É Franquia?</label>
                <select id="franquia" name="franquia" required>
                    <option value="não">Não</option>
                    <option value="sim">Sim</option>
                </select>
            </div>

            <h3>Contato e Acesso</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone (com DDD):</label>
                    <input type="text" id="telefone" name="telefone" maxlength="14" required>
                </div>
            </div>

            <div class="form-group">
                <label for="senha">Senha (para acesso ao Painel):</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <h3>Endereço</h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="cep">CEP:</label>
                    <input type="text" id="cep" name="cep" maxlength="9" placeholder="00000-000" required>
                </div>
                <div class="form-group">
                    <label for="estado">Estado (UF - 2 letras):</label>
                    <input type="text" id="estado" name="estado" maxlength="2" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="cidade">Cidade:</label>
                    <input type="text" id="cidade" name="cidade" required>
                </div>
                <div class="form-group">
                    <label for="bairro">Bairro:</label>
                    <input type="text" id="bairro" name="bairro" required>
                </div>
            </div>

            <div class="form-group">
                <label for="endereco">Rua/Avenida + Número (Ex: Rua das Flores, 123):</label>
                <input type="text" id="endereco" name="endereco" required>
            </div>

            <input type="submit" value="Cadastrar Loja" class="btn-cadastro">
        </form>
        
        <div class="nav-links">
            <p>Já tem conta? <a href="login_loja.php">Faça login aqui</a>.</p>
            <p><a href="index.php">Voltar para a Página Inicial</a></p>
        </div>
    </div>
</body>
</html>