<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CandyPlace: O Lado Doce da Sua Cidade</title>
    <link rel="stylesheet" href="css/style2.css">
</head>
<body>

    <nav id="main-navbar">
        <div class="container nav-content">
            <a href="index.php" class="nav-title">Candyplace</a>

            <ul class="nav-links">
                <li><a href="#feedback" class="nav-item">Feedback</a></li>
                <li><a href="#beneficios" class="nav-item">Benefícios</a></li>
                <li><a href="#historia" class="nav-item">História</a></li>
                <li><a href="#cadastro" class="nav-item nav-cta">Comece</a></li> 
            </ul>
        </div>
    </nav>
    
    <header class="hero-section" id="hero">
        <div class="container">
            <h1 class="logo fade-scale-element">
                <img src="Logotipo_Candy_Place.png" alt="Logo CandyPlace - O Lado Doce da Sua Cidade">
            </h1>
            
            <p class="hero-subtitle slide-up-element">Conectando o sabor artesanal da sua cidade à conveniência do seu lar.</p>
            
            <a href="#cadastro" class="btn-primary hero-cta fade-in-delay-element" id="main-cta">
                Descubra agora um mundo doce
            </a>
            
            <div class="candy-visual" aria-hidden="true"></div>
        </div>
    </header>

    <section class="section-feedback" id="feedback">
        <div class="container">
            <h2 class="section-title slide-up-element">A Voz de Nossos Clientes</h2>
            <div class="feedback-grid">
                
                <div class="feedback-card scroll-fade-up" data-delay="0.1">
                    <p class="feedback-name">Cláudia N., Itanhaém</p>
                    <div class="stars">★★★★★</div>
                    <p class="feedback-text">"A variedade é incrível! Encontrei aquela torta da confeitaria Dona Marina que achei que estava fechada. A entrega foi super rápida. O CandyPlace realmente salvou minhas festas!"</p>
                </div>

                <div class="feedback-card scroll-fade-up" data-delay="0.3">
                    <p class="feedback-name">João F., Peruíbe</p>
                    <div class="stars">★★★★★</div>
                    <p class="feedback-text">"Facilidade nota 10. Uso o app há meses e nunca tive problemas. O atendimento das lojas é excelente, e o sistema de rastreio funciona perfeitamente. Comprarei sempre aqui."</p>
                </div>

                <div class="feedback-card scroll-fade-up" data-delay="0.5">
                    <p class="feedback-name">Sofia A., Mongaguá</p>
                    <div class="stars">★★★★★</div>
                    <p class="feedback-text">"Finalmente um marketplace que valoriza o artesanal! Os doces são sempre fresquinhos e bem embalados. É meu novo lugar favorito para presentear e satisfazer a vontade de doce."</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-benefits" id="beneficios">
        <div class="container">
            <h2 class="section-title slide-up-element">Por que o CandyPlace Eleva o Seu Negócio?</h2>
            
            <div class="benefits-grid">
                
                <div class="benefit-card scroll-fade-left">
                    <div class="icon-wrapper"><i class="icon-globe" data-icon="🌎"></i></div>
                    <h3>Melhora no Alcance e Visibilidade</h3>
                    <p>Nosso algoritmo inteligente conecta seus produtos a novos bairros e clientes que você nunca alcançaria sozinho. Aumente seu fluxo de pedidos sem sair da sua cozinha.</p>
                </div>

                <div class="benefit-card scroll-fade-up">
                    <div class="icon-wrapper"><i class="icon-camera" data-icon="📸"></i></div>
                    <h3>Profissionalismo e Imagem de Marca</h3>
                    <p>Tenha uma página dedicada e elegante, com fotos de alta qualidade e o poder de avaliações positivas, construindo a confiança do cliente e elevando a percepção da sua marca.</p>
                </div>

                <div class="benefit-card scroll-fade-right">
                    <div class="icon-wrapper"><i class="icon-rocket" data-icon="🚀"></i></div>
                    <h3>Otimização e Eficiência de Pedidos</h3>
                    <p>Centralize todos os seus pedidos em um único painel intuitivo. Menos erros, mais organização e controle simples do seu estoque e vendas em tempo real.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-storytelling parallax-bg" id="historia">
        <div class="container">
            <h2 class="section-title slide-up-element" style="color: var(--cor-fundo);">A História Que Nos Fez Nascer</h2>
            
            <div class="story-content scroll-fade-up">
                <p class="narrative">Em meio à falta de incentivo e à crescente digitalização, muitos confeiteiros e pequenos negócios artesanais da nossa Baixada Santista estavam se tornando **invisíveis**. A **Confeitaria da Dona Marina** quase fechou as portas; a **Pastelaria da Ana** não conseguia clientes além do seu quarteirão.</p>
                
                <p class="narrative highlight-text">O CandyPlace nasceu da revolta por ver tanto talento e paixão se apagando. Não podíamos permitir que o sabor da nossa cidade fosse perdido.</p>
                
                <p class="narrative">Nossa missão foi clara: dar a essas famílias uma plataforma digital. De repente, a Dona Marina estava entregando tortas no bairro vizinho, e a Ana via seus pedidos triplicarem, salvando o sustento de suas famílias.</p>
                
                <p class="narrative">Somos mais que um marketplace; somos um **salvador de negócios locais**. Junte-se a esta comunidade que celebra cada doce e cada história de sucesso.</p>
            </div>
        </div>
    </section>

    <section class="section-final-cta" id="cadastro">
        <div class="container">
            <h2 class="section-title slide-up-element">Junte-se ao Lado Doce da Cidade</h2>
            
            <div class="cta-cards-grid">
                
                <div class="cta-card scroll-fade-left">
                    <i class="cta-icon" data-icon="🛒"></i>
                    <h3>Criar Conta Cliente</h3>
                    <p>Faça seu cadastro rápido e comece a pedir os melhores doces e guloseimas artesanais com a conveniência de receber em casa.</p>
                    <a href="cadastro_cliente.php" class="btn-cta-cliente btn-microinteraction">Cadastrar como Cliente</a>
                </div>

                <div class="cta-card scroll-fade-right cta-loja">
                    <i class="cta-icon" data-icon="🏪"></i>
                    <h3>Criar Conta para Lojas</h3>
                    <p>Leve seu negócio para o próximo nível. Expanda seu alcance, profissionalize suas vendas e faça parte da revolução do doce local.</p>
                    <a href="cadastro_loja.php" class="btn-cta-loja btn-microinteraction">Cadastrar sua Loja</a>
                </div>
                
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 CandyPlace. Conectando sabores e histórias.</p>
        </div>
    </footer>
    
    <script src="js/script2.js"></script>
</body>
</html>