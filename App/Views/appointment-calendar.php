<?php
/**
 * View de Calendário de Agendamentos
 */
?>
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
            <button class="btn btn-outline-secondary" onclick="previousMonth()">
                <i class="bi bi-chevron-left"></i> Mês Anterior
            </button>
            <button class="btn btn-outline-secondary" onclick="currentMonth()">
                Hoje
            </button>
            <button class="btn btn-outline-secondary" onclick="nextMonth()">
                Próximo Mês <i class="bi bi-chevron-right"></i>
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
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mês/Ano</label>
                    <input type="month" class="form-control" id="monthFilter" value="<?php echo date('Y-m'); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadCalendar()">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calendário -->
    <div class="card">
        <div class="card-body">
            <div id="loadingCalendar" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
            </div>
            <div id="calendarContainer" style="display: none;">
                <div id="calendarHeader" class="mb-3">
                    <h4 id="currentMonthYear"></h4>
                </div>
                <div id="calendarGrid" class="row g-2">
                    <!-- Calendário será renderizado aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let appointments = [];
let professionals = [];
let currentDate = new Date();

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        loadProfessionalsForSelect();
        loadCalendar();
    }, 100);
});

function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    document.getElementById('monthFilter').value = currentDate.toISOString().slice(0, 7);
    loadCalendar();
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    document.getElementById('monthFilter').value = currentDate.toISOString().slice(0, 7);
    loadCalendar();
}

function currentMonth() {
    currentDate = new Date();
    document.getElementById('monthFilter').value = currentDate.toISOString().slice(0, 7);
    loadCalendar();
}

async function loadCalendar() {
    try {
        document.getElementById('loadingCalendar').style.display = 'block';
        document.getElementById('calendarContainer').style.display = 'none';
        
        const month = document.getElementById('monthFilter').value || currentDate.toISOString().slice(0, 7);
        const [year, monthNum] = month.split('-');
        currentDate = new Date(year, monthNum - 1, 1);
        
        const startDate = new Date(year, monthNum - 1, 1).toISOString().split('T')[0];
        const endDate = new Date(year, monthNum, 0).toISOString().split('T')[0];
        
        const queryParams = new URLSearchParams();
        queryParams.append('start_date', startDate);
        queryParams.append('end_date', endDate);
        
        const professionalId = document.getElementById('professionalFilter').value;
        const status = document.getElementById('statusFilter').value;
        
        if (professionalId) queryParams.append('professional_id', professionalId);
        if (status) queryParams.append('status', status);
        
        const response = await apiRequest(`/v1/appointments?${queryParams.toString()}`);
        appointments = Array.isArray(response.data) ? response.data : [];
        
        renderCalendar();
    } catch (error) {
        console.error('Erro ao carregar calendário:', error);
        showAlert('Erro ao carregar calendário: ' + error.message, 'danger');
    } finally {
        document.getElementById('loadingCalendar').style.display = 'none';
        document.getElementById('calendarContainer').style.display = 'block';
    }
}

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                       'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    document.getElementById('currentMonthYear').textContent = `${monthNames[month]} ${year}`;
    
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';
    
    // Cabeçalho dos dias da semana
    const weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    weekDays.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'col text-center fw-bold';
        dayHeader.textContent = day;
        grid.appendChild(dayHeader);
    });
    
    // Dias vazios no início
    for (let i = 0; i < startingDayOfWeek; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'col border rounded p-2';
        emptyDay.style.minHeight = '100px';
        grid.appendChild(emptyDay);
    }
    
    // Dias do mês
    for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'col border rounded p-2';
        dayCell.style.minHeight = '100px';
        
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayAppointments = appointments.filter(apt => apt.appointment_date === dateStr);
        
        dayCell.innerHTML = `
            <div class="fw-bold mb-2">${day}</div>
            <div class="small">
                ${dayAppointments.map(apt => {
                    const statusBadge = apt.status === 'scheduled' ? 'bg-secondary' :
                                      apt.status === 'confirmed' ? 'bg-primary' :
                                      apt.status === 'completed' ? 'bg-success' : 'bg-warning';
                    return `
                        <div class="badge ${statusBadge} mb-1 d-block" style="font-size: 0.7rem;">
                            ${apt.appointment_time} - Pet #${apt.pet_id}
                        </div>
                    `;
                }).join('')}
            </div>
        `;
        
        if (dayAppointments.length > 0) {
            dayCell.style.cursor = 'pointer';
            dayCell.onclick = () => {
                window.location.href = `/appointments?start_date=${dateStr}&end_date=${dateStr}`;
            };
        }
        
        grid.appendChild(dayCell);
    }
}

async function loadProfessionalsForSelect() {
    try {
        const response = await apiRequest('/v1/professionals');
        professionals = Array.isArray(response.data) ? response.data : [];
        
        const select = document.getElementById('professionalFilter');
        professionals.forEach(prof => {
            if (prof.status === 'active') {
                const option = document.createElement('option');
                option.value = prof.id;
                option.textContent = prof.name || 'Profissional #' + prof.id;
                select.appendChild(option);
            }
        });
    } catch (error) {
        console.error('Erro ao carregar profissionais:', error);
    }
}
</script>

