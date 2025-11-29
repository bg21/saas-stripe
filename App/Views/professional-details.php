<?php
/**
 * View de Detalhes do Profissional
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/professionals">Profissionais</a></li>
            <li class="breadcrumb-item active">Detalhes</li>
        </ol>
    </nav>

    <div id="alertContainer"></div>
    <div id="loadingProfessional" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div id="professionalDetails" style="display: none;">
        <!-- Modo Visualização -->
        <div id="viewMode">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Informações do Profissional</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                        <i class="bi bi-pencil"></i> Editar
                    </button>
                </div>
                <div class="card-body" id="professionalInfo">
                </div>
            </div>
        </div>

        <!-- Modo Edição -->
        <div id="editMode" style="display: none;">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Profissional</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleEditMode()">
                        <i class="bi bi-x"></i> Cancelar
                    </button>
                </div>
                <div class="card-body">
                    <form id="editProfessionalForm" class="needs-validation" novalidate>
                        <input type="hidden" id="editProfessionalId">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Nota:</strong> Nome, email e telefone são gerenciados através do usuário vinculado. 
                            <a href="/user-details?id=${professionalData?.user_id || ''}" target="_blank">Editar usuário</a>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editProfessionalCrmv" class="form-label">CRMV</label>
                                <input type="text" class="form-control" id="editProfessionalCrmv" name="crmv" maxlength="20">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editProfessionalStatus" class="form-label">Status *</label>
                                <select class="form-select" id="editProfessionalStatus" name="status" required>
                                    <option value="active">Ativo</option>
                                    <option value="inactive">Inativo</option>
                                    <option value="on_leave">Em Licença</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editProfessionalDuration" class="form-label">Duração Padrão de Consulta (minutos)</label>
                            <input type="number" class="form-control" id="editProfessionalDuration" name="default_consultation_duration" min="15" max="240" step="15" value="30">
                            <div class="invalid-feedback"></div>
                        </div>

                        <div id="editProfessionalError" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                                Salvar Alterações
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleEditMode()">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Agenda do Profissional -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Agenda</h5>
                <a href="/schedule?professional_id=<?php echo htmlspecialchars($_GET['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-calendar"></i> Ver Agenda Completa
                </a>
            </div>
            <div class="card-body">
                <div id="scheduleInfo">
                    <p class="text-muted">Carregando agenda...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const professionalId = urlParams.get('id');

if (!professionalId) {
    window.location.href = '/professionals';
}

let professionalData = null;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadProfessionalDetails();
    }, 100);
});

async function loadProfessionalDetails() {
    try {
        const [professional, scheduleResponse] = await Promise.all([
            apiRequest(`/v1/professionals/${professionalId}`),
            apiRequest(`/v1/professionals/${professionalId}/schedule`).catch(() => ({ data: { schedule: [], blocks: [] } }))
        ]);

        professionalData = professional.data;
        renderProfessionalInfo(professionalData);
        
        // Extrai o array schedule do objeto retornado
        const scheduleData = scheduleResponse.data || {};
        const schedule = Array.isArray(scheduleData.schedule) ? scheduleData.schedule : [];
        renderSchedule(schedule);

        document.getElementById('loadingProfessional').style.display = 'none';
        document.getElementById('professionalDetails').style.display = 'block';
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}

function renderProfessionalInfo(professional) {
    const statusBadge = professional.status === 'active' ? 'bg-success' : 
                       professional.status === 'inactive' ? 'bg-secondary' : 'bg-warning';
    const user = professional.user || {};
    const roleBadge = user.role === 'admin' ? 'bg-danger' : 
                     user.role === 'editor' ? 'bg-primary' : 'bg-secondary';
    
    document.getElementById('professionalInfo').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> ${professional.id}</p>
                <p><strong>Nome:</strong> ${user.name || '-'}</p>
                <p><strong>Email:</strong> ${user.email || '-'}</p>
                <p><strong>User ID:</strong> ${professional.user_id || '-'}</p>
            </div>
            <div class="col-md-6">
                <p><strong>CRMV:</strong> ${professional.crmv || '-'}</p>
                <p><strong>Role do Usuário:</strong> <span class="badge ${roleBadge}">${user.role || '-'}</span></p>
                <p><strong>Status:</strong> <span class="badge ${statusBadge}">${professional.status || 'active'}</span></p>
                <p><strong>Duração Padrão:</strong> ${professional.default_consultation_duration || 30} minutos</p>
                <p><strong>Criado em:</strong> ${formatDate(professional.created_at)}</p>
            </div>
        </div>
        ${professional.specialties && Array.isArray(professional.specialties) && professional.specialties.length > 0 ? `
            <div class="mt-3">
                <strong>Especialidades:</strong>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    ${professional.specialties.map(specId => `<span class="badge bg-info">Especialidade #${specId}</span>`).join('')}
                </div>
            </div>
        ` : ''}
    `;
}

function renderSchedule(schedule) {
    const container = document.getElementById('scheduleInfo');
    
    if (!schedule || schedule.length === 0) {
        container.innerHTML = '<p class="text-muted">Nenhum horário configurado</p>';
        return;
    }
    
    const days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Horário</th>
                        <th>Disponível</th>
                    </tr>
                </thead>
                <tbody>
                    ${schedule.map(s => `
                        <tr>
                            <td>${days[s.day_of_week] || '-'}</td>
                            <td>${s.start_time} - ${s.end_time}</td>
                            <td><span class="badge ${s.is_available ? 'bg-success' : 'bg-secondary'}">${s.is_available ? 'Sim' : 'Não'}</span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function toggleEditMode() {
    const viewMode = document.getElementById('viewMode');
    const editMode = document.getElementById('editMode');
    
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        if (professionalData) {
            loadProfessionalForEdit();
        }
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}

function loadProfessionalForEdit() {
    if (!professionalData) return;
    
    document.getElementById('editProfessionalId').value = professionalData.id;
    // Nota: name, email, phone são do User, não do Professional
    // Para editar esses campos, é necessário editar o User separadamente
    document.getElementById('editProfessionalCrmv').value = professionalData.crmv || '';
    document.getElementById('editProfessionalStatus').value = professionalData.status || 'active';
}

document.getElementById('editProfessionalForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const formData = {
        crmv: document.getElementById('editProfessionalCrmv').value.trim() || null,
        status: document.getElementById('editProfessionalStatus').value,
        default_consultation_duration: parseInt(document.getElementById('editProfessionalDuration').value) || 30
    };

    const submitBtn = this.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    const errorDiv = document.getElementById('editProfessionalError');
    
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    errorDiv.classList.add('d-none');

    apiRequest(`/v1/professionals/${professionalId}`, {
        method: 'PUT',
        body: JSON.stringify(formData)
    })
    .then(data => {
        if (data.success) {
            showAlert('Profissional atualizado com sucesso!', 'success');
            professionalData = data.data;
            renderProfessionalInfo(professionalData);
            toggleEditMode();
            cache.clear(`/v1/professionals/${professionalId}`);
        } else {
            throw new Error(data.error || 'Erro ao atualizar profissional');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        errorDiv.textContent = error.message;
        errorDiv.classList.remove('d-none');
    })
    .finally(() => {
        submitBtn.disabled = false;
        spinner.classList.add('d-none');
    });
});
</script>

