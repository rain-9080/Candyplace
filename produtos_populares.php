<?php
include 'db_connect.php'; // Inclui a conexão com o banco de dados
session_start(); // Inicia a sessão

// LÓGICA DE SESSÃO (REUTILIZADA DO index.php)
$is_logged_in = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
$user_type = $is_logged_in ? ($_SESSION['tipo_usuario'] ?? null) : null;
$user_name = $is_logged_in ? (htmlspecialchars($_SESSION['nm_usuario'] ?? 'Usuário')) : '';

// =========================================================
// BUSCA TODOS OS PRODUTOS NO BANCO DE DADOS (SEM LIMITE)
// =========================================================
$produtos = []; // Array para armazenar TODOS os produtos

// SQL para buscar TODOS os produtos
$sql = "
    SELECT 
        p.cd_produto, 
        p.nm_produto, 
        p.vl_preco, 
        p.ds_imagem,
        l.nm_loja
    FROM 
        produto p
    JOIN 
        cadastro_loja l ON p.cd_loja = l.cd_loja
    ORDER BY 
        p.cd_produto DESC 
";

$stmt = $mysqli->prepare($sql);
$stmt->execute();
$resultado = $stmt->get_result();

while ($produto = $resultado->fetch_assoc()) {
    $produtos[] = $produto;
}

$stmt->close();
$mysqli->close(); // Fecha a conexão
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todos os Produtos - Candy Place</title>
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
            <a href="login_cliente.php" class="icon-link user-state-toggle" title="Minha Conta" aria-label="Conta do Usuário"><span class="user-icon">👤</span><span class="auth-text" data-state="logged-out">Login Cliente</span></a><a href="login_loja.php" class="icon-link shop-panel-link" title="Painel do Lojista" aria-label="Painel do Lojista"><span class="shop-icon">🏪</span><span class="auth-text" data-state="logged-out">Login Loja</span></a>
            <?php endif; ?> 
        </nav>
    </header>
    <main class="container">
        <h1 class="section-title" style="margin-top: 20px;">🍬 Todos os Doces e Produtos</h1>
        <p><a href="index.php" style="color: var(--color-primary); text-decoration: underline; margin-bottom: 20px; display: inline-block;">← Voltar para a Página Inicial</a></p>
        
        <section class="grid product-grid" aria-labelledby="all-products-title">
            
            <?php 
            if (empty($produtos)): 
            ?>
                <p>Nenhum produto cadastrado no momento. 😕</p>
            <?php
            else:
                foreach ($produtos as $produto): 
                    // Preparação das variáveis
                    $cd_produto = $produto['cd_produto'];
                    $nm_produto = htmlspecialchars($produto['nm_produto']);
                    $nm_loja = htmlspecialchars($produto['nm_loja']);
                    $vl_preco = number_format($produto['vl_preco'], 2, ',', '.');
                    $link_produto = "produto_detalhe.php?id=" . $cd_produto;
                    $ds_imagem = $produto['ds_imagem'] ?: 'placeholders/default.jpg'; 
                    $preco_aria = str_replace('.', ' e ', str_replace(',', ' centavos', $vl_preco));
            ?>
            <div class="product-card">
                <a class="product-link">
                    <figure class="product-image">
                        <img src="<?php echo $ds_imagem; ?>" alt="<?php echo $nm_produto; ?> da <?php echo $nm_loja; ?>" loading="lazy">
                    </figure>
                    <div class="product-info">
                        <h3><?php echo $nm_produto; ?></h3>
                        <p class="product-vendor"><?php echo $nm_loja; ?></p>
                        <div class="product-price" aria-label="Preço: R$ <?php echo $preco_aria; ?>">R$ <?php echo $vl_preco; ?></div>
                    </div>
                </a>
                
                <form method="post" action="add_cart.php">
                    <input type="hidden" name="cd_produto" value="<?php echo $cd_produto; ?>">
                    <input type="hidden" name="qt_produto" value="1"> <button type="submit" class="add-to-cart-btn" aria-label="Adicionar 1 unidade de <?php echo $nm_produto; ?> ao carrinho">Adicionar</button>
                </form>
            </div>
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