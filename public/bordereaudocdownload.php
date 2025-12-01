<?php
/* Copyright (C) 2025 Pierre ARDOIN <developpeur@lesmetiersdubatiment.fr>
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

/**
* \file       public/bordereaudocdownload.php
* \ingroup    diffusionplans
* \brief      Public download entry for Bordereaudoc files using hash tokens.
*/

if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/diffusionplans/class/bordereaudoc.class.php');

$hash = GETPOST('hash', 'alphanohtml');
if (empty($hash)) {
	http_response_code(400);
	echo 'Missing hash';
	exit;
}

$sql = 'SELECT f.rowid, f.filename, f.filepath, f.entity, b.ref';
$sql .= ' FROM '.MAIN_DB_PREFIX.'bordereaudoc_file as f';
$sql .= ' JOIN '.MAIN_DB_PREFIX.'bordereaudoc as b ON b.rowid = f.fk_bordereaudoc';
$sql .= " WHERE f.hash = '".$db->escape($hash)."'";
$sql .= ' AND f.entity IN ('.getEntity('bordereaudoc', 1).')';

$resql = $db->query($sql);
if (!$resql) {
	http_response_code(500);
	echo 'Error';
	exit;
}

$obj = $db->fetch_object($resql);
if (!$obj) {
	http_response_code(404);
	echo 'File not found';
	exit;
}

$entity = (int) $obj->entity;
$baseDir = !empty($conf->diffusionplans->multidir_output[$entity]) ? $conf->diffusionplans->multidir_output[$entity] : $conf->diffusionplans->dir_output;
$fullpath = rtrim($baseDir, '/').'/'.trim($obj->filepath, '/').'/'.$obj->filename;

if (!is_readable($fullpath)) {
	http_response_code(404);
	echo 'File not found';
	exit;
}

$mime = dol_mimetype($fullpath);
$filename = basename($fullpath);
top_httphead($mime, 0, 0, 0);
header('Content-Disposition: attachment; filename="'.dol_escape_htmltag($filename).'"');
readfile($fullpath);
exit;
