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
 * \file	bordereaudoc_list.php
 * \ingroup     diffusionplans
 * \brief       List page for Bordereaudoc objects.
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
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/class/bordereaudoc.class.php';

// Load translation files required by the page
$langs->loadLangs(array('diffusionplans@diffusionplans', 'projects'));

// Security check
if (empty($user->rights->diffusionplans->bordereaudoc->read)) {
	accessforbidden();
}

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = (int) GETPOST('page', 'int');
if ($page < 0) {
	$page = 0;
}
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

$search_ref = GETPOST('search_ref', 'alpha');
$search_title = GETPOST('search_title', 'alpha');
$search_project = GETPOSTINT('search_project');
$search_status = GETPOSTINT('search_status', -1);
$search_date_startday = GETPOSTINT('search_date_startday');
$search_date_startmonth = GETPOSTINT('search_date_startmonth');
$search_date_startyear = GETPOSTINT('search_date_startyear');
$search_date_endday = GETPOSTINT('search_date_endday');
$search_date_endmonth = GETPOSTINT('search_date_endmonth');
$search_date_endyear = GETPOSTINT('search_date_endyear');

$search_date_start = dol_mktime(0, 0, 0, $search_date_startmonth, $search_date_startday, $search_date_startyear);
$search_date_end = dol_mktime(23, 59, 59, $search_date_endmonth, $search_date_endday, $search_date_endyear);

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');

// Objects
$form = new Form($db);
$formproject = new FormProjets($db);
$projectstatic = new Project($db);
$object = new Bordereaudoc($db);

$formconfirm = '';

$arrayofmassactions = array();
if (!empty($user->rights->diffusionplans->bordereaudoc->delete)) {
	$arrayofmassactions['delete'] = $langs->trans('Delete');
}

if ($action === 'confirm_delete' && $confirm === 'yes' && $massaction === 'delete' && !empty($toselect)) {
	$error = 0;
	foreach ($toselect as $selectedId) {
	if (empty($selectedId)) {
	continue;
	}
	$resFetch = $object->fetch((int) $selectedId);
	if ($resFetch > 0) {
if ((int) $object->statut === Bordereaudoc::STATUS_DRAFT) {
	$resDel = $object->delete($user);
	if ($resDel < 0) {
	$error++;
	setEventMessages($object->error, $object->errors, 'errors');
	}
	} else {
	$error++;
	setEventMessages($langs->trans('ErrorBordereaudocDeleteDraftOnly', $object->ref), null, 'errors');
	}
	}
	}

	if (!$error) {
	setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
	}
	}

if ($massaction === 'delete' && !empty($arrayofmassactions) && !empty($toselect)) {
$formconfirm = $form->formconfirm($_SERVER['PHP_SELF'], $langs->trans('Delete'), $langs->trans('ConfirmDelete'), 'confirm_delete', array('massaction' => 'delete', 'toselect' => $toselect));
}

if (empty($sortfield)) {
	$sortfield = 't.ref';
}
if (empty($sortorder)) {
	$sortorder = 'ASC';
}

$statusLabels = array(
Bordereaudoc::STATUS_DRAFT => $langs->trans('Draft'),
Bordereaudoc::STATUS_VALIDATED => $langs->trans('Validated'),
Bordereaudoc::STATUS_DELIVERED => $langs->trans('Delivered'),
Bordereaudoc::STATUS_CLOSED => $langs->trans('Closed')
);

$sql = 'SELECT t.rowid, t.ref, t.title, t.fk_project, t.statut, t.datec, t.tms, p.ref as project_ref, p.title as project_title,';
$sql .= ' bc.cnt_contact as nb_contacts, bf.cnt_visible as nb_visible_files';
$sql .= ' FROM '.MAIN_DB_PREFIX.'bordereaudoc as t';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."projet as p ON p.rowid = t.fk_project";
$sql .= ' LEFT JOIN (SELECT fk_bordereaudoc, COUNT(*) as cnt_contact FROM '.MAIN_DB_PREFIX."bordereaudoc_contact WHERE active = 1 AND entity IN (".getEntity('bordereaudoc').") GROUP BY fk_bordereaudoc) as bc ON bc.fk_bordereaudoc = t.rowid";
$sql .= ' LEFT JOIN (SELECT fk_bordereaudoc, COUNT(*) as cnt_visible FROM '.MAIN_DB_PREFIX."bordereaudoc_file WHERE is_visible = 1 AND entity IN (".getEntity('bordereaudoc').") GROUP BY fk_bordereaudoc) as bf ON bf.fk_bordereaudoc = t.rowid";
$sql .= ' WHERE t.entity IN ('.getEntity('bordereaudoc').')';
if (!empty($search_ref)) {
	$sql .= natural_search('t.ref', $search_ref);
}
if (!empty($search_title)) {
	$sql .= natural_search('t.title', $search_title);
}
if (!empty($search_project)) {
	$sql .= ' AND t.fk_project = '.((int) $search_project);
}
if ($search_status >= 0) {
$sql .= ' AND t.statut = '.((int) $search_status);
}
if ($search_date_start > 0) {
	$sql .= " AND t.datec >= '".$db->idate($search_date_start)."'";
}
if ($search_date_end > 0) {
	$sql .= " AND t.datec <= '".$db->idate($search_date_end)."'";
}

$sql .= $db->order($sortfield, $sortorder ? $sortorder : 'DESC');
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	$num = 0;
} else {
	$num = $db->num_rows($resql);
}

$newcardbutton = '';
if (!empty($user->rights->diffusionplans->bordereaudoc->write)) {
	$newcardbutton = dolGetButtonTitle($langs->trans('NewBordereaudoc'), '', 'fa fa-plus-circle', dol_buildpath('/diffusionplans/bordereaudoc_card.php', 1).'?action=create');
}

$massactionbutton = '';
if (!empty($arrayofmassactions)) {
	$massactionbutton = $form->selectMassAction($massaction, $arrayofmassactions);
}

$param = '';
if (!empty($search_ref)) {
	$param .= '&search_ref='.urlencode($search_ref);
}
if (!empty($search_title)) {
	$param .= '&search_title='.urlencode($search_title);
}
if (!empty($search_project)) {
	$param .= '&search_project='.(int) $search_project;
}
if ($search_status >= 0) {
	$param .= '&search_status='.(int) $search_status;
}
if ($search_date_startday) {
	$param .= '&search_date_startday='.(int) $search_date_startday;
}
if ($search_date_startmonth) {
	$param .= '&search_date_startmonth='.(int) $search_date_startmonth;
}
if ($search_date_startyear) {
	$param .= '&search_date_startyear='.(int) $search_date_startyear;
}
if ($search_date_endday) {
	$param .= '&search_date_endday='.(int) $search_date_endday;
}
if ($search_date_endmonth) {
	$param .= '&search_date_endmonth='.(int) $search_date_endmonth;
}
if ($search_date_endyear) {
	$param .= '&search_date_endyear='.(int) $search_date_endyear;
}

llxHeader('', $langs->trans('BordereaudocList'));

if (!empty($formconfirm)) {
	print $formconfirm;
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" name="search_form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="">';
print '<input type="hidden" name="massaction" value="'.dol_escape_htmltag($massaction).'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.(int) $page.'">';
print '<input type="hidden" name="limit" value="'.(int) $limit.'">';

print_barre_liste($langs->trans('BordereaudocList'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $totalnboflines = '', '', 0, '', '', $massactionbutton.$newcardbutton, '', 0, 0, 1);

print '<div class="div-table-responsive">';
print '<table class="tagtable noborder centpercent listofitems">';
print '<tr class="liste_titre">';
		if (!empty($massactionbutton)) {
	print_liste_field_titre($form->showCheckAddButtons($num, 'checkforselect'), '', '', '', '', 'center width20');
}
print_liste_field_titre($langs->trans('Ref'), $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Title'), $_SERVER['PHP_SELF'], 't.title', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Project'), $_SERVER['PHP_SELF'], 'p.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 't.statut', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('DateCreation'), $_SERVER['PHP_SELF'], 't.datec', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('DateModificationShort'), $_SERVER['PHP_SELF'], 't.tms', '', $param, '', $sortfield, $sortorder, 'center');
print_liste_field_titre($langs->trans('ContactsCount'), $_SERVER['PHP_SELF'], 'nb_contacts', '', $param, '', $sortfield, $sortorder, 'right');
print_liste_field_titre($langs->trans('VisibleDocuments'), $_SERVER['PHP_SELF'], 'nb_visible_files', '', $param, '', $sortfield, $sortorder, 'right');
print_liste_field_titre('');
print '</tr>';

print '<tr class="liste_titre">';
		if (!empty($massactionbutton)) {
	print '<td class="liste_titre center"></td>';
}
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
		print '</td>';
print '<td class="liste_titre">';
print '<input type="text" class="flat" name="search_title" value="'.dol_escape_htmltag($search_title).'">';
		print '</td>';
print '<td class="liste_titre">';
print $formproject->select_projects(-1, $search_project, 'search_project', 0, 0, 1, 1, 0, 0, '', 1, 0, array(), false);
print '</td>';
print '<td class="liste_titre center">';
print $form->selectarray('search_status', $statusLabels, $search_status, 1);
		print '</td>';
print '<td class="liste_titre center">';
print $form->selectDate($search_date_start, 'search_date_start', 0, 0, 1, '', 1, 0, 1);
print '<br>';
print $form->selectDate($search_date_end, 'search_date_end', 0, 0, 1, '', 1, 0, 1);
		print '</td>';
print '<td class="liste_titre right">';
print $form->showFilterAndCheckAddButtons($massactionbutton ? 1 : 0);
		print '</td>';
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre right"></td>';
print '<td class="liste_titre right"></td>';
print '</tr>';

$i = 0;
$imax = min($num, $limit);
if ($resql) {
	while ($i < $imax) {
		$obj = $db->fetch_object($resql);
		if (!$obj) {
			break;
		}

		$link = dol_buildpath('/diffusionplans/bordereaudoc_card.php', 1).'?id='.$obj->rowid;
		$projectstatic->id = $obj->fk_project;
		$projectstatic->ref = $obj->project_ref;
$projectstatic->title = $obj->project_title;

		print '<tr class="oddeven">';
		if (!empty($massactionbutton)) {
		print '<td class="center">';
		print '<input class="flat checkforselect" type="checkbox" name="toselect[]" value="'.(int) $obj->rowid.'">';
		print '</td>';
}
		print '<td class="nowrap">';
		print '<a href="'.$link.'">'.dol_escape_htmltag($obj->ref).'</a>';
		print '</td>';
		print '<td class="tdoverflowmax200">'.dol_escape_htmltag($obj->title).'</td>';
		print '<td class="nowrap">';
		if (!empty($obj->fk_project)) {
			print $projectstatic->getNomUrl(1);
		}
		print '</td>';
print '<td class="center">'.(isset($statusLabels[$obj->statut]) ? $statusLabels[$obj->statut] : '').'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($obj->datec), 'day').'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($obj->tms), 'dayhour').'</td>';
		print '<td class="right">'.(int) $obj->nb_contacts.'</td>';
		print '<td class="right">'.(int) $obj->nb_visible_files.'</td>';
		print '<td class="right">';
		print '</td>';
		print '</tr>';

		$i++;
	}
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
