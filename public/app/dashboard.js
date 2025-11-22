/**
 * ✅ OTIMIZAÇÃO: JavaScript principal movido para arquivo externo
 * Isso permite cache do navegador e melhor performance
 */

// ✅ Invalidação de cache em outras abas
if (typeof BroadcastChannel !== 'undefined') {
    const cacheChannel = new BroadcastChannel('cache_invalidation');
    cacheChannel.addEventListener('message', (event) => {
        if (event.data && event.data.action === 'clear') {
            const pattern = event.data.pattern;
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.startsWith('api_cache_') && key.includes(pattern)) {
                    localStorage.removeItem(key);
                }
            });
        }
    });
}

// ✅ Listener para eventos de storage (fallback para navegadores sem BroadcastChannel)
// Nota: storage event só é disparado quando mudanças vêm de OUTRAS abas
window.addEventListener('storage', (event) => {
    if (event.key && event.key.startsWith('cache_clear_') && event.newValue) {
        const pattern = event.newValue;
        const keys = Object.keys(localStorage);
        keys.forEach(key => {
            if (key.startsWith('api_cache_') && key.includes(pattern)) {
                localStorage.removeItem(key);
            }
        });
    }
});

// Aguarda DOM estar pronto
document.addEventListener('DOMContentLoaded', () => {
    // Remove session_id da URL se estiver presente (segurança)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('session_id')) {
        urlParams.delete('session_id');
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }
    
    // Verifica se tem session_id
    if (!SESSION_ID) {
        // Redireciona apenas se não tiver session_id
        setTimeout(() => {
            window.location.href = '/login';
        }, 100);
        return;
    }
    
    // Verifica sessão em background (não bloqueia a página)
    // Usa AbortController para timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 segundos timeout
    
    fetch(API_URL + '/v1/auth/me', {
        headers: {
            'Authorization': 'Bearer ' + SESSION_ID
        },
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error('Sessão inválida');
        }
        return response.json();
    })
    .then(data => {
        // Atualiza dados do usuário e verifica role para mostrar/ocultar itens admin
        if (data.data) {
            const userRole = data.data.role || USER?.role;
            updateSidebarForRole(userRole);
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        // Só redireciona se realmente houver erro (não timeout)
        if (error.name !== 'AbortError') {
            console.warn('Sessão inválida, redirecionando...');
            localStorage.removeItem('session_id');
            localStorage.removeItem('user');
            localStorage.removeItem('tenant');
            window.location.href = '/login';
        }
    });
    
    // Verifica role do usuário atual (do PHP) para mostrar/ocultar itens
    if (USER && USER.role) {
        updateSidebarForRole(USER.role);
    }
});

// Funções globais
async function logout() {
    const confirmed = await showConfirmModal('Deseja realmente sair?', 'Confirmar Logout', 'Sair', 'btn-primary');
    if (confirmed) {
        fetch(API_URL + '/v1/auth/logout', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + SESSION_ID
            }
        }).finally(() => {
            localStorage.removeItem('session_id');
            localStorage.removeItem('user');
            localStorage.removeItem('tenant');
            window.location.href = '/login';
        });
    }
}

// Sidebar mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
}

// ✅ OTIMIZAÇÃO: Cache simples no frontend (localStorage) com melhor performance
const cache = {
    get: (key) => {
        try {
            const item = localStorage.getItem('api_cache_' + key);
            if (!item) return null;
            const { data, expires } = JSON.parse(item);
            if (expires && Date.now() > expires) {
                localStorage.removeItem('api_cache_' + key);
                return null;
            }
            return data;
        } catch (e) {
            return null;
        }
    },
    set: (key, data, ttl = 60000) => {
        try {
            const item = {
                data,
                expires: Date.now() + ttl
            };
            localStorage.setItem('api_cache_' + key, JSON.stringify(item));
        } catch (e) {
            // Ignora erros de localStorage (quota excedida, etc)
        }
    },
    clear: (pattern) => {
        try {
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                // ✅ CORREÇÃO: Procura por chaves que contenham o padrão (não apenas que comecem)
                // A chave do cache é: api_cache_ + endpoint + method + body
                if (key.startsWith('api_cache_') && key.includes(pattern)) {
                    localStorage.removeItem(key);
                }
            });
            
            // ✅ Invalida cache em outras abas usando BroadcastChannel
            if (typeof BroadcastChannel !== 'undefined') {
                const channel = new BroadcastChannel('cache_invalidation');
                channel.postMessage({ action: 'clear', pattern: pattern });
            }
            
            // ✅ Fallback: usa localStorage para notificar outras abas
            try {
                const notificationKey = 'cache_clear_' + Date.now();
                localStorage.setItem(notificationKey, pattern);
                // Remove imediatamente para não poluir o localStorage
                setTimeout(() => {
                    try {
                        localStorage.removeItem(notificationKey);
                    } catch (e) {}
                }, 100);
            } catch (e) {
                // Ignora se não suportado
            }
        } catch (e) {}
    }
};

// ✅ OTIMIZAÇÃO: Helper para fazer requisições autenticadas com cache e retry
async function apiRequest(endpoint, options = {}) {
    // Verifica se SESSION_ID está disponível
    const sessionId = SESSION_ID || localStorage.getItem('session_id');
    if (!sessionId) {
        console.error('SESSION_ID não encontrado. Redirecionando para login...');
        localStorage.removeItem('session_id');
        localStorage.removeItem('user');
        localStorage.removeItem('tenant');
        window.location.href = '/login';
        throw new Error('Não autenticado');
    }
    
    const cacheKey = endpoint + (options.method || 'GET') + JSON.stringify(options.body || '');
    const useCache = !options.method || options.method === 'GET';
    const cacheTTL = options.cacheTTL || 30000; // 30 segundos padrão
    
    // Tenta obter do cache primeiro (apenas para GET)
    if (useCache && !options.skipCache) {
        const cached = cache.get(cacheKey);
        if (cached !== null) {
            return cached;
        }
    }
    
    const defaultOptions = {
        headers: {
            'Authorization': 'Bearer ' + sessionId,
            'Content-Type': 'application/json'
        }
    };
    
    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...(options.headers || {})
        }
    };
    
    // ✅ OTIMIZAÇÃO: Retry automático para falhas de rede
    let retries = options.retries || 0;
    let lastError;
    
    while (retries >= 0) {
        try {
            // Debug: log do header Authorization (apenas em desenvolvimento)
            if (typeof window !== 'undefined' && window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('Request:', {
                    url: API_URL + endpoint,
                    hasAuth: !!mergedOptions.headers['Authorization'],
                    authHeader: mergedOptions.headers['Authorization'] ? mergedOptions.headers['Authorization'].substring(0, 20) + '...' : 'none'
                });
            }
            
            const response = await fetch(API_URL + endpoint, mergedOptions);
            const data = await response.json();
            
            if (!response.ok) {
                // Log detalhado do erro
                console.error('API Error:', {
                    status: response.status,
                    statusText: response.statusText,
                    data: data,
                    endpoint: endpoint
                });
                throw new Error(data.message || data.error || 'Erro na requisição');
            }
            
            // Salva no cache (apenas para GET bem-sucedido)
            if (useCache && response.ok) {
                cache.set(cacheKey, data, cacheTTL);
            }
            
            return data;
        } catch (error) {
            lastError = error;
            if (retries > 0 && (error.name === 'TypeError' || error.message.includes('network'))) {
                retries--;
                await new Promise(resolve => setTimeout(resolve, 1000)); // Aguarda 1s antes de retry
                continue;
            }
            throw error;
        }
    }
    
    throw lastError;
}

// ✅ OTIMIZAÇÃO: Helper para debounce (melhor performance)
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Helper para mostrar alertas
function showAlert(message, type = 'info', containerId = 'alertContainer') {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // ✅ CORREÇÃO: Mapeia ícones para cada tipo de alerta
    const iconMap = {
        'danger': 'exclamation-triangle',
        'success': 'check-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `<i class="bi bi-${iconMap[type] || 'info-circle'}-fill"></i> ${message}`;
    container.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// ✅ OTIMIZAÇÃO: Helper para formatar moeda (cache de formatter)
const currencyFormatters = {};
function formatCurrency(value, currency = 'BRL') {
    if (!currencyFormatters[currency]) {
        currencyFormatters[currency] = new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: currency.toLowerCase()
        });
    }
    return currencyFormatters[currency].format(value / 100);
}

// ✅ OTIMIZAÇÃO: Helper para formatar data (cache de formatter)
const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
});

function formatDate(timestamp) {
    if (!timestamp) return '-';
    // Se for string de data MySQL (YYYY-MM-DD HH:MM:SS), converte
    if (typeof timestamp === 'string' && timestamp.match(/^\d{4}-\d{2}-\d{2}/)) {
        const date = new Date(timestamp);
        return dateFormatter.format(date);
    }
    // Se for timestamp Unix (número)
    const date = new Date(timestamp * 1000);
    return dateFormatter.format(date);
}

// Atualiza sidebar baseado no role do usuário
function updateSidebarForRole(role) {
    // Encontra a seção de administração procurando pelo link de usuários
    const adminLinks = document.querySelectorAll('.nav-link[href="/users"]');
    adminLinks.forEach(link => {
        const adminSection = link.closest('.nav-section');
        if (adminSection) {
            if (role !== 'admin') {
                // Oculta toda a seção de administração se não for admin
                adminSection.style.display = 'none';
            } else {
                // Mostra a seção de administração se for admin
                adminSection.style.display = 'block';
            }
        }
    });
}

// ✅ Modal de Confirmação Reutilizável (substitui confirm() nativo)
function showConfirmModal(message, title = 'Confirmar Ação', confirmText = 'Confirmar', confirmClass = 'btn-danger') {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const modalTitle = document.getElementById('confirmModalLabel');
        const modalBody = document.getElementById('confirmModalBody');
        const confirmButton = document.getElementById('confirmModalButton');
        
        // Configura o modal
        modalTitle.textContent = title;
        modalBody.textContent = message;
        confirmButton.textContent = confirmText;
        confirmButton.className = `btn ${confirmClass}`;
        
        // Remove listeners anteriores
        const newConfirmButton = confirmButton.cloneNode(true);
        confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
        
        // Adiciona listener para confirmar
        newConfirmButton.addEventListener('click', () => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            bsModal.hide();
            resolve(true);
        });
        
        // Adiciona listener para cancelar
        modal.addEventListener('hidden.bs.modal', function onHidden() {
            modal.removeEventListener('hidden.bs.modal', onHidden);
            resolve(false);
        }, { once: true });
        
        // Mostra o modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    });
}

