<?php
include 'db_connect.php'; 
session_start(); 

// lógica de sessão
$is_logged_in = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
$user_type = $is_logged_in ? ($_SESSION['tipo_usuario'] ?? null) : null;
$user_name = $is_logged_in ? (htmlspecialchars($_SESSION['nm_usuario'] ?? 'Usuário')) : '';


// busca as lojas no banco de dados

$lojas = []; 

$sql_lojas = "
    SELECT 
        cd_loja, 
        nm_loja
    FROM 
        cadastro_loja
    WHERE
        status_loja = 'Ativa'
    ORDER BY 
        nm_loja ASC 
";

$stmt_lojas = $mysqli->prepare($sql_lojas);
$stmt_lojas->execute();
$resultado_lojas = $stmt_lojas->get_result();

while ($loja = $resultado_lojas->fetch_assoc()) {
    $lojas[] = $loja;
}

$stmt_lojas->close();
$mysqli->close(); 
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todas as Lojas - Candy Place</title>
    <link rel="stylesheet" href="css/style.css"> 
</head>
<body>

    <header class="header-top">
        <a href="/" class="logo" aria-label="CandyPlace - Voltar para a página inicial">CandyPlace</a>

        <form class="search-bar-container" role="search" aria-label="Buscar produtos e lojas">
            <div class="search-bar">
                <label for="search-input" class="sr-only">Pesquisar</label>
                <input type="search" id="search-input" placeholder="Pesquisar doces, lojas...">
                <button type="submit" class="search-btn" aria-label="Buscar">🔍</button>
            </div>
        </form>

        <nav class="nav-actions" aria-label="Ações do Usuário e Carrinho">
            <?php if ($user_type === 'cliente'): ?>
            <a href="carrinho.php" class="icon-link" title="Ver Sacola" aria-label="Ver Sacola">🛍️</a><a href="painel_cliente.php" class="icon-link user-state-toggle" title="Acessar Painel" aria-label="Painel do Cliente"><span class="user-icon">👤</span><span class="auth-text" data-state="logged-in">Olá, <?php echo $user_name; ?>!</span></a>
            <?php elseif ($user_type === 'loja'): ?>
            <a href="gerenciar_pedidos.php" class="icon-link" title="Gerenciar Pedidos" aria-label="Gerenciar Pedidos">📝</a><a href="painel_loja.php" class="icon-link shop-panel-link" title="Acessar Painel Loja" aria-label="Painel da Loja"><span class="shop-icon">🏪</span><span class="auth-text" data-state="logged-in">Painel Loja: <?php echo $user_name; ?></span></a>
            <?php else: ?>
            <a href="pedidos.php" class="icon-link" title="Meus Pedidos" aria-label="Meus Pedidos">📝</a>
            <a href="carrinho.php" class="icon-link" title="Ver Sacola" aria-label="Ver Sacola">🛍️</a>
            <a href="login_cliente.php" class="icon-link user-state-toggle" title="Minha Conta" aria-label="Conta do Usuário"><span class="user-icon">👤</span><span class="auth-text" data-state="logged-out">Logar</span></a>
            <a href="login_loja.php" class="icon-link shop-panel-link" title="Painel do Lojista" aria-label="Painel do Lojista"><span class="shop-icon">🏪</span></a>
            <?php endif; ?> 
        </nav>
    </header>

    <main class="container">
        <h1 class="section-title" style="margin-top: 20px;">🏪 Todas as Lojas Cadastradas</h1>
        <p><a href="index.php" style="color: var(--color-primary); text-decoration: underline; margin-bottom: 20px; display: inline-block;">← Voltar para a Página Inicial</a></p>
        
        <section class="shop-list" aria-labelledby="all-shops-title">
            
            <?php 
            if (empty($lojas)): 
            ?>
                <p>Nenhuma loja ativa cadastrada no momento. 🙁</p>
            <?php
            else:
                foreach ($lojas as $loja): 
                    $cd_loja = $loja['cd_loja'];
                    $nm_loja = htmlspecialchars($loja['nm_loja']);
                    $link_loja = "loja_detalhe.php?id=" . $cd_loja; 
            ?>
            <a href="<?php echo $link_loja; ?>" class="shop-card">
                <figure class="shop-logo" aria-hidden="true"></figure>
                <span><?php echo $nm_loja; ?></span>
            </a>
            <?php 
                endforeach;
            endif;
            ?>
        </section>
        
    </main>

    <footer class="footer">
        <nav class="footer-links" aria-label="Links Úteis">
            <a href="/sobre">Sobre Nós</a>
            <a href="/ajuda">Ajuda</a>
            <a href="/termos">Termos de Uso</a>
            <a href="/politica">Política de Privacidade</a>
        </nav>
        <p class="copyright">&copy; <time datetime="2025">2025</time> CandyPlace. Todos os direitos reservados.</p>
    </footer>

    <script src="js/script.js" defer></script> 
</body>
</html>