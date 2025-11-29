<?php
/**
 * View de Agenda do Profissional
 */
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/professionals">Profissionais</a></li>
            <li class="breadcrumb-item active">Agenda</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-calendar-week"></i> Agenda do Profissional</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBlockModal">
            <i class="bi bi-plus-circle"></i> Criar Bloqueio
        </button>
    </div>

    <div id="alertContainer"></div>

    <!-- Seleção de Profissional -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Profissional *</label>
                    <select class="form-select" id="professionalSelect" required>
                        <option value="">Selecione um profissional...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data</label>
                    <input type="date" class="form-control" id="dateSelect" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100" onclick="loadSchedule()">
                        <i class="bi bi-search"></i> Carregar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Agenda Semanal -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Horários Padrões da Semana</h5>
        </div>
        <div class="card-body">
            <div id="loadingSchedule" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="scheduleList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Dia da Semana</th>
                                <th>Horário</th>
                                <th>Disponível</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bloqueios -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Bloqueios de Agenda</h5>
        </div>
        <div class="card-body">
            <div id="blocksList">
                <p class="text-muted">Selecione um profissional para ver os bloqueios</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Bloqueio -->
<div class="modal fade" id="createBlockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Bloqueio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createBlockForm">
                <div class="modal-body">
                    <input type="hidden" id="createBlockProfessionalId">
                    <div class="mb-3">
                        <label class="form-label">Data/Hora Inicial *</label>
                        <input type="datetime-local" class="form-control" name="start_datetime" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data/Hora Final *</label>
                        <input type="datetime-local" class="form-control" name="end_datetime" required>
                        <div class="invalid-feedback"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <input type="text" class="form-control" name="reason" placeholder="Ex: Férias, Licença médica...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Bloqueio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let schedule = [];
let blocks = [];
let professionals = [];
let selectedProfessionalId = null;

document.addEventListener('DOMContentLoaded', () => {
    // Lê professional_id da query string
    const urlParams = new URLSearchParams(window.location.search);
    const professionalIdFromUrl = urlParams.get('professional_id');
    
    setTimeout(() => {
        loadProfessionalsForSelect(professionalIdFromUrl);
    }, 100);
    
    // Carrega agenda quando profissional é selecionado
    document.getElementById('professionalSelect').addEventListener('change', function() {
        selectedProfessionalId = this.value;
        document.getElementById('createBlockProfessionalId').value = this.value;
        if (this.value) {
            loadSchedule();
        }
    });
    
    // Form criar bloqueio
    document.getElementById('createBlockForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!e.target.checkValidity()) {
            e.target.classList.add('was-validated');
            return;
        }
        
        const professionalId = document.getElementById('createBlockProfessionalId').value;
        if (!professionalId) {
            showAlert('Selecione um profissional primeiro', 'warning');
            return;
        }
        
        const formData = new FormData(e.target);
        const data = {
            professional_id: professionalId,
            start_datetime: formData.get('start_datetime').replace('T', ' ') + ':00',
            end_datetime: formData.get('end_datetime').replace('T', ' ') + ':00',
            reason: formData.get('reason') || null
        };
        
        try {
            await apiRequest(`/v1/professionals/${professionalId}/schedule/blocks`, {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear(`/v1/professionals/${professionalId}/schedule`);
            showAlert('Bloqueio criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createBlockModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            
            setTimeout(() => {
                loadSchedule();
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadSchedule() {
    const professionalId = document.getElementById('professionalSelect').value;
    if (!professionalId) {
        showAlert('Selecione um profissional', 'warning');
        return;
    }
    
    try {
        document.getElementById('loadingSchedule').style.display = 'block';
        document.getElementById('scheduleList').style.display = 'none';
        
        const response = await apiRequest(`/v1/professionals/${professionalId}/schedule`);
        // Extrai o array schedule do objeto retornado
        const scheduleData = response.data || {};
        schedule = Array.isArray(scheduleData.schedule) ? scheduleData.schedule : [];
        const blocks = Array.isArray(scheduleData.blocks) ? scheduleData.blocks : [];
        
        renderSchedule();
        loadBlocks(blocks);
    } catch (error) {
        console.error('Erro ao carregar agenda:', error);
        showAlert('Erro ao carregar agenda: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingSchedule').style.display = 'none';
        document.getElementById('scheduleList').style.display = 'block';
    }
}

function renderSchedule() {
    const tbody = document.getElementById('scheduleTableBody');
    const days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    
    if (schedule.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Nenhum horário configurado</td></tr>';
        return;
    }
    
    tbody.innerHTML = schedule.map(s => `
        <tr>
            <td>${days[s.day_of_week] || '-'}</td>
            <td>${s.start_time} - ${s.end_time}</td>
            <td><span class="badge ${s.is_available ? 'bg-success' : 'bg-secondary'}">${s.is_available ? 'Sim' : 'Não'}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editSchedule(${s.id})">
                    <i class="bi bi-pencil"></i> Editar
                </button>
            </td>
        </tr>
    `).join('');
}

async function loadBlocks(blocks) {
    try {
        const container = document.getElementById('blocksList');
        
        if (!blocks || blocks.length === 0) {
            container.innerHTML = '<p class="text-muted">Nenhum bloqueio de agenda</p>';
            return;
        }
        
        container.innerHTML = blocks.map(block => {
            return `
                <div class="card mb-2">
                    <div class="card-body">
                        <p class="mb-1"><strong>${formatDate(block.start_datetime)}</strong> até <strong>${formatDate(block.end_datetime)}</strong></p>
                        ${block.reason ? `<p class="text-muted mb-0"><small>Motivo: ${block.reason}</small></p>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Erro ao carregar bloqueios:', error);
        const container = document.getElementById('blocksList');
        container.innerHTML = '<p class="text-danger">Erro ao carregar bloqueios</p>';
    }
}

async function loadProfessionalsForSelect(preselectedId = null) {
    try {
        const response = await apiRequest('/v1/professionals');
        const professionalsData = response.data || {};
        const professionalsList = Array.isArray(professionalsData.professionals) 
            ? professionalsData.professionals 
            : (Array.isArray(professionalsData) ? professionalsData : []);
        
        professionals = professionalsList;
        
        const select = document.getElementById('professionalSelect');
        
        if (professionals.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Nenhum profissional encontrado';
            select.appendChild(option);
        } else {
            professionals.forEach(prof => {
                if (prof.status === 'active') {
                    const option = document.createElement('option');
                    option.value = prof.id;
                    // Usa o nome do usuário relacionado se disponível
                    const name = prof.user?.name || prof.user_name || prof.name || 'Profissional #' + prof.id;
                    const crmv = prof.crmv ? ` - CRMV: ${prof.crmv}` : '';
                    option.textContent = name + crmv;
                    select.appendChild(option);
                }
            });
        }
        
        // Pré-seleciona o profissional se fornecido na URL
        if (preselectedId) {
            select.value = preselectedId;
            selectedProfessionalId = preselectedId;
            document.getElementById('createBlockProfessionalId').value = preselectedId;
            
            // Dispara o evento change para carregar a agenda automaticamente
            select.dispatchEvent(new Event('change'));
        }
    } catch (error) {
        console.error('Erro ao carregar profissionais:', error);
    }
}

function editSchedule(scheduleId) {
    // Implementar edição de horário
    showAlert('Funcionalidade de edição em desenvolvimento', 'info');
}
</script>

