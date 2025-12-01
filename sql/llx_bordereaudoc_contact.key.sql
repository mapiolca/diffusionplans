-- Copyright (C) 2025 Pierre ARDOIN
--
-- GNU GPL v3

ALTER TABLE llx_bordereaudoc_contact ADD INDEX idx_bordereaudoc_contact_entity (entity);
ALTER TABLE llx_bordereaudoc_contact ADD INDEX idx_bordereaudoc_contact_parent (fk_bordereaudoc);
ALTER TABLE llx_bordereaudoc_contact ADD INDEX idx_bordereaudoc_contact_contact (fk_contact);
