<?php
/**
 * View de Gerenciamento de Profissionais
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-person-badge"></i> Profissionais</h1>
        <?php if (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'editor'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProfessionalModal">
            <i class="bi bi-plus-circle"></i> Novo Profissional
        </button>
        <?php endif; ?>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome, CRMV, especialidade...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="sortFilter">
                        <option value="created_at">Data de Criação</option>
                        <option value="name">Nome</option>
                        <option value="crmv">CRMV</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadProfessionals(true)">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Profissionais -->
    <div class="card">
        <div class="card-body">
            <div id="loadingProfessionals" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="professionalsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>CRMV</th>
                                <th>Especialidades</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="professionalsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-person-badge fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum profissional encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let professionals = [];
let searchTimeout = null;

document.addEventListener('DOMContentLoaded', () => {
    loadProfessionals();
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadProfessionals(true);
            }, 500);
        });
    }
    
    const statusFilter = document.getElementById('statusFilter');
    const sortFilter = document.getElementById('sortFilter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', () => loadProfessionals(true));
    }
    if (sortFilter) {
        sortFilter.addEventListener('change', () => loadProfessionals(true));
    }
});

async function loadProfessionals(skipCache = false) {
    try {
        document.getElementById('loadingProfessionals').style.display = 'block';
        document.getElementById('professionalsList').style.display = 'none';
        
        if (skipCache) {
            if (typeof cache !== 'undefined' && cache.clear) {
                cache.clear('/v1/professionals');
            }
        }
        
        const params = new URLSearchParams();
        
        const search = document.getElementById('searchInput')?.value.trim();
        if (search) {
            params.append('search', search);
        }
        
        const statusFilter = document.getElementById('statusFilter')?.value;
        if (statusFilter) {
            params.append('status', statusFilter);
        }
        
        const sortFilter = document.getElementById('sortFilter')?.value;
        if (sortFilter) {
            params.append('sort', sortFilter);
        }
        
        const queryString = params.toString();
        const url = queryString ? `/v1/professionals?${queryString}` : '/v1/professionals';
        const response = await apiRequest(url, {
            skipCache: skipCache,
            cacheTTL: 10000
        });
        
        if (response.data && response.data.professionals && Array.isArray(response.data.professionals)) {
            professionals = response.data.professionals;
        } else if (Array.isArray(response.data)) {
            professionals = response.data;
        } else {
            professionals = [];
        }
        
        renderProfessionals();
    } catch (error) {
        console.error('Erro ao carregar profissionais:', error);
        showAlert('Erro ao carregar profissionais: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingProfessionals').style.display = 'none';
        document.getElementById('professionalsList').style.display = 'block';
    }
}

function renderProfessionals() {
    const tbody = document.getElementById('professionalsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (professionals.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = professionals.map(professional => {
        const statusBadge = (professional.status || 'active') === 'active' ? 'bg-success' : 'bg-secondary';
        const specialties = professional.specialties_details || [];
        const specialtiesText = specialties.length > 0 
            ? specialties.map(s => s.name).join(', ') 
            : '-';
        
        return `
            <tr>
                <td>${professional.id}</td>
                <td>${professional.user?.name || professional.name || '-'}</td>
                <td>${professional.crmv || '-'}</td>
                <td>${specialtiesText}</td>
                <td><span class="badge ${statusBadge}">${professional.status || 'active'}</span></td>
                <td>${formatDate(professional.created_at)}</td>
                <td>
                    <a href="/professional-details?id=${professional.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> Ver Detalhes
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}
</script>

