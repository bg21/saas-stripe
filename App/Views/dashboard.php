<?php
/**
 * View de Dashboard
 */
?>
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-speedometer2 text-primary"></i>
                Dashboard
            </h1>
            <p class="text-muted mb-0">
                Bem-vindo, <span id="userName"><?php echo htmlspecialchars($user['name'] ?? 'Usuário', ENT_QUOTES, 'UTF-8'); ?></span>
            </p>
        </div>
        <button class="btn btn-outline-primary" onclick="loadDashboardData()">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-credit-card fs-1 text-primary"></i>
                    <h3 class="mt-3" id="activeSubscriptions">-</h3>
                    <p class="text-muted mb-0">Assinaturas Ativas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-people fs-1 text-success"></i>
                    <h3 class="mt-3" id="totalCustomers">-</h3>
                    <p class="text-muted mb-0">Total de Clientes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-cash-stack fs-1 text-info"></i>
                    <h3 class="mt-3" id="monthlyRevenue">-</h3>
                    <p class="text-muted mb-0">Receita Mensal</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-exclamation-triangle fs-1 text-warning"></i>
                    <h3 class="mt-3" id="pendingItems">-</h3>
                    <p class="text-muted mb-0">Pendências</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscriptions Section -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-credit-card me-2"></i>
                Minhas Assinaturas
            </h5>
            <button class="btn btn-primary btn-sm" onclick="loadSubscriptions()">
                <i class="bi bi-arrow-clockwise me-2"></i>
                Atualizar
            </button>
        </div>
        <div class="card-body">
            <!-- Loading State -->
            <div id="loadingState" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Carregando assinaturas...</p>
            </div>

            <!-- Subscriptions Container -->
            <div id="subscriptionsContainer"></div>

            <!-- Empty State -->
            <div id="emptyState" class="text-center py-5" style="display: none;">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <h5 class="mt-3">Nenhuma assinatura encontrada</h5>
                <p class="text-muted">Você ainda não possui assinaturas ativas.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados imediatamente
    loadDashboardData();
});

// Função para carregar dados do dashboard
async function loadDashboardData() {
    await Promise.all([
        loadSubscriptions(),
        loadStats()
    ]);
}

// Carrega assinaturas
async function loadSubscriptions() {
    const container = document.getElementById('subscriptionsContainer');
    const loading = document.getElementById('loadingState');
    const empty = document.getElementById('emptyState');
    
    container.innerHTML = '';
    loading.style.display = 'block';
    empty.style.display = 'none';
    
    try {
        const response = await apiRequest('/v1/subscriptions');
        loading.style.display = 'none';
        
        if (!response.data || response.data.length === 0) {
            empty.style.display = 'block';
            return;
        }
        
        // Renderiza assinaturas
        container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Cliente</th>
                            <th>Valor</th>
                            <th>Próximo Pagamento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${response.data.map(sub => `
                            <tr>
                                <td><code>${sub.id}</code></td>
                                <td><span class="badge bg-${sub.status === 'active' ? 'success' : 'secondary'}">${sub.status}</span></td>
                                <td>${sub.customer_id || '-'}</td>
                                <td>${sub.amount ? formatCurrency(sub.amount, sub.currency || 'BRL') : '-'}</td>
                                <td>${sub.current_period_end ? formatDate(sub.current_period_end) : '-'}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSubscription(${sub.id})">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        loading.style.display = 'none';
        showAlert('Erro ao carregar assinaturas: ' + error.message, 'danger');
    }
}

// Carrega estatísticas
async function loadStats() {
    try {
        const response = await apiRequest('/v1/stats');
        if (response.data) {
            document.getElementById('activeSubscriptions').textContent = response.data.subscriptions?.active || 0;
            document.getElementById('totalCustomers').textContent = response.data.customers?.total || 0;
            document.getElementById('monthlyRevenue').textContent = formatCurrency(response.data.mrr || 0, response.data.revenue?.currency || 'BRL');
            document.getElementById('pendingItems').textContent = response.data.subscriptions?.past_due || 0;
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

function viewSubscription(id) {
    window.location.href = `/subscriptions?view=${id}`;
}
</script>
