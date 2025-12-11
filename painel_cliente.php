<?php
include 'db_connect.php'; 
session_start();

// Verifica se o usuário está logado e se é um cliente
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    header("Location: login_cliente.php");
    exit();
}

$nome_cliente = $_SESSION['nm_usuario'];
$id_cliente = $_SESSION['cd_usuario'];
$mensagem_perfil = "";
$mensagem_endereco = "";


// POST HANDLING: ATUALIZAÇÃO DO PERFIL (Nome, Email, Telefone)

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nm_cliente = $mysqli->real_escape_string($_POST['nome']);
    $ds_email   = $mysqli->real_escape_string($_POST['email']);
    $nr_telefone= $mysqli->real_escape_string($_POST['telefone']);
    
    $sql = "UPDATE cadastro_cliente SET nm_cliente = ?, ds_email = ?, nr_telefone = ? WHERE cd_cliente = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sssi", $nm_cliente, $ds_email, $nr_telefone, $id_cliente);
    
    if ($stmt->execute()) {
        $_SESSION['nm_usuario'] = $nm_cliente; 
        $nome_cliente = $nm_cliente; 
        $mensagem_perfil = "<p class='msg-sucesso'>✅ Perfil atualizado com sucesso!</p>";
    } else {
        if ($mysqli->errno == 1062) {
             $mensagem_perfil = "<p class='msg-erro'>❌ Erro: E-mail já cadastrado.</p>";
        } else {
             $mensagem_perfil = "<p class='msg-erro'>❌ Erro ao atualizar perfil: " . $stmt->error . "</p>";
        }
    }
    $stmt->close();
}


// B. POST HANDLING: CADASTRO/EDIÇÃO DE ENDEREÇO

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'save_address') {
    // Coleta e sanitiza os dados do endereço (ds_estado removido)
    $ds_endereco = $mysqli->real_escape_string($_POST['ds_endereco']); 
    $nm_bairro   = $mysqli->real_escape_string($_POST['nm_bairro']);
    $nm_cidade   = $mysqli->real_escape_string($_POST['nm_cidade']);
    $cd_cep      = $mysqli->real_escape_string($_POST['cd_cep']); 
    
    // Verifica se endereço já existe 
    $sql_check = "SELECT cd_endereco FROM endereco_cliente WHERE cd_cliente = ? LIMIT 1";
    $stmt_check = $mysqli->prepare($sql_check);
    $stmt_check->bind_param("i", $id_cliente);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        // UPDATE 
        $endereco_id = $result_check->fetch_assoc()['cd_endereco'];

        $sql_addr = "UPDATE endereco_cliente SET ds_endereco=?, nm_bairro=?, nm_cidade=?, cd_cep=? WHERE cd_endereco=?";
        $stmt_addr = $mysqli->prepare($sql_addr);
        $stmt_addr->bind_param("ssssi", $ds_endereco, $nm_bairro, $nm_cidade, $cd_cep, $endereco_id);
        $acao = "atualizado";
    } else {
        // INSERT 
        $sql_addr = "INSERT INTO endereco_cliente (cd_cliente, ds_endereco, nm_bairro, nm_cidade, cd_cep) VALUES (?, ?, ?, ?, ?)";
        $stmt_addr = $mysqli->prepare($sql_addr);
        $stmt_addr->bind_param("issss", $id_cliente, $ds_endereco, $nm_bairro, $nm_cidade, $cd_cep);
        $acao = "cadastrado";
    }
    
    if ($stmt_addr->execute()) {
        $mensagem_endereco = "<p class='msg-sucesso'>✅ Endereço {$acao} com sucesso!</p>";
    } else {
        $mensagem_endereco = "<p class='msg-erro'>❌ Erro ao {$acao} endereço: " . $stmt_addr->error . "</p>";
    }
    $stmt_check->close();
    $stmt_addr->close();
}


// C. FETCH DATA (Profile, Address, Cart)


// Fetch current profile data
$sql_profile = "SELECT nm_cliente, ds_email, nr_telefone FROM cadastro_cliente WHERE cd_cliente = ?";
$stmt_profile = $mysqli->prepare($sql_profile);
$stmt_profile->bind_param("i", $id_cliente);
$stmt_profile->execute();
$profile_data = $stmt_profile->get_result()->fetch_assoc();
$stmt_profile->close();

// Fetch current address data 
$sql_address = "SELECT cd_endereco, ds_endereco, nm_bairro, nm_cidade, cd_cep FROM endereco_cliente WHERE cd_cliente = ? LIMIT 1";
$stmt_address = $mysqli->prepare($sql_address);
$stmt_address->bind_param("i", $id_cliente);
$stmt_address->execute();
$address_data = $stmt_address->get_result()->fetch_assoc();
$stmt_address->close();
$address_exists = $address_data !== null;

// Fetch Cart Summary Data
$subtotal_carrinho = 0.00;
$cd_pedido = $_SESSION['cd_pedido_ativo'] ?? 0;
$total_itens_carrinho = 0;

if ($cd_pedido > 0) {
    $sql_cart = "SELECT qt_produto, vl_preco_unitario FROM itens WHERE cd_pedido = ?";
    $stmt_cart = $mysqli->prepare($sql_cart);
    $stmt_cart->bind_param("i", $cd_pedido);
    $stmt_cart->execute();
    $resultado_cart = $stmt_cart->get_result();
    
    while ($item = $resultado_cart->fetch_assoc()) {
        $subtotal_carrinho += $item['qt_produto'] * $item['vl_preco_unitario'];
        $total_itens_carrinho += $item['qt_produto'];
    }
    $stmt_cart->close();
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Cliente - CandyPlace</title>
    <style>
        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D; 
            --cor-marrom-escuro: #6B4423; 
            --cor-borda: #E0D4C5;
            --cor-verde-sucesso: #28A745;
            --cor-azul-acao: #007bff; 
            --cor-vermelho-erro: #DC3545;
            --cor-cart-fundo: #FFF8E1; 
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px;
            background-color: var(--cor-principal-fundo);
        }

        /* Container Principal do Painel (Maior que o Checkout) */
        .painel-container {
            max-width: 1000px;
            margin: 20px auto;
            background-color: var(--cor-container-fundo); 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            padding: 30px;
        }

        .painel-container h2 {
            color: var(--cor-marrom-acao); 
            border-bottom: 2px solid var(--cor-borda); 
            padding-bottom: 15px; 
            margin-top: 0; 
            margin-bottom: 25px;
        }
        
        /* Container para os 3 Cards/Seções */
        .card-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
        }
        
        .card-section {
            flex: 1 1 300px;
            border: 1px solid var(--cor-borda);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            background-color: #FBFBFB;
        }
        
        .card-section h3 {
            color: var(--cor-marrom-escuro); 
            border-bottom: 1px dashed var(--cor-borda);
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.2em;
        }

        /* Cores específicas dos cards */
        .card-carrinho {
            background-color: var(--cor-cart-fundo);
            border-left: 5px solid var(--cor-marrom-acao);
        }
        .card-perfil {
            border-left: 5px solid var(--cor-azul-acao);
        }
        .card-endereco {
            border-left: 5px solid var(--cor-verde-sucesso);
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



        /* FORMS E INPUTS */
 
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
        
        /* Estilo dos botões de Salvar */
        .btn-salvar-perfil, .btn-salvar-endereco {
            padding: 10px 15px; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            margin-top: 10px;
        }
        .btn-salvar-perfil {
            background-color: var(--cor-azul-acao);
        }
        .btn-salvar-perfil:hover {
            background-color: #0056b3;
        }
        .btn-salvar-endereco {
            background-color: var(--cor-verde-sucesso);
        }
        .btn-salvar-endereco:hover {
            background-color: #1e8449;
        }

        /* Links */
        .action-links a {
            color: var(--cor-marrom-escuro); 
            text-decoration: none; 
            font-weight: bold; 
            margin: 0 15px;
            transition: color 0.2s;
        }
        .action-links a:hover {
            color: var(--cor-marrom-acao);
        }
        .logout-link { 
            color: var(--cor-vermelho-erro) !important; 
        }
        
        /* Endereço Cadastrado Visualização */
        .address-display {
            padding: 10px;
            border: 1px dashed var(--cor-borda);
            border-radius: 4px;
            background-color: #fcfcfc;
            margin-bottom: 15px;
        }
        .address-display strong {
            color: var(--cor-marrom-escuro);
        }
        .address-display p {
            margin: 5px 0;
            font-size: 0.95em;
        }
        .address-display hr {
            border: 0;
            height: 1px;
            background-color: var(--cor-borda);
            margin: 15px 0;
        }

        @media (max-width: 768px) {
            .card-grid {
                flex-direction: column;
            }
            .card-section {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
    <div class="painel-container">
        <h2>👋 Olá, **<?php echo htmlspecialchars($nome_cliente); ?>**! Bem-vindo(a) ao seu Painel.</h2>
        
        <p class="action-links">
            <a href="index.php">⬅️ Voltar para a Home</a> 
            | 
            <a href="#endereco">📍 Editar Endereço</a>
            |
            <a href="logout.php" class="logout-link">Sair (Logout)</a>
        </p>

        <div class="card-grid">
            
            <div class="card-section card-carrinho">
                <h3>🛒 Resumo do Carrinho</h3>
                <?php if ($total_itens_carrinho > 0): ?>
                    <p>Você tem **<?php echo $total_itens_carrinho; ?>** itens no carrinho.</p>
                    <p>Subtotal (sem frete): **R$ <?php echo number_format($subtotal_carrinho, 2, ',', '.'); ?>**</p>
                    <a href="carrinho.php" style="color: var(--cor-marrom-acao); font-weight: bold;">➡️ Ir para o Carrinho / Checkout</a>
                <?php else: ?>
                    <p>Seu carrinho está vazio.</p>
                    <a href="index.php" style="color: var(--cor-verde-sucesso); font-weight: bold;">Começar a Comprar!</a>
                <?php endif; ?>
            </div>

            <div class="card-section card-perfil">
                <h3>⚙️ Editar Dados Pessoais</h3>
                <?php echo $mensagem_perfil; ?>
                <form method="post" action="painel_cliente.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="nome">Nome:</label>
                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($profile_data['nm_cliente'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile_data['ds_email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($profile_data['nr_telefone'] ?? ''); ?>" required>
                    </div>
                    
                    <button type="submit" class="btn-salvar-perfil">Salvar Alterações</button>
                </form>
            </div>

            <div class="card-section card-endereco" id="endereco">
                <h3>📍 Endereço de Entrega</h3>
                <?php echo $mensagem_endereco; ?>
                
                <?php if ($address_exists): ?>
                    <div class="address-display">
                        <p>Endereço Cadastrado:</p>
                        <p><strong><?php echo htmlspecialchars($address_data['ds_endereco'] ?? ''); ?></strong></p>
                        <p>Bairro: <?php echo htmlspecialchars($address_data['nm_bairro'] ?? ''); ?></p>
                        <p>Cidade: <?php echo htmlspecialchars($address_data['nm_cidade'] ?? ''); ?> | CEP: <?php echo htmlspecialchars($address_data['cd_cep'] ?? ''); ?></p>
                    </div>
                <?php else: ?>
                    <p style="color: var(--cor-marrom-acao); font-weight: bold;">Nenhum endereço cadastrado.</p>
                <?php endif; ?>

                <form method="post" action="painel_cliente.php#endereco">
                    <h4><?php echo $address_exists ? 'Editar Endereço' : 'Cadastrar Novo Endereço'; ?></h4>
                    <input type="hidden" name="action" value="save_address">
                    
                    <div class="form-group">
                        <label for="ds_endereco">Rua/Avenida + Número:</label>
                        <input type="text" id="ds_endereco" name="ds_endereco" value="<?php echo htmlspecialchars($address_data['ds_endereco'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nm_bairro">Bairro:</label>
                        <input type="text" id="nm_bairro" name="nm_bairro" value="<?php echo htmlspecialchars($address_data['nm_bairro'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nm_cidade">Cidade:</label>
                        <input type="text" id="nm_cidade" name="nm_cidade" value="<?php echo htmlspecialchars($address_data['nm_cidade'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cd_cep">CEP (apenas números):</label>
                        <input type="text" id="cd_cep" name="cd_cep" maxlength="9" placeholder="00000-000" value="<?php echo htmlspecialchars($address_data['cd_cep'] ?? ''); ?>" required>
                    </div>

                    <button type="submit" class="btn-salvar-endereco">Salvar Endereço</button>
                </form>
            </div>

        </div>
    </div>
</body>
</html>