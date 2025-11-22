<?php
/**
 * View de Calendário de Agendamentos com FullCalendar
 */
?>
<!-- FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/appointments">Agendamentos</a></li>
            <li class="breadcrumb-item active">Calendário</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="bi bi-calendar3"></i> Calendário de Agendamentos</h1>
        <div class="btn-group">
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
                    <label class="form-label">Profissional</label>
                    <select class="form-select" id="professionalFilter">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-3">
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
                <div class="col-md-3">
                    <label class="form-label">Visualização</label>
                    <select class="form-select" id="viewFilter">
                        <option value="dayGridMonth">Mês</option>
                        <option value="timeGridWeek">Semana</option>
                        <option value="timeGridDay">Dia</option>
                        <option value="listWeek">Lista Semanal</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="applyFilters()">
                        <i class="bi bi-search"></i> Aplicar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendário FullCalendar -->
    <div class="card">
        <div class="card-body">
            <div id="loadingCalendar" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="calendar"></div>
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
                            <div class="position-relative">
                                <input type="text" 
                                       class="form-control" 
                                       id="createAppointmentClientSearch" 
                                       placeholder="Buscar por nome ou telefone..."
                                       autocomplete="off"
                                       required>
                                <input type="hidden" name="client_id" id="createAppointmentClientId">
                                <div class="invalid-feedback"></div>
                                <!-- Dropdown de resultados -->
                                <div id="clientSearchResults" class="list-group position-absolute w-100" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;">
                                    <!-- Resultados serão inseridos aqui -->
                                </div>
                            </div>
                            <small class="text-muted">Digite o nome completo ou telefone do cliente</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pet *</label>
                            <select class="form-select" name="pet_id" id="createAppointmentPetId" required disabled>
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

<!-- Modal Detalhes do Agendamento -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Agendamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetailsContent">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <a href="#" id="editAppointmentLink" class="btn btn-primary" style="display: none;">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            </div>
        </div>
    </div>
</div>

<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

<script>
let calendar;
let appointments = [];
let professionals = [];
let clients = [];
let pets = [];
let specialties = [];
let currentFilters = {
    professional_id: '',
    status: '',
    view: 'dayGridMonth'
};

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadProfessionalsForSelect();
        loadClientsForSelect();
        loadSpecialtiesForSelect();
        initializeCalendar();
    }, 100);
    
    // Busca de clientes com autocomplete
    let clientSearchTimeout;
    let selectedClient = null;
    
    const clientSearchInput = document.getElementById('createAppointmentClientSearch');
    const clientIdInput = document.getElementById('createAppointmentClientId');
    const clientSearchResults = document.getElementById('clientSearchResults');
    const petSelect = document.getElementById('createAppointmentPetId');
    
    clientSearchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        // Limpa timeout anterior
        clearTimeout(clientSearchTimeout);
        
        // Se o campo foi limpo, reseta a seleção
        if (searchTerm === '') {
            selectedClient = null;
            clientIdInput.value = '';
            petSelect.disabled = true;
            petSelect.innerHTML = '<option value="">Selecione um cliente primeiro...</option>';
            clientSearchResults.style.display = 'none';
            return;
        }
        
        // Debounce: aguarda 300ms antes de buscar
        clientSearchTimeout = setTimeout(async () => {
            await searchClients(searchTerm);
        }, 300);
    });
    
    // Fecha dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!clientSearchInput.contains(e.target) && !clientSearchResults.contains(e.target)) {
            clientSearchResults.style.display = 'none';
        }
    });
    
    async function searchClients(searchTerm) {
        try {
            const response = await apiRequest(`/v1/clients?search=${encodeURIComponent(searchTerm)}&limit=10`);
            const clients = Array.isArray(response.data?.clients) ? response.data.clients : 
                          Array.isArray(response.data) ? response.data : [];
            
            if (clients.length === 0) {
                clientSearchResults.innerHTML = '<div class="list-group-item text-muted">Nenhum cliente encontrado</div>';
                clientSearchResults.style.display = 'block';
                return;
            }
            
            // Renderiza resultados
            clientSearchResults.innerHTML = clients.map(client => {
                const phone = client.phone || client.phone_alt || '';
                const displayText = `${client.name}${phone ? ' - ' + phone : ''}${client.email ? ' (' + client.email + ')' : ''}`;
                return `
                    <a href="#" class="list-group-item list-group-item-action" data-client-id="${client.id}" data-client-name="${client.name}">
                        <div class="fw-bold">${client.name}</div>
                        ${phone ? `<small class="text-muted">${phone}</small>` : ''}
                        ${client.email ? `<small class="text-muted d-block">${client.email}</small>` : ''}
                    </a>
                `;
            }).join('');
            
            clientSearchResults.style.display = 'block';
            
            // Adiciona event listeners aos resultados
            clientSearchResults.querySelectorAll('a').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const clientId = this.getAttribute('data-client-id');
                    const clientName = this.getAttribute('data-client-name');
                    
                    selectedClient = { id: clientId, name: clientName };
                    clientIdInput.value = clientId;
                    clientSearchInput.value = clientName;
                    clientSearchResults.style.display = 'none';
                    
                    // Habilita e carrega pets
                    petSelect.disabled = false;
                    loadPetsForClient(clientId);
                });
            });
        } catch (error) {
            console.error('Erro ao buscar clientes:', error);
            clientSearchResults.innerHTML = '<div class="list-group-item text-danger">Erro ao buscar clientes</div>';
            clientSearchResults.style.display = 'block';
        }
    }
    
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
        const data = {};
        
        // Processa campos e converte tipos
        for (let [key, value] of formData.entries()) {
            if (value !== '') {
                // Converte IDs para inteiros
                if (key.includes('_id') || key === 'professional_id' || key === 'client_id' || key === 'pet_id' || key === 'specialty_id') {
                    data[key] = parseInt(value);
                }
                // Converte duration_minutes para inteiro
                else if (key === 'duration_minutes') {
                    data[key] = parseInt(value);
                }
                else {
                    data[key] = value;
                }
            }
        }
        
        // Remove campos vazios
        Object.keys(data).forEach(key => {
            if (data[key] === '' || data[key] === null) {
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
            
            // Recarrega o calendário
            calendar.refetchEvents();
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
    
    // Aplica filtros quando mudam
    document.getElementById('professionalFilter').addEventListener('change', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('viewFilter').addEventListener('change', function() {
        currentFilters.view = this.value;
        if (calendar) {
            calendar.changeView(this.value);
        }
        applyFilters();
    });
});

function initializeCalendar() {
    const calendarEl = document.getElementById('calendar');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: currentFilters.view || 'dayGridMonth',
        locale: 'pt-br',
        firstDay: 1, // Segunda-feira como primeiro dia da semana
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            day: 'Dia',
            list: 'Lista'
        },
        height: 'auto',
        editable: false, // Não permite arrastar eventos (pode ser habilitado depois)
        selectable: true, // Permite selecionar datas
        selectMirror: true,
        dayMaxEvents: true, // Mostra "+X mais" quando há muitos eventos
        weekends: true,
        events: async function(info, successCallback, failureCallback) {
            try {
                document.getElementById('loadingCalendar').style.display = 'block';
                
                const queryParams = new URLSearchParams();
                queryParams.append('start_date', info.startStr.split('T')[0]);
                queryParams.append('end_date', info.endStr.split('T')[0]);
                
                if (currentFilters.professional_id) {
                    queryParams.append('professional_id', currentFilters.professional_id);
                }
                if (currentFilters.status) {
                    queryParams.append('status', currentFilters.status);
                }
                
                const response = await apiRequest(`/v1/appointments?${queryParams.toString()}`);
                
                // A API retorna { data: { appointments: [...], pagination: {...} } }
                const appointmentsData = Array.isArray(response.data?.appointments) ? response.data.appointments : 
                                        Array.isArray(response.data) ? response.data : [];
                
                // Formata eventos para o FullCalendar
                const events = appointmentsData.map(apt => {
                    const startDateTime = `${apt.appointment_date}T${apt.appointment_time}`;
                    const duration = apt.duration_minutes || 30;
                    const endDateTime = new Date(new Date(startDateTime).getTime() + duration * 60000);
                    
                    // Cores por status
                    let color = '#6c757d'; // scheduled - cinza
                    if (apt.status === 'confirmed') color = '#0d6efd'; // azul
                    else if (apt.status === 'completed') color = '#198754'; // verde
                    else if (apt.status === 'cancelled') color = '#dc3545'; // vermelho
                    else if (apt.status === 'no_show') color = '#ffc107'; // amarelo
                    
                    // Título mais informativo
                    const title = `${apt.appointment_time} - Pet #${apt.pet_id}`;
                    
                    return {
                        id: apt.id.toString(),
                        title: title,
                        start: startDateTime,
                        end: endDateTime.toISOString().slice(0, 16),
                        color: color,
                        textColor: '#ffffff',
                        extendedProps: {
                            appointment: apt
                        }
                    };
                });
                
                successCallback(events);
            } catch (error) {
                console.error('Erro ao carregar eventos:', error);
                showAlert('Erro ao carregar agendamentos: ' + error.message, 'danger');
                failureCallback(error);
            } finally {
                document.getElementById('loadingCalendar').style.display = 'none';
            }
        },
        eventClick: function(info) {
            const appointment = info.event.extendedProps.appointment;
            if (appointment && appointment.id) {
                showAppointmentDetails(appointment.id);
            } else {
                // Fallback: tenta obter ID do evento
                const eventId = parseInt(info.event.id);
                if (eventId) {
                    showAppointmentDetails(eventId);
                }
            }
            info.jsEvent.preventDefault();
        },
        dateClick: function(info) {
            // Ao clicar em uma data, preenche o formulário de criação
            const clickedDate = info.dateStr.split('T')[0]; // Remove hora se houver
            const clickedTime = info.dateStr.includes('T') ? info.dateStr.split('T')[1].substring(0, 5) : '';
            
            document.getElementById('createAppointmentDate').value = clickedDate;
            if (clickedTime) {
                document.getElementById('createAppointmentTime').value = clickedTime;
            }
            
            // Abre modal de criação
            const modal = new bootstrap.Modal(document.getElementById('createAppointmentModal'));
            modal.show();
        },
        eventDidMount: function(info) {
            // Tooltip com informações do agendamento
            const apt = info.event.extendedProps.appointment;
            if (apt) {
                const statusText = {
                    'scheduled': 'Marcado',
                    'confirmed': 'Confirmado',
                    'completed': 'Concluído',
                    'cancelled': 'Cancelado',
                    'no_show': 'Falta'
                };
                
                info.el.setAttribute('title', 
                    `Profissional: #${apt.professional_id || 'N/A'}\n` +
                    `Cliente: #${apt.client_id || 'N/A'}\n` +
                    `Pet: #${apt.pet_id || 'N/A'}\n` +
                    `Status: ${statusText[apt.status] || apt.status || 'N/A'}`
                );
            }
        }
    });
    
    calendar.render();
}

function applyFilters() {
    currentFilters.professional_id = document.getElementById('professionalFilter').value;
    currentFilters.status = document.getElementById('statusFilter').value;
    
    // Recarrega eventos com os novos filtros
    if (calendar) {
        calendar.refetchEvents();
    }
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
                        const user = prof.user || {};
                        const option = document.createElement('option');
                        option.value = prof.id;
                        option.textContent = user.name || 'Profissional #' + prof.id;
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
        clients = Array.isArray(response.data?.clients) ? response.data.clients : 
                  Array.isArray(response.data) ? response.data : [];
        
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
                `<span class="badge bg-success me-1 mb-1">${slot.time}</span>`
            ).join('');
            document.getElementById('availableSlotsInfo').style.display = 'block';
        } else {
            document.getElementById('availableSlotsInfo').style.display = 'none';
        }
    } catch (error) {
        console.error('Erro ao carregar horários disponíveis:', error);
    }
}

async function showAppointmentDetails(appointmentId) {
    try {
        const response = await apiRequest(`/v1/appointments/${appointmentId}`);
        const appointment = response.data;
        
        const statusText = {
            'scheduled': 'Marcado',
            'confirmed': 'Confirmado',
            'completed': 'Concluído',
            'cancelled': 'Cancelado',
            'no_show': 'Falta'
        };
        
        const statusBadge = appointment.status === 'scheduled' ? 'bg-secondary' :
                          appointment.status === 'confirmed' ? 'bg-primary' :
                          appointment.status === 'completed' ? 'bg-success' :
                          appointment.status === 'cancelled' ? 'bg-danger' : 'bg-warning';
        
        document.getElementById('appointmentDetailsContent').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>ID:</strong> ${appointment.id}</p>
                    <p><strong>Data:</strong> ${formatDate(appointment.appointment_date)}</p>
                    <p><strong>Hora:</strong> ${appointment.appointment_time}</p>
                    <p><strong>Duração:</strong> ${appointment.duration_minutes || 30} minutos</p>
                    <p><strong>Status:</strong> <span class="badge ${statusBadge}">${statusText[appointment.status] || appointment.status}</span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Profissional:</strong> <a href="/professional-details?id=${appointment.professional_id}">Ver Profissional</a></p>
                    <p><strong>Cliente:</strong> <a href="/clinic-client-details?id=${appointment.client_id}">Ver Cliente</a></p>
                    <p><strong>Pet:</strong> <a href="/pet-details?id=${appointment.pet_id}">Ver Pet</a></p>
                    ${appointment.specialty_id ? `<p><strong>Especialidade:</strong> Especialidade #${appointment.specialty_id}</p>` : ''}
                    <p><strong>Criado em:</strong> ${formatDate(appointment.created_at)}</p>
                </div>
            </div>
            ${appointment.notes ? `<div class="mt-3"><strong>Observações:</strong><p class="mt-2">${appointment.notes}</p></div>` : ''}
            ${appointment.cancellation_reason ? `<div class="mt-3"><strong>Motivo do Cancelamento:</strong><p class="mt-2 text-danger">${appointment.cancellation_reason}</p></div>` : ''}
            <div class="mt-3">
                <a href="/appointment-details?id=${appointment.id}" class="btn btn-primary">
                    <i class="bi bi-eye"></i> Ver Detalhes Completos
                </a>
            </div>
        `;
        
        document.getElementById('editAppointmentLink').href = `/appointment-details?id=${appointment.id}`;
        document.getElementById('editAppointmentLink').style.display = 'inline-block';
        
        const modal = new bootstrap.Modal(document.getElementById('appointmentDetailsModal'));
        modal.show();
    } catch (error) {
        showAlert('Erro ao carregar detalhes: ' + error.message, 'danger');
    }
}
</script>

<style>
/* Estilos customizados para o FullCalendar */
#calendar {
    font-family: inherit;
}

.fc-toolbar-title {
    font-size: 1.5rem;
    font-weight: 600;
}

.fc-button {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

.fc-button:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}

.fc-button-active {
    background-color: #0a58ca;
    border-color: #0a58ca;
}

.fc-event {
    cursor: pointer;
    border-radius: 4px;
    padding: 2px 4px;
}

.fc-event:hover {
    opacity: 0.8;
}

.fc-daygrid-day-number {
    font-weight: 500;
}

.fc-day-today {
    background-color: #f8f9fa !important;
}

.fc-daygrid-event {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fc-timegrid-event {
    border-radius: 4px;
}

.fc-list-event {
    cursor: pointer;
}

.fc-list-event:hover {
    background-color: #f8f9fa;
}

/* Responsividade */
@media (max-width: 768px) {
    .fc-toolbar {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .fc-toolbar-chunk {
        display: flex;
        justify-content: center;
        width: 100%;
    }
    
    .fc-button {
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
    }
}
</style>
