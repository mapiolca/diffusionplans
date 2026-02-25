<?php
/* Copyright (C) 2026	Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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
 * \file		core/triggers/interface_99_modDiffusionPlans_DiffusionsTriggers.class.php
 * \ingroup	diffusionplans
 * \brief		Triggers for DiffusionPlans module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 * Triggers class for DiffusionPlans
 */
class InterfaceDiffusionsTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'diffusionplans';
		$this->description = 'DiffusionPlans triggers';
		$this->version = self::VERSION_DOLIBARR;
		$this->picto = 'diffusionplans@diffusionplans';
	}

	/**
	 * Run trigger
	 *
	 * @param string	$action Trigger action code
	 * @param Object	$object Trigger object
	 * @param User	$user User running trigger
	 * @param Translate $langs Lang object
	 * @param Conf	$conf Global config object
	 * @return int
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		if (!is_object($object) || empty($object->element) || $object->element !== 'diffusion') {
			var_dump($object->element);
			return 0;
		}

		$actions = array(
			'DIFFUSION_VALIDATE',
			'DIFFUSION_UNVALIDATE',
			'DIFFUSION_SENT',
			'DIFFUSION_CANCEL',
			'DIFFUSION_REOPEN'
		);

		if (!in_array($action, $actions, true)) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

		$langs->load('diffusionplans@diffusionplans');

		$actioncomm = new ActionComm($this->db);
		$actioncomm->type_code = 'AC_OTH';
		$actioncomm->label = $langs->trans('DiffusionEvenementAgendaChangementStatut', $object->ref, $object->getLibStatut(0));
		$actioncomm->note_private = $langs->trans('DiffusionEvenementAgendaChangementStatutNote', $action, $object->ref);
		$actioncomm->datep = dol_now();
		$actioncomm->percentage = -1;
		$actioncomm->fk_element = (int) $object->id;
		$actioncomm->elementtype = $object->element;
		$actioncomm->fk_project = !empty($object->fk_project) ? (int) $object->fk_project : 0;
		$actioncomm->fk_user_action = !empty($user->id) ? (int) $user->id : 0;
		$actioncomm->userownerid = !empty($user->id) ? (int) $user->id : 0;
		if (property_exists($object, 'fk_soc') && !empty($object->fk_soc)) {
			$actioncomm->socid = (int) $object->fk_soc;
		}

		$result = $actioncomm->create($user);
		if ($result < 0) {
			$this->error = $actioncomm->error;
			$this->errors = $actioncomm->errors;
			return -1;
		}

		return 0;
	}
}
