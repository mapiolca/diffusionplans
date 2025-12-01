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
* along with this program. If not, see <https://www.gnu.org/licenses/>.
*/

/**
* \file       bordereaudoc_card.php
* \ingroup    diffusionplans
* \brief      Card page for Bordereaudoc object.
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

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
dol_include_once('/diffusionplans/class/bordereaudoc.class.php');

// Load translations
$langs->loadLangs(array('diffusionplans@diffusionplans', 'other', 'projects'));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'bordereaudoccard';
$optioncss = GETPOST('optioncss', 'aZ');

// Objects
$object = new Bordereaudoc($db);
$form = new Form($db);
$formproject = new FormProjets($db);

// Permissions
$permissiontoread = $user->hasRight('diffusionplans', 'bordereaudoc', 'read');
$permissiontoadd = $user->hasRight('diffusionplans', 'bordereaudoc', 'write');
$permissiondelete = $user->hasRight('diffusionplans', 'bordereaudoc', 'delete');

if (!$permissiontoread) {
	accessforbidden();
}

// Fetch object
if (!empty($id) || !empty($ref)) {
	$object->fetch($id, $ref);
}

// Actions
if ($cancel && $backtopage) {
	header("Location: ".$backtopage);
	exit;
}

if ($action === 'add' && $permissiontoadd) {
	$object->ref = GETPOST('ref', 'alpha');
	$object->title = GETPOST('title', 'alpha');
	$object->description = dol_htmlcleanlastbr(GETPOST('description', 'restricthtml'));
	$object->fk_project = GETPOSTINT('fk_project');

	$result = $object->create($user);
	if ($result > 0) {
		if (!empty($backtopage)) {
			header('Location: '.$backtopage);
			exit;
		}
		header('Location: '.dol_buildpath('/diffusionplans/bordereaudoc_card.php', 1).'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'create';
	}
}

if ($action === 'update' && $permissiontoadd && $object->id > 0) {
	$object->title = GETPOST('title', 'alpha');
	$object->description = dol_htmlcleanlastbr(GETPOST('description', 'restricthtml'));
	$object->fk_project = GETPOSTINT('fk_project');
	$object->fk_user_modif = $user->id;
	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordModifiedSuccessfully'), null, 'mesgs');
		$action = 'view';
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}
}

if ($action === 'confirm_delete' && GETPOST('confirm', 'alpha') === 'yes' && $permissiondelete && $object->id > 0) {
	$result = $object->delete($user);
	if ($result > 0) {
		header('Location: '.dol_buildpath('/diffusionplans/diffusion_list.php', 1));
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
}

// View
$help_url = '';
llxHeader('', $langs->trans('BordereaudocCard'), $help_url);

if ($action === 'create') {
	print load_fiche_titre($langs->trans('BordereaudocNew'), '', 'fa fa-paper-plane');
	print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if (!empty($backtopage)) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}

	print dol_get_fiche_head();
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('BordereaudocTitle').'</td><td><input type="text" class="minwidth300" name="title" value="'.dol_escape_htmltag(GETPOST('title', 'alphanohtml')).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Project').'</td><td>';
	print $formproject->select_projects(-1, GETPOSTINT('fk_project'), 'fk_project', 0, 0, 1, 1, 0, 0, '', 1, 0, array(), false);
	print '</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>';
	$doleditor = new DolEditor('description', GETPOST('description', 'restricthtml'), '', 160, 'dolibarr_notes', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_SOCIETE'), 15, 90);
	$doleditor->Create(0, '', false, 'contenteditable');
	print '</td></tr>';
	print '</table>';
	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans('Create').'">';
	print '&nbsp;';
	print '<a class="button" href="'.($backtopage ? $backtopage : dol_buildpath('/diffusionplans/diffusion_list.php', 1)).'">'.$langs->trans('Cancel').'</a>';
	print '</div>';

	print '</form>';

	dol_print_footer();
	llxFooter();
	$db->close();
	exit;
}

if ($object->id > 0) {
	$head = array();
	$head[0][0] = dol_buildpath('/diffusionplans/bordereaudoc_card.php', 1).'?id='.$object->id;
	$head[0][1] = $langs->trans('Card');
	$head[0][2] = 'card';

	dol_fiche_head($head, 'card', $langs->trans('Bordereaudoc'), -1, 'fa-paper-plane');

	$linkback = '<a href="'.dol_buildpath('/diffusionplans/diffusion_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
	$morehtmlref = '';
	if (!empty($object->fk_project)) {
		$projectstatic = new Project($db);
		$projectstatic->fetch($object->fk_project);
		$morehtmlref .= '<br>'.$projectstatic->getNomUrl(1);
	}
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.$form->showrefnav($object, 'ref', $linkback, true, 'ref', 'ref', '').'</td></tr>';
	print '<tr><td>'.$langs->trans('BordereaudocTitle').'</td><td>'.dol_escape_htmltag($object->title).'</td></tr>';
	print '<tr><td>'.$langs->trans('Project').'</td><td>'.$morehtmlref.'</td></tr>';
	print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>'.dol_htmlentitiesbr($object->description).'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
	print '</table>';

	dol_fiche_end();
}

// Footer
dol_print_footer();
llxFooter();
$db->close();
