CREATE TABLE IF NOT EXISTS cms_pages (
 document_slug VARCHAR(50) PRIMARY KEY,
 route VARCHAR(200) NOT NULL UNIQUE,
 seo_defaults TEXT NOT NULL,
 source_modified_at DATETIME NOT NULL,
 FOREIGN KEY (document_slug) REFERENCES cms_documents(slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cms_redirects (
 source_path VARCHAR(255) PRIMARY KEY,
 target_path VARCHAR(255) NOT NULL,
 provenance VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
