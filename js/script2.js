document.addEventListener('DOMContentLoaded', function() {
    
    // Altura da navbar para ajuste de rolagem e do Intersection Observer
    const navbar = document.getElementById('main-navbar');
    const navbarHeight = navbar ? navbar.offsetHeight : 100;
    
    const navLinks = document.querySelectorAll('.nav-links a');
    // Seleciona as seções de destino incluindo o header/hero para o primeiro item
    const sections = document.querySelectorAll('header#hero, #feedback, #beneficios, #historia, #cadastro');

    /**
     * 1. Rolagem Suave ao Clique
     */
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault(); 
            
            const targetId = this.getAttribute('href'); // Ex: #feedback
            const targetSection = document.querySelector(targetId);

            if (targetSection) {
                // Rola suavemente, subtraindo a altura da navbar
                window.scrollTo({
                    top: targetSection.offsetTop - navbarHeight - 10, // Subtrai a navbar + um pequeno offset
                    behavior: 'smooth'
                });
            }
        });
    });


    /**
     * 2. Intersection Observer (Destaca o Link Ativo ao Rolar)
     */
    const observerOptions = {
        root: null, // O viewport
        // Define a "janela" de ativação logo abaixo da navbar
        rootMargin: `-${navbarHeight + 1}px 0px -60% 0px`, 
        threshold: 0
    };

    const sectionObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.getAttribute('id');
                // O link correspondente tem o href que aponta para este ID (#id)
                const activeLink = document.querySelector(`.nav-links a[href="#${id}"]`);

                if (activeLink) {
                    // Remove 'active' de TODOS
                    document.querySelectorAll('.nav-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    
                    // Adiciona 'active' ao link correspondente
                    activeLink.classList.add('active');
                }
            }
        });
    }, observerOptions);

    // Observa todas as seções definidas
    sections.forEach(section => {
        sectionObserver.observe(section);
    });


    /**
     *  Lógica de Animação de Scroll Reveal (Seção 2 do CSS)
     */
    const elementsToObserve = document.querySelectorAll('.slide-up-element, .scroll-fade-up, .scroll-fade-left, .scroll-fade-right, .fade-scale-element, .fade-in-delay-element, .feedback-card, .benefit-card, .cta-card, .story-content');
    
    const scrollObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;
                
                target.classList.add('is-visible');
                
                const delay = target.getAttribute('data-delay');
                if (delay) {
                    target.style.transitionDelay = delay + 's';
                    target.removeAttribute('data-delay'); 
                }

                scrollObserver.unobserve(target);
            }
        });
    }, {
        rootMargin: '0px 0px -15% 0px', 
        threshold: 0.1 
    });

    elementsToObserve.forEach(element => {
        scrollObserver.observe(element);
    });

    /**
     *  Microinteraction/Parallax (Mantenha o código de animação do CTA e Parallax aqui, se desejar)
     */

});