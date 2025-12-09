<?php
include 'db_connect.php'; 
session_start();

// 1. VERIFICAÇÃO DE SESSÃO (Apenas Lojistas)
if (!isset($_SESSION['logado']) || $_SESSION['tipo_usuario'] !== 'loja') {
    header("Location: login_loja.php");
    exit();
}

$cd_loja_logada = $_SESSION['cd_usuario'];
// Pega o ID do produto da URL e garante que é um inteiro
$id_produto = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_produto <= 0) {
    $_SESSION['mensagem_loja'] = "<p class='msg-erro'>❌ ID do produto inválido para exclusão.</p>";
    header("Location: painel_loja.php?aba=produtos");
    exit();
}

// INICIA TRANSAÇÃO: Garante que ou TUDO é feito, ou NADA é feito.
$mysqli->begin_transaction(); 
$sucesso = false;
$msg_final = "";

try {
    // 2. EXCLUSÃO DA CHAVE ESTRANGEIRA (TABELA ITENS)
    // Deleta TODOS os registros na tabela 'itens' que fazem referência ao produto (carrinhos e pedidos antigos/ativos).
    $sql_delete_itens = "DELETE FROM itens WHERE cd_produto = ?";
    $stmt_itens = $mysqli->prepare($sql_delete_itens);
    $stmt_itens->bind_param("i", $id_produto);
    
    if (!$stmt_itens->execute()) {
        // Se a exclusão em itens falhar, lança uma exceção para o bloco catch
        throw new Exception("Falha ao deletar itens dependentes: " . $stmt_itens->error);
    }
    $itens_removidos = $stmt_itens->affected_rows;
    $stmt_itens->close();

    // 3. EXCLUSÃO DO PRODUTO (TABELA PRODUTO)
    // Deleta o produto APENAS se o ID do produto E o ID da loja logada coincidirem.
    $sql_delete_produto = "DELETE FROM produto WHERE cd_produto = ? AND cd_loja = ?";
    $stmt_produto = $mysqli->prepare($sql_delete_produto);
    $stmt_produto->bind_param("ii", $id_produto, $cd_loja_logada);
    
    if (!$stmt_produto->execute()) {
        // Se a exclusão em produto falhar, lança uma exceção para o bloco catch
        throw new Exception("Falha ao deletar o produto: " . $stmt_produto->error);
    }
    
    if ($stmt_produto->affected_rows > 0) {
        // Sucesso total
        $sucesso = true;
        $msg_final = "<p class='msg-sucesso'>🗑️ Produto excluído com sucesso! " . ($itens_removidos > 0 ? "($itens_removidos item(s) em pedidos/carrinhos foram removidos forçadamente)." : "") . "</p>";
    } else {
        // Se affected_rows for 0, o produto não existe ou não pertence à loja
        $msg_final = "<p class='msg-aviso'>⚠️ Produto não encontrado ou você não tem permissão para excluí-lo.</p>";
    }
    
    $stmt_produto->close();

    // 4. FINALIZAR TRANSAÇÃO
    if ($sucesso) {
        $mysqli->commit(); // Confirma as operações de exclusão
    } else {
        $mysqli->rollback(); // Desfaz a operação em 'itens' (se houver) e não houve exclusão do produto
    }
    $_SESSION['mensagem_loja'] = $msg_final;


} catch (Exception $e) {
    // 5. TRATAMENTO DE ERRO FATAL (Rollback)
    $mysqli->rollback(); 
    // Mensagem de erro genérica (com detalhes do sistema para debug, se necessário)
    $_SESSION['mensagem_loja'] = "<p class='msg-erro'>❌ Erro de Sistema: A exclusão falhou completamente. Por favor, tente novamente.</p>";
}

$mysqli->close();

// 6. REDIRECIONA DE VOLTA PARA O PAINEL DE PRODUTOS
header("Location: painel_loja.php?aba=produtos");
exit();
?>