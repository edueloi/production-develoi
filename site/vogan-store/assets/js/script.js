document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. Efeito de Scroll Reveal (Aparecer suavemente) ---
    const hiddenElements = document.querySelectorAll('.hidden');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('show');
            }
        });
    }, {
        threshold: 0.1 // Começa a animar quando 10% do item aparece
    });

    hiddenElements.forEach((el) => observer.observe(el));


    // --- 2. Simulação de Carrinho ---
    let contagem = 0;
    const cartCountElement = document.getElementById('cart-count');
    const botoesAdicionar = document.querySelectorAll('.btn-add');

    // Função que roda quando clicas no botão "+"
    botoesAdicionar.forEach(botao => {
        botao.addEventListener('click', () => {
            contagem++;
            cartCountElement.innerText = contagem;
            
            // Efeito visual no botão (feedback de clique)
            botao.style.transform = "scale(1.2)";
            setTimeout(() => {
                botao.style.transform = "scale(1)";
            }, 200);
        });
    });
});

// Função global para chamar no HTML se necessário
function adicionarCarrinho() {
    console.log("Produto adicionado!");
}