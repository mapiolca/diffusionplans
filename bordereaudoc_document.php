<?php
/* Copyright (C) 2025 Pierre ARDOIN <developpeur@lesmetiersdubatiment.fr>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
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
* \file       bordereaudoc_document.php
* \ingroup    diffusionplans
* \brief      Tab for documents linked to Bordereaudoc.
*/

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/diffusionplans/class/bordereaudoc.class.php');
dol_include_once('/diffusionplans/lib/diffusionplans_bordereaudoc.lib.php');

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */

// Load translation files required by the page
$langs->loadLangs(array('diffusionplans@diffusionplans', 'companies', 'other', 'mails'));

// Get parameters
$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) {
	$page = 0;
}
$offset = $limit * $page;
if (!$sortorder) {
	$sortorder = 'ASC';
}
if (!$sortfield) {
	$sortfield = 'name';
}

// Initialize objects
$object = new Bordereaudoc($db);
$form = new Form($db);
$formfile = new FormFile($db);
$hookmanager->initHooks(array('bordereaudocdocument', 'globalcard'));

// Permissions
$permissiontoread = $user->hasRight('diffusionplans', 'bordereaudoc', 'read');
$permissiontoadd = $user->hasRight('diffusionplans', 'bordereaudoc', 'write');

if (!$permissiontoread) {
	accessforbidden();
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';
if (empty($object->id)) {
	accessforbidden();
}

$upload_dir = $object->getDocumentsDirectory();
$modulepart = 'bordereaudoc';
$relativepath = 'bordereaudoc/'.dol_sanitizeFileName($object->ref);

if ($object->id > 0) {
	$permissiontoaddfile = $permissiontoadd;
	$param = '?id='.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_pre_headers.tpl.php';
}

/*
* Actions
*/

if ($action === 'savevisibility' && $permissiontoadd && (int) $object->status === Bordereaudoc::STATUS_DRAFT) {
	$visible = GETPOST('is_visible', 'array');
	$doclines = $object->getDocumentIndex();
	foreach ($doclines as $line) {
		$isVisible = !empty($visible[$line->rowid]) ? 1 : 0;
		$object->updateFileVisibility($line->rowid, $isVisible);
	}
	setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
}

include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';

// Refresh index to align database with filesystem and default visibility rules
$object->syncDocumentIndex($user);

/*
* View
*/

$help_url = '';
$title = $langs->trans('BordereaudocFiles');
llxHeader('', $title, $help_url);

$head = bordereaudocPrepareHead($object);
print dol_get_fiche_head($head, 'documents', $langs->trans('Bordereaudoc'), -1, $object->picto);

dol_banner_tab($object, 'ref', '', ($user->socid ? 0 : 1), 'ref', 'fk_project');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

$urlsource = $_SERVER['PHP_SELF'].'?id='.$object->id;
$caneditfiles = $permissiontoadd;
print $formfile->showdocuments($modulepart, $relativepath, $upload_dir, $urlsource, $caneditfiles, $caneditfiles, $object->model_pdf, 1, 0, 0, 0, 0, '', '', '', 0);

print '</div>';
print '<div class="fichehalfright">';

$doclines = $object->getDocumentIndex();
if (!empty($doclines)) {
	if ($caneditfiles && (int) $object->status === Bordereaudoc::STATUS_DRAFT) {
		print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="savevisibility">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
	}
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('File').'</th>';
	print '<th class="center">'.$langs->trans('BordereaudocListed').'</th>';
	print '<th>'.$langs->trans('BordereaudocDownloadLink').'</th>';
	print '</tr>';
	foreach ($doclines as $line) {
		print '<tr class="oddeven">';
		$relativefile = trim($line->filepath.'/'.dol_sanitizeFileName($line->filename), '/');
		$fileurl = DOL_URL_ROOT.'/document.php?modulepart='.urlencode($modulepart).'&file='.urlencode($relativefile);
		$hashurl = dol_buildpath('/diffusionplans/public/bordereaudocdownload.php', 2).'?hash='.urlencode($line->hash);
		print '<td><a href="'.$fileurl.'">'.img_mime($line->filename, '', 'pictofixedwidth').' '.dol_escape_htmltag($line->filename).'</a></td>';
		if ($caneditfiles && (int) $object->status === Bordereaudoc::STATUS_DRAFT) {
			print '<td class="center"><input type="checkbox" name="is_visible['.$line->rowid.']" value="1" '.(!empty($line->is_visible) ? 'checked' : '').'></td>';
		} else {
			print '<td class="center">'.yn($line->is_visible, 1, 0, 1).'</td>';
		}
		print '<td><a href="'.$hashurl.'" target="_blank" rel="noopener">'.dol_escape_htmltag($hashurl).'</a></td>';
		print '</tr>';
	}
	print '</table>';
	if ($caneditfiles && (int) $object->status === Bordereaudoc::STATUS_DRAFT) {
		print '<div class="center margintoponly">';
		print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
		print '</div>';
		print '</form>';
	}
} else {
	print '<div class="opacitymedium">'.$langs->trans('BordereaudocNoFiles').'</div>';
}

print '</div>';
print '</div>';

print dol_get_fiche_end();

// Footer
llxFooter();
$db->close();
