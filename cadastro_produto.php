<?php
include 'db_connect.php';
session_start();

//  VERIFICAÇÃO DE ACESSO (APENAS LOJAS)
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'loja') {
    header("Location: login_loja.php");
    exit();
}

$cd_loja_logada = $_SESSION['cd_usuario'];
$mensagem = "";

// DIRETÓRIO ONDE AS IMAGENS SERÃO SALVAS (CRIE ESTA PASTA!)
$diretorio_uploads = "uploads/produtos/";
if (!is_dir($diretorio_uploads)) {
    // Tenta criar o diretório se ele não existir
    mkdir($diretorio_uploads, 0777, true);
}


//  PROCESSAMENTO DE EDIÇÃO (READ - para popular o formulário) (Identificação se o usuario esta tentando editar o produto)
$produto_para_edicao = null;
$id_produto_edit = 0; // Inicializa a variável para o bloco POST

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id_produto_edit = intval($_GET['id']);

    $sql = "SELECT * FROM produto WHERE cd_produto = ? AND cd_loja = ?"; 
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ii", $id_produto_edit, $cd_loja_logada);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $produto_para_edicao = $resultado->fetch_assoc();
    } else {
        $mensagem = "<p class='msg-alerta'>⚠️ Produto não encontrado para edição.</p>";
    }
    $stmt->close();
}


//  PROCESSAMENTO DO FORMULÁRIO (CREATE & UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nm_produto = $mysqli->real_escape_string($_POST['nm_produto']);
    $ds_produto = $mysqli->real_escape_string($_POST['ds_produto']);
    $ds_categoria = $mysqli->real_escape_string($_POST['ds_categoria']);
    $vl_preco = floatval($_POST['vl_preco']); 
    $qt_estoque = intval($_POST['qt_estoque']); 
    $cd_produto_edit = isset($_POST['cd_produto_edit']) ? intval($_POST['cd_produto_edit']) : 0;

    $ds_imagem = "";
    $imagem_antiga = "";

    // Se estiver editando, precisamos carregar os dados antigos novamente
    if ($cd_produto_edit > 0) {

        $sql_get_old = "SELECT ds_imagem FROM produto WHERE cd_produto = ? AND cd_loja = ?"; 
        $stmt_get_old = $mysqli->prepare($sql_get_old);
        $stmt_get_old->bind_param("ii", $cd_produto_edit, $cd_loja_logada);
        $stmt_get_old->execute();
        $resultado_old = $stmt_get_old->get_result();
        $dados_antigos = $resultado_old->fetch_assoc();
        $ds_imagem = $dados_antigos['ds_imagem'] ?? ""; // Mantém a imagem atual como default
        $imagem_antiga = $ds_imagem;
        $stmt_get_old->close();
    }
    
    // TRATAMENTO DO UPLOAD DE IMAGEM
    if (isset($_FILES['ds_imagem']) && $_FILES['ds_imagem']['error'] === UPLOAD_ERR_OK) {
        $arquivo_temp = $_FILES['ds_imagem']['tmp_name'];
        $nome_original = basename($_FILES['ds_imagem']['name']);
        $extensao = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
        
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($extensao, $tipos_permitidos)) {
            $nome_final = uniqid('prod_', true) . '.' . $extensao;
            $caminho_completo = $diretorio_uploads . $nome_final;
            
            if (move_uploaded_file($arquivo_temp, $caminho_completo)) {
                $ds_imagem = $caminho_completo;
                
                // Se for edição e a imagem foi trocada, exclui a antiga
                if ($cd_produto_edit > 0 && !empty($imagem_antiga) && file_exists($imagem_antiga)) {
                    unlink($imagem_antiga);
                }
            } else {
                $mensagem .= "<p class='msg-erro'>❌ Erro ao mover o arquivo de imagem para o servidor. Tente novamente.</p>";
            }
        } else {
            $mensagem .= "<p class='msg-erro'>❌ Tipo de arquivo de imagem não permitido. Use JPG, JPEG, PNG ou GIF.</p>";
        }
    }
    
    if ($cd_produto_edit > 0) {
        // OPERAÇÃO UPDATE

        $sql = "UPDATE produto SET nm_produto=?, ds_produto=?, ds_categoria=?, vl_preco=?, qt_estoque=?, ds_imagem=? WHERE cd_produto=? AND cd_loja=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sssdssii", $nm_produto, $ds_produto, $ds_categoria, $vl_preco, $qt_estoque, $ds_imagem, $cd_produto_edit, $cd_loja_logada);
        $acao = "atualizado";
    } else {
        // OPERAÇÃO CREATE
   
        $sql = "INSERT INTO produto (cd_loja, nm_produto, ds_produto, ds_categoria, vl_preco, qt_estoque, ds_imagem) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql); 
        $stmt->bind_param("isssdss", $cd_loja_logada, $nm_produto, $ds_produto, $ds_categoria, $vl_preco, $qt_estoque, $ds_imagem);
        $acao = "cadastrado";
    }

    if ($stmt->execute()) {
        $mensagem .= "<p class='msg-sucesso'>✅ Produto $acao com sucesso! Redirecionando...</p>";
        // Redireciona para o painel de produtos
        header("Location: painel_loja.php?aba=produtos");
        exit();
    } else {
        $mensagem .= "<p class='msg-erro'>❌ Erro ao $acao produto: " . $stmt->error . "</p>";
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
    <title>Gestão de Produtos - Loja <?php echo $_SESSION['nm_usuario']; ?></title>
    <style>

        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D;    
            --cor-marrom-escuro: #6B4423; 
            --cor-borda: #E0D4C5;
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            background-color: var(--cor-principal-fundo);
        }
        
        /* Cabeçalho */
        .header-loja { 
            background-color: var(--cor-marrom-acao);
            color: white; 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .header-loja h1 { 
            margin: 0; 
            font-size: 1.5em; 
        }
        .header-loja a { 
            color: #FFDAB9; 
            text-decoration: none; 
            margin-left: 20px; 
            font-weight: bold; 
        }

        /* Container Principal do Conteúdo */
        .main-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background-color: var(--cor-container-fundo);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .main-container h2 {
            color: var(--cor-marrom-escuro); 
            border-bottom: 2px solid var(--cor-marrom-acao); 
            padding-bottom: 10px; 
            margin-top: 0; 
        }
        
        /* Estilos de Formulário */
        .form-container { 
            background: #FDFBF8; 
            padding: 25px; 
            border: 1px solid var(--cor-borda); 
            border-radius: 6px;
            margin-bottom: 30px;
        }

        label { font-weight: bold; display: block; margin-top: 15px; color: var(--cor-marrom-escuro); }
        input[type="text"], input[type="number"], textarea, input[type="file"] {
            width: 100%; 
            padding: 10px; 
            margin-top: 5px; 
            box-sizing: border-box; 
            border: 1px solid #C0C0C0; 
            border-radius: 4px;
        }
        textarea { resize: vertical; }

        /* Estilo de Botões */
        .btn-acao, input[type="submit"] {
            display: inline-block; 
            padding: 10px 15px; 
            background-color: var(--cor-marrom-acao); 
            color: white; 
            text-decoration: none; 
            border: none; 
            border-radius: 4px; 
            margin-top: 20px; 
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: bold;
        }
        .btn-acao:hover, input[type="submit"]:hover {
            background-color: #8B4513 !important; 
        }
        .cancel-link {
            color: var(--cor-marrom-escuro);
            margin-left: 15px;
            text-decoration: underline;
        }

        /* Mensagens de feedback */
        .msg-sucesso { color: #28A745; font-weight: bold; background: #E6F7E9; padding: 10px; border-radius: 4px; border: 1px solid #D4EED8; }
        .msg-erro { color: #DC3545; font-weight: bold; background: #FDE8E9; padding: 10px; border-radius: 4px; border: 1px solid #F8D1D5; }
        .msg-alerta { color: #FFC107; font-weight: bold; background: #FFF3CD; padding: 10px; border-radius: 4px; border: 1px solid #FFECB5; }

        /* Miniaturas de Imagem */
        .mini-imagem { 
            width: 50px; 
            height: 50px; 
            object-fit: cover; 
            border-radius: 4px; 
            border: 1px solid var(--cor-borda);
        }
    </style>
</head>
<body>
    <div class="header-loja">
        <h1>🍬 Gestão de Produtos - Loja <?php echo $_SESSION['nm_usuario']; ?></h1>
        <div>
            <a href="painel_loja.php?aba=produtos">Voltar ao Painel</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="main-container">
        <h2><?php echo $produto_para_edicao ? '✏️ Editar Produto Existente' : '➕ Cadastrar Novo Produto'; ?></h2>

        <?php echo $mensagem; ?>

        <div class="form-container">
            <h3>Preencha os dados do item</h3>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                <?php if ($produto_para_edicao): ?>
                    <input type="hidden" name="cd_produto_edit" value="<?php echo $produto_para_edicao['cd_produto']; ?>">
                <?php endif; ?>

                <label for="nm_produto">Nome do Produto:</label>
                <input type="text" id="nm_produto" name="nm_produto" value="<?php echo $produto_para_edicao ? htmlspecialchars($produto_para_edicao['nm_produto']) : ''; ?>" required>

                <label for="vl_preco">Preço (R$):</label>
                <input type="number" step="0.01" id="vl_preco" name="vl_preco" value="<?php echo $produto_para_edicao ? $produto_para_edicao['vl_preco'] : ''; ?>" required>

                <label for="qt_estoque">Estoque Inicial:</label>
                <input type="number" id="qt_estoque" name="qt_estoque" value="<?php echo $produto_para_edicao ? $produto_para_edicao['qt_estoque'] : ''; ?>" required>

                <label for="ds_categoria">Categoria (Ex: Doces, Salgados):</label>
                <input type="text" id="ds_categoria" name="ds_categoria" value="<?php echo $produto_para_edicao ? htmlspecialchars($produto_para_edicao['ds_categoria']) : ''; ?>" required>
                
                <label for="ds_produto">Descrição Detalhada:</label>
                <textarea id="ds_produto" name="ds_produto" rows="4"><?php echo $produto_para_edicao ? htmlspecialchars($produto_para_edicao['ds_produto']) : ''; ?></textarea>
                
                <label for="ds_imagem">Imagem do Produto (Trocar/Adicionar):</label>
                <input type="file" id="ds_imagem" name="ds_imagem" accept=".jpg, .jpeg, .png, .gif">
                
                <?php if ($produto_para_edicao && !empty($produto_para_edicao['ds_imagem'])): ?>
                    <p>
                        Imagem atual: 
                        <img src="<?php echo htmlspecialchars($produto_para_edicao['ds_imagem']); ?>" alt="Imagem atual do produto" class="mini-imagem"> 
                        <small>(Selecione um novo arquivo para substituir)</small>
                    </p>
                <?php endif; ?>

                <input type="submit" value="<?php echo $produto_para_edicao ? 'Salvar Alterações' : 'Cadastrar Produto'; ?>">
                
                <?php if ($produto_para_edicao): ?>
                    <a href="cadastro_produto.php" class="cancel-link">Cancelar Edição</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>