<?php
// ATENÇÃO: A linha abaixo da tag <?php deve ser a primeira instrução de código.
include 'db_connect.php';
session_start();

// 1. VERIFICAÇÃO DE SESSÃO E CARRINHO ATIVO (Redirecionamentos no TOPO)
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    header("Location: login_cliente.php");
    exit();
}

$cd_cliente = $_SESSION['cd_usuario'];

// Simulação: Verifica se existe um pedido ativo (carrinho)
if (!isset($_SESSION['cd_pedido_ativo'])) {
    header("Location: carrinho.php?status=vazio");
    exit();
}

$cd_pedido = $_SESSION['cd_pedido_ativo'];
$frete = 15.00; // Valor fixo de frete

$subtotal = 0.00;
$total = 0.00;
$cd_loja = null; // Variável para armazenar o ID da loja
$mensagem_final = ''; // Inicializa a variável de mensagem de erro

// 2. BUSCA ITENS DO PEDIDO (CARRINHO)
$sql_itens = "
    SELECT 
        i.qt_produto, 
        i.vl_preco_unitario, 
        p.nm_produto,
        p.cd_produto,
        p.cd_loja 
    FROM itens i
    JOIN produto p ON i.cd_produto = p.cd_produto
    WHERE i.cd_pedido = ?
"; // Mantido multi-linha para legibilidade do SQL

$stmt_itens = $mysqli->prepare($sql_itens);
$stmt_itens->bind_param("i", $cd_pedido);
$stmt_itens->execute();
$resultado_itens = $stmt_itens->get_result();

$itens_carrinho = [];
if ($resultado_itens->num_rows > 0) {
    while ($item = $resultado_itens->fetch_assoc()) {
        $preco_item = $item['qt_produto'] * $item['vl_preco_unitario'];
        $subtotal += $preco_item;
        $itens_carrinho[] = $item;

        // Pega o ID da loja do primeiro item (assumindo que o pedido é de uma única loja)
        if ($cd_loja === null) {
            $cd_loja = $item['cd_loja'];
        }
    }
} else {
    // Se o pedido estiver vazio, redireciona
    unset($_SESSION['cd_pedido_ativo']);
    header("Location: carrinho.php?status=vazio");
    exit();
}

$stmt_itens->close();
$total = $subtotal + $frete;

// 3. BUSCA ENDEREÇOS DO CLIENTE
$sql_enderecos = "SELECT cd_endereco, ds_endereco, nm_bairro, nm_cidade, cd_cep FROM endereco_cliente WHERE cd_cliente = ?";
$stmt_endereco = $mysqli->prepare($sql_enderecos);
$stmt_endereco->bind_param("i", $cd_cliente);
$stmt_endereco->execute();
$resultado_enderecos = $stmt_endereco->get_result();

$enderecos_cliente = [];
$endereco_selecionado = null; 
while ($endereco = $resultado_enderecos->fetch_assoc()) {
    $enderecos_cliente[] = $endereco;
}
$stmt_endereco->close();

$pode_finalizar = !empty($enderecos_cliente); // Define a flag de finalização


// 4. LÓGICA DE FINALIZAÇÃO (Quando o formulário é enviado)
if (isset($_POST['finalizar_pedido']) && $pode_finalizar) {
    
    $cd_endereco_selecionado = (int)$_POST['cd_endereco'];
    $forma_pagamento = $mysqli->real_escape_string($_POST['pagamento']);
    
    // Busca o endereço selecionado completo para a mensagem do WhatsApp
    foreach ($enderecos_cliente as $endereco) {
        if ($endereco['cd_endereco'] == $cd_endereco_selecionado) {
            $endereco_selecionado = $endereco;
            break;
        }
    }
    
    // Define o status inicial
    $status_inicial = 'Aguardando Pagamento'; 
    
    // 5. ATUALIZAÇÃO DA TABELA PEDIDO
    $sql_update_pedido = "
        UPDATE pedido SET 
            cd_loja = ?, 
            cd_endereco_entrega = ?, 
            vl_subtotal = ?, 
            vl_frete = ?, 
            vl_total = ?, 
            dt_pedido = NOW(), 
            pagamento = ?,
            ds_status_pedido = ?
        WHERE cd_pedido = ? AND cd_cliente = ?
    "; 
    
    $stmt_update = $mysqli->prepare($sql_update_pedido);
    
    // CORREÇÃO NA STRING DE TIPOS: iiddssiii (9 parâmetros)
    $stmt_update->bind_param("iiddssisi", 
        $cd_loja, 
        $cd_endereco_selecionado, 
        $subtotal, 
        $frete, 
        $total, 
        $forma_pagamento, 
        $status_inicial,
        $cd_pedido, 
        $cd_cliente
    );

    if ($stmt_update->execute()) {
        
        // =========================================================
        // 6. INTEGRAÇÃO WHATSAPP
        // =========================================================

        // 6a. Busca o número do WhatsApp da Loja
        $sql_whatsapp = "SELECT nr_telefone FROM cadastro_loja WHERE cd_loja = ?";
        $stmt_whatsapp = $mysqli->prepare($sql_whatsapp);
        $stmt_whatsapp->bind_param("i", $cd_loja);
        $stmt_whatsapp->execute();
        $result_whatsapp = $stmt_whatsapp->get_result();
        $nr_whatsapp_loja = "5511999999999"; // Número de fallback
        if ($result_whatsapp && $result_whatsapp->num_rows === 1) {
            $nr_whatsapp_loja = $result_whatsapp->fetch_assoc()['nr_telefone'];
            $nr_whatsapp_loja = preg_replace('/[^0-9]/', '', $nr_whatsapp_loja); // Remove caracteres não numéricos
        }
        $stmt_whatsapp->close();

        // 6b. Monta a mensagem de itens
        $itens_whatsapp = "";
        foreach ($itens_carrinho as $item) {
            $subtotal_item = $item['qt_produto'] * $item['vl_preco_unitario'];
            $itens_whatsapp .= "• Cod: {$item['cd_produto']} - {$item['nm_produto']} (x{$item['qt_produto']}) - Subtotal: R$ " . number_format($subtotal_item, 2, ',', '.') . "\n";
        }

        // 6c. Monta a mensagem final completa
        $data_pedido = date('d/m/Y h:i:s');
        $nome_cliente = $_SESSION['nm_usuario'];
        $total_formatado = number_format($total, 2, ',', '.');
        $subtotal_formatado = number_format($subtotal, 2, ',', '.');
        $frete_formatado = number_format($frete, 2, ',', '.');

        $endereco_texto = "Endereço não encontrado.";
        if ($endereco_selecionado) {
             $endereco_texto = "*{$endereco_selecionado['ds_endereco']}*, Bairro: {$endereco_selecionado['nm_bairro']}, Cidade: {$endereco_selecionado['nm_cidade']}, CEP: {$endereco_selecionado['cd_cep']}";
        }
        
        $mensagem_whats = "🎉 *NOVO PEDIDO (ID #{$cd_pedido}) RECEBIDO!* 🎉\n\n";
        $mensagem_whats .= "--- *CLIENTE* ---\n";
        $mensagem_whats .= "Nome: {$nome_cliente}\n\n";
        $mensagem_whats .= "--- *ITENS DO PEDIDO* ---\n";
        $mensagem_whats .= $itens_whatsapp . "\n";
        $mensagem_whats .= "--- *VALORES* ---\n";
        $mensagem_whats .= "Subtotal: R$ {$subtotal_formatado}\n";
        $mensagem_whats .= "Frete: R$ {$frete_formatado}\n";
        $mensagem_whats .= "*TOTAL A PAGAR: R$ {$total_formatado}*\n\n";
        $mensagem_whats .= "--- *PAGAMENTO & ENTREGA* ---\n";
        $mensagem_whats .= "Forma de Pagamento: *{$forma_pagamento}*\n";
        $mensagem_whats .= "Endereço: {$endereco_texto}\n";
        $mensagem_whats .= "Data/Hora: {$data_pedido}";


        // 6d. Limpa o carrinho ativo da sessão, fecha a conexão e redireciona.
        unset($_SESSION['cd_pedido_ativo']);
        
        // FECHAMENTO DA CONEXÃO DENTRO DA LÓGICA DE SUCESSO
        $mysqli->close(); 

        $url_whatsapp = "https://api.whatsapp.com/send?phone={$nr_whatsapp_loja}&text=" . urlencode($mensagem_whats);
        
        // Redireciona para o WhatsApp (deve ser a ÚLTIMA coisa a acontecer)
        header("Location: " . $url_whatsapp);
        exit();
        
    } else {
        // MENSAGEM ADAPTADA AO CSS
        $mensagem_final = "<p class='msg-erro'>❌ Erro ao finalizar o pedido: " . $stmt_update->error . "</p>";
        // É bom garantir o fechamento mesmo em caso de erro no UPDATE
        $mysqli->close(); 
    }
    $stmt_update->close();
}
// Fim da Lógica de Finalização

// Se o formulário NÃO foi enviado, a conexão é fechada aqui (após o uso em 3.)
if ($mysqli) {
    // Verifica se a conexão já está fechada (caso tenha entrado na lógica POST)
    if (isset($mysqli->connect_errno) && $mysqli->connect_errno) {
        // A conexão já foi fechada na lógica POST
    } else {
        $mysqli->close();
    }
}

// A PARTIR DAQUI COMEÇA A SAÍDA HTML, NENHUM OUTRO HEADER PODE SER CHAMADO!
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido - CandyPlace</title>
    <style>
        /* Paleta de Cores baseada no seu site: */
        :root {
            --cor-principal-fundo: #F8EFE4; 
            --cor-container-fundo: #FFFFFF;
            --cor-marrom-acao: #A0522D;    
            --cor-marrom-escuro: #6B4423;  
            --cor-borda: #E0D4C5;
            --cor-verde-finalizar: #28A745;
            --cor-vermelho-erro: #DC3545;
        }

        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px;
            background-color: var(--cor-principal-fundo);
        }

        /* Container Principal do Checkout */
        .checkout-container {
            max-width: 800px;
            margin: 20px auto;
            background-color: var(--cor-container-fundo); 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
            padding: 30px;
        }

        .checkout-container h2 {
            color: var(--cor-marrom-acao); 
            border-bottom: 2px solid var(--cor-borda); 
            padding-bottom: 15px; 
            margin-top: 0; 
            margin-bottom: 25px;
        }
        
        .checkout-container h3 {
            color: var(--cor-marrom-escuro); 
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        /* Mensagem de erro/sucesso (usada para erro de finalização) */
        .msg-erro { 
            color: var(--cor-vermelho-erro); 
            font-weight: bold; 
            background: #FDE8E9; 
            padding: 10px; 
            border-radius: 4px; 
            border: 1px solid #F8D1D5; 
            margin-bottom: 15px;
        }

        /* ========================================================= */
        /* TABELA DE RESUMO (Itens) */
        /* ========================================================= */
        .resumo-tabela { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
            background-color: #FBFBFB;
        }
        .resumo-tabela th, .resumo-tabela td { 
            padding: 10px; 
            text-align: left;
        }
        .resumo-tabela thead tr {
            background-color: var(--cor-marrom-acao); 
            color: white; 
        }
        .resumo-tabela tbody tr:nth-child(even) {
            background-color: #F5F5F5;
        }
        
        /* Rodapé da tabela */
        .resumo-tabela tfoot td {
            border-top: 2px solid var(--cor-borda); 
            font-weight: bold;
        }
        .resumo-tabela tfoot tr:last-child td {
            background-color: #EFEFEF; 
            font-size: 1.1em;
            color: var(--cor-marrom-acao);
        }
        .resumo-tabela tfoot tr:last-child strong {
            font-size: 1.2em;
        }
        
        /* Linha de separação */
        hr {
            border: 0;
            height: 1px;
            background-color: var(--cor-borda);
            margin: 30px 0;
        }

        /* ========================================================= */
        /* SELEÇÃO DE ENDEREÇO */
        /* ========================================================= */
        .endereco-item { 
            margin-bottom: 10px; 
            border: 2px solid #EAEAEA; 
            padding: 15px; 
            border-radius: 6px; 
            transition: border-color 0.2s;
        }
        .endereco-item-checked { 
            border-color: var(--cor-marrom-acao) !important; 
            background-color: #F8F4F0; /* Fundo suave */
        }
        .endereco-item input[type="radio"] {
            margin-right: 10px;
        }
        .endereco-item label {
            font-weight: normal; 
            color: var(--cor-marrom-escuro); 
            cursor: pointer;
        }
        .endereco-item small a {
            color: var(--cor-marrom-acao);
            text-decoration: none;
            font-weight: bold;
        }
        .endereco-item p {
            margin: 5px 0 0 0;
        }
        
        /* Mensagem de Erro Sem Endereço */
        .no-address-message {
            padding: 15px;
            border: 1px dashed var(--cor-vermelho-erro);
            background-color: #FDE8E9;
            color: var(--cor-vermelho-erro);
            border-radius: 4px;
        }
        .no-address-message a {
            color: var(--cor-vermelho-erro);
            font-weight: bold;
            text-decoration: underline;
        }


        /* ========================================================= */
        /* SELEÇÃO DE PAGAMENTO */
        /* ========================================================= */
        .pagamento-opcoes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .hidden-radio { 
            display: none; 
        }
        .pagamento-opcoes label button { 
            padding: 12px 25px; 
            cursor: pointer; 
            border: 2px solid var(--cor-borda); 
            background-color: #F9F9F9; 
            border-radius: 6px; 
            font-weight: bold;
            color: var(--cor-marrom-escuro);
            transition: all 0.2s;
        }
        .pagamento-opcoes label button:hover {
            background-color: #EFEFEF;
        }
        /* Destaque visual quando o rádio está checado */
        .pagamento-opcoes input[type="radio"]:checked + label button { 
            border-color: var(--cor-marrom-acao); 
            background-color: #F8F4F0; 
            box-shadow: 0 0 5px rgba(160, 82, 45, 0.5);
        }

        /* ========================================================= */
        /* BOTÃO FINALIZAR */
        /* ========================================================= */
        .btn-finalizar {
            display: block;
            width: 100%;
            padding: 18px; 
            background-color: var(--cor-verde-finalizar); 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer;
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 30px;
            transition: background-color 0.3s;
        }
        .btn-finalizar:hover:not(:disabled) {
            background-color: #1E8449; 
        }
        .btn-finalizar:disabled {
            background-color: #C0C0C0; 
            cursor: not-allowed;
        }

    </style>
</head>
<body>
    <div class="checkout-container">
        <h2>🎉 Finalização do Pedido (ID #<?php echo $cd_pedido; ?>)</h2>
        
        <?php if (isset($mensagem_final)) echo $mensagem_final; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            
            <h3>1. Resumo do Pedido</h3>
            <table class="resumo-tabela">
                <thead>
                    <tr>
                        <th style="width: 50%;">Produto</th>
                        <th style="width: 15%; text-align: center;">Qtd</th>
                        <th style="width: 15%; text-align: right;">Preço Unit.</th>
                        <th style="width: 20%; text-align: right;">Total Item</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens_carrinho as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nm_produto']); ?></td>
                        <td style="text-align: center;"><?php echo $item['qt_produto']; ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($item['vl_preco_unitario'], 2, ',', '.'); ?></td>
                        <td style="text-align: right;">R$ <?php echo number_format($item['qt_produto'] * $item['vl_preco_unitario'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;">Subtotal:</td>
                        <td style="text-align: right;">R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align: right;">Frete:</td>
                        <td style="text-align: right;">R$ <?php echo number_format($frete, 2, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>TOTAL A PAGAR:</strong></td>
                        <td style="text-align: right;"><strong>R$ <?php echo number_format($total, 2, ',', '.'); ?></strong></td>
                    </tr>
                </tfoot>
            </table>

            <hr>

            <h3>2. Selecione o Endereço de Entrega</h3>
            <?php if (!empty($enderecos_cliente)): ?>
                <?php foreach ($enderecos_cliente as $index => $endereco): 
                    $is_checked = ($index == 0) ? 'checked' : '';
                    $highlight_class = ($index == 0) ? 'endereco-item-checked' : '';
                ?>
                    <div class="endereco-item <?php echo $highlight_class; ?>">
                        <input type="radio" id="endereco_<?php echo $endereco['cd_endereco']; ?>" name="cd_endereco" value="<?php echo $endereco['cd_endereco']; ?>" required
                            <?php echo $is_checked; ?>
                        >
                        <label for="endereco_<?php echo $endereco['cd_endereco']; ?>">
                            <p><strong><?php echo htmlspecialchars($endereco['ds_endereco']); ?></strong>, Bairro: <?php echo htmlspecialchars($endereco['nm_bairro']); ?></p>
                            <p><small>Cidade: <?php echo htmlspecialchars($endereco['nm_cidade']); ?>, CEP: <?php echo htmlspecialchars($endereco['cd_cep']); ?></small></p>
                        </label>
                    </div>
                <?php endforeach; ?>
                <p style="text-align: right;"><small>Se o endereço desejado não estiver na lista, <a href="painel_cliente.php#endereco">edite-o no seu perfil</a>.</small></p>
                <?php $pode_finalizar = true; ?>
            <?php else: ?>
                <div class="no-address-message">
                    <p>⚠️ Você não tem **endereços cadastrados**. Por favor, <a href="painel_cliente.php#endereco">cadastre um endereço</a> antes de finalizar o pedido.</p>
                </div>
                <?php $pode_finalizar = false; ?> 
            <?php endif; ?>

            <hr>

            <h3>3. Selecione a Forma de Pagamento</h3>
            <div class="pagamento-opcoes">
                
                <input type="radio" id="pix" name="pagamento" value="Pix" class="hidden-radio" required>
                <label for="pix"><button type="button">Pix ⚡</button></label>
                
                <input type="radio" id="credito" name="pagamento" value="Cartão de Crédito" class="hidden-radio">
                <label for="credito"><button type="button">Cartão de Crédito 💳</button></label>
                
                <input type="radio" id="debito" name="pagamento" value="Cartão de Débito" class="hidden-radio">
                <label for="debito"><button type="button">Cartão de Débito 🏧</button></label>

                <input type="radio" id="dinheiro" name="pagamento" value="Dinheiro" class="hidden-radio">
                <label for="dinheiro"><button type="button">Dinheiro Físico 💵</button></label>
            </div>
            
            <input type="submit" name="finalizar_pedido" 
                class="btn-finalizar"
                value="Confirmar Pedido e Notificar Loja (R$ <?php echo number_format($total, 2, ',', '.'); ?>)" 
                <?php echo ($pode_finalizar === false) ? 'disabled' : ''; ?> 
            >
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="carrinho.php" style="color: var(--cor-marrom-escuro); text-decoration: none;">&laquo; Voltar para o Carrinho</a>
        </p>
    </div>
    
    <script>
        // Lógica para selecionar o botão de pagamento (mantida e melhorada)
        document.querySelectorAll('.pagamento-opcoes button').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault(); 
                // Encontra o radio associado ao botão
                const radioId = e.target.closest('label').getAttribute('for');
                const radio = document.getElementById(radioId);
                
                if (radio) {
                    radio.checked = true;
                    // Limpa o destaque de todos os botões e aplica ao selecionado
                    document.querySelectorAll('.pagamento-opcoes input[type="radio"]').forEach(r => {
                        r.checked = (r.id === radioId);
                    });
                }
            });
        });

        // Lógica para destacar o endereço selecionado
        document.querySelectorAll('input[name="cd_endereco"]').forEach(radio => {
            const itemDiv = radio.closest('.endereco-item');
            
            // Adiciona a classe de destaque no carregamento se estiver checado
            if (radio.checked) {
                itemDiv.classList.add('endereco-item-checked');
            }

            // Remove e adiciona a classe de destaque na mudança
            radio.addEventListener('change', (e) => {
                document.querySelectorAll('.endereco-item').forEach(d => d.classList.remove('endereco-item-checked'));
                e.target.closest('.endereco-item').classList.add('endereco-item-checked');
            });
        });
    </script>
</body>
</html>