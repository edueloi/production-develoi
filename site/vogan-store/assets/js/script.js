document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Preloader (Tela de Carregamento)
    const preloader = document.getElementById('preloader');
    
    window.addEventListener('load', () => {
        // Aguarda meio segundo para sumir suavemente
        setTimeout(() => {
            preloader.style.opacity = '0';
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 500);
        }, 500);
    });

    // 2. Menu Mobile (Abrir e Fechar)
    const mobileBtn = document.getElementById('mobile-btn');
    const closeBtn = document.getElementById('close-menu');
    const navWrapper = document.querySelector('.nav-wrapper');

    mobileBtn.addEventListener('click', () => {
        navWrapper.classList.add('active');
    });

    closeBtn.addEventListener('click', () => {
        navWrapper.classList.remove('active');
    });

    // Fechar menu ao clicar num link
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            navWrapper.classList.remove('active');
        });
    });

    // 3. Efeito de Scroll Reveal (Animar ao rolar)
    const reveals = document.querySelectorAll('.scroll-reveal');

    const revealOnScroll = () => {
        const windowHeight = window.innerHeight;
        const elementVisible = 150;

        reveals.forEach((reveal) => {
            const elementTop = reveal.getBoundingClientRect().top;
            if (elementTop < windowHeight - elementVisible) {
                reveal.classList.add('active');
            }
        });
    };

    window.addEventListener('scroll', revealOnScroll);

    // 4. Lógica Simples do Carrinho
    let cartCount = 0;
    const cartDisplay = document.querySelector('.cart-count');
    
    // Função global para ser chamada no HTML
    window.addCart = function() {
        cartCount++;
        cartDisplay.textContent = cartCount;
        
        // Pequena animação no ícone do carrinho
        cartDisplay.parentElement.style.transform = 'scale(1.2)';
        setTimeout(() => {
            cartDisplay.parentElement.style.transform = 'scale(1)';
        }, 200);

        alert('Produto adicionado ao carrinho!');
    };
});