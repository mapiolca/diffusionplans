-- Copyright (C) 2025 Pierre ARDOIN
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

CREATE TABLE llx_bordereaudoc(
rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
ref varchar(128) DEFAULT '(PROV)' NOT NULL,
title varchar(255),
description longtext,
fk_project integer NOT NULL,
statut integer NOT NULL DEFAULT 0,
entity integer NOT NULL DEFAULT 1,
datec datetime,
tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
fk_user_creat integer,
fk_user_modif integer,
import_key varchar(14),
last_main_doc varchar(255),
model_pdf varchar(255)
) ENGINE=innodb;
