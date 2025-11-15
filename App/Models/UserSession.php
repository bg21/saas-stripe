<?php

namespace App\Models;

/**
 * Model para gerenciar sessões de usuários
 */
class UserSession extends BaseModel
{
    protected string $table = 'user_sessions';

    /**
     * Cria uma nova sessão
     * 
     * @param int $userId ID do usuário
     * @param int $tenantId ID do tenant
     * @param string|null $ipAddress IP do cliente
     * @param string|null $userAgent User-Agent do cliente
     * @param int $hours Duração da sessão em horas (padrão: 24)
     * @return string Session ID (token)
     */
    public function create(int $userId, int $tenantId, ?string $ipAddress = null, ?string $userAgent = null, int $hours = 24): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));

        $this->insert([
            'id' => $sessionId,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt
        ]);

        return $sessionId;
    }

    /**
     * Valida sessão e retorna dados do usuário
     * 
     * @param string $sessionId Token da sessão
     * @return array|null Dados da sessão com informações do usuário e tenant, ou null se inválida
     */
    public function validate(string $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, 
                    u.email, u.name, u.role, u.status as user_status,
                    t.name as tenant_name, t.status as tenant_status
             FROM {$this->table} s
             INNER JOIN users u ON s.user_id = u.id
             INNER JOIN tenants t ON s.tenant_id = t.id
             WHERE s.id = :session_id 
             AND s.expires_at > NOW()
             AND u.status = 'active'
             AND t.status = 'active'"
        );
        $stmt->execute(['session_id' => $sessionId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Remove sessão (logout)
     * 
     * @param string $sessionId Token da sessão
     * @return bool Sucesso da operação
     */
    public function delete(string $sessionId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :session_id");
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Remove todas as sessões de um usuário
     * 
     * @param int $userId ID do usuário
     * @return bool Sucesso da operação
     */
    public function deleteByUser(int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Limpa sessões expiradas
     * 
     * @return int Número de sessões removidas
     */
    public function cleanExpired(): int
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Busca sessão por ID
     * 
     * @param string $sessionId Token da sessão
     * @return array|null Dados da sessão ou null se não encontrada
     */
    public function findById(string $sessionId): ?array
    {
        return $this->findBy('id', $sessionId);
    }
}

