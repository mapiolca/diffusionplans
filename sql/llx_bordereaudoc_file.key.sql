-- Copyright (C) 2025 Pierre ARDOIN
--
-- GNU GPL v3

ALTER TABLE llx_bordereaudoc_file ADD INDEX idx_bordereaudoc_file_entity (entity);
ALTER TABLE llx_bordereaudoc_file ADD INDEX idx_bordereaudoc_file_parent (fk_bordereaudoc);
ALTER TABLE llx_bordereaudoc_file ADD UNIQUE INDEX uk_bordereaudoc_file_hash (hash, entity);
