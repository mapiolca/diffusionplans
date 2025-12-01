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
* \file       bordereaudoc_agenda.php
* \ingroup    diffusionplans
* \brief      Tab of events for Bordereaudoc.
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

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
dol_include_once('/diffusionplans/class/bordereaudoc.class.php');
dol_include_once('/diffusionplans/lib/diffusionplans_bordereaudoc.lib.php');

// Load translation files required by the page
$langs->loadLangs(array('diffusionplans@diffusionplans', 'other'));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'bordereaudocagenda';

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) {
	$page = 0;
}
$offset = $limit * $page;
if (!$sortorder) {
	$sortorder = 'DESC';
}
if (!$sortfield) {
	$sortfield = 'a.datep';
}

// Initialize objects
$object = new Bordereaudoc($db);
$form = new Form($db);
$hookmanager->initHooks(array('bordereaudocagenda', 'globalcard'));

// Permissions
$permissiontoread = $user->hasRight('diffusionplans', 'bordereaudoc', 'read');
$permissiontoadd = $user->hasRight('agenda', 'myactions', 'create');

if (!$permissiontoread) {
	accessforbidden();
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';
if (empty($object->id)) {
	accessforbidden();
}

$backtopage = $backtopage ?: dol_buildpath('/diffusionplans/bordereaudoc_card.php', 1).'?id='.$object->id;
$param = '?id='.$object->id;

// Actions
$object->fetch_thirdparty();
$permissiontoreadnote = $permissiontoread;
$permissiontodeletenote = $permissiontoadd;
include DOL_DOCUMENT_ROOT.'/core/actions_agenda.inc.php';

// View
$help_url = '';
$title = $langs->trans('Agenda');
llxHeader('', $title, $help_url);

$head = bordereaudocPrepareHead($object);
print dol_get_fiche_head($head, 'agenda', $langs->trans('Bordereaudoc'), -1, $object->picto);

dol_banner_tab($object, 'ref', $backtopage, ($user->socid ? 0 : 1), 'ref', 'fk_project');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

$morehtmlright = '';
$morehtmlcenter = $langs->trans('Project').': ';
if (!empty($object->fk_project)) {
	$projectstatic = new Project($db);
	$projectstatic->fetch($object->fk_project);
	$morehtmlcenter .= $projectstatic->getNomUrl(1);
} else {
	$morehtmlcenter .= $langs->trans('None');
}

include DOL_DOCUMENT_ROOT.'/core/tpl/agenda_view.tpl.php';

print '</div>';
print '</div>';

print dol_get_fiche_end();

// Footer
llxFooter();
$db->close();
