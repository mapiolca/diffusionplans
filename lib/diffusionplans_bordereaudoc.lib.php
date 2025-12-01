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
 * \file    lib/diffusionplans_bordereaudoc.lib.php
 * \ingroup diffusionplans
 * \brief   Library file with common functions for Bordereaudoc cards.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';

/**
 * Prepare array of tabs for Bordereaudoc cards.
 *
 * @param Bordereaudoc $object Bordereaudoc object
 * @return array<array{0:string,1:string,2:string}>
 */
function bordereaudocPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load('diffusionplans@diffusionplans');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/diffusionplans/bordereaudoc_card.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Bordereaudoc');
	$head[$h][2] = 'card';
	$h++;

	$uploadDir = $object->getDocumentsDirectory();
	$nbFiles = count(dol_dir_list($uploadDir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$h][0] = dol_buildpath('/diffusionplans/bordereaudoc_document.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if (($nbFiles + $nbLinks) > 0) {
		$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>' : '');
	}
	$head[$h][2] = 'documents';
	$h++;

	$head[$h][0] = dol_buildpath('/diffusionplans/bordereaudoc_agenda.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('Events');
	$head[$h][2] = 'agenda';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bordereaudoc@diffusionplans');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bordereaudoc@diffusionplans', 'remove');

	return $head;
}

/**
 * Compatibility wrapper for legacy includes.
 *
 * @param Bordereaudoc $object Bordereaudoc object
 * @return array
 */
function diffusionPrepareHead($object)
{
	return bordereaudocPrepareHead($object);
}

