<?php
include 'db_connect.php'; 
session_start();

// 1. VERIFICAÇÃO DE SESSÃO DA LOJA
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'loja') {
    header("Location: login_loja.php"); 
    exit();
}

$cd_loja = $_SESSION['cd_usuario']; 
$nm_loja = $_SESSION['nm_usuario'];
$aba_ativa = isset($_GET['aba']) ? $_GET['aba'] : 'produtos'; 
$mensagem_config = ''; // Para exibir mensagens de sucesso ou erro na aba de configurações

// 1.1. TRATAMENTO DE MENSAGENS DE SESSÃO (APÓS EXCLUSÃO, POR EXEMPLO)
$mensagem_sessao = '';
if (isset($_SESSION['mensagem_loja'])) {
    $mensagem_sessao = $_SESSION['mensagem_loja'];
    unset($_SESSION['mensagem_loja']); // Limpa a mensagem após exibir
}

// 2. LÓGICA DE ATUALIZAÇÃO DE DADOS DA LOJA (Configurações)
if ($aba_ativa == 'configuracoes' && isset($_POST['atualizar_dados'])) {
    
    $nm_loja_novo = $mysqli->real_escape_string(trim($_POST['nm_loja']));
    $nm_razao_social = $mysqli->real_escape_string(trim($_POST['nm_razao_social']));
    $nr_telefone = $mysqli->real_escape_string(trim($_POST['nr_telefone']));
    $ds_endereco = $mysqli->real_escape_string(trim($_POST['ds_endereco']));

    // Verifica se a loja existe e realiza o UPDATE
    $sql_update = "
        UPDATE cadastro_loja SET 
            nm_loja = ?, 
            nm_razao_social = ?,  
            nr_telefone = ?, 
            ds_endereco = ?
        WHERE cd_loja = ?
    ";
    
    $stmt_update = $mysqli->prepare($sql_update);
    $stmt_update->bind_param("ssssi", $nm_loja_novo, $nm_razao_social, $nr_telefone, $ds_endereco, $cd_loja);

    if ($stmt_update->execute()) {
        $mensagem_config = "<p style='color: #4CAF50; font-weight: bold;'>✅ Dados atualizados com sucesso!</p>";
        $_SESSION['nm_usuario'] = $nm_loja_novo;
        $nm_loja = $nm_loja_novo;
    } else {
        $mensagem_config = "<p style='color: #F44336; font-weight: bold;'>❌ Erro ao atualizar: " . $stmt_update->error . "</p>";
    }
    $stmt_update->close();
}


// 3. BUSCAR DADOS ATUAIS DA LOJA 
$sql_loja = "SELECT nm_loja, nm_razao_social, nr_telefone, ds_endereco FROM cadastro_loja WHERE cd_loja = ?";
$stmt_loja = $mysqli->prepare($sql_loja);
$stmt_loja->bind_param("i", $cd_loja);
$stmt_loja->execute();
$resultado_loja = $stmt_loja->get_result();
$dados_loja = $resultado_loja->fetch_assoc();
$stmt_loja->close();

if (!$dados_loja) {
    session_destroy();
    header("Location: login_loja.php?erro=cadastro_invalido");
    exit();
}


// 4. FUNÇÃO PARA CARREGAR CONTEÚDO DAS ABAS 
function carregar_conteudo($aba, $mysqli, $cd_loja, $nm_loja, $dados_loja, $mensagem_config, $mensagem_sessao) {
    
    switch ($aba) {
        
        case 'produtos':
            $produtos_loja = [];

            // LÓGICA DE BUSCA DE PRODUTOS
            $sql_listagem = "SELECT cd_produto, nm_produto, ds_categoria, vl_preco, qt_estoque, ds_imagem FROM produto WHERE cd_loja = ? ORDER BY cd_produto DESC";

            if ($stmt_listagem = $mysqli->prepare($sql_listagem)) {
                $stmt_listagem->bind_param("i", $cd_loja);
                $stmt_listagem->execute();
                $resultado_listagem = $stmt_listagem->get_result();

                while ($produto = $resultado_listagem->fetch_assoc()) {
                    $produtos_loja[] = $produto;
                }
                $stmt_listagem->close();
            }

            // INÍCIO DO CONTEÚDO HTML DA ABA PRODUTOS
            $html = '<h3>🍔 Gestão de Produtos e Estoque</h3>';
            $html .= $mensagem_sessao; // Exibe mensagens (ex: após exclusão)
            $html .= '<p>Gerencie seus itens. Você pode editar preços, estoque e adicionar novos produtos.</p>';

            $html .= '<h3 class="subsection-title">Inventário de Produtos</h3>';

            if (empty($produtos_loja)) {
                $html .= '<p class="msg-alerta">Você ainda não possui produtos cadastrados. Utilize o botão abaixo para começar!</p>';
            } else {
                // TABELA DE PRODUTOS
                $html .= '<div class="table-responsive">';
                $html .= '<table class="inventory-table">';
                $html .= '<thead><tr><th>ID</th><th>Imagem</th><th>Nome</th><th>Categoria</th><th>Preço (R$)</th><th>Estoque</th><th>Ações</th></tr></thead>';
                $html .= '<tbody>';
                
                foreach ($produtos_loja as $produto) {
                    $imagem_tag = !empty($produto['ds_imagem']) 
                        ? '<img src="' . htmlspecialchars($produto['ds_imagem']) . '" alt="Miniatura" class="product-thumbnail">' 
                        : '🖼️';
                    $preco_formatado = number_format($produto['vl_preco'], 2, ',', '.');
                    
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($produto['cd_produto']) . '</td>';
                    $html .= '<td>' . $imagem_tag . '</td>';
                    $html .= '<td>' . htmlspecialchars($produto['nm_produto']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($produto['ds_categoria']) . '</td>';
                    $html .= '<td>R$ ' . $preco_formatado . '</td>';
                    $html .= '<td>' . htmlspecialchars($produto['qt_estoque']) . '</td>';
                    $html .= '<td>';
                    // Link para EDICÃO
                    $html .= '<a href="cadastro_produto.php?action=edit&id=' . $produto['cd_produto'] . '" class="action-link edit-link">Editar</a>';
                    // Link para EXCLUSÃO (Chama JS)
                    $html .= '<a href="#" onclick="confirmDelete(' . $produto['cd_produto'] . ')" class="action-link delete-link">Excluir</a>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody></table></div>';
            }
            
            // Botão Cadastrar Novo Produto
            $html .= '<a href="cadastro_produto.php" class="btn-acao-principal">➕ Cadastrar Novo Produto</a>';
            return $html;

        case 'configuracoes':
            // ... (HTML para Configurações, inalterado) ...
            $btn_cor = '#A0522D';
            $html = '<h3>⚙️ Dados da Loja e Configurações</h3>';
            $html .= $mensagem_config; 
            $html .= '<p>Atualize seus dados cadastrais. As alterações serão refletidas imediatamente.</p>';
            $html .= '<form method="POST" action="?aba=configuracoes">';
            
            // Campo Nome da Loja
            $html .= '<label for="nm_loja">Nome da Loja:</label><br>';
            $html .= '<input type="text" id="nm_loja" name="nm_loja" value="' . htmlspecialchars($dados_loja['nm_loja']) . '" required><br><br>';
            
            // Campo Razão Social
            $html .= '<label for="nm_razao_social">Razão Social:</label><br>';
            $html .= '<input type="text" id="nm_razao_social" name="nm_razao_social" value="' . htmlspecialchars($dados_loja['nm_razao_social']) . '" required><br><br>';

            // Campo Telefone/WhatsApp
            $html .= '<label for="nr_telefone">Telefone / WhatsApp:</label><br>';
            $html .= '<input type="text" id="nr_telefone" name="nr_telefone" value="' . htmlspecialchars($dados_loja['nr_telefone']) . '" required><br><br>';

            // Campo Endereço
            $html .= '<label for="ds_endereco">Endereço (Principal):</label><br>';
            $html .= '<textarea id="ds_endereco" name="ds_endereco" rows="3" required>' . htmlspecialchars($dados_loja['ds_endereco']) . '</textarea><br><br>';
            
            // Botão de Envio
            $html .= '<input type="submit" name="atualizar_dados" value="Salvar Alterações" class="btn-acao" style="background-color: ' . $btn_cor . ';">';
            $html .= '</form>';
            
            return $html;

        default:
            return '<h3>Bem-vindo, ' . htmlspecialchars($nm_loja) . '!</h3><p>Selecione uma aba para começar a gerenciar.</p>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel da Loja - <?php echo htmlspecialchars($nm_loja); ?></title>
    <style>
        /* Paleta de Cores baseada no seu site: */
        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D;    
            --cor-marrom-escuro: #6B4423;  
            --cor-borda: #E0D4C5; /* Nova variável de cor de borda */
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            background-color: var(--cor-principal-fundo);
        }
        
        /* Cabeçalho superior */
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
        .container-loja { 
            display: flex; 
            max-width: 1200px; 
            margin: 20px auto; 
            background-color: var(--cor-container-fundo); 
            border-radius: 8px; 
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); 
            overflow: hidden; 
        }
        
        /* Menu Lateral */
        .menu-loja { 
            width: 250px; 
            background-color: var(--cor-principal-fundo); 
            padding: 20px; 
            border-right: 1px solid var(--cor-borda); 
        }
        .menu-loja ul { 
            list-style: none; 
            padding: 0; 
        }
        .menu-loja li { 
            margin-bottom: 10px; 
        }
        .menu-loja a { 
            display: block; 
            padding: 10px; 
            background-color: #FFFFFF; 
            color: var(--cor-marrom-escuro); 
            text-decoration: none; 
            border-radius: 4px; 
            transition: background-color 0.3s;
            border: 1px solid var(--cor-borda);
        }
        .menu-loja a:hover { 
            background-color: #F0EADF; 
        }
        .menu-loja a.active { 
            background-color: var(--cor-marrom-acao); 
            color: white; 
            font-weight: bold; 
        }
        
        /* Conteúdo Principal */
        .content-loja { 
            flex-grow: 1; 
            padding: 30px; 
        }
        .content-loja h2, .content-loja h3 { 
            color: var(--cor-marrom-escuro); 
            border-bottom: 2px solid var(--cor-marrom-acao); 
            padding-bottom: 10px; 
            margin-top: 0; 
        }
        
        /* Estilos de Formulário */
        .content-loja label { font-weight: bold; display: block; margin-top: 10px; color: var(--cor-marrom-escuro); }
        .content-loja input[type="text"], .content-loja input[type="email"], .content-loja textarea { 
            width: 100%; 
            padding: 10px; 
            margin-top: 5px; 
            box-sizing: border-box; 
            border: 1px solid #C0C0C0; 
            border-radius: 4px;
        }
        .content-loja textarea { resize: vertical; }

        /* Estilos de Botão de Ação */
        .btn-acao { /* Para botões menores ou dentro de formulários */
            display: inline-block; 
            padding: 10px 15px; 
            color: white; 
            text-decoration: none; 
            border: none; 
            border-radius: 4px; 
            margin-top: 15px; 
            cursor: pointer;
            transition: background-color 0.3s;
            background-color: var(--cor-marrom-acao); /* Garante a cor base */
        }
        .btn-acao:hover {
            background-color: #8B4513 !important; 
        }

        /* Mensagens de feedback */
        .msg-sucesso { color: #28A745; font-weight: bold; background: #E6F7E9; padding: 10px; border-radius: 4px; border: 1px solid #D4EED8; }
        .msg-erro { color: #DC3545; font-weight: bold; background: #FDE8E9; padding: 10px; border-radius: 4px; border: 1px solid #F8D1D5; }
        .msg-alerta { color: #FFC107; font-weight: bold; background: #FFF3CD; padding: 10px; border-radius: 4px; border: 1px solid #FFECB5; }


        /* ========================================================= */
        /* ESTILOS DA TABELA DE PRODUTOS (INVENTÁRIO) */
        /* ========================================================= */

        .subsection-title {
            color: var(--cor-marrom-acao); 
            font-size: 1.5em;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--cor-borda);
        }

        .table-responsive {
            overflow-x: auto; 
            margin-bottom: 20px;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden; 
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .inventory-table th, .inventory-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #EAEAEA;
        }

        .inventory-table th {
            background-color: var(--cor-marrom-acao); 
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        .inventory-table tr:nth-child(even) {
            background-color: #F9F9F9; /* Linhas alternadas */
        }

        /* Miniatura da Imagem */
        .product-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--cor-borda);
        }

        /* Links de Ação (Editar/Excluir) */
        .action-link {
            display: inline-block;
            padding: 5px 8px;
            margin-right: 5px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
            text-decoration: none;
            transition: opacity 0.3s;
            border: none;
        }

        .edit-link {
            background-color: #FFC107; /* Amarelo */
            color: #333;
        }

        .delete-link {
            background-color: #DC3545; /* Vermelho */
            color: white;
        }

        .edit-link:hover { opacity: 0.8; }
        .delete-link:hover { opacity: 0.8; }

        /* Estilo do Botão Principal (Cadastrar Novo Produto) */
        .btn-acao-principal {
            display: inline-block;
            padding: 15px 30px;
            background-color: var(--cor-marrom-acao);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1em;
            margin-top: 20px;
            transition: background-color 0.3s, transform 0.3s;
        }
        .btn-acao-principal:hover {
            background-color: #8B4513; 
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="header-loja">
        <h1>🛠️ Painel da Loja: <?php echo htmlspecialchars($nm_loja); ?></h1>
        <div>
            <a href="index.php">Ir para o Site</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container-loja">
        
        <nav class="menu-loja">
            <ul>
                <li>
                    <a href="?aba=produtos" class="<?php echo ($aba_ativa == 'produtos') ? 'active' : ''; ?>">
                        🛒 Produtos
                    </a>
                </li>
                <li>
                    <a href="?aba=configuracoes" class="<?php echo ($aba_ativa == 'configuracoes') ? 'active' : ''; ?>">
                        ⚙️ Configurações
                    </a>
                </li>
            </ul>
            
            <hr>
            
            <div style="padding: 10px; font-size: 0.9em; color: var(--cor-marrom-escuro);">
                <p>📞 Telefone: <?php echo htmlspecialchars($dados_loja['nr_telefone'] ?? 'Não cadastrado'); ?></p>
            </div>
            
        </nav>

        <main class="content-loja">
            <h2><?php 
                echo ucwords(str_replace('_', ' ', $aba_ativa)); 
            ?></h2>
            
            <?php
            // 4. CARREGAMENTO DO CONTEÚDO (CHAMADA DA FUNÇÃO)
            echo carregar_conteudo($aba_ativa, $mysqli, $cd_loja, $nm_loja, $dados_loja, $mensagem_config, $mensagem_sessao);
            ?>
            
        </main>
    </div>

    <script>
        /**
         * Função JavaScript para confirmar a exclusão de um produto.
         * Redireciona para delete_produto.php após a confirmação.
         */
        function confirmDelete(idProduto) {
            if (confirm("Tem certeza que deseja EXCLUIR permanentemente este produto? Esta ação não pode ser desfeita.")) {
                // Redireciona para o script de exclusão (você precisa criar este arquivo)
                window.location.href = 'delete_produto.php?id=' + idProduto;
            }
        }
    </script>

    <?php
    // Fechamento da conexão
    $mysqli->close(); 
    ?>
</body>
</html>