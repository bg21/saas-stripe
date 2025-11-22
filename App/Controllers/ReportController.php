<?php

namespace App\Controllers;

use App\Services\ReportService;
use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\PermissionHelper;
use App\Utils\ResponseHelper;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Pet;
use App\Models\Client;
use Flight;
use Config;

/**
 * Controller para gerenciar relatórios e analytics
 */
class ReportController
{
    private ReportService $reportService;

    public function __construct(StripeService $stripeService)
    {
        $this->reportService = new ReportService($stripeService);
    }

    /**
     * Obtém receita por período
     * GET /v1/reports/revenue
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function revenue(): void
    {
        try {
            // Verifica permissão (só verifica se for autenticação de usuário)
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $revenue = $this->reportService->getRevenue($tenantId, $period);

            ResponseHelper::sendSuccess($revenue);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de receita',
                'REPORT_REVENUE_ERROR',
                ['action' => 'get_revenue_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém estatísticas de assinaturas
     * GET /v1/reports/subscriptions
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function subscriptions(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getSubscriptionsStats($tenantId, $period);

            ResponseHelper::sendSuccess($stats);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de assinaturas',
                'REPORT_SUBSCRIPTIONS_ERROR',
                ['action' => 'get_subscriptions_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém taxa de churn
     * GET /v1/reports/churn
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function churn(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $churn = $this->reportService->getChurnRate($tenantId, $period);

            ResponseHelper::sendSuccess($churn);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de churn',
                'REPORT_CHURN_ERROR',
                ['action' => 'get_churn_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém estatísticas de clientes
     * GET /v1/reports/customers
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function customers(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getCustomersStats($tenantId, $period);

            ResponseHelper::sendSuccess($stats);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de clientes',
                'REPORT_CUSTOMERS_ERROR',
                ['action' => 'get_customers_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém estatísticas de pagamentos
     * GET /v1/reports/payments
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function payments(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $stats = $this->reportService->getPaymentsStats($tenantId, $period);

            ResponseHelper::sendSuccess($stats);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de pagamentos',
                'REPORT_PAYMENTS_ERROR',
                ['action' => 'get_payments_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém MRR (Monthly Recurring Revenue)
     * GET /v1/reports/mrr
     */
    public function mrr(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $mrr = $this->reportService->getMRR($tenantId);

            ResponseHelper::sendSuccess($mrr);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter MRR',
                'REPORT_MRR_ERROR',
                ['action' => 'get_mrr_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Obtém ARR (Annual Recurring Revenue)
     * GET /v1/reports/arr
     */
    public function arr(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_revenue_report']);
                return;
            }

            $arr = $this->reportService->getARR($tenantId);

            ResponseHelper::sendSuccess($arr);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter ARR',
                'REPORT_ARR_ERROR',
                ['action' => 'get_arr_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Relatório de agendamentos da clínica
     * GET /v1/reports/clinic/appointments
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     *   - professional_id: Filtrar por profissional
     *   - status: Filtrar por status
     */
    public function clinicAppointments(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_clinic_appointments_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $appointmentModel = new Appointment();
            $filters = ['tenant_id' => $tenantId];
            
            // Filtros adicionais
            if (!empty($queryParams['professional_id'])) {
                $filters['professional_id'] = (int)$queryParams['professional_id'];
            }
            if (!empty($queryParams['status'])) {
                $filters['status'] = $queryParams['status'];
            }
            
            // Filtro por período
            if ($period['start_date']) {
                $filters['appointment_date >='] = $period['start_date'];
            }
            if ($period['end_date']) {
                $filters['appointment_date <='] = $period['end_date'];
            }
            
            $appointments = $appointmentModel->findByTenant($tenantId, $filters);
            
            // Estatísticas
            $total = count($appointments);
            $byStatus = [];
            $byProfessional = [];
            $byDate = [];
            $cancelled = 0;
            
            foreach ($appointments as $apt) {
                // Por status
                $status = $apt['status'] ?? 'scheduled';
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
                
                // Por profissional
                $profId = $apt['professional_id'] ?? 0;
                $byProfessional[$profId] = ($byProfessional[$profId] ?? 0) + 1;
                
                // Por data
                $date = $apt['appointment_date'] ?? '';
                if ($date) {
                    $byDate[$date] = ($byDate[$date] ?? 0) + 1;
                }
                
                // Cancelados
                if ($status === 'cancelled') {
                    $cancelled++;
                }
            }
            
            $cancellationRate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0;
            
            // Busca nomes dos profissionais
            $professionalModel = new Professional();
            $professionals = $professionalModel->findByTenant($tenantId);
            $professionalNames = [];
            foreach ($professionals as $prof) {
                $professionalNames[$prof['id']] = $prof['user_id'] ?? null;
            }
            
            // Enriquece com nomes de usuários
            $userModel = new \App\Models\User();
            foreach ($professionalNames as $profId => $userId) {
                if ($userId) {
                    $user = $userModel->findById($userId);
                    if ($user) {
                        $professionalNames[$profId] = $user['name'] ?? "Profissional #{$profId}";
                    }
                }
            }
            
            $result = [
                'period' => $period,
                'summary' => [
                    'total' => $total,
                    'cancelled' => $cancelled,
                    'cancellation_rate' => $cancellationRate,
                    'by_status' => $byStatus,
                ],
                'by_professional' => array_map(function($count, $profId) use ($professionalNames) {
                    return [
                        'professional_id' => (int)$profId,
                        'professional_name' => $professionalNames[$profId] ?? "Profissional #{$profId}",
                        'count' => $count
                    ];
                }, $byProfessional, array_keys($byProfessional)),
                'by_date' => $byDate,
                'appointments' => array_slice($appointments, 0, 100) // Limita a 100 para não sobrecarregar
            ];
            
            ResponseHelper::sendSuccess($result, 200, 'Relatório de agendamentos gerado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de agendamentos',
                'REPORT_CLINIC_APPOINTMENTS_ERROR',
                ['action' => 'get_clinic_appointments_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Relatório de profissionais da clínica
     * GET /v1/reports/clinic/professionals
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function clinicProfessionals(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_clinic_professionals_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $professionalModel = new Professional();
            $appointmentModel = new Appointment();
            
            $professionals = $professionalModel->findByTenant($tenantId, ['status' => 'active']);
            $userModel = new \App\Models\User();
            
            $result = [];
            
            foreach ($professionals as $prof) {
                // Busca agendamentos do profissional no período
                $filters = [
                    'tenant_id' => $tenantId,
                    'professional_id' => $prof['id']
                ];
                
                if ($period['start_date']) {
                    $filters['appointment_date >='] = $period['start_date'];
                }
                if ($period['end_date']) {
                    $filters['appointment_date <='] = $period['end_date'];
                }
                
                $appointments = $appointmentModel->findByTenant($tenantId, $filters);
                
                // Calcula estatísticas
                $totalAppointments = count($appointments);
                $completed = 0;
                $cancelled = 0;
                $totalMinutes = 0;
                
                foreach ($appointments as $apt) {
                    if ($apt['status'] === 'completed') {
                        $completed++;
                        $totalMinutes += $apt['duration_minutes'] ?? 30;
                    } elseif ($apt['status'] === 'cancelled') {
                        $cancelled++;
                    }
                }
                
                $hoursWorked = round($totalMinutes / 60, 2);
                
                // Busca nome do usuário
                $user = $userModel->findById($prof['user_id'] ?? 0);
                $professionalName = $user['name'] ?? "Profissional #{$prof['id']}";
                
                $result[] = [
                    'professional_id' => $prof['id'],
                    'professional_name' => $professionalName,
                    'crmv' => $prof['crmv'] ?? null,
                    'total_appointments' => $totalAppointments,
                    'completed_appointments' => $completed,
                    'cancelled_appointments' => $cancelled,
                    'hours_worked' => $hoursWorked,
                    'occupation_rate' => $totalAppointments > 0 ? round(($completed / $totalAppointments) * 100, 2) : 0
                ];
            }
            
            ResponseHelper::sendSuccess($result, 200, 'Relatório de profissionais gerado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de profissionais',
                'REPORT_CLINIC_PROFESSIONALS_ERROR',
                ['action' => 'get_clinic_professionals_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Relatório de pets atendidos
     * GET /v1/reports/clinic/pets
     * 
     * Query params opcionais:
     *   - period: Período predefinido (today, week, month, year, last_month, last_year)
     *   - start_date: Data inicial (formato: YYYY-MM-DD) - requer end_date
     *   - end_date: Data final (formato: YYYY-MM-DD) - requer start_date
     */
    public function clinicPets(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_clinic_pets_report']);
                return;
            }

            $queryParams = Flight::request()->query->getData();
            $period = $this->reportService->processPeriodFilter($queryParams);
            
            $appointmentModel = new Appointment();
            $petModel = new Pet();
            $clientModel = new Client();
            
            // Busca agendamentos no período
            $filters = ['tenant_id' => $tenantId];
            
            if ($period['start_date']) {
                $filters['appointment_date >='] = $period['start_date'];
            }
            if ($period['end_date']) {
                $filters['appointment_date <='] = $period['end_date'];
            }
            
            $appointments = $appointmentModel->findByTenant($tenantId, $filters);
            
            // Estatísticas
            $uniquePets = [];
            $bySpecies = [];
            $clientReturns = [];
            
            foreach ($appointments as $apt) {
                $petId = $apt['pet_id'] ?? null;
                if ($petId) {
                    $uniquePets[$petId] = true;
                    
                    // Busca dados do pet
                    $pet = $petModel->findById($petId);
                    if ($pet && $pet['species']) {
                        $species = $pet['species'];
                        $bySpecies[$species] = ($bySpecies[$species] ?? 0) + 1;
                    }
                    
                    // Conta retornos de clientes
                    $clientId = $apt['client_id'] ?? null;
                    if ($clientId) {
                        $clientReturns[$clientId] = ($clientReturns[$clientId] ?? 0) + 1;
                    }
                }
            }
            
            // Conta clientes com múltiplos agendamentos (retornos)
            $returningClients = 0;
            foreach ($clientReturns as $count) {
                if ($count > 1) {
                    $returningClients++;
                }
            }
            
            // Top espécies
            arsort($bySpecies);
            $topSpecies = array_slice($bySpecies, 0, 10, true);
            
            $result = [
                'period' => $period,
                'summary' => [
                    'unique_pets' => count($uniquePets),
                    'total_appointments' => count($appointments),
                    'returning_clients' => $returningClients,
                    'return_rate' => count($clientReturns) > 0 
                        ? round(($returningClients / count($clientReturns)) * 100, 2) 
                        : 0
                ],
                'by_species' => array_map(function($count, $species) {
                    return [
                        'species' => $species,
                        'count' => $count
                    ];
                }, $topSpecies, array_keys($topSpecies))
            ];
            
            ResponseHelper::sendSuccess($result, 200, 'Relatório de pets gerado com sucesso');
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter relatório de pets',
                'REPORT_CLINIC_PETS_ERROR',
                ['action' => 'get_clinic_pets_report', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }

    /**
     * Dashboard da clínica
     * GET /v1/reports/clinic/dashboard
     */
    public function clinicDashboard(): void
    {
        try {
            PermissionHelper::require('view_reports');

            $tenantId = Flight::get('tenant_id');
            
            if ($tenantId === null) {
                ResponseHelper::sendUnauthorizedError('Não autenticado', ['action' => 'get_clinic_dashboard']);
                return;
            }

            $appointmentModel = new Appointment();
            $today = date('Y-m-d');
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            
            // Agendamentos hoje
            $todayAppointments = $appointmentModel->findByTenant($tenantId, [
                'appointment_date' => $today
            ]);
            
            // Agendamentos da semana
            $weekAppointments = $appointmentModel->findByTenant($tenantId, [
                'appointment_date >=' => $weekStart,
                'appointment_date <=' => $weekEnd
            ]);
            
            // Próximos agendamentos (próximos 7 dias)
            $nextWeek = date('Y-m-d', strtotime('+7 days'));
            $upcomingAppointments = $appointmentModel->findByTenant($tenantId, [
                'appointment_date >=' => $today,
                'appointment_date <=' => $nextWeek
            ]);
            
            // Ordena próximos agendamentos por data e hora
            usort($upcomingAppointments, function($a, $b) {
                $dateCompare = strcmp($a['appointment_date'], $b['appointment_date']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                return strcmp($a['appointment_time'], $b['appointment_time']);
            });
            
            // Estatísticas da semana
            $weekStats = [
                'total' => count($weekAppointments),
                'scheduled' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0
            ];
            
            foreach ($weekAppointments as $apt) {
                $status = $apt['status'] ?? 'scheduled';
                if (isset($weekStats[$status])) {
                    $weekStats[$status]++;
                }
            }
            
            // Taxa de ocupação (agendamentos completados / total)
            $occupationRate = $weekStats['total'] > 0 
                ? round(($weekStats['completed'] / $weekStats['total']) * 100, 2) 
                : 0;
            
            // Limita próximos agendamentos a 10
            $upcomingAppointments = array_slice($upcomingAppointments, 0, 10);
            
            // Enriquece próximos agendamentos com dados de pet e cliente
            $petModel = new Pet();
            $clientModel = new Client();
            $professionalModel = new Professional();
            $userModel = new \App\Models\User();
            
            foreach ($upcomingAppointments as &$apt) {
                $pet = $petModel->findById($apt['pet_id'] ?? 0);
                $client = $clientModel->findById($apt['client_id'] ?? 0);
                $prof = $professionalModel->findById($apt['professional_id'] ?? 0);
                
                $apt['pet_name'] = $pet['name'] ?? 'N/A';
                $apt['client_name'] = $client['name'] ?? 'N/A';
                
                if ($prof && $prof['user_id']) {
                    $user = $userModel->findById($prof['user_id']);
                    $apt['professional_name'] = $user['name'] ?? "Profissional #{$apt['professional_id']}";
                } else {
                    $apt['professional_name'] = "Profissional #{$apt['professional_id']}";
                }
            }
            
            $result = [
                'today' => [
                    'total' => count($todayAppointments),
                    'appointments' => array_slice($todayAppointments, 0, 10)
                ],
                'week' => [
                    'start_date' => $weekStart,
                    'end_date' => $weekEnd,
                    'stats' => $weekStats,
                    'occupation_rate' => $occupationRate
                ],
                'upcoming' => [
                    'total' => count($upcomingAppointments),
                    'appointments' => $upcomingAppointments
                ]
            ];
            
            ResponseHelper::sendSuccess($result, 200, 'Dashboard da clínica gerado com sucesso');
        } catch (\Exception $e) {
            // Log detalhado do erro para debug
            \App\Services\Logger::error('Erro ao obter dashboard da clínica', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => Flight::get('tenant_id') ?? null
            ]);
            
            ResponseHelper::sendGenericError(
                $e,
                'Erro ao obter dashboard da clínica',
                'REPORT_CLINIC_DASHBOARD_ERROR',
                ['action' => 'get_clinic_dashboard', 'tenant_id' => Flight::get('tenant_id') ?? null]
            );
        }
    }
}

