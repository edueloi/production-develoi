document.addEventListener('DOMContentLoaded', () => {
    /* ========== 1. PRELOADER ========== */
    const preloader = document.getElementById('preloader');

    window.addEventListener('load', () => {
        setTimeout(() => {
            preloader.style.transition = 'opacity 0.4s ease';
            preloader.style.opacity = '0';
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 400);
        }, 600);
    });

    /* ========== 2. HEADER SCROLL ========== */
    const header = document.getElementById('header');
    const onScrollHeader = () => {
        if (window.scrollY > 20) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    };
    onScrollHeader();
    window.addEventListener('scroll', onScrollHeader);

    /* ========== 3. MENU MOBILE ========== */
    const mobileBtn = document.getElementById('mobile-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const closeMenuBtn = document.getElementById('close-menu');

    if (mobileBtn && mobileMenu) {
        mobileBtn.addEventListener('click', () => {
            mobileMenu.classList.add('open');
        });
    }

    if (closeMenuBtn) {
        closeMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.remove('open');
        });
    }

    document.querySelectorAll('.mobile-link').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('open');
        });
    });

    /* ========== 4. NAV LINKS ACTIVE (SCROLL SUAVE OPCIONAL) ========== */
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        link.addEventListener('click', e => {
            const href = link.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const section = document.querySelector(href);
                if (section) {
                    window.scrollTo({
                        top: section.offsetTop - 72,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    /* ========== 5. SCROLL REVEAL ========== */
    const revealElements = document.querySelectorAll('.scroll-reveal');

    const handleReveal = () => {
        const windowHeight = window.innerHeight;
        revealElements.forEach(el => {
            const rect = el.getBoundingClientRect();
            if (rect.top < windowHeight - 100) {
                el.classList.add('visible');
            }
        });
    };

    handleReveal();
    window.addEventListener('scroll', handleReveal);

    /* ========== 6. FILTRO DE CATEGORIAS ========== */
    const categoryButtons = document.querySelectorAll('.chip-category');
    const products = document.querySelectorAll('.product-card');

    categoryButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;

            categoryButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            products.forEach(card => {
                const cardCat = card.dataset.category;
                if (filter === 'all' || cardCat === filter) {
                    card.style.display = 'flex';
                    requestAnimationFrame(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    });
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(10px)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 180);
                }
            });
        });
    });

    /* ========== 7. CARRINHO SIMPLES ========== */
    let cartCount = 0;
    const cartDisplay = document.querySelector('.cart-count');
    const cartBtn = document.querySelector('.cart-btn');

    window.addCart = function () {
        cartCount++;
        if (cartDisplay) {
            cartDisplay.textContent = cartCount;
        }

        if (cartBtn) {
            cartBtn.style.transform = 'scale(1.1)';
            setTimeout(() => {
                cartBtn.style.transform = 'scale(1)';
            }, 150);
        }
        alert('Produto adicionado ao carrinho!');
    };

    /* ========== 8. BUSCA (DEMO) ========== */
    const searchBtn = document.querySelector('.search-btn');
    if (searchBtn) {
        searchBtn.addEventListener('click', () => {
            const term = prompt('O que você está procurando hoje? (ex: headset, teclado, mouse)');
            if (term) {
                alert(`Em breve: resultados para "${term}" na Vogan Store 🔍`);
            }
        });
    }

        /* ========== 9. CHATBOT WHATSAPP ========== */
    const whatsappNumber = '5515992675429'; // 55 + 15 + 992675429

    const chatbotToggle = document.getElementById('chatbot-toggle');
    const chatbotWidget = document.getElementById('chatbot-widget');
    const chatbotClose = document.getElementById('chatbot-close');

    function openChatbot() {
        if (chatbotWidget) {
            chatbotWidget.classList.add('open');
        }
    }

    function closeChatbot() {
        if (chatbotWidget) {
            chatbotWidget.classList.remove('open');
        }
    }

    if (chatbotToggle) {
        chatbotToggle.addEventListener('click', () => {
            if (chatbotWidget && chatbotWidget.classList.contains('open')) {
                closeChatbot();
            } else {
                openChatbot();
            }
        });
    }

    if (chatbotClose) {
        chatbotClose.addEventListener('click', closeChatbot);
    }

    // clicar fora do card fecha o chatbot (opcional)
    document.addEventListener('click', (event) => {
        if (!chatbotWidget || !chatbotToggle) return;
        const clickInsideWidget = chatbotWidget.contains(event.target);
        const clickOnToggle = chatbotToggle.contains(event.target);
        if (!clickInsideWidget && !clickOnToggle) {
            chatbotWidget.classList.remove('open');
        }
    });

    // Função para abrir WhatsApp com a mensagem
    function openWhatsAppWithMessage(message) {
        const text = encodeURIComponent(message + ' (enviado pelo site da Vogan Store)');
        const url = `https://wa.me/${whatsappNumber}?text=${text}`;
        window.open(url, '_blank');
    }

    // Opções do chatbot
    document.querySelectorAll('.chatbot-option').forEach(option => {
        option.addEventListener('click', () => {
            const text = option.getAttribute('data-text') || 'Olá, vim do site da Vogan Store.';
            openWhatsAppWithMessage(text);
        });
    });

    // Botão do rodapé (falar direto com atendente)
    const footerBtn = document.querySelector('.chatbot-footer-btn');
    if (footerBtn) {
        footerBtn.addEventListener('click', () => {
            const text = footerBtn.getAttribute('data-text') || 'Olá, preciso de atendimento humano da Vogan Store.';
            openWhatsAppWithMessage(text);
        });
    }

});
