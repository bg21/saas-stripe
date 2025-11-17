<?php
/**
 * View de Logs de Auditoria
 */
?>
<div class="container-fluid py-4">
    <h1 class="h3 mb-4"><i class="bi bi-journal-text"></i> Logs de Auditoria</h1>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Ação</label>
                    <input type="text" class="form-control" id="actionFilter" placeholder="Ex: create, update, delete">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="userFilter" placeholder="ID do usuário">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Limite</label>
                    <select class="form-select" id="limitFilter">
                        <option value="50">50</option>
                        <option value="100" selected>100</option>
                        <option value="200">200</option>
                        <option value="500">500</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Offset</label>
                    <input type="number" class="form-control" id="offsetFilter" value="0" min="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadAuditLogs()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Logs -->
    <div class="card">
        <div class="card-body">
            <div id="loadingLogs" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="logsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>Ação</th>
                                <th>Recurso</th>
                                <th>IP</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-journal-text fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum log encontrado</p>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button class="btn btn-outline-secondary" id="prevPage" onclick="previousPage()" disabled>
                        <i class="bi bi-arrow-left"></i> Anterior
                    </button>
                    <span id="pageInfo" class="text-muted"></span>
                    <button class="btn btn-outline-secondary" id="nextPage" onclick="nextPage()" disabled>
                        Próxima <i class="bi bi-arrow-right"></i>
                    </button>
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
        </div>
    </div>
</div>

<script>
let logs = [];
let currentOffset = 0;
let currentLimit = 100;

document.addEventListener('DOMContentLoaded', () => {
    // Carrega dados após um pequeno delay para não bloquear a renderização
    setTimeout(() => {
        loadAuditLogs();
    }, 100);
});

async function loadAuditLogs() {
    try {
        document.getElementById('loadingLogs').style.display = 'block';
        document.getElementById('logsList').style.display = 'none';
        
        const params = new URLSearchParams();
        const action = document.getElementById('actionFilter')?.value;
        const userId = document.getElementById('userFilter')?.value;
        currentLimit = parseInt(document.getElementById('limitFilter')?.value || 100);
        currentOffset = parseInt(document.getElementById('offsetFilter')?.value || 0);
        
        if (action) params.append('action', action);
        if (userId) params.append('user_id', userId);
        params.append('limit', currentLimit);
        params.append('offset', currentOffset);
        
        const url = '/v1/audit-logs' + (params.toString() ? '?' + params.toString() : '');
        const response = await apiRequest(url);
        logs = response.data || [];
        
        renderLogs();
        updatePagination();
    } catch (error) {
        showAlert('Erro ao carregar logs: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingLogs').style.display = 'none';
        document.getElementById('logsList').style.display = 'block';
    }
}

function renderLogs() {
    const tbody = document.getElementById('logsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (logs.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = logs.map(log => {
        const actionBadge = {
            'create': 'bg-success',
            'update': 'bg-primary',
            'delete': 'bg-danger',
            'read': 'bg-info',
            'login': 'bg-warning',
            'logout': 'bg-secondary'
        }[log.action] || 'bg-secondary';
        
        return `
            <tr>
                <td>${log.id}</td>
                <td>${formatDate(log.created_at)}</td>
                <td>${log.user_id || '-'}</td>
                <td><span class="badge ${actionBadge}">${log.action}</span></td>
                <td><code class="text-muted">${log.resource_type || '-'}</code></td>
                <td><small>${log.ip_address || '-'}</small></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewLogDetails(${log.id})">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function updatePagination() {
    const hasMore = logs.length === currentLimit;
    document.getElementById('prevPage').disabled = currentOffset === 0;
    document.getElementById('nextPage').disabled = !hasMore;
    document.getElementById('pageInfo').textContent = 
        `Mostrando ${currentOffset + 1} - ${currentOffset + logs.length}`;
}

function previousPage() {
    if (currentOffset > 0) {
        currentOffset = Math.max(0, currentOffset - currentLimit);
        document.getElementById('offsetFilter').value = currentOffset;
        loadAuditLogs();
    }
}

function nextPage() {
    if (logs.length === currentLimit) {
        currentOffset += currentLimit;
        document.getElementById('offsetFilter').value = currentOffset;
        loadAuditLogs();
    }
}

async function viewLogDetails(logId) {
    const modal = new bootstrap.Modal(document.getElementById('logDetailsModal'));
    const content = document.getElementById('logDetailsContent');
    
    content.innerHTML = '<div class="text-center py-5"><div class="spinner-border"></div></div>';
    modal.show();
    
    try {
        const log = await apiRequest(`/v1/audit-logs/${logId}`);
        const data = log.data;
        
        content.innerHTML = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>ID:</strong> ${data.id}
                </div>
                <div class="col-md-6">
                    <strong>Data/Hora:</strong> ${formatDate(data.created_at)}
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Usuário ID:</strong> ${data.user_id || '-'}
                </div>
                <div class="col-md-6">
                    <strong>IP:</strong> ${data.ip_address || '-'}
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Ação:</strong> <span class="badge bg-primary">${data.action}</span>
                </div>
                <div class="col-md-6">
                    <strong>Recurso:</strong> <code>${data.resource_type || '-'}</code>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-12">
                    <strong>Resource ID:</strong> ${data.resource_id || '-'}
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <strong>Dados:</strong>
                    <pre class="bg-light p-3 rounded mt-2" style="max-height: 300px; overflow-y: auto;">${JSON.stringify(data.data || {}, null, 2)}</pre>
                </div>
            </div>
        `;
    } catch (error) {
        content.innerHTML = `<div class="alert alert-danger">Erro ao carregar detalhes: ${error.message}</div>`;
    }
}
</script>

