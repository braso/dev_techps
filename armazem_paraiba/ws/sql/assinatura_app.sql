-- Suporte ao app: notificacoes de assinatura eletronica
-- (o endpoint /ws/signatures tambem cria a coluna automaticamente se faltar)
ALTER TABLE assinantes ADD COLUMN IF NOT EXISTS app_lida_em DATETIME NULL DEFAULT NULL;

-- Tabela referenciada por assinatura/renovar_link.php mas que nao existia no banco
CREATE TABLE IF NOT EXISTS notificacoes (
  notf_nb_id INT(11) NOT NULL AUTO_INCREMENT,
  notf_nb_entidade INT(11) NOT NULL,
  notf_tx_titulo VARCHAR(255) NOT NULL,
  notf_tx_mensagem TEXT NULL,
  notf_tx_link VARCHAR(500) NULL,
  notf_tx_tipo VARCHAR(30) NOT NULL DEFAULT 'info',
  notf_tx_status VARCHAR(20) NOT NULL DEFAULT 'nao_lida',
  notf_tx_dataCadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notf_tx_dataLeitura DATETIME NULL,
  PRIMARY KEY (notf_nb_id),
  KEY idx_notf_entidade (notf_nb_entidade, notf_tx_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
