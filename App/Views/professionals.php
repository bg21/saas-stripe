<?php
/**
 * View de Gerenciamento de Profissionais (Veterinários, Atendentes)
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-person-badge"></i> Profissionais</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProfessionalModal">
            <i class="bi bi-plus-circle"></i> Novo Profissional
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nome, CRMV, email...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                        <option value="on_leave">Em Licença</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" id="roleFilter">
                        <option value="">Todos</option>
                        <option value="veterinarian">Veterinário</option>
                        <option value="assistant">Atendente</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadProfessionals()">
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
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Role</th>
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

<!-- Modal Criar Profissional -->
<div class="modal fade" id="createProfessionalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Profissional</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createProfessionalForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nome *</label>
                            <input type="text" class="form-control" name="name" required minlength="2" maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Telefone</label>
                            <input type="text" class="form-control" name="phone" placeholder="(00) 00000-0000">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CRMV</label>
                            <input type="text" class="form-control" name="crmv" placeholder="CRMV-XX 00000">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Selecione...</option>
                                <option value="veterinarian">Veterinário</option>
                                <option value="assistant">Atendente</option>
                                <option value="admin">Administrador</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Ativo</option>
                                <option value="inactive">Inativo</option>
                                <option value="on_leave">Em Licença</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usuário (Opcional)</label>
                        <select class="form-select" name="user_id" id="userIdSelect">
                            <option value="">Nenhum (criar depois)</option>
                        </select>
                        <small class="form-text text-muted">Vincular a um usuário existente ou criar depois</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Especialidade</label>
                        <select class="form-select" name="specialty_id" id="specialtySelect">
                            <option value="">Nenhuma</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Profissional</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let professionals = [];
let users = [];
let specialties = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadProfessionals();
        loadUsersForSelect();
        loadSpecialtiesForSelect();
    }, 100);
    
    // Form criar profissional
    document.getElementById('createProfessionalForm').addEventListener('submit', async (e) => {
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
            const response = await apiRequest('/v1/professionals', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/professionals');
            showAlert('Profissional criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createProfessionalModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(async () => {
                await loadProfessionals(true);
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadProfessionals(skipCache = false) {
    try {
        document.getElementById('loadingProfessionals').style.display = 'block';
        document.getElementById('professionalsList').style.display = 'none';
        
        if (skipCache) {
            cache.clear('/v1/professionals');
        }
        
        const response = await apiRequest('/v1/professionals', {
            skipCache: skipCache
        });
        
        professionals = Array.isArray(response.data) ? response.data : [];
        
        // Aplicar filtros
        applyFilters();
        
        renderProfessionals();
    } catch (error) {
        console.error('Erro ao carregar profissionais:', error);
        showAlert('Erro ao carregar profissionais: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingProfessionals').style.display = 'none';
        document.getElementById('professionalsList').style.display = 'block';
    }
}

function applyFilters() {
    const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    const roleFilter = document.getElementById('roleFilter')?.value || '';
    
    professionals = professionals.filter(prof => {
        const matchSearch = !search || 
            (prof.name?.toLowerCase().includes(search)) ||
            (prof.crmv?.toLowerCase().includes(search)) ||
            (prof.email?.toLowerCase().includes(search));
        
        const matchStatus = !statusFilter || prof.status === statusFilter;
        const matchRole = !roleFilter || prof.role === roleFilter;
        
        return matchSearch && matchStatus && matchRole;
    });
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
    
    tbody.innerHTML = professionals.map(prof => {
        const statusBadge = prof.status === 'active' ? 'bg-success' : 
                           prof.status === 'inactive' ? 'bg-secondary' : 'bg-warning';
        const roleBadge = prof.role === 'veterinarian' ? 'bg-primary' : 
                         prof.role === 'assistant' ? 'bg-info' : 'bg-danger';
        
        return `
            <tr>
                <td>${prof.id}</td>
                <td>${prof.name || '-'}</td>
                <td>${prof.crmv || '-'}</td>
                <td>${prof.email || '-'}</td>
                <td>${prof.phone || '-'}</td>
                <td><span class="badge ${roleBadge}">${prof.role || '-'}</span></td>
                <td><span class="badge ${statusBadge}">${prof.status || 'active'}</span></td>
                <td>${formatDate(prof.created_at)}</td>
                <td>
                    <div class="btn-group" role="group">
                        <a href="/professional-details?id=${prof.id}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProfessional(${prof.id})" title="Excluir profissional">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function loadUsersForSelect() {
    try {
        const response = await apiRequest('/v1/users');
        users = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('userIdSelect');
        if (select) {
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.name || user.email} (${user.email})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
    }
}

async function loadSpecialtiesForSelect() {
    try {
        const response = await apiRequest('/v1/specialties');
        specialties = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('specialtySelect');
        if (select) {
            specialties.forEach(spec => {
                if (spec.status === 'active') {
                    const option = document.createElement('option');
                    option.value = spec.id;
                    option.textContent = spec.name;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Erro ao carregar especialidades:', error);
    }
}

async function deleteProfessional(professionalId) {
    const confirmed = await showConfirmModal(
        'Tem certeza que deseja remover este profissional? Esta ação não pode ser desfeita.',
        'Confirmar Exclusão',
        'Remover Profissional'
    );
    if (!confirmed) return;
    
    try {
        await apiRequest(`/v1/professionals/${professionalId}`, {
            method: 'DELETE'
        });
        
        cache.clear('/v1/professionals');
        showAlert('Profissional removido com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadProfessionals(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

