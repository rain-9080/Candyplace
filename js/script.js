
// Funções de Utilitário (Debounce)

const debounce = (func, delay = 100) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            func.apply(null, args);
        }, delay);
    };
};

document.addEventListener('DOMContentLoaded', () => {


    //  FADE-IN DA PÁGINA COMPLETA
    document.body.classList.add('body-loaded');



    // Carrossel Principal

    const carousel = document.getElementById('main-carousel');
    const prevButton = document.querySelector('.prev');
    const nextButton = document.querySelector('.next');
    const items = document.querySelectorAll('.carousel-item');
    
    if (carousel && items.length > 0) {
        let currentIndex = 0;
        let autoSlideInterval;

        // Calcula e aplica a transformação para mostrar o slide correto
        function updateCarousel() {
            
            const itemWidth = items[0].offsetWidth; 
            requestAnimationFrame(() => {
                carousel.style.transform = `translateX(${-currentIndex * itemWidth}px)`;
            });
        }

        function nextSlide() {
            currentIndex = (currentIndex + 1) % items.length;
            updateCarousel();
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + items.length) % items.length;
            updateCarousel();
        }

        // Adiciona listeners de clique
        nextButton.addEventListener('click', () => { nextSlide(); resetAutoSlide(); });
        prevButton.addEventListener('click', () => { prevSlide(); resetAutoSlide(); });

        // Lógica de Auto-Slide
        function startAutoSlide() {
            if (!autoSlideInterval) {
                autoSlideInterval = setInterval(nextSlide, 4000);
            }
        }

        function stopAutoSlide() {
            clearInterval(autoSlideInterval);
            autoSlideInterval = null;
        }

        function resetAutoSlide() {
            stopAutoSlide();
            startAutoSlide();
        }
        
        // Pausa o carrossel no hover
        carousel.parentNode.addEventListener('mouseenter', stopAutoSlide);
        carousel.parentNode.addEventListener('mouseleave', startAutoSlide);

        // Atualiza a posição no resize com debounce
        window.addEventListener('resize', debounce(updateCarousel, 250));
        
        // Inicia
        updateCarousel();
        startAutoSlide();
    }



    // Animação Fade-in com Intersection Observer (Scroll Reveal)

    const observerOptions = {
        root: null,
        rootMargin: "0px",
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
                obs.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Seleciona todos os elementos que devem ter a animação de fade-in
    const animatedElements = document.querySelectorAll(".carousel-container, .section-title, .grid, .shop-list, .view-more-btn-container");

    animatedElements.forEach(el => {
        observer.observe(el);
    });
    

    // Rolagem suave nos links internos

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener("click", function(e) {
            e.preventDefault();

            const target = document.querySelector(this.getAttribute("href"));
            if (target) {
                target.scrollIntoView({
                    behavior: "smooth"
                });
            }
        });
    });
});