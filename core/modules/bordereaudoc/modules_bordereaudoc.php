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
* \file       core/modules/bordereaudoc/modules_bordereaudoc.php
* \ingroup    diffusionplans
* \brief      Parent classes for bordereaudoc PDF and numbering models.
*/

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';
require_once DOL_DOCUMENT_ROOT+'/core/class/commonnumrefgenerator.class.php';

/**
* Parent class for bordereaudoc PDF models.
*/
abstract class ModelePDFBordereaudoc extends CommonDocGenerator
	{
	/**
	* Return list of active generation modules.
	*
	* @param DoliDB $db Database handler
	* @param int $maxfilenamelength Max length of value to show
	* @return array|int
	*/
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
	$type = 'bordereaudoc';
	$list = array();

	include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
	$list = getListOfModels($db, $type, $maxfilenamelength);

	return $list;
}

/**
* Build document on disk.
*
* @param Bordereaudoc $object Object source to build document
* @param Translate $outputlangs Lang output object
* @param string $srctemplatepath Full path of source filename for generator using a template file
* @param int $hidedetails Do not show line details
* @param int $hidedesc Do not show desc
* @param int $hideref Do not show ref
* @return int
*/
abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}

/**
* Parent class to manage numbering of Bordereaudoc.
*/
abstract class ModeleNumRefBordereaudoc extends CommonNumRefGenerator
	{
	/**
	* Return an example of numbering.
	*
	* @return string
	*/
	abstract public function getExample();

	/**
	* Return next free value.
	*
	* @param Bordereaudoc $object Object we need next value for
	* @return string|int
	*/
	abstract public function getNextValue($object);
}
