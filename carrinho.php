<?php
include 'db_connect.php';
session_start();

// 1. VERIFICAÇÃO DE LOGIN
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    header("Location: login_cliente.php");
    exit();
}

$cd_cliente = $_SESSION['cd_usuario'];
$mensagem = "";

// 2. VERIFICA OU CRIA O CARRINHO ATIVO (REGISTRO NA TABELA PEDIDO)
if (!isset($_SESSION['cd_pedido_ativo'])) {
    
    // Procura primeiro se existe um carrinho pendente que foi salvo no banco (recuperação do logout)
    $sql_carrinho_ativo = "SELECT cd_pedido FROM pedido WHERE cd_cliente = ? AND ds_status_pedido = 'Carrinho' LIMIT 1";
    $stmt_carrinho = $mysqli->prepare($sql_carrinho_ativo);
    $stmt_carrinho->bind_param("i", $cd_cliente);
    $stmt_carrinho->execute();
    $resultado_carrinho = $stmt_carrinho->get_result();
    
    if ($resultado_carrinho->num_rows === 1) {
        // Encontrou um carrinho salvo, carrega o ID
        $pedido_ativo = $resultado_carrinho->fetch_assoc();
        $_SESSION['cd_pedido_ativo'] = $pedido_ativo['cd_pedido'];
        // MENSAGEM ADAPTADA AO CSS
        $mensagem .= "<p class='msg-alerta'>🔄 Seu carrinho anterior foi recuperado com sucesso.</p>";
    } else {
        // Se não houver carrinho na sessão nem no banco, cria um novo
        // Inicia um novo pedido no banco de dados, salvando o status 'Carrinho'
        $sql_cria_pedido = "INSERT INTO pedido (cd_cliente, dt_pedido, ds_status_pedido) VALUES (?, NOW(), 'Carrinho')";
        $stmt_cria = $mysqli->prepare($sql_cria_pedido);
        $stmt_cria->bind_param("i", $cd_cliente);
        
        if ($stmt_cria->execute()) {
            $_SESSION['cd_pedido_ativo'] = $mysqli->insert_id;
        } else {
            // Erro fatal ao criar pedido
            // MENSAGEM ADAPTADA AO CSS
            $mensagem = "<p class='msg-erro'>❌ Erro fatal ao iniciar o carrinho: " . $stmt_cria->error . "</p>";
        }
        if (isset($stmt_cria)) {
            $stmt_cria->close();
        }
    }
    if (isset($stmt_carrinho)) {
        $stmt_carrinho->close();
    }
}

$cd_pedido = $_SESSION['cd_pedido_ativo'] ?? 0; // Recupera o ID do pedido ativo

// =========================================================
// 3. POST HANDLING: REMOÇÃO E ATUALIZAÇÃO DE ITENS
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && $cd_pedido > 0) {
    
    $cd_produto = isset($_POST['cd_produto']) ? intval($_POST['cd_produto']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : ''; // 'remove_one' ou 'delete_item'

    if ($cd_produto > 0) {
        
        try {
            // Busca a quantidade atual do item no carrinho
            $sql_qt_atual = "SELECT qt_produto FROM itens WHERE cd_pedido = ? AND cd_produto = ?";
            $stmt_qt_atual = $mysqli->prepare($sql_qt_atual);
            $stmt_qt_atual->bind_param("ii", $cd_pedido, $cd_produto);
            $stmt_qt_atual->execute();
            $resultado = $stmt_qt_atual->get_result();
            
            $qt_atual = $resultado->num_rows > 0 ? $resultado->fetch_assoc()['qt_produto'] : 0;
            $stmt_qt_atual->close();

            // Ação 1: Deletar o item completamente (Botão X)
            if ($action === 'delete_item') {
                $sql_delete = "DELETE FROM itens WHERE cd_pedido = ? AND cd_produto = ?";
                $stmt_delete = $mysqli->prepare($sql_delete);
                $stmt_delete->bind_param("ii", $cd_pedido, $cd_produto);
                $stmt_delete->execute();
                $mensagem_redirect = "Item removido completamente do carrinho.";
                $stmt_delete->close();

            // Ação 2: Remover apenas uma unidade (Botão -)
            } elseif ($action === 'remove_one') {
                if ($qt_atual > 1) {
                    $qt_nova = $qt_atual - 1;
                    $sql_update = "UPDATE itens SET qt_produto = ? WHERE cd_pedido = ? AND cd_produto = ?";
                    $stmt_update = $mysqli->prepare($sql_update);
                    $stmt_update->bind_param("iii", $qt_nova, $cd_pedido, $cd_produto);
                    $stmt_update->execute();
                    $mensagem_redirect = "Quantidade do item atualizada para {$qt_nova}.";
                    $stmt_update->close();
                } else {
                    // Se for a última unidade, remove o item
                    $sql_delete = "DELETE FROM itens WHERE cd_pedido = ? AND cd_produto = ?";
                    $stmt_delete = $mysqli->prepare($sql_delete);
                    $stmt_delete->bind_param("ii", $cd_pedido, $cd_produto);
                    $stmt_delete->execute();
                    $mensagem_redirect = "Item removido completamente do carrinho (última unidade).";
                    $stmt_delete->close();
                }
            }
            
        } catch (mysqli_sql_exception $e) {
            $mensagem_redirect = "Erro no banco de dados ao processar a ação.";
        }
        
        // Redireciona para o carrinho (GET) para evitar reenvio do formulário
        header("Location: carrinho.php?status=" . urlencode($mensagem_redirect));
        exit();
    }
}
// FIM DO POST HANDLING

// 4. MENSAGENS DE STATUS (GET)
if (isset($_GET['status'])) {
    // MENSAGEM ADAPTADA AO CSS
    $mensagem .= "<p class='msg-sucesso'>✅ " . htmlspecialchars($_GET['status']) . "</p>";
}
if (isset($_GET['erro'])) {
    // MENSAGEM ADAPTADA AO CSS
    $mensagem .= "<p class='msg-erro'>❌ " . htmlspecialchars($_GET['erro']) . "</p>";
}

// 5. BUSCA DETALHADA DOS ITENS DO CARRINHO PARA EXIBIÇÃO
$itens_carrinho = [];
$subtotal_carrinho = 0.00;

if ($cd_pedido > 0) {
    // Seleciona detalhes do item, do produto, nome da loja e estoque
    $sql_resumo = "
        SELECT 
            i.cd_produto, 
            i.qt_produto, 
            i.vl_preco_unitario, 
            p.nm_produto,
            p.ds_imagem,
            p.qt_estoque,
            l.nm_loja
        FROM itens i
        JOIN produto p ON i.cd_produto = p.cd_produto
        JOIN cadastro_loja l ON p.cd_loja = l.cd_loja
        WHERE i.cd_pedido = ?
    ";
    
    $stmt_resumo = $mysqli->prepare($sql_resumo);
    $stmt_resumo->bind_param("i", $cd_pedido);
    $stmt_resumo->execute();
    $resultado_resumo = $stmt_resumo->get_result();
    
    while ($item = $resultado_resumo->fetch_assoc()) {
        $itens_carrinho[] = $item;
    }

    $stmt_resumo->close();
}

// Calculando o subtotal fora do loop de fetch (se o carrinho não estiver vazio)
if (!empty($itens_carrinho)) {
    foreach ($itens_carrinho as $item) {
        $subtotal_carrinho += $item['qt_produto'] * $item['vl_preco_unitario'];
    }
}

// Não fechar a conexão aqui se ela for usada em outros includes!
// $mysqli->close(); 
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho - CandyPlace</title>
    <style>
        /* Paleta de Cores baseada no seu site: */
        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D;    
            --cor-marrom-escuro: #6B4423;  
            --cor-borda: #E0D4C5;
            --cor-verde-estoque: #28A745;
            --cor-vermelho-erro: #DC3545;
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px;
            background-color: var(--cor-principal-fundo);
        }

        /* Container Principal */
        .carrinho-container {
            max-width: 1100px;
            margin: 20px auto;
            background-color: var(--cor-container-fundo); 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            padding: 30px;
        }

        .carrinho-container h2 {
            color: var(--cor-marrom-escuro); 
            border-bottom: 2px solid var(--cor-marrom-acao); 
            padding-bottom: 10px; 
            margin-top: 0; 
            margin-bottom: 20px;
        }
        
        /* Mensagens de feedback */
        .msg-sucesso { 
            color: #28A745; 
            font-weight: bold; 
            background: #E6F7E9; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #D4EED8; 
            margin-bottom: 15px;
        }
        .msg-erro { 
            color: var(--cor-vermelho-erro); 
            font-weight: bold; 
            background: #FDE8E9; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #F8D1D5; 
            margin-bottom: 15px;
        }
        .msg-alerta { 
            color: #FFC107; 
            font-weight: bold; 
            background: #FFF3CD; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #FFECB5; 
            margin-bottom: 15px;
        }

        /* ========================================================= */
        /* ESTILOS DA TABELA DO CARRINHO */
        /* ========================================================= */

        .table-responsive {
            overflow-x: auto; 
            margin-bottom: 30px;
        }

        .carrinho-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }

        .carrinho-table th, .carrinho-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #EAEAEA;
        }

        .carrinho-table thead tr {
            background-color: var(--cor-marrom-acao); 
            color: white;
        }

        .carrinho-table tbody tr:hover {
            background-color: #F0EADF;
        }

        /* Imagem do Produto */
        .product-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--cor-borda);
        }

        /* Controles de Quantidade */
        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        .quantity-controls button {
            background-color: var(--cor-borda);
            color: var(--cor-marrom-escuro);
            border: 1px solid #C0C0C0;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .quantity-controls button:hover {
            background-color: #D5C4B0;
        }
        .quantity-display {
            font-weight: bold;
            min-width: 25px;
            text-align: center;
        }

        /* Coluna de Estoque */
        .stock-status {
            font-weight: bold;
        }
        .stock-low { color: var(--cor-vermelho-erro); }
        .stock-ok { color: var(--cor-verde-estoque); }

        /* Coluna de Subtotal do Item */
        .item-subtotal {
            font-weight: bold;
            color: var(--cor-marrom-acao);
        }

        /* Rodapé (Subtotal do Pedido) */
        .carrinho-table tfoot td {
            padding: 15px 12px;
            background-color: #EFEFEF; 
            border-top: 2px solid var(--cor-marrom-acao);
        }
        .carrinho-table tfoot strong {
            font-size: 1.2em;
        }
        .carrinho-table tfoot .final-total {
            color: var(--cor-marrom-acao);
        }

        /* Botão Remover */
        .btn-remove {
            color: var(--cor-vermelho-erro); 
            background: none; 
            border: none; 
            font-weight: bold; 
            cursor: pointer;
            text-decoration: underline;
        }
        .btn-remove:hover {
            opacity: 0.8;
        }

        /* ========================================================= */
        /* AÇÕES FINAIS (Botões abaixo da tabela) */
        /* ========================================================= */
        .carrinho-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .btn-continuar {
            color: var(--cor-marrom-escuro);
            text-decoration: none;
            font-weight: bold;
            padding: 10px;
            transition: color 0.2s;
        }
        .btn-continuar:hover {
            color: var(--cor-marrom-acao);
        }

        .btn-finalizar {
            padding: 15px 30px;
            background-color: var(--cor-marrom-acao);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1em;
            transition: background-color 0.3s;
        }
        .btn-finalizar:hover {
            background-color: #8B4513; 
        }

        /* Estilo para carrinho vazio */
        .carrinho-vazio {
            padding: 30px;
            text-align: center;
            font-size: 1.1em;
            color: var(--cor-marrom-escuro);
            border: 1px dashed var(--cor-borda);
            border-radius: 4px;
        }
        .carrinho-vazio a {
            color: var(--cor-marrom-acao);
            font-weight: bold;
            text-decoration: none;
        }
        .carrinho-vazio a:hover {
            text-decoration: underline;
        }

        /* ========================================================= */
        /* ESTILOS DA MODAL DE CONFIRMAÇÃO (NOVO) */
        /* ========================================================= */
        .custom-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); 
            display: none; /* Inicia oculto */
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .custom-modal.is-visible {
            display: flex; /* Exibir com flex */
            opacity: 1; /* Transição de opacidade */
        }
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px; /* Usei 8px por ser o padrão de border-radius que você já usava */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
            width: 90%;
            max-width: 400px;
            text-align: center;
        }
        .modal-content h3 {
            color: var(--cor-marrom-escuro);
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .modal-content p {
            margin-bottom: 25px;
            color: var(--cor-marrom-escuro);
        }
        .modal-actions {
            display: flex;
            justify-content: space-around;
            gap: 15px;
        }
        .modal-actions button, .modal-actions a {
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
            flex-grow: 1;
            text-align: center;
            border: none;
        }
        .cancel-btn {
            background-color: #ddd;
            color: var(--cor-marrom-escuro);
        }
        .cancel-btn:hover {
            background-color: #ccc;
        }
        .confirm-btn {
            background-color: var(--cor-vermelho-erro); /* Usar vermelho para indicar DELETAR */
            color: white;
        }
        .confirm-btn:hover {
            background-color: #C82333;
        }
    </style>
</head>
<body>
    <div class="carrinho-container">
        <h2>🛍️ Meu Carrinho de Compras</h2>
        
        <?php echo $mensagem; ?>

        <?php if (!empty($itens_carrinho)): ?>
            
            <div class="table-responsive">
                <table class="carrinho-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Produto</th>
                            <th>Loja</th>
                            <th>Preço Unitário</th>
                            <th style="text-align: center;">Quantidade</th>
                            <th style="text-align: center;">Estoque</th>
                            <th>Subtotal Item</th>
                            <th style="text-align: center;">Remover</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens_carrinho as $item): 
                            $total_item = $item['qt_produto'] * $item['vl_preco_unitario'];
                            $ds_imagem = htmlspecialchars($item['ds_imagem'] ?? 'placeholders/default.jpg'); 
                            $estoque_status_class = ($item['qt_produto'] > $item['qt_estoque']) ? 'stock-low' : 'stock-ok';
                        ?>
                        <tr>
                            <td style="text-align: center;">
                                <img src="<?php echo $ds_imagem; ?>" 
                                    alt="<?php echo htmlspecialchars($item['nm_produto']); ?>" 
                                    class="product-thumbnail">
                            </td>
                            <td><?php echo htmlspecialchars($item['nm_produto']); ?></td>
                            <td><?php echo htmlspecialchars($item['nm_loja']); ?></td>
                            <td>R$ <?php echo number_format($item['vl_preco_unitario'], 2, ',', '.'); ?></td>
                            
                            <td style="text-align: center;">
                                <div class="quantity-controls">
                                    <form method="post" action="carrinho.php" style="display: inline;">
                                        <input type="hidden" name="cd_produto" value="<?php echo $item['cd_produto']; ?>">
                                        <input type="hidden" name="action" value="remove_one">
                                        <button type="submit">-</button>
                                    </form>
                                    
                                    <span class="quantity-display"><?php echo $item['qt_produto']; ?></span>
                                    
                                    <form method="post" action="add_cart.php" style="display: inline;">
                                        <input type="hidden" name="cd_produto" value="<?php echo $item['cd_produto']; ?>">
                                        <input type="hidden" name="qt_produto" value="1"> 
                                        <button type="submit">+</button>
                                    </form>
                                </div>
                            </td>
                            
                            <td style="text-align: center;">
                                <span class="stock-status <?php echo $estoque_status_class; ?>">
                                    <?php echo $item['qt_estoque']; ?>
                                </span>
                            </td>

                            <td class="item-subtotal">
                                R$ <?php echo number_format($total_item, 2, ',', '.'); ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <form method="post" action="carrinho.php" class="js-delete-form">
                                    <input type="hidden" name="cd_produto" value="<?php echo $item['cd_produto']; ?>">
                                    <input type="hidden" name="action" value="delete_item">
                                    <button type="button" class="btn-remove js-remove-item-trigger"
                                            data-product-name="<?php echo htmlspecialchars($item['nm_produto']); ?>">
                                        ❌
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" style="text-align: right;"><strong>Subtotal do Pedido:</strong></td>
                            <td colspan="2" class="final-total"><strong>R$ <?php echo number_format($subtotal_carrinho, 2, ',', '.'); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="carrinho-actions">
                <a href="index.php" class="btn-continuar">
                    ⬅️ Continuar Comprando
                </a>
                <a href="finalizar_pedido.php" class="btn-finalizar">
                    Finalizar Pedido (R$ <?php echo number_format($subtotal_carrinho, 2, ',', '.'); ?>)
                </a>
            </div>
            
        <?php else: ?>
            <div class="carrinho-vazio">
                <p>Seu carrinho está vazio. 🙁</p>
                <p><a href="index.php">Clique aqui para ver os produtos populares e começar a comprar!</a></p>
            </div>
        <?php endif; ?>
    </div>

    <div id="custom-confirm-modal" class="custom-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-hidden="true">
        <div class="modal-content">
            <h3 id="modal-title">Confirmação de Remoção</h3>
            <p>Você tem certeza que deseja remover <strong id="product-name-placeholder">este item</strong> completamente do seu carrinho?</p>
            <div class="modal-actions">
                <button id="modal-cancel-btn" class="cancel-btn">Cancelar</button>
                <button id="modal-confirm-btn" class="confirm-btn" type="button">Confirmar Remoção</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('custom-confirm-modal');
            const confirmBtn = document.getElementById('modal-confirm-btn');
            const cancelBtn = document.getElementById('modal-cancel-btn');
            const productNamePlaceholder = document.getElementById('product-name-placeholder');
            
            let formToSubmit = null; // Armazena a referência do formulário que será enviado

            // 1. ABRIR MODAL
            document.querySelectorAll('.js-remove-item-trigger').forEach(button => {
                button.addEventListener('click', function() {
                    formToSubmit = this.closest('form.js-delete-form'); // Captura o formulário pai
                    const productName = this.getAttribute('data-product-name');

                    if (productName && productNamePlaceholder) {
                        productNamePlaceholder.textContent = `o produto "${productName}"`;
                    } else {
                        productNamePlaceholder.textContent = `este item`;
                    }

                    modal.classList.add('is-visible');
                    modal.setAttribute('aria-hidden', 'false');
                });
            });

            // 2. CONFIRMAR (Submeter o Formulário)
            confirmBtn.addEventListener('click', () => {
                if (formToSubmit) {
                    formToSubmit.submit(); // Envia o formulário POST
                }
                closeModal();
            });

            // 3. CANCELAR / FECHAR
            const closeModal = () => {
                modal.classList.remove('is-visible');
                modal.setAttribute('aria-hidden', 'true');
                formToSubmit = null; // Limpa a referência
            };

            cancelBtn.addEventListener('click', closeModal);
            
            // Fechar ao clicar no fundo escuro
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            // Fechar ao pressionar ESC
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && modal.classList.contains('is-visible')) {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>