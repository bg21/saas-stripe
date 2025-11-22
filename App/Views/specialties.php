<?php
/**
 * View de Gerenciamento de Especialidades
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-heart-pulse"></i> Especialidades</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSpecialtyModal">
            <i class="bi bi-plus-circle"></i> Nova Especialidade
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome, descrição...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="active">Ativas</option>
                        <option value="inactive">Inativas</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadSpecialties()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Especialidades -->
    <div class="card">
        <div class="card-body">
            <div id="loadingSpecialties" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="specialtiesList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="specialtiesTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-heart-pulse fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhuma especialidade encontrada</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Especialidade -->
<div class="modal fade" id="createSpecialtyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Especialidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createSpecialtyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="name" required minlength="2" maxlength="255">
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="active">Ativa</option>
                            <option value="inactive">Inativa</option>
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Especialidade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let specialties = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadSpecialties();
    }, 100);
    
    // Form criar especialidade
    document.getElementById('createSpecialtyForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        // Remove campos vazios
        Object.keys(data).forEach(key => {
            if (data[key] === '') {
                delete data[key];
            }
        });
        
        try {
            const response = await apiRequest('/v1/specialties', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/specialties');
            showAlert('Especialidade criada com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createSpecialtyModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadSpecialties(true);
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadSpecialties(skipCache = false) {
    try {
        document.getElementById('loadingSpecialties').style.display = 'block';
        document.getElementById('specialtiesList').style.display = 'none';
        
        if (skipCache) {
            cache.clear('/v1/specialties');
        }
        
        const response = await apiRequest('/v1/specialties', {
            skipCache: skipCache
        });
        
        specialties = Array.isArray(response.data) ? response.data : [];
        
        // Aplicar filtros
        applyFilters();
        
        renderSpecialties();
    } catch (error) {
        console.error('Erro ao carregar especialidades:', error);
        showAlert('Erro ao carregar especialidades: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingSpecialties').style.display = 'none';
        document.getElementById('specialtiesList').style.display = 'block';
    }
}

function applyFilters() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    specialties = specialties.filter(spec => {
        const matchSearch = !search || 
            (spec.name?.toLowerCase().includes(search)) ||
            (spec.description?.toLowerCase().includes(search));
        
        const matchStatus = !statusFilter || spec.status === statusFilter;
        
        return matchSearch && matchStatus;
    });
}

function renderSpecialties() {
    const tbody = document.getElementById('specialtiesTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (specialties.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = specialties.map(spec => {
        const statusBadge = spec.status === 'active' ? 'bg-success' : 'bg-secondary';
        return `
            <tr>
                <td>${spec.id}</td>
                <td>${spec.name || '-'}</td>
                <td>${spec.description || '-'}</td>
                <td><span class="badge ${statusBadge}">${spec.status || 'active'}</span></td>
                <td>${formatDate(spec.created_at)}</td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editSpecialty(${spec.id})" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSpecialty(${spec.id})" title="Excluir">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function editSpecialty(specialtyId) {
    try {
        const response = await apiRequest(`/v1/specialties/${specialtyId}`);
        const specialty = response.data;
        
        const name = prompt('Nome da especialidade:', specialty.name);
        if (!name) return;
        
        const description = prompt('Descrição:', specialty.description || '');
        const status = confirm('Especialidade ativa?') ? 'active' : 'inactive';
        
        await apiRequest(`/v1/specialties/${specialtyId}`, {
            method: 'PUT',
            body: JSON.stringify({
                name: name,
                description: description || null,
                status: status
            })
        });
        
        cache.clear('/v1/specialties');
        showAlert('Especialidade atualizada com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadSpecialties(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function deleteSpecialty(specialtyId) {
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover esta especialidade? Esta ação não pode ser desfeita.',
        'Confirmar Exclusão',
        'Remover Especialidade'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/specialties/${specialtyId}`, {
            method: 'DELETE'
        });
        
        cache.clear('/v1/specialties');
        showAlert('Especialidade removida com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadSpecialties(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

