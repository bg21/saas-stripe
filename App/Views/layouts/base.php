<?php
/**
 * Layout base para todas as views
 * 
 * @var string $title Título da página
 * @var string $apiUrl URL base da API
 * @var array|null $user Dados do usuário autenticado
 * @var array|null $tenant Dados do tenant
 * @var string $currentPage Página atual (para highlight no menu)
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?> - Sistema SaaS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="/css/dashboard.css">
    
    <!-- Security Helper (deve ser carregado primeiro) -->
    <script src="/app/security.js"></script>
</head>
<body>
    <!-- Mobile Header Bar -->
    <div class="mobile-header-bar">
        <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div class="mobile-header-logo">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </div>
    </div>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo desktop-logo">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </div>
        
        <nav>
            <ul class="nav-menu">
                <li class="nav-section">
                    <span class="nav-section-title">Principal</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/dashboard" class="nav-link <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/customers" class="nav-link <?php echo ($currentPage ?? '') === 'customers' ? 'active' : ''; ?>">
                                <i class="bi bi-people"></i>
                                <span>Clientes</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/subscriptions" class="nav-link <?php echo ($currentPage ?? '') === 'subscriptions' ? 'active' : ''; ?>">
                                <i class="bi bi-credit-card"></i>
                                <span>Assinaturas</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/products" class="nav-link <?php echo ($currentPage ?? '') === 'products' ? 'active' : ''; ?>">
                                <i class="bi bi-box"></i>
                                <span>Produtos</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/prices" class="nav-link <?php echo ($currentPage ?? '') === 'prices' ? 'active' : ''; ?>">
                                <i class="bi bi-tag"></i>
                                <span>Preços</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-section">
                    <span class="nav-section-title">Financeiro</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/transactions" class="nav-link <?php echo ($currentPage ?? '') === 'transactions' ? 'active' : ''; ?>">
                                <i class="bi bi-arrow-left-right"></i>
                                <span>Transações</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/invoices" class="nav-link <?php echo ($currentPage ?? '') === 'invoices' ? 'active' : ''; ?>">
                                <i class="bi bi-receipt"></i>
                                <span>Faturas</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/refunds" class="nav-link <?php echo ($currentPage ?? '') === 'refunds' ? 'active' : ''; ?>">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <span>Reembolsos</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/disputes" class="nav-link <?php echo ($currentPage ?? '') === 'disputes' ? 'active' : ''; ?>">
                                <i class="bi bi-exclamation-triangle"></i>
                                <span>Disputas</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/charges" class="nav-link <?php echo ($currentPage ?? '') === 'charges' ? 'active' : ''; ?>">
                                <i class="bi bi-credit-card-2-front"></i>
                                <span>Cobranças</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/payouts" class="nav-link <?php echo ($currentPage ?? '') === 'payouts' ? 'active' : ''; ?>">
                                <i class="bi bi-bank"></i>
                                <span>Saques</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/invoice-items" class="nav-link <?php echo ($currentPage ?? '') === 'invoice-items' ? 'active' : ''; ?>">
                                <i class="bi bi-list-ul"></i>
                                <span>Itens de Fatura</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/tax-rates" class="nav-link <?php echo ($currentPage ?? '') === 'tax-rates' ? 'active' : ''; ?>">
                                <i class="bi bi-percent"></i>
                                <span>Taxas de Imposto</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-section">
                    <span class="nav-section-title">Assinaturas</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/subscription-history" class="nav-link <?php echo ($currentPage ?? '') === 'subscription-history' ? 'active' : ''; ?>">
                                <i class="bi bi-clock-history"></i>
                                <span>Histórico</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-section">
                    <span class="nav-section-title">Promoções</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/coupons" class="nav-link <?php echo ($currentPage ?? '') === 'coupons' ? 'active' : ''; ?>">
                                <i class="bi bi-ticket-perforated"></i>
                                <span>Cupons</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/promotion-codes" class="nav-link <?php echo ($currentPage ?? '') === 'promotion-codes' ? 'active' : ''; ?>">
                                <i class="bi bi-tag"></i>
                                <span>Códigos Promocionais</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-section">
                    <span class="nav-section-title">Pagamentos</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/payment-methods" class="nav-link <?php echo ($currentPage ?? '') === 'payment-methods' ? 'active' : ''; ?>">
                                <i class="bi bi-wallet2"></i>
                                <span>Métodos de Pagamento</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/billing-portal" class="nav-link <?php echo ($currentPage ?? '') === 'billing-portal' ? 'active' : ''; ?>">
                                <i class="bi bi-door-open"></i>
                                <span>Portal de Cobrança</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-section">
                    <span class="nav-section-title">Relatórios</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/reports" class="nav-link <?php echo ($currentPage ?? '') === 'reports' ? 'active' : ''; ?>">
                                <i class="bi bi-graph-up"></i>
                                <span>Relatórios</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                <li class="nav-section">
                    <span class="nav-section-title">Administração</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/users" class="nav-link <?php echo ($currentPage ?? '') === 'users' ? 'active' : ''; ?>">
                                <i class="bi bi-person-gear"></i>
                                <span>Usuários</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/permissions" class="nav-link <?php echo ($currentPage ?? '') === 'permissions' ? 'active' : ''; ?>">
                                <i class="bi bi-shield-check"></i>
                                <span>Permissões</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/audit-logs" class="nav-link <?php echo ($currentPage ?? '') === 'audit-logs' ? 'active' : ''; ?>">
                                <i class="bi bi-journal-text"></i>
                                <span>Logs de Auditoria</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <li class="nav-section">
                    <span class="nav-section-title">Configurações</span>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="/settings" class="nav-link <?php echo ($currentPage ?? '') === 'settings' ? 'active' : ''; ?>">
                                <i class="bi bi-gear"></i>
                                <span>Configurações</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" onclick="logout(); return false;">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Sair</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <?php echo $content ?? ''; ?>
    </main>

    <!-- Bootstrap JS (defer para não bloquear renderização) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <script>
        const API_URL = <?php echo json_encode($apiUrl ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const SESSION_ID = localStorage.getItem('session_id');
        const USER = <?php echo json_encode($user ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const TENANT = <?php echo json_encode($tenant ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Verifica autenticação ao carregar (não bloqueia a renderização)
        document.addEventListener('DOMContentLoaded', () => {
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
        function logout() {
            if (confirm('Deseja realmente sair?')) {
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
        
        // Cache simples no frontend (localStorage)
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
                        if (key.startsWith('api_cache_' + pattern)) {
                            localStorage.removeItem(key);
                        }
                    });
                } catch (e) {}
            }
        };
        
        // Helper para fazer requisições autenticadas com cache
        async function apiRequest(endpoint, options = {}) {
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
                    'Authorization': 'Bearer ' + SESSION_ID,
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
            
            const response = await fetch(API_URL + endpoint, mergedOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || data.error || 'Erro na requisição');
            }
            
            // Salva no cache (apenas para GET bem-sucedido)
            if (useCache && response.ok) {
                cache.set(cacheKey, data, cacheTTL);
            }
            
            return data;
        }
        
        // Helper para debounce
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
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="bi bi-${type === 'danger' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'}-fill"></i> ${message}`;
            container.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        // Helper para formatar moeda
        function formatCurrency(value, currency = 'BRL') {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: currency.toLowerCase()
            }).format(value / 100);
        }
        
        // Helper para formatar data
        function formatDate(timestamp) {
            if (!timestamp) return '-';
            // Se for string de data MySQL (YYYY-MM-DD HH:MM:SS), converte
            if (typeof timestamp === 'string' && timestamp.match(/^\d{4}-\d{2}-\d{2}/)) {
                const date = new Date(timestamp);
                return date.toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            // Se for timestamp Unix (número)
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
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
    </script>
    
    <?php if (isset($scripts)): ?>
        <?php echo $scripts; ?>
    <?php endif; ?>
</body>
</html>

