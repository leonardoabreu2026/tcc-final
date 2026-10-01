-- ============================================================
-- TCC FINAL — CONECTA VAGAS DF — ESTRUTURA DO BANCO (schema)
-- ============================================================
-- Cria o banco tcc_final do zero: APAGA e recria SÓ esse banco.
-- Depois de importar este arquivo, importe o database/seed.sql (dados de demonstração).
--
-- phpMyAdmin: Importar > schema.sql e depois seed.sql
-- Terminal (Windows/XAMPP), a partir da pasta do projeto:
--   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\schema.sql
--   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\seed.sql
--
-- Tabelas: usuarios (contas) → perfis (1:1, candidato ou empresa) → curriculos, vagas;
-- categorias; cursos; candidaturas (candidato × vaga); matches (nota candidato × vaga);
-- assinaturas (planos); tentativas_login e redefinicoes_senha (segurança da conta);
-- São 11 tabelas. As chaves estrangeiras usam ON DELETE CASCADE: excluir um usuário remove tudo dele.
-- As máquinas de extração não têm tabela própria: os padrões automáticos delas são tirados na hora das
-- vagas e dos cursos já cadastrados (app/Services/Extracao/PadroesExtracao.php).
-- ============================================================
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS tcc_final;
CREATE DATABASE tcc_final CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tcc_final;

CREATE TABLE usuarios (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(255) NOT NULL,
 email VARCHAR(255) NOT NULL UNIQUE,
 senha VARCHAR(255) NOT NULL,
 tipo ENUM('admin','candidato','empresa') NOT NULL DEFAULT 'candidato',
 telefone VARCHAR(30),
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 ultimo_acesso DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_usuario_tipo(tipo), INDEX idx_usuario_ativo(ativo)
) ENGINE=InnoDB;

CREATE TABLE perfis (
 id INT AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT NOT NULL UNIQUE,
 titulo_profissional VARCHAR(255),
 bio TEXT,
 data_nascimento DATE NULL,
 cidade VARCHAR(100) DEFAULT 'Brasília',
 uf VARCHAR(2) DEFAULT 'DF',
 nome_fantasia VARCHAR(255),
 cnpj VARCHAR(20),
 setor VARCHAR(100),
 site VARCHAR(255),
 habilidades TEXT,
 experiencias TEXT,
 formacao TEXT,
 cursos_complementares TEXT,
 informacoes_adicionais TEXT,
 idiomas TEXT,
 competencias TEXT,
 nivel_experiencia ENUM('estagiario','junior','pleno','senior') DEFAULT 'junior',
 disponibilidade VARCHAR(100),
 objetivo TEXT,
 links TEXT,
 cnh VARCHAR(5),
 pretensao_salarial VARCHAR(60),
 foto VARCHAR(255),
 publico TINYINT(1) NOT NULL DEFAULT 1,
 aceite_lgpd TINYINT(1) NOT NULL DEFAULT 0,
 aceite_em DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categorias (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nome VARCHAR(100) NOT NULL,
 tipo ENUM('vaga','curso') NOT NULL,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_categoria(nome,tipo),
 INDEX idx_categoria_tipo_ativo(tipo,ativo,nome)
) ENGINE=InnoDB;

CREATE TABLE vagas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 perfil_empresa_id INT NOT NULL,
 categoria_id INT NULL,
 titulo VARCHAR(255) NOT NULL,
 anunciante VARCHAR(150) NULL,              -- empresa do anúncio, quando a vaga é publicada pela curadoria (ex.: lida do cartaz)
 descricao TEXT,
 requisitos TEXT,
 beneficios TEXT,
 contato VARCHAR(255) NULL,                 -- contato informado no anúncio (WhatsApp, telefone, e-mail)
 tipo_vaga ENUM('clt','pj','estagio','temporario') NOT NULL DEFAULT 'clt',
 nivel_experiencia ENUM('estagiario','junior','pleno','senior') NOT NULL DEFAULT 'junior',
 remoto ENUM('presencial','remoto','hibrido') NOT NULL DEFAULT 'presencial',
 cidade VARCHAR(100),
 uf VARCHAR(2),
 salario_minimo DECIMAL(10,2) NULL,
 salario_maximo DECIMAL(10,2) NULL,
 imagem VARCHAR(255),
 status ENUM('ativa','pausada','encerrada') NOT NULL DEFAULT 'ativa',
 destaque TINYINT(1) NOT NULL DEFAULT 0,
 data_publicacao DATE NULL,
 data_expiracao DATE NULL,
 visualizacoes INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(perfil_empresa_id) REFERENCES perfis(id) ON DELETE CASCADE,
 FOREIGN KEY(categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
 INDEX idx_vaga_empresa(perfil_empresa_id),
 INDEX idx_vaga_categoria(categoria_id),
 INDEX idx_vaga_status(status),
 INDEX idx_vaga_destaque(destaque),
 INDEX idx_vaga_cidade(cidade,uf)
) ENGINE=InnoDB;

CREATE TABLE cursos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 categoria_id INT NULL,
 titulo VARCHAR(255) NOT NULL,
 descricao TEXT,
 tipo ENUM('curso','ebook','video') NOT NULL DEFAULT 'curso',
 modalidade ENUM('ead','presencial','hibrido') NOT NULL DEFAULT 'ead',
 nivel ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante',
 duracao VARCHAR(50),
 gratuito TINYINT(1) NOT NULL DEFAULT 1,
 preco DECIMAL(10,2) NULL,
 url VARCHAR(500),
 imagem VARCHAR(255),
 instituicao VARCHAR(255),
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
 INDEX idx_curso_categoria(categoria_id),
 INDEX idx_curso_ativo(ativo)
) ENGINE=InnoDB;

CREATE TABLE curriculos (
 id INT AUTO_INCREMENT PRIMARY KEY,
 perfil_id INT NOT NULL,
 titulo VARCHAR(255),
 arquivo_pdf VARCHAR(255) NOT NULL,
 arquivo_tipo VARCHAR(20) NOT NULL,
 curriculo_texto LONGTEXT,
 template VARCHAR(50) NOT NULL DEFAULT 'moderno',
 downloads INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(perfil_id) REFERENCES perfis(id) ON DELETE CASCADE,
 INDEX idx_cv_perfil(perfil_id)
) ENGINE=InnoDB;

CREATE TABLE matches (
 id INT AUTO_INCREMENT PRIMARY KEY,
 perfil_candidato_id INT NOT NULL,
 vaga_id INT NOT NULL,
 pontuacao DECIMAL(5,2) NOT NULL DEFAULT 0,
 nivel ENUM('excelente','alto','medio','baixo') NOT NULL DEFAULT 'baixo',
 detalhes TEXT NULL COMMENT 'JSON com a explicação da pontuação (competências atendidas/faltantes, cargo, local, nível)',
 visualizado TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(perfil_candidato_id) REFERENCES perfis(id) ON DELETE CASCADE,
 FOREIGN KEY(vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
 UNIQUE KEY uniq_match(perfil_candidato_id,vaga_id),
 INDEX idx_match_vaga(vaga_id)
) ENGINE=InnoDB;

CREATE TABLE assinaturas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT NOT NULL,
 plano ENUM('assinante','empresa') NOT NULL,
 valor DECIMAL(10,2) NOT NULL DEFAULT 0,
 data_inicio DATE NOT NULL,
 data_fim DATE NOT NULL,
 status ENUM('ativa','cancelada','expirada') NOT NULL DEFAULT 'ativa',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
 INDEX idx_ass_user(usuario_id),
 INDEX idx_ass_status(status),
 INDEX idx_ass_usuario_vigente(usuario_id,status,data_fim),
 INDEX idx_ass_fim(data_fim)
) ENGINE=InnoDB;

CREATE TABLE candidaturas (
 id INT AUTO_INCREMENT PRIMARY KEY,
 perfil_candidato_id INT NOT NULL,
 vaga_id INT NOT NULL,
 curriculo_id INT NULL,
 carta_apresentacao TEXT,
 status ENUM('enviada','em_analise','entrevista','aprovado','rejeitado','cancelada') NOT NULL DEFAULT 'enviada',
 observacao_empresa TEXT,
 data_candidatura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(perfil_candidato_id) REFERENCES perfis(id) ON DELETE CASCADE,
 FOREIGN KEY(vaga_id) REFERENCES vagas(id) ON DELETE CASCADE,
 FOREIGN KEY(curriculo_id) REFERENCES curriculos(id) ON DELETE SET NULL,
 UNIQUE KEY uniq_candidatura(perfil_candidato_id,vaga_id),
 INDEX idx_candidatura_vaga(vaga_id)
) ENGINE=InnoDB;


-- Controle de força-bruta no login: cada linha é uma tentativa com senha errada.
-- Bloqueia após muitas falhas para o mesmo e-mail/IP dentro de uma janela de tempo.
CREATE TABLE tentativas_login (
 id INT AUTO_INCREMENT PRIMARY KEY,
 ip VARCHAR(45) NOT NULL,
 email VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_tentativa_ip_email(ip,email,created_at),
 INDEX idx_tentativa_data(created_at)
) ENGINE=InnoDB;

-- Recuperação de senha (demonstrativa: sem envio de e-mail real).
-- Guarda só o HASH SHA-256 do token; o token em si nunca fica no banco.
CREATE TABLE redefinicoes_senha (
 id INT AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT NOT NULL,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expira_em DATETIME NOT NULL,
 usado_em DATETIME NULL,
 ip VARCHAR(45) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
 INDEX idx_redef_usuario(usuario_id,created_at)
) ENGINE=InnoDB;
