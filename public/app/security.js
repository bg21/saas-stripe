/**
 * Funções de segurança para o frontend
 * Previne XSS e outras vulnerabilidades client-side
 */

/**
 * Escapa HTML para prevenir XSS
 * @param {string|null|undefined} text - Texto a ser escapado
 * @returns {string} Texto escapado
 */
function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

/**
 * Escapa atributos HTML
 * @param {string|null|undefined} text - Texto a ser escapado
 * @returns {string} Texto escapado
 */
function escapeHtmlAttribute(text) {
    if (text === null || text === undefined) {
        return '';
    }
    
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#x27;');
}

/**
 * Cria elemento de forma segura usando textContent
 * @param {string} tag - Tag HTML
 * @param {Object} attributes - Atributos do elemento
 * @param {string} text - Texto do elemento
 * @returns {HTMLElement} Elemento criado
 */
function createSafeElement(tag, attributes = {}, text = '') {
    const element = document.createElement(tag);
    
    // Adiciona atributos de forma segura
    for (const [key, value] of Object.entries(attributes)) {
        if (key === 'innerHTML' || key === 'textContent') {
            continue; // Ignora, será definido depois
        }
        element.setAttribute(key, escapeHtmlAttribute(value));
    }
    
    // Define texto de forma segura
    element.textContent = text;
    
    return element;
}

/**
 * Insere HTML de forma segura (usa DOMPurify se disponível, senão escapa)
 * @param {HTMLElement} element - Elemento onde inserir
 * @param {string} html - HTML a inserir (será sanitizado)
 */
function setSafeHTML(element, html) {
    if (typeof DOMPurify !== 'undefined') {
        // Se DOMPurify estiver disponível, usa ele
        element.innerHTML = DOMPurify.sanitize(html);
    } else {
        // Fallback: escapa tudo (menos seguro, mas melhor que nada)
        element.textContent = html;
    }
}

// Exporta para uso global
if (typeof window !== 'undefined') {
    window.escapeHtml = escapeHtml;
    window.escapeHtmlAttribute = escapeHtmlAttribute;
    window.createSafeElement = createSafeElement;
    window.setSafeHTML = setSafeHTML;
}

