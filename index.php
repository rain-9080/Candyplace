<?php
// OBRIGATÓRIO: Inicia ou retoma a sessão para ler as variáveis de login.
session_start(); 
include 'db_connect.php'; // 1. INCLUI A CONEXÃO COM O BANCO DE DADOS

// =========================================================
// 1. LÓGICA DO CARROSSEL: Busca 3 produtos aleatórios com imagem
// =========================================================
$produtos_destaque = [];
$sql_destaque = "
    SELECT nm_produto, ds_imagem, ds_categoria 
    FROM produto 
    WHERE ds_imagem IS NOT NULL AND ds_imagem != '' 
    ORDER BY RAND() 
    LIMIT 3
";
// Verifica se a conexão e a consulta foram bem-sucedidas
if ($mysqli && $resultado_destaque = $mysqli->query($sql_destaque)) {
    while ($produto = $resultado_destaque->fetch_assoc()) {
        $produtos_destaque[] = $produto;
    }
}


// LÓGICA DE SESSÃO MULTIUSUÁRIO (CLIENTE E LOJA)
$is_logged_in = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
$user_type = $is_logged_in ? ($_SESSION['tipo_usuario'] ?? null) : null;
$user_name = $is_logged_in ? (htmlspecialchars($_SESSION['nm_usuario'] ?? 'Usuário')) : '';

// =========================================================
// 2. BUSCA PRODUTOS NO BANCO DE DADOS (POPULARES: MAX 12 + 1)
// =========================================================
$produtos = []; // Array para armazenar os produtos
$limite_produtos = 12; // Limite de exibição na página inicial
$tem_mais_produtos = false; // Flag para o botão "Ver Mais"

// SQL para buscar ATÉ 13 produtos (12 para exibir + 1 para checar se há mais)
$sql_produtos = "
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
    LIMIT " . ($limite_produtos + 1);

// Verifica se a conexão e o preparo foram bem-sucedidos
if ($mysqli && $stmt_produtos = $mysqli->prepare($sql_produtos)) {
    $stmt_produtos->execute();
    $resultado_produtos = $stmt_produtos->get_result();

    while ($produto = $resultado_produtos->fetch_assoc()) {
        $produtos[] = $produto;
    }

    $stmt_produtos->close();

    // Lógica para limitar a 12 e checar o botão
    if (count($produtos) > $limite_produtos) {
        $tem_mais_produtos = true;
        // Remove o 13º item, deixando apenas 12 para exibição
        array_pop($produtos);
    }
}

// FECHANDO A CONEXÃO
if ($mysqli) {
    $mysqli->close(); 
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candy Place | Doces e Confeitaria Artesanal</title>
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
            <a href="carrinho.php" class="icon-link" title="Ver Sacola" aria-label="Ver Sacola">🛍️</a>
            <a href="painel_cliente.php" class="icon-link user-state-toggle" title="Acessar Painel" aria-label="Painel do Cliente">
                <span class="user-icon">👤</span>
                <span class="auth-text" data-state="logged-in">Olá, <?php echo $user_name; ?>!</span>
            </a>
            <a href="logout.php" class="icon-link" title="Sair" aria-label="Sair da Conta">➡️</a> 

        <?php elseif ($user_type === 'loja'): ?>
            <a href="cadastro_produto.php" class="icon-link" title="Cadastrar Produtos" aria-label="Cadastrar Produtos">📝</a>
            <a href="painel_loja.php" class="icon-link shop-panel-link" title="Acessar Painel Loja" aria-label="Painel da Loja">
                <span class="shop-icon">🏪</span>
                <span class="auth-text" data-state="logged-in">Painel Loja: <?php echo $user_name; ?></span>
            </a>
            <a href="logout.php" class="icon-link" title="Sair" aria-label="Sair da Conta">➡️</a> 

        <?php else: ?>
            <a href="login_cliente.php" class="icon-link user-state-toggle" title="Minha Conta" aria-label="Conta do Cliente">
                <span class="user-icon">👤</span>
                <span class="auth-text" data-state="logged-out">Login Cliente</span>
            </a>
            <a href="login_loja.php" class="icon-link shop-panel-link" title="Painel do Lojista" aria-label="Painel do Lojista">
                <span class="shop-icon">🏪</span>
                <span class="auth-text" data-state="logged-out">Login Loja</span>
            </a>
        <?php endif; ?> 
        </nav>
    </header>

    <main class="container">

        <section class="carousel-container visible" role="region" aria-label="Destaques e Promoções">
            <div class="carousel-slide" id="main-carousel" style="transform: translateX(0);">
                
                <?php if (count($produtos_destaque) > 0): ?>
                    
                    <?php foreach ($produtos_destaque as $i => $produto): 
                        // Define uma cor de fundo com base no índice
                        $cores_fundo = ['#ffddd2', '#e4c192', '#a4978e', '#d8a499'];
                        $cor_fundo = $cores_fundo[$i % count($cores_fundo)];
                    ?>
                    <div class="carousel-item" role="group" aria-roledescription="slide" style="background-color: <?php echo $cor_fundo; ?>;">
                        
                        <h3 class="carousel-titulo"><?php echo htmlspecialchars($produto['nm_produto']); ?></h3>
                        <p class="carousel-categoria">Categoria: <?php echo htmlspecialchars($produto['ds_categoria'] ?? 'Geral'); ?></p>
                        
                        <img src="<?php echo htmlspecialchars($produto['ds_imagem']); ?>" 
                            alt="Imagem do produto <?php echo htmlspecialchars($produto['nm_produto']); ?>" 
                            class="carousel-imagem">
                            
                    </div>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <div class="carousel-item" style="background-color: #ffe8d6; display: flex; align-items: center; justify-content: center;">Nenhum produto em destaque encontrado.</div>
                <?php endif; ?>
                
            </div>
            
            <button class="prev" aria-label="Slide anterior">❮</button>
            <button class="next" aria-label="Próximo slide">❯</button>
        </section>

        <h2 class="section-title">🔥 Produtos Mais Populares</h2>
        <section class="grid product-grid" aria-labelledby="popular-products-title">
            
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
                    // Cria um link simples baseado no ID do produto
                    $link_produto = "produto_detalhe.php?id=" . $cd_produto;
                    
                    // Usa a imagem do banco ou uma imagem de placeholder
                    $ds_imagem = htmlspecialchars($produto['ds_imagem'] ?? 'placeholders/default.jpg'); 
                    // Para acessibilidade
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
            <?php 
                endforeach;
            endif;
            ?>
        </section>

        <?php if ($tem_mais_produtos): ?>
            <div class="view-more-btn-container">
                <a href="produtos_populares.php" class="view-more-btn">Ver Mais Itens Populares</a>
            </div>
        <?php endif; ?>
        
        </section>

        <?php if (isset($tem_mais_lojas) && $tem_mais_lojas): ?>
            <div class="view-more-btn-container">
                <a href="todas_lojas.php" class="view-more-btn">Ver Todas as Lojas</a>
            </div>
        <?php endif; ?>

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