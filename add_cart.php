<?php
include 'db_connect.php';
session_start();

//  VERIFICAÇÃO BÁSICA DE LOGIN (APENAS CLIENTE PODE COMPRAR)
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    // Redireciona para o login se não for um cliente logado
    header("Location: login_cliente.php?redirect=index.php");
    exit();
}

$cd_cliente = $_SESSION['cd_usuario'];
$mensagem = "";

//  COLETA DE DADOS DO PRODUTO A SER ADICIONADO

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ID do produto é obrigatório
    $cd_produto = isset($_POST['cd_produto']) ? intval($_POST['cd_produto']) : 0;
    // Quantidade a ser adicionada (padrão 1, mas pode ser mudado depois)
    $qt_adicionar = isset($_POST['qt_produto']) ? intval($_POST['qt_produto']) : 1; 

    if ($cd_produto <= 0 || $qt_adicionar <= 0) {
        // ID ou quantidade inválida
        $mensagem = "⚠️ Produto ou quantidade inválida.";
        header("Location: carrinho.php?erro=" . urlencode($mensagem)); 
        exit();
    }
    

    //  INICIA OU RECUPERA O PEDIDO ATIVO (CARRINHO)

    if (!isset($_SESSION['cd_pedido_ativo'])) {
        
        // Insere um novo pedido no banco de dados, definindo o status
        $sql_cria_pedido = "INSERT INTO pedido (cd_cliente, dt_pedido, ds_status_pedido) VALUES (?, NOW(), 'Carrinho')";
        $stmt_cria = $mysqli->prepare($sql_cria_pedido);
        $stmt_cria->bind_param("i", $cd_cliente);
        
        if ($stmt_cria->execute()) {
            // Pega o ID do pedido recém-criado
            $_SESSION['ds_status_pedido'] = $mysqli->insert_id;
        } else {
            // Erro ao criar pedido
            $mensagem = "❌ Erro ao criar novo pedido: " . $stmt_cria->error;
            header("Location: carrinho.php?erro=" . urlencode($mensagem));
            exit();
        }
        $stmt_cria->close();
    }
    
    $cd_pedido = $_SESSION['cd_pedido_ativo'];


    //  BUSCA DADOS ATUAIS DO PRODUTO (PREÇO E ESTOQUE)

    $sql_produto = "SELECT vl_preco, qt_estoque FROM produto WHERE cd_produto = ?";
    $stmt_produto = $mysqli->prepare($sql_produto);
    $stmt_produto->bind_param("i", $cd_produto);
    $stmt_produto->execute();
    $resultado_produto = $stmt_produto->get_result();
    
    if ($resultado_produto->num_rows === 0) {
        $mensagem = "❌ Produto não encontrado.";
        header("Location: carrinho.php?erro=" . urlencode($mensagem));
        exit();
    }

    $produto_dados = $resultado_produto->fetch_assoc();
    $vl_preco_unitario = $produto_dados['vl_preco'];
    $qt_estoque_atual = $produto_dados['qt_estoque'];
    $stmt_produto->close();



    // VERIFICA SE O PRODUTO JÁ ESTÁ NO CARRINHO

    $sql_item_existente = "SELECT qt_produto FROM itens WHERE cd_pedido = ? AND cd_produto = ?";
    $stmt_existente = $mysqli->prepare($sql_item_existente);
    $stmt_existente->bind_param("ii", $cd_pedido, $cd_produto);
    $stmt_existente->execute();
    $resultado_existente = $stmt_existente->get_result();
    
    $qt_atual_carrinho = 0;
    if ($resultado_existente->num_rows > 0) {
        $item_existente = $resultado_existente->fetch_assoc();
        $qt_atual_carrinho = $item_existente['qt_produto'];
    }
    $stmt_existente->close();

    $qt_nova_total = $qt_atual_carrinho + $qt_adicionar;

    //  VERIFICAÇÃO DE ESTOQUE
    if ($qt_nova_total > $qt_estoque_atual) {
        $mensagem = "⚠️ Estoque insuficiente. Temos apenas {$qt_estoque_atual} unidades disponíveis.";
        header("Location: carrinho.php?erro=" . urlencode($mensagem));
        exit();
    }


    //  INSERE OU ATUALIZA O ITEM NO CARRINHO (TABELA ITENS)

    $status_mensagem = "";
    if ($qt_atual_carrinho > 0) {
        // UPDATE: O item já existe, apenas atualiza a quantidade
        $sql_update = "UPDATE itens SET qt_produto = ?, vl_preco_unitario = ? WHERE cd_pedido = ? AND cd_produto = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        // O preço unitário é o preço atual do produto no momento da adição/atualização
        $stmt_update->bind_param("idii", $qt_nova_total, $vl_preco_unitario, $cd_pedido, $cd_produto);
        $stmt_update->execute();
        $stmt_update->close();
        $status_mensagem = "Quantidade aumentada para {$qt_nova_total}.";
        
    } else {
        // INSERT: Novo item no carrinho
        $sql_insert = "INSERT INTO itens (cd_pedido, cd_produto, qt_produto, vl_preco_unitario) VALUES (?, ?, ?, ?)";
        $stmt_insert = $mysqli->prepare($sql_insert);
        $stmt_insert->bind_param("iiid", $cd_pedido, $cd_produto, $qt_adicionar, $vl_preco_unitario);
        $stmt_insert->execute();
        $stmt_insert->close();
        $status_mensagem = "Produto adicionado ao carrinho.";
    }

    // Redireciona para o carrinho
    $mysqli->close();
    header("Location: carrinho.php?status=" . urlencode($status_mensagem));
    exit();

} else {
    // Se o método não for POST (acesso direto GET), redireciona para a home
    header("Location: index.php");
    exit();
}
?>