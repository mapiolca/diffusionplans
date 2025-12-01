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
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
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
$hookmanager = new HookManager($db);
$hookmanager->initHooks(array('bordereaudoccard', 'globalcard'));

// Permissions
$permissiontoread = $user->hasRight('diffusionplans', 'bordereaudoc', 'read');
$permissiontoadd = $user->hasRight('diffusionplans', 'bordereaudoc', 'write');
$permissiontovalidate = $user->hasRight('diffusionplans', 'bordereaudoc', 'validate');
$permissiontosend = $user->hasRight('diffusionplans', 'bordereaudoc', 'send');
$permissiontoarchive = $user->hasRight('diffusionplans', 'bordereaudoc', 'archive');
$permissiondelete = $user->hasRight('diffusionplans', 'bordereaudoc', 'delete');

if (!$permissiontoread) {
	accessforbidden();
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'create';
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

$parameters = array('id' => $object->id, 'ref' => $object->ref);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
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
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'create';
	}

	if ($action === 'update' && $permissiontoadd && $object->id > 0) {
		if ((int) $object->status !== Bordereaudoc::STATUS_DRAFT) {
			setEventMessages($langs->trans('ErrorForbidden'), null, 'errors');
			$action = 'view';
		} else {
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
	}

	if ($action === 'confirm_delete' && GETPOST('confirm', 'alpha') === 'yes' && $permissiondelete && $object->id > 0) {
		$result = $object->delete($user);
		if ($result > 0) {
			header('Location: '.dol_buildpath('/diffusionplans/diffusion_list.php', 1));
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
	}

	if ($action === 'confirm_validate' && GETPOST('confirm', 'alpha') === 'yes' && $permissiontovalidate && $object->id > 0) {
		$result = $object->validate($user);
		if ($result > 0) {
			setEventMessages($langs->trans('BordereaudocValidated'), null, 'mesgs');
			$action = 'view';
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if ($action === 'confirm_delivered' && GETPOST('confirm', 'alpha') === 'yes' && $permissiontoarchive && $object->id > 0) {
		$result = $object->setDelivered($user);
		if ($result > 0) {
			setEventMessages($langs->trans('BordereaudocDelivered'), null, 'mesgs');
			$action = 'view';
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if ($action === 'confirm_close' && GETPOST('confirm', 'alpha') === 'yes' && $permissiontoarchive && $object->id > 0) {
		$result = $object->setClosed($user);
		if ($result > 0) {
			setEventMessages($langs->trans('BordereaudocClosed'), null, 'mesgs');
			$action = 'view';
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if ($action === 'builddoc' && $permissiontoread && $object->id > 0) {
		$model = GETPOST('model', 'alpha');
		if (empty($model)) {
			$model = $object->model_pdf ? $object->model_pdf : 'standard';
		}
		if ($model !== $object->model_pdf) {
			$object->model_pdf = $model;
			$object->update($user);
		}

		$result = $object->generateDocument($model, $langs);
		if ($result <= 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if ($action === 'sendemail' && $permissiontosend && $object->id > 0) {
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

		if (empty($object->last_main_doc)) {
			$object->generateDocument($object->model_pdf ? $object->model_pdf : 'standard', $langs);
		}

		$filepath = dol_buildpath('/custom/diffusionplans/documents/'.$object->ref, 0);
		$filename = dol_sanitizeFileName($object->ref.'.pdf');
		$fullpath = $filepath.'/'.$filename;

		$recipients = array();
		$lines = $object->getRecipients(1);
		foreach ($lines as $line) {
			if (!empty($line->send_email) && !empty($line->fk_contact)) {
				$contact = new Contact($db);
				if ($contact->fetch($line->fk_contact) > 0 && !empty($contact->email)) {
					$recipients[] = $contact->email;
				}
			}
		}

		if (empty($recipients)) {
			setEventMessages($langs->trans('ErrorNoEMailRecipientSelected'), null, 'errors');
		} else {
			$subject = $langs->trans('Bordereaudoc').' '.$object->ref;
			$body = dol_htmlentitiesbr($object->description);
			$mailfile = new CMailFile($subject, implode(',', $recipients), $user->email, $body, array($fullpath), array($filename), array('application/pdf'));
			if ($mailfile->sendfile()) {
				setEventMessages($langs->trans('MailSuccessfulySent', implode(',', $recipients)), null, 'mesgs');
			} else {
				setEventMessages($mailfile->error, $mailfile->errors, 'errors');
			}
		}
	}
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
	if ($action === 'edit' && (((int) $object->status) !== Bordereaudoc::STATUS_DRAFT || !$permissiontoadd)) {
		$action = 'view';
	}

	$confirmform = '';
	if ($action === 'valid' && $permissiontovalidate && $object->status == Bordereaudoc::STATUS_DRAFT) {
		$confirmform = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('ValidateBordereaudoc'), $langs->trans('ConfirmValidateBordereaudoc'), 'confirm_validate', '', 0, 1);
	}
	if ($action === 'deliver' && $permissiontoarchive && $object->status == Bordereaudoc::STATUS_VALIDATED) {
		$confirmform = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('DeliverBordereaudoc'), $langs->trans('ConfirmDeliverBordereaudoc'), 'confirm_delivered', '', 0, 1);
	}
	if ($action === 'close' && $permissiontoarchive && $object->status == Bordereaudoc::STATUS_DELIVERED) {
		$confirmform = $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('CloseBordereaudoc'), $langs->trans('ConfirmCloseBordereaudoc'), 'confirm_close', '', 0, 1);
	}

	$head = array();
	$head[0][0] = dol_buildpath('/diffusionplans/bordereaudoc_card.php', 1).'?id='.$object->id;
	$head[0][1] = $langs->trans('Card');
	$head[0][2] = 'card';

	dol_fiche_head($head, 'card', $langs->trans('Bordereaudoc'), -1, 'fa-paper-plane');
	if (!empty($confirmform)) {
		print $confirmform;
	}

	$linkback = '<a href="'.dol_buildpath('/diffusionplans/diffusion_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
	$morehtmlref = '';
	if (!empty($object->fk_project)) {
		$projectstatic = new Project($db);
		$projectstatic->fetch($object->fk_project);
		$morehtmlref .= '<br>'.$projectstatic->getNomUrl(1);
	}

	$editmode = ($action === 'edit');
	if ($editmode) {
		print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
	}

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.$form->showrefnav($object, 'ref', $linkback, true, 'ref', 'ref', '').'</td></tr>';
	if ($editmode) {
		print '<tr><td>'.$langs->trans('BordereaudocTitle').'</td><td><input type="text" class="minwidth300" name="title" value="'.dol_escape_htmltag($object->title).'"></td></tr>';
		print '<tr><td>'.$langs->trans('Project').'</td><td>'.$formproject->select_projects(-1, $object->fk_project, 'fk_project', 0, 0, 1, 1, 0, 0, '', 1, 0, array(), false).'</td></tr>';
		print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>';
		$doleditor = new DolEditor('description', $object->description, '', 160, 'dolibarr_notes', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_SOCIETE'), 15, 90);
		$doleditor->Create(0, '', false, 'contenteditable');
		print '</td></tr>';
	} else {
		print '<tr><td>'.$langs->trans('BordereaudocTitle').'</td><td>'.dol_escape_htmltag($object->title).'</td></tr>';
		print '<tr><td>'.$langs->trans('Project').'</td><td>'.$morehtmlref.'</td></tr>';
		print '<tr><td class="tdtop">'.$langs->trans('Description').'</td><td>'.dol_htmlentitiesbr($object->description).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
	print '</table>';

	if ($editmode) {
		print '<div class="center">';
		print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
		print '&nbsp;';
		print '<a class="button" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.$langs->trans('Cancel').'</a>';
		print '</div>';
		print '</form>';
	} else {
		$buttons = array();
		if ($object->status == Bordereaudoc::STATUS_DRAFT && $permissiontoadd) {
		    $buttons[] = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit">'.$langs->trans('Modify').'</a>';
		}
		if ($object->status == Bordereaudoc::STATUS_DRAFT && $permissiontovalidate) {
		    $buttons[] = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=valid">'.$langs->trans('Validate').'</a>';
		}
		if ($object->status == Bordereaudoc::STATUS_VALIDATED && $permissiontoarchive) {
		    $buttons[] = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=deliver">'.$langs->trans('MarkDelivered').'</a>';
		}
		if ($object->status == Bordereaudoc::STATUS_DELIVERED && $permissiontoarchive) {
		    $buttons[] = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=close">'.$langs->trans('Close').'</a>';
		}
		if ($object->status >= Bordereaudoc::STATUS_VALIDATED && $permissiontosend) {
		    $buttons[] = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=sendemail">'.$langs->trans('SendMail').'</a>';
		}
		if ($permissiontoread) {
		    $buttons[] = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=builddoc">'.$langs->trans('GeneratePDF').'</a>';
		    if (!empty($object->last_main_doc)) {
		        $modulepart = 'diffusionplans';
		        $filename = basename($object->last_main_doc);
		        $filelink = DOL_URL_ROOT.'/document.php?modulepart='.urlencode($modulepart).'&file='.urlencode($object->last_main_doc);
		        $buttons[] = '<a class="butAction" href="'.$filelink.'">'.$langs->trans('Download').'</a>';
		    }
		}

		print '<div class="tabsAction">'.implode('&nbsp;', $buttons).'</div>';
	}

	
dol_fiche_end();
}

// Footer
dol_print_footer();
llxFooter();
$db->close();

