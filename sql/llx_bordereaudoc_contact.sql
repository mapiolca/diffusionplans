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

CREATE TABLE llx_bordereaudoc_contact(
rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
fk_bordereaudoc integer NOT NULL,
fk_soc integer,
fk_contact integer,
nature_contact varchar(64),
type_contact varchar(64),
send_email integer NOT NULL DEFAULT 1,
send_mail integer NOT NULL DEFAULT 0,
send_hand integer NOT NULL DEFAULT 0,
active integer NOT NULL DEFAULT 1,
entity integer NOT NULL DEFAULT 1,
datec datetime,
tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
fk_user_creat integer
) ENGINE=innodb;
