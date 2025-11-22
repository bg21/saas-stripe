<?php

use Phinx\Seed\AbstractSeed;

/**
 * Seed para criar dados de teste da clínica veterinária
 * 
 * Cria:
 * - Especialidades
 * - Usuários (profissionais)
 * - Profissionais (veterinários)
 * - Clientes
 * - Pets
 * - Configuração da clínica
 * - Agendas dos profissionais
 * - Agendamentos de exemplo
 */
class VeterinaryClinicSeed extends AbstractSeed
{
    public function run(): void
    {
        // Busca o primeiro tenant
        $adapter = $this->getAdapter();
        $tenants = $adapter->fetchAll("SELECT * FROM tenants LIMIT 1");
        
        if (empty($tenants)) {
            echo "⚠️  Nenhum tenant encontrado. Execute primeiro o InitialSeed ou crie um tenant.\n";
            return;
        }
        
        $tenant = $tenants[0];
        $tenantId = (int)$tenant['id'];
        
        echo "🏥 Criando dados de teste da clínica veterinária para o tenant: {$tenant['name']} (ID: {$tenantId})\n\n";
        
        $now = date('Y-m-d H:i:s');
        
        // ============================================
        // 1. ESPECIALIDADES
        // ============================================
        echo "📋 Criando especialidades...\n";
        
        $specialties = [
            [
                'tenant_id' => $tenantId,
                'name' => 'Clínica Geral',
                'description' => 'Atendimento clínico geral para animais de pequeno e grande porte',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Cirurgia',
                'description' => 'Procedimentos cirúrgicos e castrações',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Dermatologia',
                'description' => 'Tratamento de doenças de pele e alergias',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ortopedia',
                'description' => 'Tratamento de fraturas e problemas ósseos',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Cardiologia',
                'description' => 'Exames cardíacos e tratamento de doenças do coração',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];
        
        $specialtyIds = [];
        foreach ($specialties as $specialty) {
            try {
                $this->table('specialties')->insert($specialty)->saveData();
                $specialtyId = $adapter->getConnection()->lastInsertId();
                $specialtyIds[] = $specialtyId;
                echo "  ✅ Especialidade criada: {$specialty['name']} (ID: {$specialtyId})\n";
            } catch (\Exception $e) {
                // Busca ID se já existe
                $existing = $adapter->fetchRow(
                    "SELECT id FROM specialties WHERE tenant_id = {$tenantId} AND name = " . $adapter->getConnection()->quote($specialty['name'])
                );
                if ($existing) {
                    $specialtyIds[] = (int)$existing['id'];
                    echo "  ℹ️  Especialidade já existe: {$specialty['name']} (ID: {$existing['id']})\n";
                } else {
                    echo "  ⚠️  Erro ao criar especialidade {$specialty['name']}: {$e->getMessage()}\n";
                }
            }
        }
        
        // ============================================
        // 2. USUÁRIOS (PROFISSIONAIS)
        // ============================================
        echo "\n👨‍⚕️ Criando usuários profissionais...\n";
        
        $professionalsData = [
            [
                'email' => 'dr.silva@clinica.com',
                'password' => 'senha123',
                'name' => 'Dr. João Silva',
                'crmv' => 'CRMV-SP 12345',
                'specialties' => [$specialtyIds[0], $specialtyIds[1]], // Clínica Geral, Cirurgia
                'role' => 'editor'
            ],
            [
                'email' => 'dra.santos@clinica.com',
                'password' => 'senha123',
                'name' => 'Dra. Maria Santos',
                'crmv' => 'CRMV-SP 67890',
                'specialties' => [$specialtyIds[0], $specialtyIds[2]], // Clínica Geral, Dermatologia
                'role' => 'editor'
            ],
            [
                'email' => 'dr.oliveira@clinica.com',
                'password' => 'senha123',
                'name' => 'Dr. Carlos Oliveira',
                'crmv' => 'CRMV-SP 11111',
                'specialties' => [$specialtyIds[3], $specialtyIds[4]], // Ortopedia, Cardiologia
                'role' => 'editor'
            ],
            [
                'email' => 'atendente@clinica.com',
                'password' => 'senha123',
                'name' => 'Ana Paula - Atendente',
                'crmv' => null,
                'specialties' => [],
                'role' => 'viewer'
            ]
        ];
        
        $userIds = [];
        foreach ($professionalsData as $profData) {
            try {
                // Verifica se usuário já existe
                $existingUser = $adapter->fetchRow(
                    "SELECT id FROM users WHERE tenant_id = {$tenantId} AND email = " . $adapter->getConnection()->quote($profData['email'])
                );
                
                if ($existingUser) {
                    $userId = (int)$existingUser['id'];
                    echo "  ℹ️  Usuário já existe: {$profData['email']} (ID: {$userId})\n";
                } else {
                    // Cria usuário
                    $passwordHash = password_hash($profData['password'], PASSWORD_BCRYPT);
                    $userData = [
                        'tenant_id' => $tenantId,
                        'email' => $profData['email'],
                        'password_hash' => $passwordHash,
                        'name' => $profData['name'],
                        'status' => 'active',
                        'role' => $profData['role'],
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                    
                    $this->table('users')->insert($userData)->saveData();
                    $userId = (int)$adapter->getConnection()->lastInsertId();
                    echo "  ✅ Usuário criado: {$profData['email']} (ID: {$userId})\n";
                }
                
                $userIds[] = [
                    'user_id' => $userId,
                    'crmv' => $profData['crmv'],
                    'specialties' => $profData['specialties'],
                    'name' => $profData['name']
                ];
            } catch (\Exception $e) {
                echo "  ⚠️  Erro ao criar usuário {$profData['email']}: {$e->getMessage()}\n";
            }
        }
        
        // ============================================
        // 3. PROFISSIONAIS
        // ============================================
        echo "\n👨‍⚕️ Criando profissionais...\n";
        
        $professionalIds = [];
        foreach ($userIds as $index => $userData) {
            if ($userData['crmv'] === null && $index === 3) {
                // Atendente não precisa de profissional
                continue;
            }
            
            try {
                // Verifica se profissional já existe
                $existingProf = $adapter->fetchRow(
                    "SELECT id FROM professionals WHERE tenant_id = {$tenantId} AND user_id = {$userData['user_id']}"
                );
                
                if ($existingProf) {
                    $profId = (int)$existingProf['id'];
                    echo "  ℹ️  Profissional já existe: {$userData['name']} (ID: {$profId})\n";
                } else {
                    $profData = [
                        'tenant_id' => $tenantId,
                        'user_id' => $userData['user_id'],
                        'crmv' => $userData['crmv'],
                        'specialties' => json_encode($userData['specialties']),
                        'default_consultation_duration' => 30,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                    
                    $this->table('professionals')->insert($profData)->saveData();
                    $profId = (int)$adapter->getConnection()->lastInsertId();
                    echo "  ✅ Profissional criado: {$userData['name']} (ID: {$profId})\n";
                }
                
                $professionalIds[] = $profId;
            } catch (\Exception $e) {
                echo "  ⚠️  Erro ao criar profissional {$userData['name']}: {$e->getMessage()}\n";
            }
        }
        
        // ============================================
        // 4. CLIENTES
        // ============================================
        echo "\n👥 Criando clientes...\n";
        
        $clients = [
            [
                'name' => 'Pedro Almeida',
                'email' => 'pedro.almeida@email.com',
                'phone' => '(11) 98765-4321',
                'phone_alt' => '(11) 3456-7890',
                'cpf' => '123.456.789-00',
                'address' => 'Rua das Flores, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01234-567',
                'notes' => 'Cliente preferencial, sempre pontual'
            ],
            [
                'name' => 'Juliana Costa',
                'email' => 'juliana.costa@email.com',
                'phone' => '(11) 91234-5678',
                'phone_alt' => null,
                'cpf' => '234.567.890-11',
                'address' => 'Av. Paulista, 1000',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01310-100',
                'notes' => null
            ],
            [
                'name' => 'Roberto Ferreira',
                'email' => 'roberto.ferreira@email.com',
                'phone' => '(11) 99876-5432',
                'phone_alt' => null,
                'cpf' => '345.678.901-22',
                'address' => 'Rua Augusta, 500',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01305-000',
                'notes' => 'Prefere atendimento pela manhã'
            ],
            [
                'name' => 'Fernanda Lima',
                'email' => 'fernanda.lima@email.com',
                'phone' => '(11) 97654-3210',
                'phone_alt' => '(11) 2345-6789',
                'cpf' => '456.789.012-33',
                'address' => 'Rua Consolação, 200',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01302-000',
                'notes' => null
            ],
            [
                'name' => 'Marcos Souza',
                'email' => 'marcos.souza@email.com',
                'phone' => '(11) 95555-1234',
                'phone_alt' => null,
                'cpf' => '567.890.123-44',
                'address' => 'Rua Bela Cintra, 300',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01415-000',
                'notes' => null
            ]
        ];
        
        $clientIds = [];
        foreach ($clients as $client) {
            try {
                // Verifica se cliente já existe (por CPF ou email)
                $existingClient = null;
                if (!empty($client['cpf'])) {
                    $existingClient = $adapter->fetchRow(
                        "SELECT id FROM clients WHERE tenant_id = {$tenantId} AND cpf = " . $adapter->getConnection()->quote($client['cpf'])
                    );
                }
                if (!$existingClient && !empty($client['email'])) {
                    $existingClient = $adapter->fetchRow(
                        "SELECT id FROM clients WHERE tenant_id = {$tenantId} AND email = " . $adapter->getConnection()->quote($client['email'])
                    );
                }
                
                if ($existingClient) {
                    $clientId = (int)$existingClient['id'];
                    echo "  ℹ️  Cliente já existe: {$client['name']} (ID: {$clientId})\n";
                } else {
                    $clientData = [
                        'tenant_id' => $tenantId,
                        'name' => $client['name'],
                        'cpf' => $client['cpf'],
                        'email' => $client['email'],
                        'phone' => $client['phone'],
                        'phone_alt' => $client['phone_alt'],
                        'address' => $client['address'],
                        'city' => $client['city'],
                        'state' => $client['state'],
                        'postal_code' => $client['postal_code'],
                        'notes' => $client['notes'],
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                    
                    $this->table('clients')->insert($clientData)->saveData();
                    $clientId = (int)$adapter->getConnection()->lastInsertId();
                    echo "  ✅ Cliente criado: {$client['name']} (ID: {$clientId})\n";
                }
                
                $clientIds[] = $clientId;
            } catch (\Exception $e) {
                echo "  ⚠️  Erro ao criar cliente {$client['name']}: {$e->getMessage()}\n";
            }
        }
        
        // ============================================
        // 5. PETS
        // ============================================
        echo "\n🐾 Criando pets...\n";
        
        $pets = [
            [
                'client_id' => $clientIds[0],
                'name' => 'Rex',
                'species' => 'cachorro',
                'breed' => 'Golden Retriever',
                'gender' => 'male',
                'birth_date' => '2020-05-15',
                'weight' => 28.5,
                'color' => 'Dourado',
                'microchip' => '123456789012345',
                'notes' => 'Muito dócil, gosta de brincar'
            ],
            [
                'client_id' => $clientIds[0],
                'name' => 'Luna',
                'species' => 'cachorro',
                'breed' => 'Labrador',
                'gender' => 'female',
                'birth_date' => '2021-03-20',
                'weight' => 22.0,
                'color' => 'Preto',
                'microchip' => null,
                'notes' => null
            ],
            [
                'client_id' => $clientIds[1],
                'name' => 'Mimi',
                'species' => 'gato',
                'breed' => 'Persa',
                'gender' => 'female',
                'birth_date' => '2019-11-10',
                'weight' => 4.2,
                'color' => 'Branco',
                'microchip' => '987654321098765',
                'notes' => 'Necessita cuidados especiais com pelos'
            ],
            [
                'client_id' => $clientIds[2],
                'name' => 'Thor',
                'species' => 'cachorro',
                'breed' => 'Pastor Alemão',
                'gender' => 'male',
                'birth_date' => '2018-08-25',
                'weight' => 35.0,
                'color' => 'Preto e Marrom',
                'microchip' => '555555555555555',
                'notes' => 'Cão de guarda, muito protetor'
            ],
            [
                'client_id' => $clientIds[2],
                'name' => 'Bella',
                'species' => 'cachorro',
                'breed' => 'Bulldog Francês',
                'gender' => 'female',
                'birth_date' => '2022-01-12',
                'weight' => 8.5,
                'color' => 'Branco e Marrom',
                'microchip' => null,
                'notes' => null
            ],
            [
                'client_id' => $clientIds[3],
                'name' => 'Nina',
                'species' => 'gato',
                'breed' => 'Siamês',
                'gender' => 'female',
                'birth_date' => '2020-07-30',
                'weight' => 3.8,
                'color' => 'Bege e Marrom',
                'microchip' => null,
                'notes' => null
            ],
            [
                'client_id' => $clientIds[4],
                'name' => 'Max',
                'species' => 'cachorro',
                'breed' => 'Beagle',
                'gender' => 'male',
                'birth_date' => '2021-09-05',
                'weight' => 12.0,
                'color' => 'Tricolor',
                'microchip' => '111111111111111',
                'notes' => 'Muito ativo, adora correr'
            ]
        ];
        
        $petIds = [];
        foreach ($pets as $pet) {
            try {
                $petData = [
                    'tenant_id' => $tenantId,
                    'client_id' => $pet['client_id'],
                    'name' => $pet['name'],
                    'species' => $pet['species'],
                    'breed' => $pet['breed'],
                    'gender' => $pet['gender'],
                    'birth_date' => $pet['birth_date'],
                    'weight' => $pet['weight'],
                    'color' => $pet['color'],
                    'microchip' => $pet['microchip'],
                    'notes' => $pet['notes'],
                    'created_at' => $now,
                    'updated_at' => $now
                ];
                
                $this->table('pets')->insert($petData)->saveData();
                $petId = (int)$adapter->getConnection()->lastInsertId();
                $petIds[] = $petId;
                echo "  ✅ Pet criado: {$pet['name']} ({$pet['species']}) - Cliente ID: {$pet['client_id']} (ID: {$petId})\n";
            } catch (\Exception $e) {
                echo "  ⚠️  Erro ao criar pet {$pet['name']}: {$e->getMessage()}\n";
            }
        }
        
        // ============================================
        // 6. CONFIGURAÇÃO DA CLÍNICA
        // ============================================
        echo "\n⚙️ Criando configuração da clínica...\n";
        
        try {
            $existingConfig = $adapter->fetchRow(
                "SELECT id FROM clinic_configurations WHERE tenant_id = {$tenantId}"
            );
            
            if ($existingConfig) {
                echo "  ℹ️  Configuração já existe (ID: {$existingConfig['id']})\n";
            } else {
                $configData = [
                    'tenant_id' => $tenantId,
                    'opening_time_monday' => '08:00:00',
                    'closing_time_monday' => '18:00:00',
                    'opening_time_tuesday' => '08:00:00',
                    'closing_time_tuesday' => '18:00:00',
                    'opening_time_wednesday' => '08:00:00',
                    'closing_time_wednesday' => '18:00:00',
                    'opening_time_thursday' => '08:00:00',
                    'closing_time_thursday' => '18:00:00',
                    'opening_time_friday' => '08:00:00',
                    'closing_time_friday' => '18:00:00',
                    'opening_time_saturday' => '08:00:00',
                    'closing_time_saturday' => '12:00:00',
                    'opening_time_sunday' => null,
                    'closing_time_sunday' => null,
                    'default_appointment_duration' => 30,
                    'time_slot_interval' => 15,
                    'allow_online_booking' => true,
                    'require_confirmation' => false,
                    'cancellation_hours' => 24,
                    'created_at' => $now,
                    'updated_at' => $now
                ];
                
                $this->table('clinic_configurations')->insert($configData)->saveData();
                echo "  ✅ Configuração da clínica criada\n";
            }
        } catch (\Exception $e) {
            echo "  ⚠️  Erro ao criar configuração: {$e->getMessage()}\n";
        }
        
        // ============================================
        // 7. AGENDAS DOS PROFISSIONAIS
        // ============================================
        echo "\n📅 Criando agendas dos profissionais...\n";
        
        // Agenda padrão: Segunda a Sexta, 8h às 18h
        $scheduleDays = [
            ['day' => 1, 'start' => '08:00:00', 'end' => '18:00:00'], // Segunda
            ['day' => 2, 'start' => '08:00:00', 'end' => '18:00:00'], // Terça
            ['day' => 3, 'start' => '08:00:00', 'end' => '18:00:00'], // Quarta
            ['day' => 4, 'start' => '08:00:00', 'end' => '18:00:00'], // Quinta
            ['day' => 5, 'start' => '08:00:00', 'end' => '18:00:00'], // Sexta
            ['day' => 6, 'start' => '08:00:00', 'end' => '12:00:00'], // Sábado
        ];
        
        foreach ($professionalIds as $profId) {
            foreach ($scheduleDays as $day) {
                try {
                    $existingSchedule = $adapter->fetchRow(
                        "SELECT id FROM professional_schedules WHERE tenant_id = {$tenantId} AND professional_id = {$profId} AND day_of_week = {$day['day']}"
                    );
                    
                    if ($existingSchedule) {
                        continue; // Já existe
                    }
                    
                    $scheduleData = [
                        'tenant_id' => $tenantId,
                        'professional_id' => $profId,
                        'day_of_week' => $day['day'],
                        'start_time' => $day['start'],
                        'end_time' => $day['end'],
                        'is_available' => true,
                        'created_at' => $now,
                        'updated_at' => $now
                    ];
                    
                    $this->table('professional_schedules')->insert($scheduleData)->saveData();
                } catch (\Exception $e) {
                    // Ignora erros de duplicata
                }
            }
            echo "  ✅ Agenda criada para profissional ID: {$profId}\n";
        }
        
        // ============================================
        // 8. AGENDAMENTOS DE EXEMPLO
        // ============================================
        echo "\n📋 Criando agendamentos de exemplo...\n";
        
        // Agendamentos para os próximos dias
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $dayAfterTomorrow = date('Y-m-d', strtotime('+2 days'));
        $nextWeek = date('Y-m-d', strtotime('+7 days'));
        
        $appointments = [
            [
                'professional_id' => $professionalIds[0],
                'client_id' => $clientIds[0],
                'pet_id' => $petIds[0],
                'specialty_id' => $specialtyIds[0],
                'appointment_date' => $tomorrow,
                'appointment_time' => '09:00:00',
                'duration_minutes' => 30,
                'status' => 'scheduled',
                'notes' => 'Consulta de rotina'
            ],
            [
                'professional_id' => $professionalIds[0],
                'client_id' => $clientIds[1],
                'pet_id' => $petIds[2],
                'specialty_id' => $specialtyIds[0],
                'appointment_date' => $tomorrow,
                'appointment_time' => '10:30:00',
                'duration_minutes' => 30,
                'status' => 'confirmed',
                'notes' => 'Vacinação anual'
            ],
            [
                'professional_id' => $professionalIds[1],
                'client_id' => $clientIds[2],
                'pet_id' => $petIds[3],
                'specialty_id' => $specialtyIds[2],
                'appointment_date' => $dayAfterTomorrow,
                'appointment_time' => '14:00:00',
                'duration_minutes' => 45,
                'status' => 'scheduled',
                'notes' => 'Problema de pele'
            ],
            [
                'professional_id' => $professionalIds[2],
                'client_id' => $clientIds[3],
                'pet_id' => $petIds[5],
                'specialty_id' => $specialtyIds[3],
                'appointment_date' => $nextWeek,
                'appointment_time' => '11:00:00',
                'duration_minutes' => 60,
                'status' => 'scheduled',
                'notes' => 'Exame ortopédico'
            ],
            [
                'professional_id' => $professionalIds[0],
                'client_id' => $clientIds[4],
                'pet_id' => $petIds[6],
                'specialty_id' => $specialtyIds[0],
                'appointment_date' => $nextWeek,
                'appointment_time' => '15:30:00',
                'duration_minutes' => 30,
                'status' => 'scheduled',
                'notes' => 'Consulta de check-up'
            ]
        ];
        
        foreach ($appointments as $appointment) {
            try {
                $appointmentData = [
                    'tenant_id' => $tenantId,
                    'professional_id' => $appointment['professional_id'],
                    'client_id' => $appointment['client_id'],
                    'pet_id' => $appointment['pet_id'],
                    'specialty_id' => $appointment['specialty_id'],
                    'appointment_date' => $appointment['appointment_date'],
                    'appointment_time' => $appointment['appointment_time'],
                    'duration_minutes' => $appointment['duration_minutes'],
                    'status' => $appointment['status'],
                    'notes' => $appointment['notes'],
                    'created_at' => $now,
                    'updated_at' => $now
                ];
                
                $this->table('appointments')->insert($appointmentData)->saveData();
                $appointmentId = (int)$adapter->getConnection()->lastInsertId();
                echo "  ✅ Agendamento criado: {$appointment['appointment_date']} às {$appointment['appointment_time']} (ID: {$appointmentId})\n";
            } catch (\Exception $e) {
                echo "  ⚠️  Erro ao criar agendamento: {$e->getMessage()}\n";
            }
        }
        
        // ============================================
        // RESUMO
        // ============================================
        echo "\n✨ Seed da clínica veterinária concluído!\n\n";
        echo "📊 Resumo dos dados criados:\n";
        echo "   - Especialidades: " . count($specialtyIds) . "\n";
        echo "   - Usuários/Profissionais: " . count($userIds) . "\n";
        echo "   - Clientes: " . count($clientIds) . "\n";
        echo "   - Pets: " . count($petIds) . "\n";
        echo "   - Agendamentos: " . count($appointments) . "\n\n";
        echo "🔑 Credenciais de acesso:\n";
        echo "   - Dr. João Silva: dr.silva@clinica.com / senha123\n";
        echo "   - Dra. Maria Santos: dra.santos@clinica.com / senha123\n";
        echo "   - Dr. Carlos Oliveira: dr.oliveira@clinica.com / senha123\n";
        echo "   - Atendente: atendente@clinica.com / senha123\n\n";
        echo "💡 Use estas credenciais para testar o sistema de clínica veterinária.\n";
    }
}

