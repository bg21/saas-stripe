<?php
/**
 * View de Relatórios da Clínica Veterinária
 */
?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/dashboard">Dashboard</a></li>
            <li class="breadcrumb-item active">Relatórios da Clínica</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-graph-up"></i> Relatórios da Clínica</h1>
    </div>

    <div id="alertContainer"></div>

    <!-- Dashboard Rápido -->
    <div class="row mb-4" id="dashboardCards">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-calendar-check fs-1 text-primary"></i>
                    <h3 class="mt-3" id="todayAppointments">-</h3>
                    <p class="text-muted mb-0">Agendamentos Hoje</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-calendar-week fs-1 text-info"></i>
                    <h3 class="mt-3" id="weekAppointments">-</h3>
                    <p class="text-muted mb-0">Agendamentos da Semana</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-percent fs-1 text-success"></i>
                    <h3 class="mt-3" id="occupationRate">-</h3>
                    <p class="text-muted mb-0">Taxa de Ocupação</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="bi bi-clock-history fs-1 text-warning"></i>
                    <h3 class="mt-3" id="upcomingAppointments">-</h3>
                    <p class="text-muted mb-0">Próximos (7 dias)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Período</label>
                    <select class="form-select" id="periodFilter" onchange="loadReports()">
                        <option value="today">Hoje</option>
                        <option value="week">Esta Semana</option>
                        <option value="month" selected>Este Mês</option>
                        <option value="year">Este Ano</option>
                        <option value="last_month">Mês Passado</option>
                        <option value="last_year">Ano Passado</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>
                <div class="col-md-3" id="customDateRange" style="display: none;">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" class="form-control" id="startDateFilter" onchange="loadReports()">
                </div>
                <div class="col-md-3" id="customDateRangeEnd" style="display: none;">
                    <label class="form-label">Data Final</label>
                    <input type="date" class="form-control" id="endDateFilter" onchange="loadReports()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Relatório</label>
                    <select class="form-select" id="reportTypeFilter" onchange="switchReportType()">
                        <option value="dashboard">Dashboard</option>
                        <option value="appointments">Agendamentos</option>
                        <option value="professionals">Profissionais</option>
                        <option value="pets">Pets</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard -->
    <div id="dashboardReport" class="report-section">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Estatísticas da Semana</h5>
                    </div>
                    <div class="card-body">
                        <div id="weekStatsContent">
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Próximos Agendamentos</h5>
                    </div>
                    <div class="card-body">
                        <div id="upcomingAppointmentsContent">
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Relatório de Agendamentos -->
    <div id="appointmentsReport" class="report-section" style="display: none;">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Agendamentos por Status</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="appointmentsByStatusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Agendamentos por Profissional</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="appointmentsByProfessionalChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Agendamentos por Data</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="appointmentsByDateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Resumo de Agendamentos</h5>
            </div>
            <div class="card-body">
                <div id="appointmentsSummaryContent">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Relatório de Profissionais -->
    <div id="professionalsReport" class="report-section" style="display: none;">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Desempenho dos Profissionais</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Profissional</th>
                                <th>CRMV</th>
                                <th>Total de Consultas</th>
                                <th>Concluídas</th>
                                <th>Canceladas</th>
                                <th>Horas Trabalhadas</th>
                                <th>Taxa de Ocupação</th>
                            </tr>
                        </thead>
                        <tbody id="professionalsReportTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
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

    <!-- Relatório de Pets -->
    <div id="petsReport" class="report-section" style="display: none;">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Espécies Mais Atendidas</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="petsBySpeciesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Resumo de Pets</h5>
                    </div>
                    <div class="card-body">
                        <div id="petsSummaryContent">
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let charts = {};
let currentPeriod = 'month';
let currentReportType = 'dashboard';

document.addEventListener('DOMContentLoaded', () => {
    // Verifica se SESSION_ID está disponível
    if (!SESSION_ID) {
        console.error('SESSION_ID não encontrado. Redirecionando para login...');
        window.location.href = '/login';
        return;
    }
    
    document.getElementById('periodFilter').addEventListener('change', function() {
        if (this.value === 'custom') {
            document.getElementById('customDateRange').style.display = 'block';
            document.getElementById('customDateRangeEnd').style.display = 'block';
        } else {
            document.getElementById('customDateRange').style.display = 'none';
            document.getElementById('customDateRangeEnd').style.display = 'none';
        }
        loadReports();
    });
    
    loadReports();
});

function switchReportType() {
    const reportType = document.getElementById('reportTypeFilter').value;
    currentReportType = reportType;
    
    // Esconde todas as seções
    document.querySelectorAll('.report-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Mostra a seção selecionada
    document.getElementById(`${reportType}Report`).style.display = 'block';
    
    loadReports();
}

function loadReports() {
    currentPeriod = document.getElementById('periodFilter').value;
    
    // Carrega dashboard sempre
    loadDashboard();
    
    // Carrega relatório específico
    switch(currentReportType) {
        case 'appointments':
            loadAppointmentsReport();
            break;
        case 'professionals':
            loadProfessionalsReport();
            break;
        case 'pets':
            loadPetsReport();
            break;
    }
}

async function loadDashboard() {
    try {
        const response = await apiRequest('/v1/reports/clinic/dashboard');
        // A resposta vem em response.data conforme ResponseHelper::sendSuccess
        const data = response.data;
        
        // Valida se os dados existem
        if (!data) {
            console.error('Resposta vazia do dashboard:', response);
            showAlert('Erro: Resposta vazia do servidor', 'warning');
            return;
        }
        
        // Debug: log da estrutura da resposta (apenas em desenvolvimento)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('Dashboard response:', response);
            console.log('Dashboard data:', data);
        }
        
        // Atualiza cards com valores padrão se não existirem
        document.getElementById('todayAppointments').textContent = (data.today && data.today.total !== undefined) ? data.today.total : 0;
        document.getElementById('weekAppointments').textContent = (data.week && data.week.stats && data.week.stats.total !== undefined) ? data.week.stats.total : 0;
        document.getElementById('occupationRate').textContent = (data.week && data.week.occupation_rate !== undefined) ? data.week.occupation_rate + '%' : '0%';
        document.getElementById('upcomingAppointments').textContent = (data.upcoming && data.upcoming.total !== undefined) ? data.upcoming.total : 0;
        
        // Estatísticas da semana
        if (data.week) {
            document.getElementById('weekStatsContent').innerHTML = `
                <div class="row">
                    <div class="col-6 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total:</span>
                            <strong>${data.week.stats.total}</strong>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Marcados:</span>
                            <strong>${data.week.stats.scheduled}</strong>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Confirmados:</span>
                            <strong>${data.week.stats.confirmed}</strong>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Concluídos:</span>
                            <strong class="text-success">${data.week.stats.completed}</strong>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Cancelados:</span>
                            <strong class="text-danger">${data.week.stats.cancelled}</strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <span>Taxa de Ocupação:</span>
                            <strong class="text-primary">${data.week.occupation_rate}%</strong>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Próximos agendamentos
        if (data.upcoming && data.upcoming.appointments) {
            const appointments = data.upcoming.appointments;
            if (appointments.length === 0) {
                document.getElementById('upcomingAppointmentsContent').innerHTML = 
                    '<p class="text-muted text-center">Nenhum agendamento nos próximos 7 dias</p>';
            } else {
                document.getElementById('upcomingAppointmentsContent').innerHTML = `
                    <div class="list-group">
                        ${appointments.map(apt => `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">${apt.pet_name || 'N/A'}</h6>
                                        <small class="text-muted">${apt.client_name || 'N/A'} - ${apt.professional_name || 'N/A'}</small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">${formatDate(apt.appointment_date)}</small><br>
                                        <small class="text-muted">${apt.appointment_time}</small>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
        showAlert('Erro ao carregar dashboard: ' + error.message, 'danger');
    }
}

async function loadAppointmentsReport() {
    try {
        const queryParams = buildPeriodQuery();
        const response = await apiRequest(`/v1/reports/clinic/appointments?${queryParams}`);
        // A resposta pode vir em response.data ou diretamente em response
        const data = response.data || response;
        
        // Gráfico por status
        if (charts.appointmentsByStatus) {
            charts.appointmentsByStatus.destroy();
        }
        const statusCtx = document.getElementById('appointmentsByStatusChart').getContext('2d');
        charts.appointmentsByStatus = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data.summary.by_status || {}),
                datasets: [{
                    data: Object.values(data.summary.by_status || {}),
                    backgroundColor: [
                        '#6c757d', // scheduled
                        '#0d6efd', // confirmed
                        '#198754', // completed
                        '#dc3545', // cancelled
                        '#ffc107'  // no_show
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Gráfico por profissional
        if (charts.appointmentsByProfessional) {
            charts.appointmentsByProfessional.destroy();
        }
        const profData = data.by_professional || [];
        const profCtx = document.getElementById('appointmentsByProfessionalChart').getContext('2d');
        charts.appointmentsByProfessional = new Chart(profCtx, {
            type: 'bar',
            data: {
                labels: profData.map(p => p.professional_name),
                datasets: [{
                    label: 'Agendamentos',
                    data: profData.map(p => p.count),
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gráfico por data
        if (charts.appointmentsByDate) {
            charts.appointmentsByDate.destroy();
        }
        const dateData = data.by_date || {};
        const sortedDates = Object.keys(dateData).sort();
        const dateCtx = document.getElementById('appointmentsByDateChart').getContext('2d');
        charts.appointmentsByDate = new Chart(dateCtx, {
            type: 'line',
            data: {
                labels: sortedDates,
                datasets: [{
                    label: 'Agendamentos',
                    data: sortedDates.map(date => dateData[date]),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Resumo
        document.getElementById('appointmentsSummaryContent').innerHTML = `
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h3>${data.summary.total}</h3>
                        <p class="text-muted mb-0">Total de Agendamentos</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 class="text-danger">${data.summary.cancelled}</h3>
                        <p class="text-muted mb-0">Cancelados</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 class="text-warning">${data.summary.cancellation_rate}%</h3>
                        <p class="text-muted mb-0">Taxa de Cancelamento</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h3 class="text-success">${data.summary.by_status.completed || 0}</h3>
                        <p class="text-muted mb-0">Concluídos</p>
                    </div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Erro ao carregar relatório de agendamentos:', error);
        showAlert('Erro ao carregar relatório: ' + error.message, 'danger');
    }
}

async function loadProfessionalsReport() {
    try {
        const queryParams = buildPeriodQuery();
        const response = await apiRequest(`/v1/reports/clinic/professionals?${queryParams}`);
        // A resposta pode vir em response.data ou diretamente em response
        const data = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : []);
        
        const tbody = document.getElementById('professionalsReportTableBody');
        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhum dado disponível</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.map(prof => `
            <tr>
                <td>${prof.professional_name}</td>
                <td>${prof.crmv || '-'}</td>
                <td>${prof.total_appointments}</td>
                <td class="text-success">${prof.completed_appointments}</td>
                <td class="text-danger">${prof.cancelled_appointments}</td>
                <td>${prof.hours_worked}h</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" role="progressbar" style="width: ${prof.occupation_rate}%">
                            ${prof.occupation_rate}%
                        </div>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (error) {
        console.error('Erro ao carregar relatório de profissionais:', error);
        showAlert('Erro ao carregar relatório: ' + error.message, 'danger');
    }
}

async function loadPetsReport() {
    try {
        const queryParams = buildPeriodQuery();
        const response = await apiRequest(`/v1/reports/clinic/pets?${queryParams}`);
        // A resposta pode vir em response.data ou diretamente em response
        const data = response.data || response;
        
        // Gráfico por espécie
        if (charts.petsBySpecies) {
            charts.petsBySpecies.destroy();
        }
        const speciesData = data.by_species || [];
        const speciesCtx = document.getElementById('petsBySpeciesChart').getContext('2d');
        charts.petsBySpecies = new Chart(speciesCtx, {
            type: 'pie',
            data: {
                labels: speciesData.map(s => s.species),
                datasets: [{
                    data: speciesData.map(s => s.count),
                    backgroundColor: [
                        '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d',
                        '#0dcaf0', '#6610f2', '#e83e8c', '#fd7e14', '#20c997'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Resumo
        document.getElementById('petsSummaryContent').innerHTML = `
            <div class="mb-3">
                <h4>${data.summary.unique_pets}</h4>
                <p class="text-muted mb-0">Pets Únicos Atendidos</p>
            </div>
            <div class="mb-3">
                <h4>${data.summary.total_appointments}</h4>
                <p class="text-muted mb-0">Total de Agendamentos</p>
            </div>
            <div class="mb-3">
                <h4>${data.summary.returning_clients}</h4>
                <p class="text-muted mb-0">Clientes com Retorno</p>
            </div>
            <div>
                <h4 class="text-success">${data.summary.return_rate}%</h4>
                <p class="text-muted mb-0">Taxa de Retorno</p>
            </div>
        `;
    } catch (error) {
        console.error('Erro ao carregar relatório de pets:', error);
        showAlert('Erro ao carregar relatório: ' + error.message, 'danger');
    }
}

function buildPeriodQuery() {
    const period = document.getElementById('periodFilter').value;
    const params = new URLSearchParams();
    
    if (period === 'custom') {
        const startDate = document.getElementById('startDateFilter').value;
        const endDate = document.getElementById('endDateFilter').value;
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
    } else {
        params.append('period', period);
    }
    
    return params.toString();
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('pt-BR');
}
</script>

