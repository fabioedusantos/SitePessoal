const messages = ["Carregando arquivos CSS...", "Carregando arquivos JS...", "Buscando cabras no servidor...", "Limpando o cache (não o seu, o do navegador)...", "Preparando memes...", "Finalizando downloads secretos..."];
let messageIndex = 0;
const messageElement = document.getElementById('loading-message');
const interval = setInterval(() => {
    messageElement.textContent = messages[messageIndex];
    messageIndex = (messageIndex + 1) % messages.length
}, 1000);
window.addEventListener('load', function () {
    const preloader = document.getElementById('preloader');
    clearInterval(interval);
    preloader.style.display = 'none';
})