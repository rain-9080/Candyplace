<?php
include 'db_connect.php';
session_start();

// 1. VERIFICAÇÃO DE ACESSO
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'cliente') {
    header("Location: login_cliente.php");
    exit();
}

// Verifica se há um carrinho ativo
if (!isset($_SESSION['cd_pedido_ativo'])) {
    header("Location: carrinho.php?status=vazio");
    exit();
}

$cd_pedido = $_SESSION['cd_pedido_ativo'];
$cd_produto = isset($_POST['cd_produto']) ? intval($_POST['cd_produto']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // Pode ser 'remove_one' ou 'delete_item'

if ($cd_produto <= 0) {
    header("Location: carrinho.php?erro=" . urlencode("ID do produto inválido."));
    exit();
}

$mensagem = "";

// =========================================================
// 2. PROCESSAMENTO DA AÇÃO
// =========================================================

try {
    // Busca a quantidade atual do item no carrinho
    $sql_qt_atual = "SELECT qt_produto FROM itens WHERE cd_pedido = ? AND cd_produto = ?";
    $stmt_qt_atual = $mysqli->prepare($sql_qt_atual);
    $stmt_qt_atual->bind_param("ii", $cd_pedido, $cd_produto);
    $stmt_qt_atual->execute();
    $resultado = $stmt_qt_atual->get_result();
    
    if ($resultado->num_rows === 0) {
        $mensagem = "O item não está no seu carrinho.";
        goto end_processing;
    }
    
    $item = $resultado->fetch_assoc();
    $qt_atual = $item['qt_produto'];
    $stmt_qt_atual->close();

    // Ação 1: Deletar o item completamente (Botão X ou Remover Tudo)
    if ($action === 'delete_item') {
        $sql_delete = "DELETE FROM itens WHERE cd_pedido = ? AND cd_produto = ?";
        $stmt_delete = $mysqli->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $cd_pedido, $cd_produto);
        $stmt_delete->execute();
        $mensagem = "Item removido completamente do carrinho.";
        $stmt_delete->close();

    // Ação 2: Remover apenas uma unidade (Botão -)
    } elseif ($action === 'remove_one') {
        if ($qt_atual > 1) {
            $qt_nova = $qt_atual - 1;
            $sql_update = "UPDATE itens SET qt_produto = ? WHERE cd_pedido = ? AND cd_produto = ?";
            $stmt_update = $mysqli->prepare($sql_update);
            $stmt_update->bind_param("iii", $qt_nova, $cd_pedido, $cd_produto);
            $stmt_update->execute();
            $mensagem = "Quantidade do item atualizada.";
            $stmt_update->close();
        } else {
            // Se a quantidade for 1, remove o item completamente para não deixar quantidade 0
            $sql_delete = "DELETE FROM itens WHERE cd_pedido = ? AND cd_produto = ?";
            $stmt_delete = $mysqli->prepare($sql_delete);
            $stmt_delete->bind_param("ii", $cd_pedido, $cd_produto);
            $stmt_delete->execute();
            $mensagem = "Item removido completamente do carrinho (última unidade).";
            $stmt_delete->close();
        }
    } else {
        $mensagem = "Ação não reconhecida.";
    }

} catch (mysqli_sql_exception $e) {
    $mensagem = "Erro no banco de dados ao tentar remover item: " . $e->getMessage();
}

end_processing:
$mysqli->close();
// Redireciona de volta para o carrinho com status
header("Location: carrinho.php?status=" . urlencode($mensagem));
exit();
?>