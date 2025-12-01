-- Copyright (C) 2025 Pierre ARDOIN
--
-- GNU GPL v3

ALTER TABLE llx_bordereaudoc ADD INDEX idx_bordereaudoc_entity (entity);
ALTER TABLE llx_bordereaudoc ADD INDEX idx_bordereaudoc_project (fk_project);
ALTER TABLE llx_bordereaudoc ADD UNIQUE INDEX uk_bordereaudoc_ref (ref, entity);
