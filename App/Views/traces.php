<?php
/**
 * View de Tracing de Requisições
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-diagram-3"></i> Tracing de Requisições</h1>

    <div id="alertContainer"></div>

    <!-- Tabs para diferentes tipos de busca -->
    <ul class="nav nav-tabs mb-4" id="traceTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="requestId-tab" data-bs-toggle="tab" data-bs-target="#requestId" type="button" role="tab">
                <i class="bi bi-fingerprint"></i> Por Request ID
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="timeRange-tab" data-bs-toggle="tab" data-bs-target="#timeRange" type="button" role="tab">
                <i class="bi bi-calendar-range"></i> Por Intervalo de Tempo
            </button>
        </li>
    </ul>

    <div class="tab-content" id="traceTabsContent">
        <!-- Busca por Request ID -->
        <div class="tab-pane fade show active" id="requestId" role="tabpanel">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Buscar Trace por Request ID</h5>
                    <p class="text-muted small">
                        Digite o Request ID (32 caracteres hexadecimais) para visualizar todos os logs relacionados a uma requisição.
                        O Request ID pode ser encontrado no header <code>X-Request-ID</code> das respostas da API.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Request ID</label>
                            <input type="text" class="form-control" id="requestIdInput" 
                                   placeholder="Ex: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6" 
                                   maxlength="32" pattern="[a-fA-F0-9]{32}">
                            <div class="form-text">
                                Formato: 32 caracteres hexadecimais (0-9, a-f)
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-primary w-100" onclick="loadTrace()">
                                <i class="bi bi-search"></i> Buscar Trace
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Busca por Intervalo de Tempo -->
        <div class="tab-pane fade" id="timeRange" role="tabpanel">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Buscar Traces por Intervalo de Tempo</h5>
                    <p class="text-muted small">
                        Busque todos os logs (auditoria e aplicação) em um intervalo de tempo específico.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Data/Hora Inicial</label>
                            <input type="datetime-local" class="form-control" id="startDateInput" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data/Hora Final</label>
                            <input type="datetime-local" class="form-control" id="endDateInput" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nível do Log</label>
                            <select class="form-select" id="levelFilter">
                                <option value="">Todos</option>
                                <option value="DEBUG">DEBUG</option>
                                <option value="INFO">INFO</option>
                                <option value="WARNING">WARNING</option>
                                <option value="ERROR">ERROR</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Request ID (opcional)</label>
                            <input type="text" class="form-control" id="requestIdFilter" 
                                   placeholder="Filtrar por request_id" maxlength="32">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" onclick="searchByTimeRange()">
                                <i class="bi bi-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <button class="btn btn-sm btn-outline-secondary" onclick="setQuickRange('lastHour')">Última Hora</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setQuickRange('lastDay')">Últimas 24h</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="setQuickRange('lastWeek')">Última Semana</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultado do Trace -->
    <div id="traceResult" style="display: none;">
        <!-- Resumo -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Resumo do Trace</h5>
            </div>
            <div class="card-body">
                <div class="row" id="traceSummary">
                    <!-- Preenchido via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="card mb-4" id="timelineCard" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Timeline de Eventos</h5>
            </div>
            <div class="card-body">
                <div id="timelineContent">
                    <!-- Preenchido via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Lista de Logs -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Logs</h5>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleView('table')" id="viewTableBtn">
                        <i class="bi bi-table"></i> Tabela
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleView('timeline')" id="viewTimelineBtn">
                        <i class="bi bi-clock-history"></i> Timeline
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="loadingTrace" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
                <div id="traceLogs" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Timestamp</th>
                                    <th>Tipo</th>
                                    <th>Endpoint/Message</th>
                                    <th>Método/Nível</th>
                                    <th>Status</th>
                                    <th>Tempo (ms)</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="traceLogsTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="timelineView" style="display: none;">
                    <div id="timelineViewContent">
                        <!-- Preenchido via JavaScript -->
                    </div>
                </div>
                <div id="emptyTrace" class="text-center py-5" style="display: none;">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum log encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes do Log -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRequestId = null;

function loadTrace() {
    const requestId = document.getElementById('requestIdInput').value.trim();
    
    if (!requestId) {
        showAlert('Por favor, informe o Request ID', 'warning');
        return;
    }
    
    // Valida formato (32 caracteres hexadecimais)
    if (!/^[a-fA-F0-9]{32}$/.test(requestId)) {
        showAlert('Request ID inválido. Deve ter 32 caracteres hexadecimais (0-9, a-f)', 'danger');
        return;
    }
    
    currentRequestId = requestId;
    
    // Mostra loading
    document.getElementById('traceResult').style.display = 'block';
    document.getElementById('loadingTrace').style.display = 'block';
    document.getElementById('traceLogs').style.display = 'none';
    document.getElementById('emptyTrace').style.display = 'none';
    
    // Busca trace
    fetch(`/v1/traces/${requestId}`, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Erro ao buscar trace');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.data) {
            displayTrace(data.data);
        } else {
            throw new Error('Resposta inválida do servidor');
        }
    })
    .catch(error => {
        console.error('Erro ao buscar trace:', error);
        showAlert('Erro ao buscar trace: ' + error.message, 'danger');
        document.getElementById('emptyTrace').style.display = 'block';
        document.getElementById('loadingTrace').style.display = 'none';
    });
}

let currentView = 'table';

function displayTrace(trace) {
    // Exibe resumo
    const summary = trace.summary || {};
    const summaryHtml = `
        <div class="col-md-2">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total de Logs</h6>
                    <h3 class="mb-0">${trace.total_logs || 0}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Audit Logs</h6>
                    <h3 class="mb-0">${trace.audit_logs_count || 0}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">App Logs</h6>
                    <h3 class="mb-0">${trace.application_logs_count || 0}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Tempo Médio</h6>
                    <h3 class="mb-0">${summary.average_response_time || 0}ms</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Primeiro Evento</h6>
                    <small class="text-muted">${summary.first_event ? formatDateTime(summary.first_event.timestamp) : 'N/A'}</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Último Evento</h6>
                    <small class="text-muted">${summary.last_event ? formatDateTime(summary.last_event.timestamp) : 'N/A'}</small>
                </div>
            </div>
        </div>
    `;
    document.getElementById('traceSummary').innerHTML = summaryHtml;
    
    // Exibe timeline se disponível
    if (trace.timeline && Object.keys(trace.timeline).length > 0) {
        displayTimeline(trace.timeline);
        document.getElementById('timelineCard').style.display = 'block';
    } else {
        document.getElementById('timelineCard').style.display = 'none';
    }
    
    // Exibe logs
    displayLogs(trace.logs || []);
    
    document.getElementById('loadingTrace').style.display = 'none';
}

function displayLogs(logs) {
    if (logs.length === 0) {
        document.getElementById('emptyTrace').style.display = 'block';
        document.getElementById('traceLogs').style.display = 'none';
        document.getElementById('timelineView').style.display = 'none';
        return;
    }
    
    if (currentView === 'table') {
        displayLogsTable(logs);
    } else {
        displayLogsTimeline(logs);
    }
}

function displayLogsTable(logs) {
    const tbody = document.getElementById('traceLogsTableBody');
    tbody.innerHTML = '';
    
    logs.forEach((log, index) => {
        const row = document.createElement('tr');
        const logType = log.log_type || (log.endpoint ? 'audit' : 'application');
        const typeBadge = logType === 'audit' 
            ? '<span class="badge bg-primary">Audit</span>'
            : '<span class="badge bg-info">App</span>';
        
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${formatDateTime(log.created_at)}</td>
            <td>${typeBadge}</td>
            <td><code>${log.endpoint || log.message || 'N/A'}</code></td>
            <td>${log.method ? `<span class="badge bg-secondary">${log.method}</span>` : (log.level ? `<span class="badge bg-${getLevelBadgeColor(log.level)}">${log.level}</span>` : 'N/A')}</td>
            <td>${log.response_status ? getStatusBadge(log.response_status) : '-'}</td>
            <td>${log.response_time || '-'}</td>
            <td>
                ${log.id ? `<button class="btn btn-sm btn-outline-primary" onclick="showLogDetails('${logType}', ${log.id})">
                    <i class="bi bi-eye"></i> Detalhes
                </button>` : '-'}
            </td>
        `;
        tbody.appendChild(row);
    });
    
    document.getElementById('traceLogs').style.display = 'block';
    document.getElementById('timelineView').style.display = 'none';
    document.getElementById('emptyTrace').style.display = 'none';
}

function displayLogsTimeline(logs) {
    const content = document.getElementById('timelineViewContent');
    content.innerHTML = '';
    
    // Agrupa por data
    const groupedByDate = {};
    logs.forEach(log => {
        const date = log.created_at ? log.created_at.split(' ')[0] : 'unknown';
        if (!groupedByDate[date]) {
            groupedByDate[date] = [];
        }
        groupedByDate[date].push(log);
    });
    
    // Cria timeline visual
    Object.keys(groupedByDate).sort().reverse().forEach(date => {
        const dateLogs = groupedByDate[date];
        const dateCard = document.createElement('div');
        dateCard.className = 'mb-4';
        dateCard.innerHTML = `
            <h6 class="text-muted mb-3"><i class="bi bi-calendar"></i> ${formatDate(date)}</h6>
            <div class="timeline">
        `;
        
        dateLogs.forEach((log, index) => {
            const logType = log.log_type || (log.endpoint ? 'audit' : 'application');
            const time = log.created_at ? log.created_at.split(' ')[1] : '00:00:00';
            const message = log.endpoint 
                ? `${log.method || 'UNKNOWN'} ${log.endpoint} - ${log.response_status || 'N/A'}`
                : (log.message || 'Sem mensagem');
            
            const timelineItem = document.createElement('div');
            timelineItem.className = 'timeline-item mb-3';
            timelineItem.innerHTML = `
                <div class="d-flex">
                    <div class="timeline-marker me-3">
                        <div class="badge bg-${logType === 'audit' ? 'primary' : 'info'} rounded-circle" style="width: 12px; height: 12px;"></div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="card">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-muted">${time}</strong>
                                        <span class="badge bg-${logType === 'audit' ? 'primary' : 'info'} ms-2">${logType === 'audit' ? 'Audit' : 'App'}</span>
                                        ${log.level ? `<span class="badge bg-${getLevelBadgeColor(log.level)} ms-1">${log.level}</span>` : ''}
                                        <p class="mb-0 mt-1">${message}</p>
                                    </div>
                                    ${log.id ? `<button class="btn btn-sm btn-outline-primary" onclick="showLogDetails('${logType}', ${log.id})">
                                        <i class="bi bi-eye"></i>
                                    </button>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            dateCard.querySelector('.timeline').appendChild(timelineItem);
        });
        
        dateCard.innerHTML += '</div>';
        content.appendChild(dateCard);
    });
    
    document.getElementById('traceLogs').style.display = 'none';
    document.getElementById('timelineView').style.display = 'block';
    document.getElementById('emptyTrace').style.display = 'none';
}

function displayTimeline(timeline) {
    const content = document.getElementById('timelineContent');
    content.innerHTML = '';
    
    Object.keys(timeline).sort().reverse().forEach(date => {
        const events = timeline[date];
        const dateCard = document.createElement('div');
        dateCard.className = 'mb-3';
        dateCard.innerHTML = `
            <h6 class="text-muted mb-2"><i class="bi bi-calendar"></i> ${formatDate(date)}</h6>
        `;
        
        events.forEach(event => {
            const eventDiv = document.createElement('div');
            eventDiv.className = 'mb-2 ps-3 border-start border-2 border-' + (event.type === 'audit' ? 'primary' : 'info');
            eventDiv.innerHTML = `
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>${event.time}</strong>
                        <span class="badge bg-${event.type === 'audit' ? 'primary' : 'info'} ms-2">${event.type === 'audit' ? 'Audit' : 'App'}</span>
                        ${event.level ? `<span class="badge bg-${getLevelBadgeColor(event.level)} ms-1">${event.level}</span>` : ''}
                        <p class="mb-0 mt-1 small">${event.message}</p>
                    </div>
                </div>
            `;
            dateCard.appendChild(eventDiv);
        });
        
        content.appendChild(dateCard);
    });
}

function toggleView(view) {
    currentView = view;
    
    if (view === 'table') {
        document.getElementById('viewTableBtn').classList.add('active');
        document.getElementById('viewTimelineBtn').classList.remove('active');
    } else {
        document.getElementById('viewTableBtn').classList.remove('active');
        document.getElementById('viewTimelineBtn').classList.add('active');
    }
    
    // Recarrega visualização se houver dados
    const traceResult = document.getElementById('traceResult');
    if (traceResult.style.display === 'block') {
        const logs = Array.from(document.querySelectorAll('#traceLogsTableBody tr')).map(row => {
            // Extrai dados do log da linha (simplificado)
            return { created_at: row.cells[1]?.textContent || '' };
        });
        if (logs.length > 0) {
            // Se já temos logs carregados, apenas alterna a visualização
            // Em uma implementação completa, manteríamos os dados em memória
        }
    }
}

function searchByTimeRange() {
    const startDate = document.getElementById('startDateInput').value;
    const endDate = document.getElementById('endDateInput').value;
    const level = document.getElementById('levelFilter').value;
    const requestId = document.getElementById('requestIdFilter').value.trim();
    
    if (!startDate || !endDate) {
        showAlert('Por favor, informe data inicial e final', 'warning');
        return;
    }
    
    // Converte datetime-local para formato Y-m-d H:i:s
    const startDateTime = new Date(startDate).toISOString().slice(0, 19).replace('T', ' ');
    const endDateTime = new Date(endDate).toISOString().slice(0, 19).replace('T', ' ');
    
    // Mostra loading
    document.getElementById('traceResult').style.display = 'block';
    document.getElementById('loadingTrace').style.display = 'block';
    document.getElementById('traceLogs').style.display = 'none';
    document.getElementById('timelineView').style.display = 'none';
    document.getElementById('emptyTrace').style.display = 'none';
    
    // Monta query string
    const params = new URLSearchParams({
        start_date: startDateTime,
        end_date: endDateTime
    });
    
    if (level) {
        params.append('level', level);
    }
    
    if (requestId && /^[a-fA-F0-9]{32}$/.test(requestId)) {
        params.append('request_id', requestId);
    }
    
    // Busca traces
    fetch(`/v1/traces/search?${params.toString()}`, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || 'Erro ao buscar traces');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.data) {
            displayTraceSearch(data.data);
        } else {
            throw new Error('Resposta inválida do servidor');
        }
    })
    .catch(error => {
        console.error('Erro ao buscar traces:', error);
        showAlert('Erro ao buscar traces: ' + error.message, 'danger');
        document.getElementById('emptyTrace').style.display = 'block';
        document.getElementById('loadingTrace').style.display = 'none';
    });
}

function displayTraceSearch(data) {
    // Cria estrutura similar ao trace
    const trace = {
        request_id: data.request_id || 'N/A',
        total_logs: data.total_logs || 0,
        audit_logs_count: data.total_audit_logs || 0,
        application_logs_count: data.total_application_logs || 0,
        logs: data.logs || [],
        timeline: data.timeline || {},
        summary: {
            total_audit_logs: data.total_audit_logs || 0,
            total_application_logs: data.total_application_logs || 0,
            total_logs: data.total_logs || 0,
            first_event: data.logs && data.logs.length > 0 ? {
                timestamp: data.logs[0].created_at,
                message: data.logs[0].endpoint || data.logs[0].message
            } : null,
            last_event: data.logs && data.logs.length > 0 ? {
                timestamp: data.logs[data.logs.length - 1].created_at,
                message: data.logs[data.logs.length - 1].endpoint || data.logs[data.logs.length - 1].message
            } : null
        }
    };
    
    displayTrace(trace);
}

function setQuickRange(range) {
    const now = new Date();
    const endDate = new Date(now);
    let startDate = new Date(now);
    
    switch(range) {
        case 'lastHour':
            startDate.setHours(startDate.getHours() - 1);
            break;
        case 'lastDay':
            startDate.setDate(startDate.getDate() - 1);
            break;
        case 'lastWeek':
            startDate.setDate(startDate.getDate() - 7);
            break;
    }
    
    // Formata para datetime-local (YYYY-MM-DDTHH:mm)
    document.getElementById('startDateInput').value = formatDateTimeLocal(startDate);
    document.getElementById('endDateInput').value = formatDateTimeLocal(endDate);
}

function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function getLevelBadgeColor(level) {
    const colors = {
        'DEBUG': 'secondary',
        'INFO': 'info',
        'WARNING': 'warning',
        'ERROR': 'danger',
        'CRITICAL': 'danger'
    };
    return colors[level] || 'secondary';
}

function getStatusBadge(status) {
    if (status >= 200 && status < 300) {
        return `<span class="badge bg-success">${status}</span>`;
    } else if (status >= 300 && status < 400) {
        return `<span class="badge bg-info">${status}</span>`;
    } else if (status >= 400 && status < 500) {
        return `<span class="badge bg-warning">${status}</span>`;
    } else if (status >= 500) {
        return `<span class="badge bg-danger">${status}</span>`;
    }
    return `<span class="badge bg-secondary">${status || 'N/A'}</span>`;
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function showLogDetails(logType, logId) {
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    const content = document.getElementById('logDetailsContent');
    
    content.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status"></div></div>';
    modal.show();
    
    // Busca detalhes do log (endpoint diferente para application logs)
    const endpoint = logType === 'application' 
        ? `/v1/application-logs/${logId}` // Será implementado se necessário
        : `/v1/audit-logs/${logId}`;
    
    fetch(endpoint, {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${getAuthToken()}`,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok && logType === 'application') {
            // Se endpoint não existir, mostra dados do log atual
            return null;
        }
        return response.json();
    })
    .then(data => {
        if (data && data.success && data.data) {
            const log = data.data;
            content.innerHTML = formatLogDetails(log, logType);
        } else if (logType === 'application') {
            // Para application logs, mostra informações básicas
            content.innerHTML = `
                <div class="alert alert-info">
                    <p>Detalhes completos de application logs serão exibidos aqui.</p>
                    <p>ID do log: ${logId}</p>
                </div>
            `;
        } else {
            content.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do log</div>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar detalhes:', error);
        if (logType === 'application') {
            content.innerHTML = `
                <div class="alert alert-info">
                    <p>Log de aplicação (ID: ${logId})</p>
                    <p>Detalhes completos disponíveis na tabela application_logs.</p>
                </div>
            `;
        } else {
            content.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do log</div>';
        }
    });
}

function formatLogDetails(log, logType) {
    if (logType === 'application') {
        return `
            <div class="mb-3">
                <strong>ID:</strong> ${log.id}<br>
                <strong>Request ID:</strong> <code>${log.request_id || 'N/A'}</code><br>
                <strong>Timestamp:</strong> ${formatDateTime(log.created_at)}<br>
                <strong>Nível:</strong> <span class="badge bg-${getLevelBadgeColor(log.level)}">${log.level || 'N/A'}</span><br>
                <strong>Channel:</strong> ${log.channel || 'N/A'}<br>
                <strong>Mensagem:</strong> ${log.message || 'N/A'}<br>
            </div>
            ${log.context ? `
                <div class="mb-3">
                    <strong>Contexto:</strong>
                    <pre class="bg-light p-3 rounded"><code>${JSON.stringify(JSON.parse(log.context || '{}'), null, 2)}</code></pre>
                </div>
            ` : ''}
        `;
    } else {
        return `
            <div class="mb-3">
                <strong>ID:</strong> ${log.id}<br>
                <strong>Request ID:</strong> <code>${log.request_id || 'N/A'}</code><br>
                <strong>Timestamp:</strong> ${formatDateTime(log.created_at)}<br>
                <strong>Endpoint:</strong> <code>${log.endpoint || 'N/A'}</code><br>
                <strong>Método:</strong> ${log.method || 'N/A'}<br>
                <strong>Status:</strong> ${getStatusBadge(log.response_status)}<br>
                <strong>Tempo de Resposta:</strong> ${log.response_time || 0}ms<br>
                <strong>IP:</strong> ${log.ip_address || 'N/A'}<br>
                <strong>User Agent:</strong> <small>${log.user_agent || 'N/A'}</small>
            </div>
            ${log.request_body ? `
                <div class="mb-3">
                    <strong>Request Body:</strong>
                    <pre class="bg-light p-3 rounded"><code>${JSON.stringify(JSON.parse(log.request_body || '{}'), null, 2)}</code></pre>
                </div>
            ` : ''}
        `;
    }
}

function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.appendChild(alert);
    
    // Remove após 5 segundos
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Permite buscar com Enter
document.getElementById('requestIdInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        loadTrace();
    }
});
</script>

