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
     * @return int ID do registro criado
     */
    public function recordChange(
        int $subscriptionId,
        int $tenantId,
        string $changeType,
        array $oldData = [],
        array $newData = [],
        ?string $changedBy = null,
        ?string $description = null
    ): int {
        $historyData = [
            'subscription_id' => $subscriptionId,
            'tenant_id' => $tenantId,
            'change_type' => $changeType,
            'changed_by' => $changedBy ?? self::CHANGED_BY_API,
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
     * Busca histórico de uma assinatura
     * 
     * @param int $subscriptionId ID da assinatura
     * @param int|null $tenantId ID do tenant (para validação de segurança)
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de registros de histórico
     */
    public function findBySubscription(int $subscriptionId, ?int $tenantId = null, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE subscription_id = :subscription_id";
        $params = ['subscription_id' => $subscriptionId];

        // Validação de segurança: se tenant_id fornecido, filtra por ele
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params['tenant_id'] = $tenantId;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de registros de histórico de uma assinatura
     * 
     * @param int $subscriptionId ID da assinatura
     * @param int|null $tenantId ID do tenant (para validação de segurança)
     * @return int Total de registros
     */
    public function countBySubscription(int $subscriptionId, ?int $tenantId = null): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE subscription_id = :subscription_id";
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
        return (int) ($result['total'] ?? 0);
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
}

