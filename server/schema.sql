-- OpenQP access on qchem.knu.ac.kr: tables for sign-in, requests and downloads.
-- Loaded once by install.sh into the database "openqp_access".

CREATE TABLE IF NOT EXISTS academic_domains (
  domain VARCHAR(253) NOT NULL PRIMARY KEY,   -- matches the domain and every subdomain
  note   VARCHAR(200) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO academic_domains (domain, note) VALUES
  ('ac.kr', 'Korean universities'), ('edu', 'US universities'), ('ac.uk', 'UK universities'),
  ('ac.jp', 'Japanese universities'), ('edu.cn', 'Chinese universities'), ('ac.in', 'Indian universities'),
  ('edu.au', 'Australian universities'), ('ethz.ch', 'ETH Zurich'), ('epfl.ch', 'EPFL'),
  ('tum.de', 'Technical University of Munich'), ('uni-heidelberg.de', 'Heidelberg University'),
  ('kisti.re.kr', 'KISTI (research institute)');

-- One row per emailed sign-in link; only the SHA-256 of the link's secret is stored.
CREATE TABLE IF NOT EXISTS login_tokens (
  token_hash CHAR(64)     NOT NULL PRIMARY KEY,
  email      VARCHAR(254) NOT NULL,
  created_at DATETIME     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  ip         VARCHAR(45)  NULL,
  KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every email the page sends, for rate limiting.
CREATE TABLE IF NOT EXISTS mail_log (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email   VARCHAR(254) NOT NULL,
  ip      VARCHAR(45)  NULL,
  kind    VARCHAR(20)  NOT NULL,
  sent_at DATETIME     NOT NULL,
  KEY (email, sent_at),
  KEY (ip, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per person: who they are, licence acceptance, and the decision.
CREATE TABLE IF NOT EXISTS access_requests (
  email               VARCHAR(254)  NOT NULL PRIMARY KEY,
  full_name           VARCHAR(200)  NOT NULL,
  institution         VARCHAR(300)  NOT NULL,
  purpose             VARCHAR(2000) NULL,
  licence_version     VARCHAR(60)   NOT NULL,
  licence_accepted_at DATETIME      NOT NULL,
  status              ENUM('approved','pending','declined') NOT NULL,
  created_at          DATETIME      NOT NULL,
  decided_at          DATETIME      NULL,
  decided_by          VARCHAR(254)  NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every download link handed out.
CREATE TABLE IF NOT EXISTS download_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(254) NOT NULL,
  asset_name VARCHAR(255) NOT NULL,
  ip         VARCHAR(45)  NULL,
  at         DATETIME     NOT NULL,
  KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
