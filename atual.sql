-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 16/11/2025 às 01:11
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `saas_payments`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--
-- Criação: 14/11/2025 às 19:57
-- Última atualização: 16/11/2025 às 00:10
--

CREATE TABLE `audit_logs` (
  `id` int(11) UNSIGNED NOT NULL,
  `tenant_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'ID do tenant (null para master key)',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID do usuário (quando aplicável)',
  `endpoint` varchar(255) NOT NULL COMMENT 'Endpoint/URL acessada',
  `method` varchar(10) NOT NULL COMMENT 'Método HTTP (GET, POST, PUT, DELETE, etc)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Endereço IP do cliente (suporta IPv4 e IPv6)',
  `user_agent` text DEFAULT NULL COMMENT 'User-Agent do cliente',
  `request_body` text DEFAULT NULL COMMENT 'Corpo da requisição (JSON, limitado a 10KB)',
  `response_status` int(3) NOT NULL COMMENT 'Status HTTP da resposta',
  `response_time` int(11) NOT NULL COMMENT 'Tempo de resposta em milissegundos',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data e hora da requisição'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de logs de auditoria - rastreabilidade de ações';

--
-- RELACIONAMENTOS PARA TABELAS `audit_logs`:
--   `tenant_id`
--       `tenants` -> `id`
--

--
-- Despejando dados para a tabela `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `endpoint`, `method`, `ip_address`, `user_agent`, `request_body`, `response_status`, `response_time`, `created_at`) VALUES
(1, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 150, '2025-11-14 22:53:29'),
(2, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:53:35'),
(3, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-14 22:54:00'),
(4, NULL, NULL, '/v1/stats', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:54:07'),
(5, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-14 22:54:14'),
(6, NULL, NULL, '/v1/customers', 'POST', '::1', NULL, '{\"email\":\"teste@example.com\",\"name\":\"Teste\"}', 200, 3031, '2025-11-14 22:54:23'),
(7, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 5, '2025-11-14 22:56:16'),
(8, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-14 22:56:22'),
(9, NULL, NULL, '/v1/audit-logs/6', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:56:29'),
(10, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 44, '2025-11-14 22:57:01'),
(11, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-14 22:57:08'),
(12, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:57:14'),
(13, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:57:21'),
(14, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:57:27'),
(15, NULL, NULL, '/v1/audit-logs/99999', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-14 22:57:34'),
(16, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:57:40'),
(17, NULL, NULL, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-14 22:57:47'),
(18, NULL, NULL, '/v1/audit-logs/99999', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-14 22:58:36'),
(19, NULL, NULL, '/v1/audit-logs/99999', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-14 22:58:57'),
(20, NULL, NULL, '/v1/subscriptions', 'POST', '::1', NULL, '{\"customer_id\":1,\"price_id\":\"price_1STWCtByYvrEJg7O0Q5siyvS\"}', 200, 2147, '2025-11-14 23:15:19'),
(21, NULL, NULL, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-14 23:16:36'),
(22, NULL, NULL, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-14 23:16:43'),
(23, NULL, NULL, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-14 23:17:14'),
(24, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 147, '2025-11-15 00:19:11'),
(25, NULL, 2, '/v1/auth/me', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:19:17'),
(26, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 66, '2025-11-15 00:19:24'),
(27, NULL, 2, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 13, '2025-11-15 00:19:31'),
(28, NULL, 2, '/v1/auth/logout', 'POST', '::1', NULL, '[]', 200, 312, '2025-11-15 00:19:37'),
(29, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-15 00:19:44'),
(30, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 116, '2025-11-15 00:20:20'),
(31, NULL, 2, '/v1/auth/me', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-15 00:20:26'),
(32, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 67, '2025-11-15 00:20:33'),
(33, NULL, 2, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-15 00:20:39'),
(34, NULL, 2, '/v1/auth/logout', 'POST', '::1', NULL, '[]', 200, 69, '2025-11-15 00:20:46'),
(35, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-15 00:20:52'),
(36, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 136, '2025-11-15 00:22:12'),
(37, NULL, 2, '/v1/auth/me', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-15 00:22:19'),
(38, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 401, 70, '2025-11-15 00:22:25'),
(39, NULL, 2, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 1, '2025-11-15 00:22:32'),
(40, NULL, 2, '/v1/auth/logout', 'POST', '::1', NULL, '[]', 200, 83, '2025-11-15 00:22:38'),
(41, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 30, '2025-11-15 00:22:45'),
(42, NULL, NULL, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 17, '2025-11-15 00:49:30'),
(43, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 9, '2025-11-15 00:49:36'),
(44, NULL, NULL, '/v1/customers', 'POST', '::1', NULL, '{\"email\":\"test_1763167776@example.com\",\"name\":\"Test Customer\"}', 200, 2100, '2025-11-15 00:49:45'),
(45, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 208, '2025-11-15 00:49:52'),
(46, NULL, 2, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:49:58'),
(47, NULL, 2, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:50:05'),
(48, NULL, 2, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 5, '2025-11-15 00:50:11'),
(49, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"editor@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 147, '2025-11-15 00:50:18'),
(50, NULL, 3, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 12, '2025-11-15 00:50:24'),
(51, NULL, 3, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:50:31'),
(52, NULL, 3, '/v1/customers', 'POST', '::1', NULL, '{\"email\":\"editor_test_1763167831@example.com\",\"name\":\"Editor Test Customer\"}', 200, 856, '2025-11-15 00:50:38'),
(53, NULL, 3, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 403, 3, '2025-11-15 00:50:45'),
(54, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"viewer@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 147, '2025-11-15 00:50:51'),
(55, NULL, 4, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-15 00:50:58'),
(56, NULL, 4, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-15 00:51:04'),
(57, NULL, 4, '/v1/customers', 'POST', '::1', NULL, '{\"email\":\"viewer_test_1763167864@example.com\",\"name\":\"Viewer Test Customer\"}', 403, 2, '2025-11-15 00:51:10'),
(58, NULL, 4, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 403, 2, '2025-11-15 00:51:17'),
(59, NULL, NULL, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:51:23'),
(60, NULL, 3, '/v1/subscriptions/1', 'DELETE', '::1', NULL, NULL, 403, 3, '2025-11-15 00:51:29'),
(61, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 28, '2025-11-15 00:51:36'),
(62, NULL, 4, '/v1/customers/1', 'PUT', '::1', NULL, '{\"name\":\"Updated Name\"}', 403, 72, '2025-11-15 00:51:42'),
(63, NULL, NULL, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:52:15'),
(64, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:52:21'),
(65, NULL, NULL, '/v1/customers', 'POST', '::1', NULL, '{\"email\":\"test_1763167941@example.com\",\"name\":\"Test Customer\"}', 200, 903, '2025-11-15 00:52:28'),
(66, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 136, '2025-11-15 00:52:35'),
(67, NULL, 2, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 4, '2025-11-15 00:52:41'),
(68, NULL, 2, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-15 00:52:48'),
(69, NULL, 2, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 200, 16, '2025-11-15 00:52:54'),
(70, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"editor@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 203, '2025-11-15 00:53:01'),
(71, NULL, 3, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 4, '2025-11-15 00:53:07'),
(72, NULL, 3, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:53:14'),
(73, NULL, 3, '/v1/customers', 'POST', '::1', NULL, '{\"email\":\"editor_test_1763167994@example.com\",\"name\":\"Editor Test Customer\"}', 200, 858, '2025-11-15 00:53:21'),
(74, NULL, 3, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 403, 3, '2025-11-15 00:53:27'),
(75, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"viewer@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 147, '2025-11-15 00:53:34'),
(76, NULL, 4, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 8, '2025-11-15 00:53:40'),
(77, NULL, 4, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-15 00:53:47'),
(78, NULL, 4, '/v1/customers', 'POST', '::1', NULL, '{\"email\":\"viewer_test_1763168027@example.com\",\"name\":\"Viewer Test Customer\"}', 403, 2, '2025-11-15 00:53:53'),
(79, NULL, 4, '/v1/audit-logs', 'GET', '::1', NULL, NULL, 403, 3, '2025-11-15 00:54:00'),
(80, NULL, NULL, '/v1/subscriptions', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:54:06'),
(81, NULL, 3, '/v1/subscriptions/1', 'DELETE', '::1', NULL, NULL, 403, 3, '2025-11-15 00:54:12'),
(82, NULL, NULL, '/v1/customers', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 00:54:19'),
(83, NULL, 4, '/v1/customers/1', 'PUT', '::1', NULL, '{\"name\":\"Updated Name\"}', 403, 2, '2025-11-15 00:54:25'),
(84, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 169, '2025-11-15 01:05:16'),
(85, NULL, 2, '/v1/users', 'GET', '::1', NULL, NULL, 200, 26, '2025-11-15 01:05:23'),
(86, NULL, 2, '/v1/users', 'POST', '::1', NULL, '{\"email\":\"test_user_1763168723@example.com\",\"password\":\"[REDACTED]\",\"name\":\"Test User\",\"role\":\"viewer\"}', 200, 319, '2025-11-15 01:05:30'),
(87, NULL, 2, '/v1/users/5', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-15 01:05:36'),
(88, NULL, 2, '/v1/users/5', 'PUT', '::1', NULL, '{\"name\":\"Updated Test User\",\"status\":\"active\"}', 200, 70, '2025-11-15 01:05:43'),
(89, NULL, 2, '/v1/users/5/role', 'PUT', '::1', NULL, '{\"role\":\"editor\"}', 200, 70, '2025-11-15 01:05:49'),
(90, NULL, 2, '/v1/users/5', 'DELETE', '::1', NULL, NULL, 200, 58, '2025-11-15 01:05:56'),
(91, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"editor@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 179, '2025-11-15 01:06:02'),
(92, NULL, 3, '/v1/users', 'GET', '::1', NULL, NULL, 403, 3, '2025-11-15 01:06:09'),
(93, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"viewer@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 174, '2025-11-15 01:06:15'),
(94, NULL, 4, '/v1/users', 'GET', '::1', NULL, NULL, 403, 26, '2025-11-15 01:06:22'),
(95, NULL, NULL, '/v1/users', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 01:06:28'),
(96, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 147, '2025-11-15 01:09:35'),
(97, NULL, 2, '/v1/users', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-15 01:09:41'),
(98, NULL, 2, '/v1/users', 'POST', '::1', NULL, '{\"email\":\"test_user_1763168981@example.com\",\"password\":\"[REDACTED]\",\"name\":\"Test User\",\"role\":\"viewer\"}', 200, 263, '2025-11-15 01:09:48'),
(99, NULL, 2, '/v1/users/6', 'GET', '::1', NULL, NULL, 200, 95, '2025-11-15 01:09:55'),
(100, NULL, 2, '/v1/users/6', 'PUT', '::1', NULL, '{\"name\":\"Updated Test User\",\"status\":\"active\"}', 200, 82, '2025-11-15 01:10:01'),
(101, NULL, 2, '/v1/users/6/role', 'PUT', '::1', NULL, '{\"role\":\"editor\"}', 200, 188, '2025-11-15 01:10:08'),
(102, NULL, 2, '/v1/users/6', 'DELETE', '::1', NULL, NULL, 200, 72, '2025-11-15 01:10:15'),
(103, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"editor@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 169, '2025-11-15 01:10:21'),
(104, NULL, 3, '/v1/users', 'GET', '::1', NULL, NULL, 403, 7, '2025-11-15 01:10:28'),
(105, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"viewer@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 113, '2025-11-15 01:10:35'),
(106, NULL, 4, '/v1/users', 'GET', '::1', NULL, NULL, 403, 3, '2025-11-15 01:10:41'),
(107, NULL, NULL, '/v1/users', 'GET', '::1', NULL, NULL, 403, 0, '2025-11-15 01:10:48'),
(108, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 114, '2025-11-15 01:19:53'),
(109, NULL, 2, '/v1/permissions', 'GET', '::1', NULL, NULL, 200, 63, '2025-11-15 01:20:00'),
(110, NULL, 2, '/v1/users/4/permissions', 'GET', '::1', NULL, NULL, 200, 13, '2025-11-15 01:20:06'),
(111, NULL, 2, '/v1/users/4/permissions', 'POST', '::1', NULL, '{\"permission\":\"view_audit_logs\"}', 200, 58, '2025-11-15 01:20:13'),
(112, NULL, 2, '/v1/users/4/permissions', 'GET', '::1', NULL, NULL, 200, 2, '2025-11-15 01:20:19'),
(113, NULL, 2, '/v1/users/4/permissions/view_audit_logs', 'DELETE', '::1', NULL, NULL, 200, 47, '2025-11-15 01:20:26'),
(114, NULL, 2, '/v1/users/4/permissions', 'GET', '::1', NULL, NULL, 200, 4, '2025-11-15 01:20:32'),
(115, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"editor@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 216, '2025-11-15 01:20:39'),
(116, NULL, 3, '/v1/permissions', 'GET', '::1', NULL, NULL, 403, 39, '2025-11-15 01:20:46'),
(117, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"viewer@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 191, '2025-11-15 01:20:52'),
(118, NULL, 4, '/v1/permissions', 'GET', '::1', NULL, NULL, 403, 4, '2025-11-15 01:20:59'),
(119, NULL, NULL, '/v1/permissions', 'GET', '::1', NULL, NULL, 403, 1, '2025-11-15 01:21:05'),
(120, NULL, 2, '/v1/users/4/permissions', 'POST', '::1', NULL, '{\"permission\":\"invalid_permission_xyz\"}', 400, 2, '2025-11-15 01:21:12'),
(121, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 169, '2025-11-15 01:45:18'),
(122, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 43, '2025-11-15 01:45:24'),
(123, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 5, '2025-11-15 01:45:30'),
(124, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 6, '2025-11-15 01:45:37'),
(125, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 17, '2025-11-15 01:45:43'),
(126, NULL, 2, '/v1/subscriptions/1/history/stats', 'GET', '::1', NULL, NULL, 500, 24, '2025-11-15 01:45:50'),
(127, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 5, '2025-11-15 01:45:56'),
(128, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 4, '2025-11-15 01:46:03'),
(129, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 202, '2025-11-15 01:47:52'),
(130, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 6, '2025-11-15 01:47:59'),
(131, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 5, '2025-11-15 01:48:05'),
(132, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 5, '2025-11-15 01:48:11'),
(133, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 5, '2025-11-15 01:48:18'),
(134, NULL, 2, '/v1/subscriptions/1/history/stats', 'GET', '::1', NULL, NULL, 200, 31, '2025-11-15 01:48:25'),
(135, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 4, '2025-11-15 01:48:31'),
(136, NULL, 2, '/v1/subscriptions/1/history', 'GET', '::1', NULL, NULL, 200, 3, '2025-11-15 01:48:38'),
(137, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"admin@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 176, '2025-11-15 01:57:38'),
(138, NULL, NULL, '/v1/disputes', 'GET', '::1', NULL, NULL, 200, 1385, '2025-11-15 01:57:46'),
(139, NULL, 2, '/v1/disputes', 'GET', '::1', NULL, NULL, 200, 1232, '2025-11-15 01:57:54'),
(140, NULL, 2, '/v1/disputes', 'GET', '::1', NULL, NULL, 200, 874, '2025-11-15 01:58:01'),
(141, NULL, 2, '/v1/disputes', 'GET', '::1', NULL, NULL, 200, 835, '2025-11-15 01:58:09'),
(142, NULL, NULL, '/v1/auth/login', 'POST', '::1', NULL, '{\"email\":\"editor@example.com\",\"password\":\"[REDACTED]\",\"tenant_id\":1}', 200, 126, '2025-11-15 01:58:16'),
(143, NULL, 3, '/v1/disputes', 'GET', '::1', NULL, NULL, 200, 970, '2025-11-15 01:58:23'),
(144, NULL, NULL, '/health/detailed', 'GET', '::1', NULL, NULL, 200, 4435, '2025-11-15 02:15:01'),
(145, NULL, NULL, '/health/detailed', 'GET', '::1', NULL, NULL, 200, 4202, '2025-11-15 02:15:06'),
(146, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 1408, '2025-11-15 03:09:32'),
(147, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 688, '2025-11-15 03:09:40'),
(148, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 6025, '2025-11-15 03:09:52'),
(149, NULL, NULL, '/v1/charges/ch_invalid123', 'GET', '::1', NULL, NULL, 200, 1326, '2025-11-15 03:10:00'),
(150, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 650, '2025-11-15 03:10:08'),
(151, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 1726, '2025-11-15 03:10:16'),
(152, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 680, '2025-11-15 03:10:50'),
(153, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 739, '2025-11-15 03:10:57'),
(154, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 861, '2025-11-15 03:11:05'),
(155, NULL, NULL, '/v1/charges/ch_invalid123', 'GET', '::1', NULL, NULL, 404, 754, '2025-11-15 03:11:12'),
(156, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 665, '2025-11-15 03:11:20'),
(157, NULL, NULL, '/v1/charges', 'GET', '::1', NULL, NULL, 200, 787, '2025-11-15 03:11:27'),
(158, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2902, '2025-11-15 22:33:11'),
(159, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 686, '2025-11-15 22:33:18'),
(160, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 735, '2025-11-15 22:33:26'),
(161, 3, NULL, '/v1/customers', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"email\":\"juhcosta23@gmail.com\",\"name\":\"Gediel Gomes\",\"metadata\":[]}', 200, 905, '2025-11-15 22:34:00'),
(162, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":6,\"price_id\":\"price_1STWCtByYvrEJg7O0Q5siyvS\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 1, '2025-11-15 22:34:07'),
(163, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":6,\"price_id\":\"price_1STDZSByYvrEJg7Oof5EuKEc\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 0, '2025-11-15 22:34:24'),
(164, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 813, '2025-11-15 22:35:47'),
(165, 3, NULL, '/v1/customers/6', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 674, '2025-11-15 22:35:54'),
(166, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":6,\"price_id\":\"price_1STWCtByYvrEJg7O0Q5siyvS\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 980, '2025-11-15 22:36:21'),
(167, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1282, '2025-11-15 22:47:57'),
(168, 3, NULL, '/v1/customers', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"email\":\"juhcosta23@gmail.com\",\"name\":\"Gediel Gomes\",\"metadata\":[]}', 200, 896, '2025-11-15 22:48:13'),
(169, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":7,\"price_id\":\"price_1STDZSByYvrEJg7Oof5EuKEc\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 871, '2025-11-15 22:48:20'),
(170, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 893, '2025-11-15 22:49:09'),
(171, 3, NULL, '/v1/customers/7', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 776, '2025-11-15 22:49:17'),
(172, 3, NULL, '/v1/customers', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"email\":\"juhcosta23@gmail.com\",\"name\":\"Gediel Gomes\",\"metadata\":[]}', 200, 1068, '2025-11-15 22:49:24'),
(173, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":8,\"price_id\":\"price_1STDZSByYvrEJg7Oof5EuKEc\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 728, '2025-11-15 22:49:32'),
(174, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 729, '2025-11-15 22:50:16'),
(175, 3, NULL, '/v1/customers/8', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 694, '2025-11-15 22:50:23'),
(176, 3, NULL, '/v1/customers', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"email\":\"juhcosta23@gmail.com\",\"name\":\"Gediel Gomes\",\"metadata\":[]}', 200, 1007, '2025-11-15 22:50:31'),
(177, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":9,\"price_id\":\"price_1STDZSByYvrEJg7Oof5EuKEc\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 705, '2025-11-15 22:50:38'),
(178, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 737, '2025-11-15 22:51:10'),
(179, 3, NULL, '/v1/customers/9', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 733, '2025-11-15 22:51:17'),
(180, 3, NULL, '/v1/customers', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"email\":\"juhcosta23@gmail.com\",\"name\":\"Gediel Gomes\",\"metadata\":[]}', 200, 1019, '2025-11-15 22:51:25'),
(181, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":10,\"price_id\":\"price_1STWCtByYvrEJg7O0Q5siyvS\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 952, '2025-11-15 22:51:33'),
(182, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 921, '2025-11-15 22:52:45'),
(183, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 846, '2025-11-15 22:52:53'),
(184, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 755, '2025-11-15 22:53:00'),
(185, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 851, '2025-11-15 22:53:30'),
(186, 3, NULL, '/v1/customers', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"email\":\"juhcosta23@gmail.com\",\"name\":\"Gediel Gomes\",\"metadata\":[]}', 200, 1056, '2025-11-15 22:53:52'),
(187, 3, NULL, '/v1/checkout', 'POST', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '{\"customer_id\":11,\"price_id\":\"price_1STWCtByYvrEJg7O0Q5siyvS\",\"success_url\":\"http:\\/\\/localhost\\/success.html?session_id={CHECKOUT_SESSION_ID}\",\"cancel_url\":\"http:\\/\\/localhost\\/index.html\",\"metadata\":{\"plan_name\":\"Plano\",\"customer_name\":\"Gediel Gomes\"}}', 200, 964, '2025-11-15 22:53:59'),
(188, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:19:31'),
(189, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:19:38'),
(190, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1239, '2025-11-15 23:19:46'),
(191, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:19:52'),
(192, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:20:36'),
(193, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-15 23:20:42'),
(194, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:23:52'),
(195, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:23:59'),
(196, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-15 23:24:05'),
(197, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:24:12'),
(198, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:24:27'),
(199, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:24:34'),
(200, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:31:24'),
(201, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:31:30'),
(202, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:31:40'),
(203, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:31:47'),
(204, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:32:06'),
(205, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5, '2025-11-15 23:32:13'),
(206, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-15 23:32:34'),
(207, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:32:41'),
(208, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:33:14'),
(209, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-15 23:33:20'),
(210, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:33:27'),
(211, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:33:33'),
(212, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:33:40'),
(213, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:33:46'),
(214, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:33:53'),
(215, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 35, '2025-11-15 23:34:00'),
(216, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:34:06'),
(217, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:34:13'),
(218, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:35:05'),
(219, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-15 23:35:12'),
(220, 3, NULL, '/v1/balance-transactions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1071, '2025-11-15 23:35:20'),
(221, 3, NULL, '/v1/charges', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 911, '2025-11-15 23:35:27'),
(222, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:35:34'),
(223, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:35:40'),
(224, 3, NULL, '/v1/stats', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:35:47'),
(225, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:35:53'),
(226, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:36:00'),
(227, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:36:06'),
(228, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:36:13'),
(229, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 725, '2025-11-15 23:36:20'),
(230, 3, NULL, '/v1/customers/11', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 698, '2025-11-15 23:36:27'),
(231, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 17, '2025-11-15 23:36:34'),
(232, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:36:41'),
(233, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:36:48'),
(234, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:36:55'),
(235, 3, NULL, '/v1/balance-transactions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 713, '2025-11-15 23:37:02'),
(236, 3, NULL, '/v1/charges', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 823, '2025-11-15 23:37:09'),
(237, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:37:16'),
(238, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:37:22'),
(239, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:37:28'),
(240, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:37:35'),
(241, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-15 23:37:42'),
(242, 3, NULL, '/v1/balance-transactions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 863, '2025-11-15 23:37:49'),
(243, 3, NULL, '/v1/charges', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 915, '2025-11-15 23:37:57'),
(244, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:41:35'),
(245, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-15 23:41:42'),
(246, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:51:22'),
(247, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:51:28'),
(248, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:51:35'),
(249, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:51:41'),
(250, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 52, '2025-11-15 23:51:48'),
(251, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-15 23:51:55'),
(252, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-15 23:52:28'),
(253, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-15 23:52:35'),
(254, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-15 23:52:42'),
(255, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 18, '2025-11-15 23:52:48'),
(256, 3, NULL, '/v1/balance-transactions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 936, '2025-11-15 23:52:56'),
(257, 3, NULL, '/v1/charges', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 869, '2025-11-15 23:53:04'),
(258, 3, NULL, '/v1/stats', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:53:10'),
(259, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-15 23:53:17'),
(260, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:53:24'),
(261, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-15 23:53:30'),
(262, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:53:37'),
(263, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:53:43'),
(264, 3, NULL, '/v1/stats', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 7, '2025-11-15 23:54:49'),
(265, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1241, '2025-11-15 23:54:57'),
(266, 3, NULL, '/v1/customers/11', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1201, '2025-11-15 23:55:05'),
(267, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:55:11'),
(268, 3, NULL, '/v1/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 404, 0, '2025-11-15 23:55:18'),
(269, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 22, '2025-11-15 23:55:40'),
(270, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:55:46'),
(271, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-15 23:56:22'),
(272, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:56:29'),
(273, 3, NULL, '/v1/stats', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-15 23:56:35'),
(274, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-16 00:01:04'),
(275, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:01:16'),
(276, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:01:23'),
(277, 3, NULL, '/v1/balance-transactions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1229, '2025-11-16 00:02:15'),
(278, 3, NULL, '/v1/charges', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1388, '2025-11-16 00:02:23'),
(279, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:02:30'),
(280, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-16 00:02:37'),
(281, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-16 00:05:27'),
(282, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:05:40'),
(283, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:06:32'),
(284, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5, '2025-11-16 00:06:39'),
(285, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-16 00:06:45'),
(286, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-16 00:06:52'),
(287, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:07:00'),
(288, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-16 00:07:57'),
(289, 3, NULL, '/v1/users', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 403, 1, '2025-11-16 00:08:11'),
(290, 3, NULL, '/v1/coupons', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 826, '2025-11-16 00:08:21'),
(291, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5, '2025-11-16 00:08:41'),
(292, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:08:48'),
(293, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-16 00:09:19'),
(294, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-16 00:09:26'),
(295, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:09:32'),
(296, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 784, '2025-11-16 00:09:40'),
(297, 3, NULL, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 760, '2025-11-16 00:09:47'),
(298, 3, NULL, '/v1/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 404, 0, '2025-11-16 00:09:54'),
(299, 3, NULL, '/v1/stats', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 22, '2025-11-16 00:10:00'),
(300, 3, NULL, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-16 00:10:06'),
(301, 3, NULL, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5, '2025-11-16 00:10:13');

-- --------------------------------------------------------

--
-- Estrutura para tabela `backup_logs`
--
-- Criação: 15/11/2025 às 02:31
--

CREATE TABLE `backup_logs` (
  `id` int(11) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Nome do arquivo de backup',
  `file_path` text DEFAULT NULL COMMENT 'Caminho completo do arquivo de backup',
  `file_size` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Tamanho do arquivo em bytes',
  `status` enum('success','failed') NOT NULL DEFAULT 'success' COMMENT 'Status do backup',
  `duration_seconds` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Duração do backup em segundos',
  `compressed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se o backup foi comprimido (gzip)',
  `error_message` text DEFAULT NULL COMMENT 'Mensagem de erro (se houver)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data de criação do backup'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de backups do banco de dados';

--
-- RELACIONAMENTOS PARA TABELAS `backup_logs`:
--

--
-- Despejando dados para a tabela `backup_logs`
--

INSERT INTO `backup_logs` (`id`, `filename`, `file_path`, `file_size`, `status`, `duration_seconds`, `compressed`, `error_message`, `created_at`) VALUES
(1, 'backup_saas_payments_2025-11-15_03-45-30.sql.gz', 'D:\\xampp\\htdocs\\saas-stripe\\backups\\backup_saas_payments_2025-11-15_03-45-30.sql.gz', 9830, 'success', 1.96, 1, NULL, '2025-11-15 06:45:32'),
(2, 'backup_saas_payments_2025-11-15_03-48-15.sql', 'D:\\xampp\\htdocs\\saas-stripe\\backups\\backup_saas_payments_2025-11-15_03-48-15.sql', 0, 'failed', 0.19, 1, 'Arquivo de backup não foi criado: D:\\xampp\\htdocs\\saas-stripe\\backups\\backup_saas_payments_2025-11-15_03-48-15.sql.gz', '2025-11-15 06:48:15'),
(3, 'backup_saas_payments_2025-11-15_03-49-18.sql.gz', 'D:\\xampp\\htdocs\\saas-stripe\\backups\\backup_saas_payments_2025-11-15_03-49-18.sql.gz', 9957, 'success', 0.06, 1, NULL, '2025-11-15 06:49:18'),
(4, 'backup_saas_payments_2025-11-15_03-52-55.sql.gz', 'D:\\xampp\\htdocs\\saas-stripe\\backups\\backup_saas_payments_2025-11-15_03-52-55.sql.gz', 9984, 'success', 0.11, 1, NULL, '2025-11-15 06:52:55');

-- --------------------------------------------------------

--
-- Estrutura para tabela `customers`
--
-- Criação: 14/11/2025 às 19:54
-- Última atualização: 15/11/2025 às 22:53
--

CREATE TABLE `customers` (
  `id` int(11) UNSIGNED NOT NULL,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `stripe_customer_id` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de clientes Stripe';

--
-- RELACIONAMENTOS PARA TABELAS `customers`:
--   `tenant_id`
--       `tenants` -> `id`
--

--
-- Despejando dados para a tabela `customers`
--

INSERT INTO `customers` (`id`, `tenant_id`, `stripe_customer_id`, `email`, `name`, `metadata`, `created_at`, `updated_at`) VALUES
(6, 3, 'cus_TQjVLvwY3eWrIE', 'juhcosta23@gmail.com', 'Gediel Gomes', '[]', '2025-11-15 22:34:00', '2025-11-15 22:34:00'),
(7, 3, 'cus_TQjjgNs51vejsd', 'juhcosta23@gmail.com', 'Gediel Gomes', '[]', '2025-11-15 22:48:12', '2025-11-15 22:48:12'),
(8, 3, 'cus_TQjlICf9oNPvga', 'juhcosta23@gmail.com', 'Gediel Gomes', '[]', '2025-11-15 22:49:24', '2025-11-15 22:49:24'),
(9, 3, 'cus_TQjmMr3rcNqhVU', 'juhcosta23@gmail.com', 'Gediel Gomes', '[]', '2025-11-15 22:50:31', '2025-11-15 22:50:31'),
(10, 3, 'cus_TQjn7Ln6J1tC7X', 'juhcosta23@gmail.com', 'Gediel Gomes', '[]', '2025-11-15 22:51:25', '2025-11-15 22:51:25'),
(11, 3, 'cus_TQjp0XSez1QWQY', 'juhcosta23@gmail.com', 'Gediel Gomes', '[]', '2025-11-15 22:53:52', '2025-11-15 22:53:52');

-- --------------------------------------------------------

--
-- Estrutura para tabela `phinxlog`
--
-- Criação: 14/11/2025 às 19:47
--

CREATE TABLE `phinxlog` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELACIONAMENTOS PARA TABELAS `phinxlog`:
--

--
-- Despejando dados para a tabela `phinxlog`
--

INSERT INTO `phinxlog` (`version`, `migration_name`, `start_time`, `end_time`, `breakpoint`) VALUES
(20250115000001, 'InitialSchema', '2025-11-14 23:54:45', '2025-11-14 23:54:51', 0),
(20250116000001, 'CreateBackupLogsTable', '2025-11-15 06:31:11', '2025-11-15 06:31:12', 0),
(20251114195137, 'CreateAuditLogsTable', '2025-11-14 23:57:09', '2025-11-14 23:57:11', 0),
(20251114230642, 'CreateSubscriptionHistoryTable', '2025-11-15 03:09:26', '2025-11-15 03:09:29', 0),
(20251115000545, 'AddUserAuthAndPermissions', '2025-11-15 04:13:35', '2025-11-15 04:13:39', 0),
(20251115012954, 'AddUserIdToSubscriptionHistory', '2025-11-15 05:34:51', '2025-11-15 05:34:53', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `rate_limits`
--
-- Criação: 14/11/2025 às 19:54
-- Última atualização: 16/11/2025 às 00:10
--

CREATE TABLE `rate_limits` (
  `id` int(11) UNSIGNED NOT NULL,
  `identifier_key` varchar(255) NOT NULL,
  `request_count` int(11) NOT NULL DEFAULT 1,
  `reset_at` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de rate limits (fallback quando Redis não está disponível)';

--
-- RELACIONAMENTOS PARA TABELAS `rate_limits`:
--

--
-- Despejando dados para a tabela `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `identifier_key`, `request_count`, `reset_at`, `created_at`, `updated_at`) VALUES
(1, 'ratelimit::tenant_1:60:2df18f4bb243db7805da72563bb2619b', 3, 1763160866, 1763160806, 1763160851),
(2, 'ratelimit::tenant_1:3600:2df18f4bb243db7805da72563bb2619b', 17, 1763164409, 1763160809, 1763168007),
(3, 'ratelimit::tenant_1:60:3d2dd16387c5b2b6c15f00a314f302e2', 2, 1763160898, 1763160838, 1763160858),
(4, 'ratelimit::tenant_1:3600:3d2dd16387c5b2b6c15f00a314f302e2', 23, 1763164440, 1763160840, 1763168033),
(5, 'ratelimit::tenant_1:60:c1258a8afea49c93018a38c7a0a9a84e', 1, 1763160904, 1763160844, 1763160844),
(6, 'ratelimit::tenant_1:3600:c1258a8afea49c93018a38c7a0a9a84e', 1, 1763164447, 1763160847, 1763160847),
(7, 'ratelimit::tenant_1:60:2df18f4bb243db7805da72563bb2619b', 9, 1763161033, 1763160973, 1763161064),
(8, 'ratelimit::tenant_1:60:f97c50aaeba202cecaf34be506630f96', 1, 1763161046, 1763160986, 1763160986),
(9, 'ratelimit::tenant_1:3600:f97c50aaeba202cecaf34be506630f96', 1, 1763164589, 1763160989, 1763160989),
(10, 'ratelimit::tenant_1:60:96bba6b119a3add94b7b48de76c31a4e', 3, 1763161111, 1763161051, 1763161134),
(11, 'ratelimit::tenant_1:3600:96bba6b119a3add94b7b48de76c31a4e', 3, 1763164654, 1763161054, 1763161137),
(12, 'ratelimit::tenant_1:60:e6eb4274bcab75a413be314fa04bcdb5', 1, 1763162174, 1763162114, 1763162114),
(13, 'ratelimit::tenant_1:3600:e6eb4274bcab75a413be314fa04bcdb5', 11, 1763165716, 1763162116, 1763168046),
(14, 'ratelimit::tenant_1:60:9079b885b4dd601b72589bef1a860532', 3, 1763162254, 1763162194, 1763162231),
(15, 'ratelimit::tenant_1:3600:9079b885b4dd601b72589bef1a860532', 3, 1763165796, 1763162196, 1763162233),
(16, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 4, 1763166008, 1763165948, 1763166030),
(17, 'ratelimit::ip_::1:3600:37fad38087330284f77583a6f2b913fc', 25, 1763169551, 1763165951, 1763171895),
(18, 'ratelimit::tenant_1:60:6e5e928d8fb06e2df412898dafa8076a', 2, 1763166015, 1763165955, 1763166024),
(19, 'ratelimit::tenant_1:3600:6e5e928d8fb06e2df412898dafa8076a', 3, 1763169557, 1763165957, 1763166138),
(20, 'ratelimit::tenant_1:60:3d2dd16387c5b2b6c15f00a314f302e2', 4, 1763166029, 1763165969, 1763166050),
(21, 'ratelimit::tenant_1:60:f1cb602c35e73dbea2eea68297357ca5', 2, 1763166035, 1763165975, 1763166043),
(22, 'ratelimit::tenant_1:3600:f1cb602c35e73dbea2eea68297357ca5', 3, 1763169577, 1763165977, 1763166158),
(23, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 2, 1763166190, 1763166130, 1763166143),
(24, 'ratelimit::tenant_1:60:6e5e928d8fb06e2df412898dafa8076a', 1, 1763166196, 1763166136, 1763166136),
(25, 'ratelimit::tenant_1:60:3d2dd16387c5b2b6c15f00a314f302e2', 2, 1763166209, 1763166149, 1763166163),
(26, 'ratelimit::tenant_1:60:f1cb602c35e73dbea2eea68297357ca5', 1, 1763166216, 1763166156, 1763166156),
(27, 'ratelimit::tenant_1:60:e6eb4274bcab75a413be314fa04bcdb5', 5, 1763167828, 1763167768, 1763167881),
(28, 'ratelimit::tenant_1:60:3d2dd16387c5b2b6c15f00a314f302e2', 7, 1763167834, 1763167774, 1763167868),
(29, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 3, 1763167849, 1763167789, 1763167849),
(30, 'ratelimit::tenant_1:60:2df18f4bb243db7805da72563bb2619b', 3, 1763167869, 1763167809, 1763167874),
(31, 'ratelimit::tenant_1:60:53ecd1111f850a5b8ba0b72b47cb1709', 1, 1763167947, 1763167887, 1763167887),
(32, 'ratelimit::tenant_1:3600:53ecd1111f850a5b8ba0b72b47cb1709', 2, 1763171489, 1763167889, 1763168052),
(33, 'ratelimit::tenant_1:60:3d2dd16387c5b2b6c15f00a314f302e2', 6, 1763167954, 1763167894, 1763167998),
(34, 'ratelimit::tenant_1:60:245cfa272641e7fd7acafc6d9477fc5d', 1, 1763167960, 1763167900, 1763167900),
(35, 'ratelimit::tenant_1:3600:245cfa272641e7fd7acafc6d9477fc5d', 2, 1763171502, 1763167902, 1763168065),
(36, 'ratelimit::tenant_1:60:e6eb4274bcab75a413be314fa04bcdb5', 5, 1763167992, 1763167932, 1763168044),
(37, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 3, 1763168013, 1763167953, 1763168012),
(38, 'ratelimit::tenant_1:60:2df18f4bb243db7805da72563bb2619b', 3, 1763168032, 1763167972, 1763168037),
(39, 'ratelimit::tenant_1:60:3d2dd16387c5b2b6c15f00a314f302e2', 3, 1763168085, 1763168025, 1763168057),
(40, 'ratelimit::tenant_1:3600:2df18f4bb243db7805da72563bb2619b', 1, 1763171640, 1763168040, 1763168040),
(41, 'ratelimit::tenant_1:60:53ecd1111f850a5b8ba0b72b47cb1709', 1, 1763168110, 1763168050, 1763168050),
(42, 'ratelimit::tenant_1:3600:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763171659, 1763168059, 1763168059),
(43, 'ratelimit::tenant_1:60:245cfa272641e7fd7acafc6d9477fc5d', 1, 1763168123, 1763168063, 1763168063),
(44, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 3, 1763168774, 1763168714, 1763168773),
(45, 'ratelimit::tenant_1:60:6d0ee73650759187be1579bbd255b5f9', 5, 1763168781, 1763168721, 1763168786),
(46, 'ratelimit::tenant_1:3600:6d0ee73650759187be1579bbd255b5f9', 10, 1763172323, 1763168723, 1763169048),
(47, 'ratelimit::tenant_1:60:0021b7bd0c19b1e95e23e1b19624dec9', 3, 1763168794, 1763168734, 1763168753),
(48, 'ratelimit::tenant_1:3600:0021b7bd0c19b1e95e23e1b19624dec9', 3, 1763172336, 1763168736, 1763168756),
(49, 'ratelimit::tenant_1:60:4c83ef71f1482b144b9cc966b9a076d1', 1, 1763168807, 1763168747, 1763168747),
(50, 'ratelimit::tenant_1:3600:4c83ef71f1482b144b9cc966b9a076d1', 1, 1763172349, 1763168749, 1763168749),
(51, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 3, 1763169032, 1763168972, 1763169032),
(52, 'ratelimit::tenant_1:60:6d0ee73650759187be1579bbd255b5f9', 5, 1763169039, 1763168979, 1763169046),
(53, 'ratelimit::tenant_1:60:c588bcf44b2583e0dfb2d9e3ad2ab9bc', 3, 1763169052, 1763168992, 1763169012),
(54, 'ratelimit::tenant_1:3600:c588bcf44b2583e0dfb2d9e3ad2ab9bc', 3, 1763172595, 1763168995, 1763169015),
(55, 'ratelimit::tenant_1:60:3fc7a8a6fa4f5bfaceacbef68ef5e149', 1, 1763169066, 1763169006, 1763169006),
(56, 'ratelimit::tenant_1:3600:3fc7a8a6fa4f5bfaceacbef68ef5e149', 1, 1763172608, 1763169008, 1763169008),
(57, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 3, 1763169651, 1763169591, 1763169650),
(58, 'ratelimit::tenant_1:60:30983146e0c0f29208638c3e295d5e4b', 4, 1763169657, 1763169597, 1763169663),
(59, 'ratelimit::tenant_1:3600:30983146e0c0f29208638c3e295d5e4b', 4, 1763173199, 1763169599, 1763169665),
(60, 'ratelimit::tenant_1:60:88ab6a3e6f6e3d6cd0fb013a4c02bd26', 5, 1763169664, 1763169604, 1763169669),
(61, 'ratelimit::tenant_1:3600:88ab6a3e6f6e3d6cd0fb013a4c02bd26', 5, 1763173206, 1763169606, 1763169672),
(62, 'ratelimit::tenant_1:60:68201ed99f3f0b26dac53a58c461848b', 1, 1763169683, 1763169623, 1763169623),
(63, 'ratelimit::tenant_1:3600:68201ed99f3f0b26dac53a58c461848b', 1, 1763173225, 1763169625, 1763169625),
(64, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 1, 1763171175, 1763171115, 1763171115),
(65, 'ratelimit::tenant_1:60:9079b885b4dd601b72589bef1a860532', 6, 1763171182, 1763171122, 1763171161),
(66, 'ratelimit::tenant_1:3600:9079b885b4dd601b72589bef1a860532', 12, 1763174724, 1763171124, 1763171318),
(67, 'ratelimit::tenant_1:60:2a17b9cb88d308f67493d838e302b70c', 1, 1763171207, 1763171147, 1763171147),
(68, 'ratelimit::tenant_1:3600:2a17b9cb88d308f67493d838e302b70c', 2, 1763174750, 1763171150, 1763171305),
(69, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 1, 1763171329, 1763171269, 1763171269),
(70, 'ratelimit::tenant_1:60:9079b885b4dd601b72589bef1a860532', 6, 1763171336, 1763171276, 1763171316),
(71, 'ratelimit::tenant_1:60:2a17b9cb88d308f67493d838e302b70c', 1, 1763171362, 1763171302, 1763171302),
(72, 'ratelimit::ip_::1:60:37fad38087330284f77583a6f2b913fc', 2, 1763171916, 1763171856, 1763171893),
(73, 'ratelimit::tenant_1:60:0173882242e4ed9c7a352e118d39724a', 5, 1763171923, 1763171863, 1763171900),
(74, 'ratelimit::tenant_1:3600:0173882242e4ed9c7a352e118d39724a', 5, 1763175465, 1763171865, 1763171902),
(75, 'ratelimit::tenant_1:60:4237999e5685cf86dbef60af2c4434d1', 10, 1763176228, 1763176168, 1763176284),
(76, 'ratelimit::tenant_1:3600:4237999e5685cf86dbef60af2c4434d1', 10, 1763179771, 1763176171, 1763176286),
(77, 'ratelimit::tenant_1:60:70dc56276ea0e84396c1d513dcbd7e43', 2, 1763176256, 1763176196, 1763176269),
(78, 'ratelimit::tenant_1:3600:70dc56276ea0e84396c1d513dcbd7e43', 2, 1763179799, 1763176199, 1763176272),
(79, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 3, 1763246046, 1763245986, 1763246003),
(80, 'ratelimit::tenant_3:3600:3576c29f16a7eaa16c0b9165b7c916d2', 17, 1763249588, 1763245988, 1763251786),
(81, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763246097, 1763246037, 1763246037),
(82, 'ratelimit::tenant_3:3600:3d2dd16387c5b2b6c15f00a314f302e2', 59, 1763249639, 1763246039, 1763251813),
(83, 'ratelimit::tenant_3:60:687ec96a092058ea684521c3a9d7fdee', 2, 1763246105, 1763246045, 1763246062),
(84, 'ratelimit::tenant_3:3600:687ec96a092058ea684521c3a9d7fdee', 8, 1763249647, 1763246047, 1763247238),
(85, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 1, 1763246204, 1763246144, 1763246144),
(86, 'ratelimit::tenant_3:60:de76da3791b8c76a668aacb07aae6ac8', 1, 1763246211, 1763246151, 1763246151),
(87, 'ratelimit::tenant_3:3600:de76da3791b8c76a668aacb07aae6ac8', 1, 1763249753, 1763246153, 1763246153),
(88, 'ratelimit::tenant_3:60:687ec96a092058ea684521c3a9d7fdee', 1, 1763246237, 1763246177, 1763246177),
(89, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 2, 1763246933, 1763246873, 1763246946),
(90, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 2, 1763246950, 1763246890, 1763246961),
(91, 'ratelimit::tenant_3:60:687ec96a092058ea684521c3a9d7fdee', 2, 1763246957, 1763246897, 1763246969),
(92, 'ratelimit::tenant_3:60:dfaf6838c611446f544ee1dd51a58bbe', 1, 1763247014, 1763246954, 1763246954),
(93, 'ratelimit::tenant_3:3600:dfaf6838c611446f544ee1dd51a58bbe', 1, 1763250556, 1763246956, 1763246956),
(94, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 2, 1763247073, 1763247013, 1763247067),
(95, 'ratelimit::tenant_3:60:87748db51baac6b4046ccdf0809dbbec', 1, 1763247080, 1763247020, 1763247020),
(96, 'ratelimit::tenant_3:3600:87748db51baac6b4046ccdf0809dbbec', 1, 1763250622, 1763247022, 1763247022),
(97, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 2, 1763247088, 1763247028, 1763247082),
(98, 'ratelimit::tenant_3:60:687ec96a092058ea684521c3a9d7fdee', 2, 1763247095, 1763247035, 1763247089),
(99, 'ratelimit::tenant_3:60:50796b6c4f5a610a9e00bf17018831de', 1, 1763247134, 1763247074, 1763247074),
(100, 'ratelimit::tenant_3:3600:50796b6c4f5a610a9e00bf17018831de', 1, 1763250676, 1763247076, 1763247076),
(101, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 4, 1763247222, 1763247162, 1763247207),
(102, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763247288, 1763247228, 1763247228),
(103, 'ratelimit::tenant_3:60:687ec96a092058ea684521c3a9d7fdee', 1, 1763247296, 1763247236, 1763247236),
(104, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 3, 1763248829, 1763248769, 1763248840),
(105, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 2, 1763248836, 1763248776, 1763248833),
(106, 'ratelimit::tenant_3:3600:e6eb4274bcab75a413be314fa04bcdb5', 35, 1763252378, 1763248778, 1763251806),
(107, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 1, 1763248842, 1763248782, 1763248782),
(108, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 3, 1763249090, 1763249030, 1763249065),
(109, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 3, 1763249096, 1763249036, 1763249072),
(110, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 5, 1763249542, 1763249482, 1763249598),
(111, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 5, 1763249548, 1763249488, 1763249591),
(112, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 5, 1763249665, 1763249605, 1763249709),
(113, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 5, 1763249671, 1763249611, 1763249703),
(114, 'ratelimit::tenant_3:60:607cc60c594bdb4f37836a070a8d3cd6', 2, 1763249776, 1763249716, 1763249819),
(115, 'ratelimit::tenant_3:3600:607cc60c594bdb4f37836a070a8d3cd6', 5, 1763253319, 1763249719, 1763251334),
(116, 'ratelimit::tenant_3:60:4237999e5685cf86dbef60af2c4434d1', 2, 1763249784, 1763249724, 1763249826),
(117, 'ratelimit::tenant_3:3600:4237999e5685cf86dbef60af2c4434d1', 5, 1763253326, 1763249726, 1763251342),
(118, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 8, 1763249791, 1763249731, 1763249846),
(119, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 6, 1763249798, 1763249738, 1763249853),
(120, 'ratelimit::tenant_3:60:c1258a8afea49c93018a38c7a0a9a84e', 1, 1763249804, 1763249744, 1763249744),
(121, 'ratelimit::tenant_3:3600:c1258a8afea49c93018a38c7a0a9a84e', 5, 1763253347, 1763249747, 1763251800),
(122, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 1, 1763249837, 1763249777, 1763249777),
(123, 'ratelimit::tenant_3:60:3365cd18e5b61aebf0dab18e451e0c52', 1, 1763249845, 1763249785, 1763249785),
(124, 'ratelimit::tenant_3:3600:3365cd18e5b61aebf0dab18e451e0c52', 2, 1763253387, 1763249787, 1763250903),
(125, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763249919, 1763249859, 1763249859),
(126, 'ratelimit::tenant_3:60:607cc60c594bdb4f37836a070a8d3cd6', 1, 1763249926, 1763249866, 1763249866),
(127, 'ratelimit::tenant_3:60:4237999e5685cf86dbef60af2c4434d1', 1, 1763249933, 1763249873, 1763249873),
(128, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763250153, 1763250093, 1763250093),
(129, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 1, 1763250159, 1763250099, 1763250099),
(130, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 5, 1763250739, 1763250679, 1763250766),
(131, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 7, 1763250746, 1763250686, 1763250801),
(132, 'ratelimit::tenant_3:60:607cc60c594bdb4f37836a070a8d3cd6', 1, 1763250833, 1763250773, 1763250773),
(133, 'ratelimit::tenant_3:60:4237999e5685cf86dbef60af2c4434d1', 1, 1763250841, 1763250781, 1763250781),
(134, 'ratelimit::tenant_3:60:c1258a8afea49c93018a38c7a0a9a84e', 2, 1763250848, 1763250788, 1763250887),
(135, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 1, 1763250868, 1763250808, 1763250808),
(136, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 3, 1763250875, 1763250815, 1763250909),
(137, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 1, 1763250953, 1763250893, 1763250893),
(138, 'ratelimit::tenant_3:60:3365cd18e5b61aebf0dab18e451e0c52', 1, 1763250961, 1763250901, 1763250901),
(139, 'ratelimit::tenant_3:60:dfe99a92f49e14a69725780f32feb861', 1, 1763250976, 1763250916, 1763250916),
(140, 'ratelimit::tenant_3:3600:dfe99a92f49e14a69725780f32feb861', 2, 1763254518, 1763250918, 1763251793),
(141, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 2, 1763250997, 1763250937, 1763250980),
(142, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 2, 1763251004, 1763250944, 1763250986),
(143, 'ratelimit::tenant_3:60:c1258a8afea49c93018a38c7a0a9a84e', 1, 1763251053, 1763250993, 1763250993),
(144, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 4, 1763251321, 1763251261, 1763251354),
(145, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 1, 1763251334, 1763251274, 1763251274),
(146, 'ratelimit::tenant_3:60:607cc60c594bdb4f37836a070a8d3cd6', 1, 1763251390, 1763251330, 1763251330),
(147, 'ratelimit::tenant_3:60:4237999e5685cf86dbef60af2c4434d1', 1, 1763251399, 1763251339, 1763251339),
(148, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 6, 1763251585, 1763251525, 1763251618),
(149, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 2, 1763251663, 1763251603, 1763251719),
(150, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 4, 1763251734, 1763251674, 1763251770),
(151, 'ratelimit::tenant_3:60:6d0ee73650759187be1579bbd255b5f9', 1, 1763251749, 1763251689, 1763251689),
(152, 'ratelimit::tenant_3:3600:6d0ee73650759187be1579bbd255b5f9', 1, 1763255291, 1763251691, 1763251691),
(153, 'ratelimit::tenant_3:60:dd751c3d4297ebddc716386eb9be207d', 1, 1763251758, 1763251698, 1763251698),
(154, 'ratelimit::tenant_3:3600:dd751c3d4297ebddc716386eb9be207d', 1, 1763255300, 1763251700, 1763251700),
(155, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 2, 1763251817, 1763251757, 1763251804),
(156, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 2, 1763251836, 1763251776, 1763251784),
(157, 'ratelimit::tenant_3:60:dfe99a92f49e14a69725780f32feb861', 1, 1763251851, 1763251791, 1763251791),
(158, 'ratelimit::tenant_3:60:c1258a8afea49c93018a38c7a0a9a84e', 1, 1763251858, 1763251798, 1763251798),
(159, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763251871, 1763251811, 1763251811);

-- --------------------------------------------------------

--
-- Estrutura para tabela `stripe_events`
--
-- Criação: 14/11/2025 às 19:54
--

CREATE TABLE `stripe_events` (
  `id` int(11) UNSIGNED NOT NULL,
  `event_id` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de eventos Stripe (idempotência de webhooks)';

--
-- RELACIONAMENTOS PARA TABELAS `stripe_events`:
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `subscriptions`
--
-- Criação: 14/11/2025 às 19:54
-- Última atualização: 15/11/2025 às 22:34
--

CREATE TABLE `subscriptions` (
  `id` int(11) UNSIGNED NOT NULL,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `customer_id` int(11) UNSIGNED NOT NULL,
  `stripe_subscription_id` varchar(255) NOT NULL,
  `stripe_customer_id` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `plan_id` varchar(255) DEFAULT NULL,
  `plan_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'usd',
  `current_period_start` datetime DEFAULT NULL,
  `current_period_end` datetime DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de assinaturas';

--
-- RELACIONAMENTOS PARA TABELAS `subscriptions`:
--   `customer_id`
--       `customers` -> `id`
--   `tenant_id`
--       `tenants` -> `id`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `subscription_history`
--
-- Criação: 15/11/2025 às 01:34
-- Última atualização: 15/11/2025 às 22:34
--

CREATE TABLE `subscription_history` (
  `id` int(11) UNSIGNED NOT NULL,
  `subscription_id` int(11) UNSIGNED NOT NULL COMMENT 'ID da assinatura (FK para subscriptions)',
  `tenant_id` int(11) UNSIGNED NOT NULL COMMENT 'ID do tenant (para filtros rápidos)',
  `change_type` varchar(50) NOT NULL COMMENT 'Tipo de mudança: created, updated, canceled, reactivated, plan_changed, status_changed',
  `changed_by` varchar(50) DEFAULT NULL COMMENT 'Origem da mudança: api, webhook, admin',
  `user_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'ID do usuário que fez a mudança (quando via API com autenticação de usuário)',
  `old_status` varchar(50) DEFAULT NULL COMMENT 'Status anterior da assinatura',
  `new_status` varchar(50) DEFAULT NULL COMMENT 'Status novo da assinatura',
  `old_plan_id` varchar(255) DEFAULT NULL COMMENT 'ID do plano anterior (price_id)',
  `new_plan_id` varchar(255) DEFAULT NULL COMMENT 'ID do plano novo (price_id)',
  `old_amount` decimal(10,2) DEFAULT NULL COMMENT 'Valor anterior (em formato monetário)',
  `new_amount` decimal(10,2) DEFAULT NULL COMMENT 'Valor novo (em formato monetário)',
  `old_currency` varchar(3) DEFAULT NULL COMMENT 'Moeda anterior',
  `new_currency` varchar(3) DEFAULT NULL COMMENT 'Moeda nova',
  `old_current_period_end` datetime DEFAULT NULL COMMENT 'Fim do período anterior',
  `new_current_period_end` datetime DEFAULT NULL COMMENT 'Fim do período novo',
  `old_cancel_at_period_end` tinyint(1) DEFAULT 0 COMMENT 'Cancelar ao fim do período anterior',
  `new_cancel_at_period_end` tinyint(1) DEFAULT 0 COMMENT 'Cancelar ao fim do período novo',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Metadados adicionais da mudança (JSON)' CHECK (json_valid(`metadata`)),
  `description` text DEFAULT NULL COMMENT 'Descrição da mudança (opcional)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data e hora da mudança'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de mudanças de assinaturas - auditoria de alterações';

--
-- RELACIONAMENTOS PARA TABELAS `subscription_history`:
--   `subscription_id`
--       `subscriptions` -> `id`
--   `tenant_id`
--       `tenants` -> `id`
--   `user_id`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `tenants`
--
-- Criação: 14/11/2025 às 19:54
-- Última atualização: 15/11/2025 às 22:34
--

CREATE TABLE `tenants` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de tenants (clientes SaaS)';

--
-- RELACIONAMENTOS PARA TABELAS `tenants`:
--

--
-- Despejando dados para a tabela `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `api_key`, `status`, `created_at`, `updated_at`) VALUES
(3, 'Principal', '2259e1ec9b69c26140000304940d58e7ee4ccd61c6a3771e3e5719d6e7c41035', 'active', '2025-11-15 22:31:42', '2025-11-15 22:31:42');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--
-- Criação: 15/11/2025 às 00:13
-- Última atualização: 15/11/2025 às 22:34
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `tenant_id` int(11) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `role` enum('admin','viewer','editor') NOT NULL DEFAULT 'viewer' COMMENT 'Role do usuário: admin (todas permissões), editor (editar), viewer (apenas visualizar)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de usuários';

--
-- RELACIONAMENTOS PARA TABELAS `users`:
--   `tenant_id`
--       `tenants` -> `id`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_permissions`
--
-- Criação: 15/11/2025 às 00:13
-- Última atualização: 15/11/2025 às 22:34
--

CREATE TABLE `user_permissions` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL COMMENT 'ID do usuário',
  `permission` varchar(100) NOT NULL COMMENT 'Nome da permissão (ex: view_subscriptions, create_subscriptions)',
  `granted` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Se a permissão está concedida (true) ou negada (false)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data de criação'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permissões específicas de usuários - controle granular além das roles';

--
-- RELACIONAMENTOS PARA TABELAS `user_permissions`:
--   `user_id`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_sessions`
--
-- Criação: 15/11/2025 às 00:13
-- Última atualização: 15/11/2025 às 22:34
--

CREATE TABLE `user_sessions` (
  `id` varchar(64) NOT NULL COMMENT 'Token de sessão (hash)',
  `user_id` int(11) UNSIGNED NOT NULL COMMENT 'ID do usuário',
  `tenant_id` int(11) UNSIGNED NOT NULL COMMENT 'ID do tenant',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP do cliente',
  `user_agent` text DEFAULT NULL COMMENT 'User-Agent do cliente',
  `expires_at` datetime NOT NULL COMMENT 'Data de expiração da sessão',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data de criação'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessões de usuários autenticados - tokens de acesso';

--
-- RELACIONAMENTOS PARA TABELAS `user_sessions`:
--   `tenant_id`
--       `tenants` -> `id`
--   `user_id`
--       `users` -> `id`
--

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_method` (`method`),
  ADD KEY `idx_response_status` (`response_status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_tenant_created` (`tenant_id`,`created_at`);

--
-- Índices de tabela `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stripe_customer` (`stripe_customer_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_email` (`email`);

--
-- Índices de tabela `phinxlog`
--
ALTER TABLE `phinxlog`
  ADD PRIMARY KEY (`version`);

--
-- Índices de tabela `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier_key` (`identifier_key`),
  ADD KEY `idx_reset_at` (`reset_at`);

--
-- Índices de tabela `stripe_events`
--
ALTER TABLE `stripe_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_processed` (`processed`);

--
-- Índices de tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stripe_subscription` (`stripe_subscription_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `subscription_history`
--
ALTER TABLE `subscription_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subscription_id` (`subscription_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_change_type` (`change_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_subscription_created` (`subscription_id`,`created_at`),
  ADD KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Índices de tabela `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_api_key` (`api_key`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_email` (`tenant_id`,`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_tenant_id` (`tenant_id`);

--
-- Índices de tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Índices de tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_session_id` (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=302;

--
-- AUTO_INCREMENT de tabela `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT de tabela `stripe_events`
--
ALTER TABLE `stripe_events`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `subscription_history`
--
ALTER TABLE `subscription_history`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_subscriptions_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subscriptions_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `subscription_history`
--
ALTER TABLE `subscription_history`
  ADD CONSTRAINT `fk_subscription_history_subscription_id` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subscription_history_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subscription_history_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_user_permissions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_tenant_id` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
