-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 18/11/2025 às 00:05
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
-- Última atualização: 17/11/2025 às 23:01
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
(1, NULL, NULL, '/dashboard', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 84, '2025-11-17 02:11:17'),
(2, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-17 02:11:24'),
(3, 3, 7, '/v1/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:11:30'),
(4, 3, 7, '/v1/stats', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 16, '2025-11-17 02:11:37'),
(5, NULL, NULL, '/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 8, '2025-11-17 02:12:44'),
(6, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:12:51'),
(7, 3, 7, '/v1/customers', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-17 02:12:57'),
(8, NULL, NULL, '/customer-details', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 86, '2025-11-17 02:13:30'),
(9, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 25, '2025-11-17 02:13:36'),
(10, 3, 7, '/v1/customers/6/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 404, 0, '2025-11-17 02:13:43'),
(11, 3, 7, '/v1/customers/6', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4790, '2025-11-17 02:13:54'),
(12, 3, 7, '/v1/customers/6/invoices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 919, '2025-11-17 02:14:01'),
(13, 3, 7, '/v1/customers/6/payment-methods', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 804, '2025-11-17 02:14:08'),
(14, NULL, NULL, '/customer-details', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5, '2025-11-17 02:14:15'),
(15, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-17 02:14:21'),
(16, 3, 7, '/v1/customers/6', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4855, '2025-11-17 02:14:33'),
(17, 3, 7, '/v1/customers/6/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 404, 0, '2025-11-17 02:14:39'),
(18, 3, 7, '/v1/customers/6/invoices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 840, '2025-11-17 02:14:46'),
(19, 3, 7, '/v1/customers/6/payment-methods', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 787, '2025-11-17 02:14:53'),
(20, NULL, NULL, '/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-17 02:15:00'),
(21, NULL, NULL, '/customer-details', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 6, '2025-11-17 02:15:06'),
(22, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:15:13'),
(23, 3, 7, '/v1/customers/6', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4785, '2025-11-17 02:15:24'),
(24, 3, 7, '/v1/customers/6/invoices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 794, '2025-11-17 02:15:31'),
(25, 3, 7, '/v1/customers/6/subscriptions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 404, 0, '2025-11-17 02:15:38'),
(26, 3, 7, '/v1/customers/6/payment-methods', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 693, '2025-11-17 02:15:45'),
(27, NULL, NULL, '/invoices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 49, '2025-11-17 02:18:04'),
(28, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:18:10'),
(29, NULL, NULL, '/transactions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-17 02:18:17'),
(30, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:18:23'),
(31, 3, 7, '/v1/balance-transactions', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1020, '2025-11-17 02:18:31'),
(32, NULL, NULL, '/transaction-details', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-17 02:18:42'),
(33, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-17 02:18:48'),
(34, 3, 7, '/v1/balance-transactions/txn_3STsMDByYvrEJg7O0PSN42F1', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1000, '2025-11-17 02:18:56'),
(35, NULL, NULL, '/invoice-items', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 6, '2025-11-17 02:19:12'),
(36, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:19:19'),
(37, 3, 7, '/v1/invoice-items', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 964, '2025-11-17 02:19:26'),
(38, NULL, NULL, '/billing-portal', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 22, '2025-11-17 02:19:36'),
(39, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:19:42'),
(40, NULL, NULL, '/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 7, '2025-11-17 02:19:50'),
(41, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-17 02:19:57'),
(42, 3, 7, '/v1/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4958, '2025-11-17 02:20:08'),
(43, NULL, NULL, '/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 6, '2025-11-17 02:20:21'),
(44, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:20:27'),
(45, 3, 7, '/v1/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5041, '2025-11-17 02:20:39'),
(46, NULL, NULL, '/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5, '2025-11-17 02:20:50'),
(47, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:20:57'),
(48, 3, 7, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5036, '2025-11-17 02:21:08'),
(49, 3, 7, '/v1/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4989, '2025-11-17 02:21:20'),
(50, NULL, NULL, '/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-17 02:24:04'),
(51, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:24:10'),
(52, 3, 7, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5014, '2025-11-17 02:24:22'),
(53, 3, 7, '/v1/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4940, '2025-11-17 02:24:34'),
(54, NULL, NULL, '/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5, '2025-11-17 02:26:26'),
(55, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 1, '2025-11-17 02:26:32'),
(56, 3, 7, '/v1/prices', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5420, '2025-11-17 02:26:44'),
(57, 3, 7, '/v1/products', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 5024, '2025-11-17 02:26:56'),
(58, NULL, NULL, '/subscription-history', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 3, '2025-11-17 02:27:07'),
(59, 3, 7, '/v1/auth/me', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 2, '2025-11-17 02:27:14'),
(60, NULL, NULL, '/dashboard', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 174, '2025-11-17 22:34:19'),
(61, NULL, NULL, '/login', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-17 22:34:26'),
(62, NULL, NULL, '/login', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 71, '2025-11-17 22:57:45'),
(63, NULL, NULL, '/login', 'GET', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', NULL, 200, 4, '2025-11-17 23:01:02');

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
-- Última atualização: 17/11/2025 às 23:01
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
(1, 'ratelimit::ip_::1:60:e75cd9ae5f91981fbdab9e7abbde8866', 1, 1763345535, 1763345475, 1763345475),
(2, 'ratelimit::ip_::1:3600:e75cd9ae5f91981fbdab9e7abbde8866', 1, 1763349077, 1763345477, 1763345477),
(3, 'ratelimit::tenant_3:60:6e5e928d8fb06e2df412898dafa8076a', 2, 1763345541, 1763345481, 1763345569),
(4, 'ratelimit::tenant_3:3600:6e5e928d8fb06e2df412898dafa8076a', 16, 1763349084, 1763345484, 1763346434),
(5, 'ratelimit::tenant_3:60:e6eb4274bcab75a413be314fa04bcdb5', 1, 1763345548, 1763345488, 1763345488),
(6, 'ratelimit::tenant_3:3600:e6eb4274bcab75a413be314fa04bcdb5', 1, 1763349090, 1763345490, 1763345490),
(7, 'ratelimit::tenant_3:60:c1258a8afea49c93018a38c7a0a9a84e', 1, 1763345554, 1763345494, 1763345494),
(8, 'ratelimit::tenant_3:3600:c1258a8afea49c93018a38c7a0a9a84e', 1, 1763349096, 1763345496, 1763345496),
(9, 'ratelimit::ip_::1:60:129a01f1b8e424875b7f01084713d18d', 1, 1763345622, 1763345562, 1763345562),
(10, 'ratelimit::ip_::1:3600:129a01f1b8e424875b7f01084713d18d', 1, 1763349164, 1763345564, 1763345564),
(11, 'ratelimit::tenant_3:60:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763345635, 1763345575, 1763345575),
(12, 'ratelimit::tenant_3:3600:3d2dd16387c5b2b6c15f00a314f302e2', 1, 1763349177, 1763345577, 1763345577),
(13, 'ratelimit::ip_::1:60:06a791e2012b1bcefd11b7cc8cac418a', 3, 1763345667, 1763345607, 1763345704),
(14, 'ratelimit::ip_::1:3600:06a791e2012b1bcefd11b7cc8cac418a', 3, 1763349210, 1763345610, 1763345706),
(15, 'ratelimit::tenant_3:60:6e5e928d8fb06e2df412898dafa8076a', 3, 1763345674, 1763345614, 1763345711),
(16, 'ratelimit::tenant_3:60:48599aa073cc9e0f006ce942b14fac58', 3, 1763345681, 1763345621, 1763345735),
(17, 'ratelimit::tenant_3:3600:48599aa073cc9e0f006ce942b14fac58', 3, 1763349223, 1763345623, 1763345738),
(18, 'ratelimit::tenant_3:60:de76da3791b8c76a668aacb07aae6ac8', 3, 1763345687, 1763345627, 1763345717),
(19, 'ratelimit::tenant_3:3600:de76da3791b8c76a668aacb07aae6ac8', 3, 1763349229, 1763345629, 1763345719),
(20, 'ratelimit::tenant_3:60:470b2c477efd9f1f6fce4c9d18f0f1a4', 3, 1763345698, 1763345638, 1763345728),
(21, 'ratelimit::tenant_3:3600:470b2c477efd9f1f6fce4c9d18f0f1a4', 3, 1763349240, 1763345640, 1763345730),
(22, 'ratelimit::tenant_3:60:efe94e029fc4aa1c80bd2322eb6a992a', 3, 1763345705, 1763345645, 1763345742),
(23, 'ratelimit::tenant_3:3600:efe94e029fc4aa1c80bd2322eb6a992a', 3, 1763349248, 1763345648, 1763345744),
(24, 'ratelimit::ip_::1:60:51679ce3738a69b94d40325e0e3d57db', 1, 1763345758, 1763345698, 1763345698),
(25, 'ratelimit::ip_::1:3600:51679ce3738a69b94d40325e0e3d57db', 3, 1763349300, 1763345700, 1763346021),
(26, 'ratelimit::ip_::1:60:08c3be2613106c543c8783da63448efd', 1, 1763345942, 1763345882, 1763345882),
(27, 'ratelimit::ip_::1:3600:08c3be2613106c543c8783da63448efd', 1, 1763349484, 1763345884, 1763345884),
(28, 'ratelimit::tenant_3:60:6e5e928d8fb06e2df412898dafa8076a', 6, 1763345948, 1763345888, 1763345994),
(29, 'ratelimit::ip_::1:60:8042d8d867efaf1c0b2f02fa297d95d5', 1, 1763345955, 1763345895, 1763345895),
(30, 'ratelimit::ip_::1:3600:8042d8d867efaf1c0b2f02fa297d95d5', 1, 1763349497, 1763345897, 1763345897),
(31, 'ratelimit::tenant_3:60:607cc60c594bdb4f37836a070a8d3cd6', 1, 1763345968, 1763345908, 1763345908),
(32, 'ratelimit::tenant_3:3600:607cc60c594bdb4f37836a070a8d3cd6', 1, 1763349510, 1763345910, 1763345910),
(33, 'ratelimit::ip_::1:60:9d0940cc3942357440ad2234f6a073bf', 1, 1763345980, 1763345920, 1763345920),
(34, 'ratelimit::ip_::1:3600:9d0940cc3942357440ad2234f6a073bf', 1, 1763349522, 1763345922, 1763345922),
(35, 'ratelimit::tenant_3:60:b07fe3572f266d20611e07df36f31ac7', 1, 1763345993, 1763345933, 1763345933),
(36, 'ratelimit::tenant_3:3600:b07fe3572f266d20611e07df36f31ac7', 1, 1763349535, 1763345935, 1763345935),
(37, 'ratelimit::ip_::1:60:d39931da558e7336d7027b0b49316e2e', 1, 1763346010, 1763345950, 1763345950),
(38, 'ratelimit::ip_::1:3600:d39931da558e7336d7027b0b49316e2e', 1, 1763349552, 1763345952, 1763345952),
(39, 'ratelimit::tenant_3:60:39ead4769935b9899d63312341877f6b', 1, 1763346023, 1763345963, 1763345963),
(40, 'ratelimit::tenant_3:3600:39ead4769935b9899d63312341877f6b', 1, 1763349565, 1763345965, 1763345965),
(41, 'ratelimit::ip_::1:60:719533395fccf90beb2b225a765e0b41', 1, 1763346034, 1763345974, 1763345974),
(42, 'ratelimit::ip_::1:3600:719533395fccf90beb2b225a765e0b41', 1, 1763349576, 1763345976, 1763345976),
(43, 'ratelimit::ip_::1:60:51679ce3738a69b94d40325e0e3d57db', 2, 1763346048, 1763345988, 1763346018),
(44, 'ratelimit::tenant_3:60:dfe99a92f49e14a69725780f32feb861', 3, 1763346061, 1763346001, 1763346072),
(45, 'ratelimit::tenant_3:3600:dfe99a92f49e14a69725780f32feb861', 5, 1763349603, 1763346003, 1763346411),
(46, 'ratelimit::tenant_3:60:6e5e928d8fb06e2df412898dafa8076a', 2, 1763346085, 1763346025, 1763346055),
(47, 'ratelimit::ip_::1:60:6f4a1cc68dd53451ea21cd95c694cf3c', 1, 1763346108, 1763346048, 1763346048),
(48, 'ratelimit::ip_::1:3600:6f4a1cc68dd53451ea21cd95c694cf3c', 3, 1763349650, 1763346050, 1763346386),
(49, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 1, 1763346121, 1763346061, 1763346061),
(50, 'ratelimit::tenant_3:3600:3576c29f16a7eaa16c0b9165b7c916d2', 3, 1763349663, 1763346063, 1763346399),
(51, 'ratelimit::ip_::1:60:6f4a1cc68dd53451ea21cd95c694cf3c', 1, 1763346301, 1763346241, 1763346241),
(52, 'ratelimit::tenant_3:60:6e5e928d8fb06e2df412898dafa8076a', 1, 1763346308, 1763346248, 1763346248),
(53, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 1, 1763346315, 1763346255, 1763346255),
(54, 'ratelimit::tenant_3:60:dfe99a92f49e14a69725780f32feb861', 1, 1763346326, 1763346266, 1763346266),
(55, 'ratelimit::ip_::1:60:6f4a1cc68dd53451ea21cd95c694cf3c', 1, 1763346443, 1763346383, 1763346383),
(56, 'ratelimit::tenant_3:60:6e5e928d8fb06e2df412898dafa8076a', 2, 1763346450, 1763346390, 1763346432),
(57, 'ratelimit::tenant_3:60:3576c29f16a7eaa16c0b9165b7c916d2', 1, 1763346457, 1763346397, 1763346397),
(58, 'ratelimit::tenant_3:60:dfe99a92f49e14a69725780f32feb861', 1, 1763346469, 1763346409, 1763346409),
(59, 'ratelimit::ip_::1:60:9fc9d71b1b5f5a4ab702fc23a85751de', 1, 1763346485, 1763346425, 1763346425),
(60, 'ratelimit::ip_::1:3600:9fc9d71b1b5f5a4ab702fc23a85751de', 1, 1763350027, 1763346427, 1763346427),
(61, 'ratelimit::ip_::1:60:e75cd9ae5f91981fbdab9e7abbde8866', 1, 1763418917, 1763418857, 1763418857),
(62, 'ratelimit::ip_::1:3600:e75cd9ae5f91981fbdab9e7abbde8866', 1, 1763422459, 1763418859, 1763418859),
(63, 'ratelimit::ip_::1:60:4146ec82a0f0a638db9293a0c2039e6b', 1, 1763418924, 1763418864, 1763418864),
(64, 'ratelimit::ip_::1:3600:4146ec82a0f0a638db9293a0c2039e6b', 3, 1763422466, 1763418866, 1763420462),
(65, 'ratelimit::ip_::1:60:4146ec82a0f0a638db9293a0c2039e6b', 1, 1763420323, 1763420263, 1763420263),
(66, 'ratelimit::ip_::1:60:4146ec82a0f0a638db9293a0c2039e6b', 1, 1763420520, 1763420460, 1763420460);

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

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `email`, `password_hash`, `name`, `status`, `role`, `created_at`, `updated_at`) VALUES
(7, 3, 'admin@admin.com', '$2y$10$QFgIfIE5OMmsMOoA5E.2ieRg39TxrZJNxeLTaBlWhKtaMhFJ15FnG', 'Admin Principal', 'active', 'admin', '2025-11-16 03:43:22', '2025-11-16 03:43:22');

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_permissions`
--
-- Criação: 15/11/2025 às 00:13
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
-- Despejando dados para a tabela `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `tenant_id`, `ip_address`, `user_agent`, `expires_at`, `created_at`) VALUES
('074d211c04aa19d76c6200a80dfc864b02c5078902bd25751529765be8492a14', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 01:57:40', '2025-11-17 00:57:40'),
('0aa620274d4f0aa26290f1340617702269477b0f3dc929a7cb6ed3bf4147952a', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 04:53:09', '2025-11-16 03:53:09'),
('2024a0366c6c86a0a37c1137849217cd9ce8c99bf3244e986c180bd68c8c28af', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 02:00:39', '2025-11-17 01:00:39'),
('287cc8d3cde52eaaddfe63bf1d0d773f53e536825d54f8f46e9319611e3b7917', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 01:15:10', '2025-11-17 00:15:10'),
('3537c741df28407a5e14e5411a4a5b3143d0b5923e69f48af582fb4dd07b962c', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 03:03:54', '2025-11-17 02:03:54'),
('410bd52eca97b9ca09976f131d384a254042f6e88c9112b4022f2917358603b3', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 01:53:53', '2025-11-17 00:53:53'),
('7984979a40ffa937b5abadf32960fc5f22f88ca496d666d690a7508b79103915', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 01:11:00', '2025-11-17 00:11:00'),
('7f4d8c1279982490cd74ef31dc6dae8443dce0326334b72c9d9c1a4d3b3d8b65', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 02:00:23', '2025-11-17 01:00:23'),
('9444767ab57f9904b1219943e2ed7975c1c4d397434057a35d46a728944b6649', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 01:17:31', '2025-11-17 00:17:31'),
('b2ad4468474aab1e4014c6ebf62362d108176541fd43d80c4e910f000b3517e5', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 02:02:16', '2025-11-17 01:02:16'),
('b7c1e9374e924ed3828397f0837d784b85707e1c5ece7f6da6146fafbccf358e', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 05:10:49', '2025-11-16 04:10:49'),
('bb9bf5fb2621bce300e41dd322f8ce266c94bc11b9d11587245784a72989520d', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 05:16:28', '2025-11-16 04:16:28'),
('c2be260c529606c830b89d9254097ed2d37dcf75976556d11d90e30132309382', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 05:11:10', '2025-11-16 04:11:10'),
('feb68d7304d218f6c3dbf4154402740f23e1cc5a33208117c88a4260abb4b023', 7, 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-17 05:32:34', '2025-11-16 04:32:34');

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
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

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
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

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
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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


--
-- Metadata
--
USE `phpmyadmin`;

--
-- Metadata para tabela audit_logs
--

--
-- Metadata para tabela backup_logs
--

--
-- Metadata para tabela customers
--

--
-- Metadata para tabela phinxlog
--

--
-- Metadata para tabela rate_limits
--

--
-- Metadata para tabela stripe_events
--

--
-- Metadata para tabela subscriptions
--

--
-- Metadata para tabela subscription_history
--

--
-- Metadata para tabela tenants
--

--
-- Metadata para tabela users
--

--
-- Metadata para tabela user_permissions
--

--
-- Metadata para tabela user_sessions
--

--
-- Metadata para o banco de dados saas_payments
--
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
