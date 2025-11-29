<?php

namespace App\Models;

/**
 * Model para gerenciar histórico de mudanças de assinaturas
 */
class SubscriptionHistory extends BaseModel
{
    protected string $table = 'subscription_history';

    /**
     * Tipos de mudança suportados
     */
    public const CHANGE_TYPE_CREATED = 'created';
    public const CHANGE_TYPE_UPDATED = 'updated';
    public const CHANGE_TYPE_CANCELED = 'canceled';
    public const CHANGE_TYPE_REACTIVATED = 'reactivated';
    public const CHANGE_TYPE_PLAN_CHANGED = 'plan_changed';
    public const CHANGE_TYPE_STATUS_CHANGED = 'status_changed';

    /**
     * Origens de mudança suportadas
     */
    public const CHANGED_BY_API = 'api';
    public const CHANGED_BY_WEBHOOK = 'webhook';
    public const CHANGED_BY_ADMIN = 'admin';

    /**
     * Registra uma mudança no histórico
     * 
     * @param int $subscriptionId ID da assinatura
     * @param int $tenantId ID do tenant
     * @param string $changeType Tipo de mudança
     * @param array $oldData Dados antigos (opcional)
     * @param array $newData Dados novos (opcional)
     * @param string|null $changedBy Origem da mudança (api, webhook, admin)
     * @param string|null $description Descrição da mudança (opcional)
     * @param int|null $userId ID do usuário que fez a mudança (opcional, quando via API com autenticação de usuário)
     * @return int ID do registro criado
     */
    public function recordChange(
        int $subscriptionId,
        int $tenantId,
        string $changeType,
        array $oldData = [],
        array $newData = [],
        ?string $changedBy = null,
        ?string $description = null,
        ?int $userId = null
    ): int {
        $historyData = [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'change_type' => $changeType,
            'changed_by' => $changedBy ?? self::CHANGED_BY_API,
            'user_id' => $userId,
            'old_status' => $oldData['status'] ?? null,
            'new_status' => $newData['status'] ?? null,
            'old_plan_id' => $oldData['plan_id'] ?? null,
            'new_plan_id' => $newData['plan_id'] ?? null,
            'old_amount' => isset($oldData['amount']) ? (float) $oldData['amount'] : null,
            'new_amount' => isset($newData['amount']) ? (float) $newData['amount'] : null,
            'old_currency' => $oldData['currency'] ?? null,
            'new_currency' => $newData['currency'] ?? null,
            'old_current_period_end' => isset($oldData['current_period_end']) 
                ? (is_string($oldData['current_period_end']) ? $oldData['current_period_end'] : date('Y-m-d H:i:s', $oldData['current_period_end']))
                : null,
            'new_current_period_end' => isset($newData['current_period_end'])
                ? (is_string($newData['current_period_end']) ? $newData['current_period_end'] : date('Y-m-d H:i:s', $newData['current_period_end']))
                : null,
            'old_cancel_at_period_end' => isset($oldData['cancel_at_period_end']) ? (int) $oldData['cancel_at_period_end'] : null,
            'new_cancel_at_period_end' => isset($newData['cancel_at_period_end']) ? (int) $newData['cancel_at_period_end'] : null,
            'description' => $description,
            'metadata' => !empty($oldData['metadata']) || !empty($newData['metadata']) 
                ? json_encode([
                    'old_metadata' => $oldData['metadata'] ?? null,
                    'new_metadata' => $newData['metadata'] ?? null
                ])
                : null
        ];

        return $this->insert($historyData);
    }

    /**
     * Busca histórico de uma assinatura com filtros opcionais
     * 
     * @param int $subscriptionId ID da assinatura
     * @param int|null $tenantId ID do tenant (para validação de segurança)
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @param array $filters Filtros opcionais:
     *   - change_type: Tipo de mudança (created, updated, canceled, etc.)
     *   - changed_by: Origem da mudança (api, webhook, admin)
     *   - user_id: ID do usuário que fez a mudança
     *   - date_from: Data inicial (Y-m-d H:i:s)
     *   - date_to: Data final (Y-m-d H:i:s)
     * @return array Lista de registros de histórico
     */
    public function findBySubscription(
        int $subscriptionId, 
        ?int $tenantId = null, 
        int $limit = 100, 
        int $offset = 0,
        array $filters = []
    ): array {
        try {
            $sql = "SELECT sh.*, u.email as user_email, u.name as user_name 
                    FROM {$this->table} sh 
                    LEFT JOIN users u ON sh.user_id = u.id 
                    WHERE sh.subscription_id = :subscription_id";
            $params = ['subscription_id' => $subscriptionId];

            // Validação de segurança: se tenant_id fornecido, filtra por ele
            if ($tenantId !== null) {
                $sql .= " AND sh.tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }

            // Filtros opcionais
            if (!empty($filters['change_type'])) {
                $sql .= " AND sh.change_type = :change_type";
                $params['change_type'] = $filters['change_type'];
            }

            if (!empty($filters['changed_by'])) {
                $sql .= " AND sh.changed_by = :changed_by";
                $params['changed_by'] = $filters['changed_by'];
            }

            if (isset($filters['user_id']) && $filters['user_id'] !== null) {
                $sql .= " AND sh.user_id = :user_id";
                $params['user_id'] = (int)$filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND sh.created_at >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND sh.created_at <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }

            $sql .= " ORDER BY sh.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // ✅ CORREÇÃO: Se houver erro (tabela não existe, etc), retorna array vazio
            // Log do erro para debug, mas não quebra a aplicação
            error_log("Erro ao buscar histórico de assinatura: " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            // ✅ Captura qualquer outro tipo de exceção também
            error_log("Erro inesperado ao buscar histórico de assinatura: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Conta total de registros de histórico de uma assinatura com filtros opcionais
     * 
     * @param int $subscriptionId ID da assinatura
     * @param int|null $tenantId ID do tenant (para validação de segurança)
     * @param array $filters Filtros opcionais (mesmos do findBySubscription)
     * @return int Total de registros
     */
    public function countBySubscription(int $subscriptionId, ?int $tenantId = null, array $filters = []): int
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE subscription_id = :subscription_id";
            $params = ['subscription_id' => $subscriptionId];

            if ($tenantId !== null) {
                $sql .= " AND tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }

            // Aplica mesmos filtros do findBySubscription
            if (!empty($filters['change_type'])) {
                $sql .= " AND change_type = :change_type";
                $params['change_type'] = $filters['change_type'];
            }

            if (!empty($filters['changed_by'])) {
                $sql .= " AND changed_by = :changed_by";
                $params['changed_by'] = $filters['changed_by'];
            }

            if (isset($filters['user_id']) && $filters['user_id'] !== null) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = (int)$filters['user_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND created_at >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND created_at <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }

            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['total'] ?? 0);
        } catch (\PDOException $e) {
            // ✅ CORREÇÃO: Se houver erro, retorna 0 (não crítico)
            error_log("Erro ao contar histórico de assinatura: " . $e->getMessage());
            return 0;
        } catch (\Exception $e) {
            // ✅ Captura qualquer outro tipo de exceção também
            error_log("Erro inesperado ao contar histórico de assinatura: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Remove histórico antigo (retenção configurável)
     * 
     * @param int $daysToKeep Dias para manter histórico
     * @return int Número de registros removidos
     */
    public function deleteOldHistory(int $daysToKeep = 365): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE created_at < :cutoff_date");
        $stmt->bindValue(':cutoff_date', $cutoffDate);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Obtém estatísticas do histórico de uma assinatura
     * 
     * @param int $subscriptionId ID da assinatura
     * @param int|null $tenantId ID do tenant (para validação de segurança)
     * @return array Estatísticas do histórico
     */
    public function getStatistics(int $subscriptionId, ?int $tenantId = null): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_changes,
                        COUNT(DISTINCT change_type) as unique_change_types,
                        COUNT(DISTINCT changed_by) as unique_sources,
                        COUNT(DISTINCT user_id) as unique_users,
                        MIN(created_at) as first_change,
                        MAX(created_at) as last_change,
                        SUM(CASE WHEN change_type = 'created' THEN 1 ELSE 0 END) as created_count,
                        SUM(CASE WHEN change_type = 'updated' THEN 1 ELSE 0 END) as updated_count,
                        SUM(CASE WHEN change_type = 'canceled' THEN 1 ELSE 0 END) as canceled_count,
                        SUM(CASE WHEN change_type = 'reactivated' THEN 1 ELSE 0 END) as reactivated_count,
                        SUM(CASE WHEN change_type = 'plan_changed' THEN 1 ELSE 0 END) as plan_changed_count,
                        SUM(CASE WHEN change_type = 'status_changed' THEN 1 ELSE 0 END) as status_changed_count
                    FROM {$this->table} 
                    WHERE subscription_id = :subscription_id";
            
            $params = ['subscription_id' => $subscriptionId];

            if ($tenantId !== null) {
                $sql .= " AND tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }

            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return [
                'total_changes' => (int) ($result['total_changes'] ?? 0),
                'unique_change_types' => (int) ($result['unique_change_types'] ?? 0),
                'unique_sources' => (int) ($result['unique_sources'] ?? 0),
                'unique_users' => (int) ($result['unique_users'] ?? 0),
                'first_change' => $result['first_change'] ?? null,
                'last_change' => $result['last_change'] ?? null,
                'by_type' => [
                    'created' => (int) ($result['created_count'] ?? 0),
                    'updated' => (int) ($result['updated_count'] ?? 0),
                    'canceled' => (int) ($result['canceled_count'] ?? 0),
                    'reactivated' => (int) ($result['reactivated_count'] ?? 0),
                    'plan_changed' => (int) ($result['plan_changed_count'] ?? 0),
                    'status_changed' => (int) ($result['status_changed_count'] ?? 0)
                ]
            ];
        } catch (\PDOException $e) {
            // ✅ CORREÇÃO: Se houver erro, retorna estatísticas vazias (não crítico)
            error_log("Erro ao obter estatísticas do histórico: " . $e->getMessage());
            return $this->getEmptyStatistics();
        } catch (\Exception $e) {
            // ✅ Captura qualquer outro tipo de exceção também
            error_log("Erro inesperado ao obter estatísticas do histórico: " . $e->getMessage());
            return $this->getEmptyStatistics();
        }
    }

    /**
     * Retorna estatísticas vazias (quando tabela não existe ou há erro)
     * 
     * @return array
     */
    private function getEmptyStatistics(): array
    {
        return [
            'total_changes' => 0,
            'unique_change_types' => 0,
            'unique_sources' => 0,
            'unique_users' => 0,
            'first_change' => null,
            'last_change' => null,
            'by_type' => [
                'created' => 0,
                'updated' => 0,
                'canceled' => 0,
                'reactivated' => 0,
                'plan_changed' => 0,
                'status_changed' => 0
            ]
        ];
    }
}

