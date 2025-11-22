<?php
/**
 * View de Gerenciamento de Agendamentos
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-calendar-check"></i> Agendamentos</h1>
        <div class="btn-group">
            <a href="/appointment-calendar" class="btn btn-outline-primary">
                <i class="bi bi-calendar3"></i> Visualização em Calendário
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAppointmentModal">
                <i class="bi bi-plus-circle"></i> Novo Agendamento
            </button>
        </div>
    </div>

    <div id="alertContainer"></div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" class="form-control" id="startDateFilter">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Final</label>
                    <input type="date" class="form-control" id="endDateFilter">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos</option>
                        <option value="scheduled">Marcado</option>
                        <option value="confirmed">Confirmado</option>
                        <option value="completed">Concluído</option>
                        <option value="cancelled">Cancelado</option>
                        <option value="no_show">Falta</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Profissional</label>
                    <select class="form-select" id="professionalFilter">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadAppointments()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Agendamentos -->
    <div class="card">
        <div class="card-body">
            <div id="loadingAppointments" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="appointmentsList" style="display: none;">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Profissional</th>
                                <th>Cliente</th>
                                <th>Pet</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentsTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="emptyState" class="text-center py-5" style="display: none;">
                    <i class="bi bi-calendar-check fs-1 text-muted"></i>
                    <p class="text-muted mt-3">Nenhum agendamento encontrado</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Criar Agendamento -->
<div class="modal fade" id="createAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Agendamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createAppointmentForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Profissional *</label>
                            <select class="form-select" name="professional_id" id="createAppointmentProfessionalId" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente *</label>
                            <select class="form-select" name="client_id" id="createAppointmentClientId" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pet *</label>
                            <select class="form-select" name="pet_id" id="createAppointmentPetId" required>
                                <option value="">Selecione um cliente primeiro...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Especialidade</label>
                            <select class="form-select" name="specialty_id" id="createAppointmentSpecialtyId">
                                <option value="">Nenhuma</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Data *</label>
                            <input type="date" class="form-control" name="appointment_date" id="createAppointmentDate" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hora *</label>
                            <input type="time" class="form-control" name="appointment_time" id="createAppointmentTime" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Duração (min) *</label>
                            <input type="number" class="form-control" name="duration_minutes" value="30" required min="15" max="240" step="15">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    <div class="alert alert-info" id="availableSlotsInfo" style="display: none;">
                        <strong>Horários disponíveis:</strong>
                        <div id="availableSlotsList" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Agendamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let appointments = [];
let professionals = [];
let clients = [];
let pets = [];
let specialties = [];

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadAppointments();
        loadProfessionalsForSelect();
        loadClientsForSelect();
        loadSpecialtiesForSelect();
    }, 100);
    
    // Carrega pets quando cliente é selecionado
    document.getElementById('createAppointmentClientId').addEventListener('change', function() {
        loadPetsForClient(this.value);
    });
    
    // Carrega horários disponíveis quando profissional e data são selecionados
    document.getElementById('createAppointmentProfessionalId').addEventListener('change', checkAvailableSlots);
    document.getElementById('createAppointmentDate').addEventListener('change', checkAvailableSlots);
    
    // Form criar agendamento
    document.getElementById('createAppointmentForm').addEventListener('submit', async (e) => {
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
            const response = await apiRequest('/v1/appointments', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            cache.clear('/v1/appointments');
            showAlert('Agendamento criado com sucesso!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createAppointmentModal')).hide();
            e.target.reset();
            e.target.classList.remove('was-validated');
            document.getElementById('availableSlotsInfo').style.display = 'none';
            
            setTimeout(async () => {
                await loadAppointments(true);
            }, 100);
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function loadAppointments(skipCache = false) {
    try {
        document.getElementById('loadingAppointments').style.display = 'block';
        document.getElementById('appointmentsList').style.display = 'none';
        
        if (skipCache) {
            cache.clear('/v1/appointments');
        }
        
        const queryParams = new URLSearchParams();
        const startDate = document.getElementById('startDateFilter')?.value;
        const endDate = document.getElementById('endDateFilter')?.value;
        const status = document.getElementById('statusFilter')?.value;
        const professionalId = document.getElementById('professionalFilter')?.value;
        
        if (startDate) queryParams.append('start_date', startDate);
        if (endDate) queryParams.append('end_date', endDate);
        if (status) queryParams.append('status', status);
        if (professionalId) queryParams.append('professional_id', professionalId);
        
        const url = '/v1/appointments' + (queryParams.toString() ? '?' + queryParams.toString() : '');
        const response = await apiRequest(url, {
            skipCache: skipCache
        });
        
        appointments = Array.isArray(response.data) ? response.data : [];
        
        renderAppointments();
    } catch (error) {
        console.error('Erro ao carregar agendamentos:', error);
        showAlert('Erro ao carregar agendamentos: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingAppointments').style.display = 'none';
        document.getElementById('appointmentsList').style.display = 'block';
    }
}

function renderAppointments() {
    const tbody = document.getElementById('appointmentsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (appointments.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    
    tbody.innerHTML = appointments.map(apt => {
        const statusBadge = apt.status === 'scheduled' ? 'bg-secondary' :
                          apt.status === 'confirmed' ? 'bg-primary' :
                          apt.status === 'completed' ? 'bg-success' :
                          apt.status === 'cancelled' ? 'bg-danger' : 'bg-warning';
        const statusText = apt.status === 'scheduled' ? 'Marcado' :
                          apt.status === 'confirmed' ? 'Confirmado' :
                          apt.status === 'completed' ? 'Concluído' :
                          apt.status === 'cancelled' ? 'Cancelado' : 'Falta';
        
        return `
            <tr>
                <td>${apt.id}</td>
                <td>${formatDate(apt.appointment_date)}</td>
                <td>${apt.appointment_time}</td>
                <td>Profissional #${apt.professional_id}</td>
                <td>Cliente #${apt.client_id}</td>
                <td>Pet #${apt.pet_id}</td>
                <td><span class="badge ${statusBadge}">${statusText}</span></td>
                <td>
                    <div class="btn-group" role="group">
                        <a href="/appointment-details?id=${apt.id}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Ver
                        </a>
                        ${apt.status === 'scheduled' ? `
                            <button class="btn btn-sm btn-outline-success" onclick="confirmAppointment(${apt.id})" title="Confirmar">
                                <i class="bi bi-check-circle"></i>
                            </button>
                        ` : ''}
                        ${apt.status !== 'cancelled' && apt.status !== 'completed' ? `
                            <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment(${apt.id})" title="Cancelar">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function loadProfessionalsForSelect() {
    try {
        const response = await apiRequest('/v1/professionals');
        professionals = Array.isArray(response.data) ? response.data : [];
        
        const createSelect = document.getElementById('createAppointmentProfessionalId');
        const filterSelect = document.getElementById('professionalFilter');
        
        [createSelect, filterSelect].forEach(select => {
            if (select) {
                professionals.forEach(prof => {
                    if (prof.status === 'active') {
                        const option = document.createElement('option');
                        option.value = prof.id;
                        option.textContent = prof.name || 'Profissional #' + prof.id;
                        select.appendChild(option);
                    }
                });
            }
        });
    } catch (error) {
        console.error('Erro ao carregar profissionais:', error);
    }
}

async function loadClientsForSelect() {
    try {
        const response = await apiRequest('/v1/clients');
        clients = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('createAppointmentClientId');
        if (select) {
            clients.forEach(client => {
                const option = document.createElement('option');
                option.value = client.id;
                option.textContent = `${client.name || 'Cliente #' + client.id} (${client.email || client.phone || ''})`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar clientes:', error);
    }
}

async function loadPetsForClient(clientId) {
    if (!clientId) {
        document.getElementById('createAppointmentPetId').innerHTML = '<option value="">Selecione um cliente primeiro...</option>';
        return;
    }
    
    try {
        const response = await apiRequest(`/v1/clients/${clientId}/pets`);
        pets = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('createAppointmentPetId');
        select.innerHTML = '<option value="">Selecione...</option>';
        
        pets.forEach(pet => {
            const option = document.createElement('option');
            option.value = pet.id;
            option.textContent = `${pet.name} (${pet.species || ''})`;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Erro ao carregar pets:', error);
    }
}

async function loadSpecialtiesForSelect() {
    try {
        const response = await apiRequest('/v1/specialties?status=active');
        specialties = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('createAppointmentSpecialtyId');
        if (select) {
            specialties.forEach(spec => {
                const option = document.createElement('option');
                option.value = spec.id;
                option.textContent = spec.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar especialidades:', error);
    }
}

async function checkAvailableSlots() {
    const professionalId = document.getElementById('createAppointmentProfessionalId').value;
    const date = document.getElementById('createAppointmentDate').value;
    
    if (!professionalId || !date) {
        document.getElementById('availableSlotsInfo').style.display = 'none';
        return;
    }
    
    try {
        const response = await apiRequest(`/v1/appointments/available-slots?professional_id=${professionalId}&date=${date}`);
        const slots = Array.isArray(response.data) ? response.data : [];
        
        if (slots.length > 0) {
            const slotsList = document.getElementById('availableSlotsList');
            slotsList.innerHTML = slots.map(slot => 
                `<span class="badge bg-success me-1">${slot.time}</span>`
            ).join('');
            document.getElementById('availableSlotsInfo').style.display = 'block';
        } else {
            document.getElementById('availableSlotsInfo').style.display = 'none';
        }
    } catch (error) {
        console.error('Erro ao carregar horários disponíveis:', error);
    }
}

async function confirmAppointment(appointmentId) {
    try {
        await apiRequest(`/v1/appointments/${appointmentId}/confirm`, {
            method: 'POST'
        });
        
        cache.clear('/v1/appointments');
        showAlert('Agendamento confirmado com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadAppointments(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function cancelAppointment(appointmentId) {
    const reason = prompt('Motivo do cancelamento:');
    if (!reason) return;
    
    try {
        await apiRequest(`/v1/appointments/${appointmentId}`, {
            method: 'DELETE',
            body: JSON.stringify({ reason: reason })
        });
        
        cache.clear('/v1/appointments');
        showAlert('Agendamento cancelado com sucesso!', 'success');
        
        setTimeout(async () => {
            await loadAppointments(true);
        }, 100);
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}
</script>

