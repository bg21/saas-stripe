<?php
/**
 * View de Relatórios e Estatísticas
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-graph-up"></i> Relatórios e Estatísticas</h1>

    <div id="alertContainer"></div>

    <!-- Filtros de Período -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Período</label>
                    <select class="form-select" id="periodFilter" onchange="loadStats()">
                        <option value="today">Hoje</option>
                        <option value="week">Esta Semana</option>
                        <option value="month" selected>Este Mês</option>
                        <option value="year">Este Ano</option>
                        <option value="all">Todos</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-people fs-1 text-primary"></i>
                    <h3 class="mt-3" id="totalCustomers">-</h3>
                    <p class="text-muted mb-0">Total de Clientes</p>
                    <small class="text-success" id="newCustomers">+0 novos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-credit-card fs-1 text-success"></i>
                    <h3 class="mt-3" id="totalSubscriptions">-</h3>
                    <p class="text-muted mb-0">Assinaturas Ativas</p>
                    <small class="text-success" id="newSubscriptions">+0 novas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-cash-coin fs-1 text-warning"></i>
                    <h3 class="mt-3" id="totalRevenue">-</h3>
                    <p class="text-muted mb-0">Receita Total</p>
                    <small class="text-success" id="periodRevenue">R$ 0,00 no período</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-graph-up-arrow fs-1 text-info"></i>
                    <h3 class="mt-3" id="mrr">-</h3>
                    <p class="text-muted mb-0">MRR (Receita Recorrente Mensal)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos e Tabelas -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Assinaturas por Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="subscriptionsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Receita por Período</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Top Clientes -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Top Clientes</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Assinaturas</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody id="topCustomersTable">
                        <tr>
                            <td colspan="4" class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let subscriptionsChart = null;
let revenueChart = null;

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados após um pequeno delay para não bloquear a renderização
    setTimeout(() => {
        loadStats();
        loadTopCustomers();
    }, 100);
});

async function loadStats() {
    try {
        const period = document.getElementById('periodFilter').value;
        const response = await apiRequest(`/v1/stats?period=${period}`);
        const stats = response.data || {};
        
        // Atualiza cards
        document.getElementById('totalCustomers').textContent = stats.customers?.total || 0;
        document.getElementById('newCustomers').textContent = `+${stats.customers?.new || 0} novos`;
        
        document.getElementById('totalSubscriptions').textContent = stats.subscriptions?.active || 0;
        document.getElementById('newSubscriptions').textContent = `+${stats.subscriptions?.new || 0} novas`;
        
        const revenue = stats.revenue?.total || 0;
        document.getElementById('totalRevenue').textContent = formatCurrency(revenue, stats.revenue?.currency || 'BRL');
        document.getElementById('periodRevenue').textContent = formatCurrency(stats.revenue?.period || 0, stats.revenue?.currency || 'BRL') + ' no período';
        
        document.getElementById('mrr').textContent = formatCurrency(stats.mrr || 0, stats.revenue?.currency || 'BRL');
        
        // Atualiza gráficos
        updateCharts(stats);
    } catch (error) {
        showAlert('Erro ao carregar estatísticas: ' + error.message, 'danger');
    }
}

function updateCharts(stats) {
    // Gráfico de Assinaturas por Status
    const subscriptionsCtx = document.getElementById('subscriptionsChart');
    if (subscriptionsChart) {
        subscriptionsChart.destroy();
    }
    
    subscriptionsChart = new Chart(subscriptionsCtx, {
        type: 'doughnut',
        data: {
            labels: ['Ativas', 'Canceladas', 'Em Trial', 'Vencidas'],
            datasets: [{
                data: [
                    stats.subscriptions?.active || 0,
                    stats.subscriptions?.canceled || 0,
                    stats.subscriptions?.trialing || 0,
                    stats.subscriptions?.past_due || 0
                ],
                backgroundColor: [
                    '#28a745',
                    '#dc3545',
                    '#17a2b8',
                    '#ffc107'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
    
    // Gráfico de Receita (simplificado - você pode melhorar com dados históricos)
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueChart) {
        revenueChart.destroy();
    }
    
    revenueChart = new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: ['Receita Total', 'MRR', 'Receita do Período'],
            datasets: [{
                label: 'Valores',
                data: [
                    (stats.revenue?.total || 0) / 100,
                    (stats.mrr || 0) / 100,
                    (stats.revenue?.period || 0) / 100
                ],
                backgroundColor: ['#007bff', '#28a745', '#ffc107']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

async function loadTopCustomers() {
    try {
        const customersResponse = await apiRequest('/v1/customers');
        const customers = customersResponse.data || [];
        
        // Ordena por número de assinaturas
        const topCustomers = customers
            .sort((a, b) => (b.subscriptions_count || 0) - (a.subscriptions_count || 0))
            .slice(0, 10);
        
        const tbody = document.getElementById('topCustomersTable');
        if (topCustomers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nenhum cliente encontrado</td></tr>';
            return;
        }
        
        tbody.innerHTML = topCustomers.map(customer => `
            <tr>
                <td>${customer.name || 'Sem nome'}</td>
                <td>${customer.email}</td>
                <td><span class="badge bg-info">${customer.subscriptions_count || 0}</span></td>
                <td>-</td>
            </tr>
        `).join('');
    } catch (error) {
        document.getElementById('topCustomersTable').innerHTML = 
            '<tr><td colspan="4" class="text-center text-danger">Erro ao carregar clientes</td></tr>';
    }
}
</script>

