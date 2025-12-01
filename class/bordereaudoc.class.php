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
* \file        class/bordereaudoc.class.php
* \ingroup     diffusionplans
* \brief       Business class for delivery slip of documents.
*/

// Dolibarr includes
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
* Class for Bordereaudoc
*/
class Bordereaudoc extends CommonObject
{
	/**
	 * @var string Module name
	 */
	public $module = 'diffusionplans';

	/**
	 * @var string Element name
	 */
	public $element = 'bordereaudoc';

	/**
	 * @var string Table without prefix
	 */
	public $table_element = 'bordereaudoc';

	/**
	 * @var string Picto code
	 */
	public $picto = 'fa-paper-plane';

	/**
	 * Status constants
	 */
	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_DELIVERED = 2;
	const STATUS_CLOSED = 3;

	/**
	 * @var array Fields definition
	 */
	public $fields = array(
	'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
	'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1),
	'title' => array('type' => 'varchar(255)', 'label' => 'Title', 'enabled' => '1', 'position' => 20, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300', 'cssview' => 'wordbreak', 'validate' => 1),
	'description' => array('type' => 'html', 'label' => 'Description', 'enabled' => '1', 'position' => 30, 'notnull' => 0, 'visible' => 3, 'validate' => 1),
	'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'picto' => 'project', 'enabled' => "isModEnabled('project')", 'position' => 40, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'validate' => 1),
	'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array('0' => 'Draft', '1' => 'Validated', '2' => 'Delivered', '3' => 'Closed'), 'validate' => 1),
	'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 60, 'notnull' => 1, 'visible' => -2, 'index' => 1),
	'datec' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 70, 'notnull' => 1, 'visible' => -2),
	'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => -2),
	'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => '1', 'position' => 90, 'notnull' => 1, 'visible' => -2),
	'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => '1', 'position' => 100, 'notnull' => -1, 'visible' => -2),
	'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => '1', 'position' => 110, 'notnull' => -1, 'visible' => -2),
	'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => '1', 'position' => 120, 'notnull' => 0, 'visible' => 0),
	'model_pdf' => array('type' => 'varchar(255)', 'label' => 'ModelPDF', 'enabled' => '1', 'position' => 130, 'notnull' => -1, 'visible' => 0),
	);

	public $rowid;
	public $ref;
	public $title;
	public $description;
	public $fk_project;
	public $status;
	public $entity;
	public $datec;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;
	public $last_main_doc;
	public $model_pdf;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf;

		$this->db = $db;
		$this->ismultientitymanaged = 1;
		$this->isextrafieldmanaged = 1;

		$this->fields['entity']['default'] = !empty($conf->entity) ? (int) $conf->entity : 1;
		$this->fields['datec']['default'] = dol_now();
		$this->fields['status']['default'] = self::STATUS_DRAFT;
	}

	/**
	 * Get next reference for object
	 *
	 * @param User $user Current user
	 * @return string
	 */
	public function getNextNumRef(User $user)
	{
		$prefix = getDolGlobalString('DIFFUSIONPLANS_BORDEREAU_REF_PREFIX', 'BRD');

		return $this->getDefaultRef($this->db, $prefix, '', 'bordereaudoc', 'ref');
	}

	/**
	 * Create object in database
	 *
	 * @param User $user Current user
	 * @param bool $notrigger Disable triggers
	 * @return int
	 */
	public function create(User $user, $notrigger = false)
	{
		if (empty($this->fk_project)) {
			$this->error = 'ErrorFieldRequired';

			return -1;
		}

		if (empty($this->ref)) {
			$this->ref = '(PROV)';
		}

		$this->entity = !empty($this->entity) ? $this->entity : $user->entity;
		$this->datec = dol_now();
		$this->fk_user_creat = $user->id;
		$this->status = self::STATUS_DRAFT;

		return parent::create($user, $notrigger);
	}

	/**
	 * Validate object
	 *
	 * @param User $user Current user
	 * @return int
	 */
	public function validate(User $user)
	{
		if (empty($this->fk_project)) {
			$this->error = 'ErrorFieldRequired';

			return -1;
		}

		if ($this->ref == '(PROV)') {
			$this->ref = $this->getNextNumRef($user);
		}

		$this->status = self::STATUS_VALIDATED;
		$this->fk_user_modif = $user->id;

		return $this->update($user);
	}

	/**
	 * Set status back to draft
	 *
	 * @param User $user Current user
	 * @return int
	 */
	public function setDraft(User $user)
	{
		$this->status = self::STATUS_DRAFT;
		$this->fk_user_modif = $user->id;

		return $this->update($user);
	}

	/**
	 * Mark as delivered
	 *
	 * @param User $user Current user
	 * @return int
	 */
	public function setDelivered(User $user)
	{
		$this->status = self::STATUS_DELIVERED;
		$this->fk_user_modif = $user->id;

		return $this->update($user);
	}

	/**
	 * Add external project contacts as recipients
	 *
	 * @param User $user Current user
	 * @return int
	 */
	public function addProjectExternalContacts(User $user)
	{
		if (empty($this->fk_project) || empty($this->id)) {
			return 0;
		}

		$project = new Project($this->db);
		if ($project->fetch($this->fk_project) <= 0) {
			return -1;
		}

		$contacts = $project->liste_contact(-1, 'external');
		if (empty($contacts)) {
			return 0;
		}

		$added = 0;
		foreach ($contacts as $contact) {
			if ($this->recipientExists($contact['id'])) {
				continue;
			}

			$this->addRecipient($user, $contact['socid'], $contact['id'], $contact['libelle_fonction'], 'External', 1, 0, 0);
			$added++;
		}

		return $added;
	}

	/**
	 * Close object
	 *
	 * @param User $user Current user
	 * @return int
	 */
	public function setClosed(User $user)
	{
		$this->status = self::STATUS_CLOSED;
		$this->fk_user_modif = $user->id;
	
		return $this->update($user);
	}
	
	/**
	 * Get documents directory for the bordereaudoc.
	 *
	 * @return string
	 */
	public function getDocumentsDirectory()
	{
	global $conf;
	
		$entity = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		if (empty($conf->diffusionplans->multidir_output[$entity])) {
		return '';
	}
	
		$path = rtrim($conf->diffusionplans->multidir_output[$entity], '/').'/bordereaudoc/'.dol_sanitizeFileName($this->ref);
		dol_mkdir($path);
	
		return $path;
	}
	
	/**
	 * Synchronize filesystem files with database index.
	 *
	 * @param User $user Current user
	 * @return int
	 */
	public function syncDocumentIndex(User $user)
	{
		$dir = $this->getDocumentsDirectory();
		if (empty($dir)) {
		return -1;
	}
	
		$fsFiles = dol_dir_list($dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', '', '', 1);
		$existing = $this->fetchDocumentIndex();
		$existingMap = array();
		$basepath = 'bordereaudoc/'.dol_sanitizeFileName($this->ref);
	
		foreach ($existing as $line) {
		$key = trim($line->filepath, '/').'/'.$line->filename;
		$existingMap[$key] = $line;
	}
	
		$seen = array();
		foreach ($fsFiles as $file) {
		$relativeName = !empty($file['relativename']) ? $file['relativename'] : $file['name'];
		$key = $basepath.'/'.ltrim($relativeName, '/');
		$seen[$key] = 1;
		if (!isset($existingMap[$key])) {
		$visible = ((int) $this->status === self::STATUS_DRAFT) ? 1 : 0;
		$this->addFileIndex($file['name'], $basepath, $visible, $user);
	}
	}
	
		foreach ($existingMap as $key => $line) {
		if (!isset($seen[$key])) {
		$this->deleteFileIndex($line->rowid);
	}
	}
	
		return 1;
	}
	
	/**
	 * Get indexed files for the bordereaudoc.
	 *
	 * @param int $onlyVisible Restrict to visible files
	 * @return array
	 */
	public function getDocumentIndex($onlyVisible = 0)
	{
		$sql = 'SELECT rowid, fk_bordereaudoc, filename, filepath, hash, is_visible, entity';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'bordereaudoc_file';
		$sql .= ' WHERE fk_bordereaudoc = '.((int) $this->id).' AND entity = '.((int) $this->entity);
		if (!empty($onlyVisible)) {
		$sql .= ' AND is_visible = 1';
	}
	
		$resql = $this->db->query($sql);
		if (!$resql) {
		$this->error = $this->db->lasterror();
	
		return array();
	}
	
		$lines = array();
		while ($obj = $this->db->fetch_object($resql)) {
		$lines[] = $obj;
	}
	
		return $lines;
	}
	
	/**
	 * Fetch document index without filtering.
	 *
	 * @return array
	 */
	protected function fetchDocumentIndex()
	{
		return $this->getDocumentIndex(0);
	}
	
	/**
	 * Add index entry for a file.
	 *
	 * @param string $filename  File name
	 * @param string $filepath  Relative path
	 * @param int    $visible   Visibility flag
	 * @param User   $user      Current user
	 * @param string $hashValue Hash value
	 * @return int
	 */
	public function addFileIndex($filename, $filepath, $visible, User $user, $hashValue = '')
	{
		if (empty($hashValue)) {
		$hashValue = $this->generateFileHash();
	}
	
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX."bordereaudoc_file (fk_bordereaudoc, filename, filepath, hash, is_visible, entity, datec, tms, fk_user_creat)";
		$sql .= " VALUES (".((int) $this->id).", '".$this->db->escape($filename)."', '".$this->db->escape($filepath)."', '".$this->db->escape($hashValue)."', ".((int) $visible).", ".((int) $this->entity).", ".$this->db->idate(dol_now()).", ".$this->db->idate(dol_now()).", ".((int) $user->id).")";
	
		$resql = $this->db->query($sql);
		if (!$resql) {
		$this->error = $this->db->lasterror();
	
		return -1;
	}
	
		return $this->db->last_insert_id(MAIN_DB_PREFIX.'bordereaudoc_file');
	}
	
	/**
	 * Delete index entry.
	 *
	 * @param int $rowid Line id
	 * @return int
	 */
	public function deleteFileIndex($rowid)
	{
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'bordereaudoc_file WHERE rowid = '.((int) $rowid);
		$sql .= ' AND fk_bordereaudoc = '.((int) $this->id).' AND entity = '.((int) $this->entity);
	
		$resql = $this->db->query($sql);
		if (!$resql) {
		$this->error = $this->db->lasterror();
	
		return -1;
	}
	
		return 1;
	}
	
	/**
	 * Update visibility flag for an indexed file.
	 *
	 * @param int $rowid    Line id
	 * @param int $visible  Visibility flag
	 * @return int
	 */
	public function updateFileVisibility($rowid, $visible)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX."bordereaudoc_file SET is_visible = ".((int) $visible);
		$sql .= ' WHERE rowid = '.((int) $rowid).' AND fk_bordereaudoc = '.((int) $this->id).' AND entity = '.((int) $this->entity);

	$resql = $this->db->query($sql);
	if (!$resql) {
	$this->error = $this->db->lasterror();

	return -1;
}

	return 1;
	}
	
	/**
	 * Generate secure hash for document link.
	 *
	 * @return string
	 */
	protected function generateFileHash()
	{
		try {
		return hash('sha256', bin2hex(random_bytes(32)).microtime(true).$this->id.dol_print_date(dol_now(), 'dayrfc'));
		} catch (Exception $e) {
		return hash('sha256', uniqid((string) $this->id, true));
	}
	}
	
	/**
	 * Load project contacts into bordereaudoc contacts table
	 *
	 * @param User $user Current user
	 * @return int
	 */
	public function loadProjectContacts(User $user)
	{
		if (empty($this->fk_project)) {
			return 0;
		}

		$project = new Project($this->db);
		$res = $project->fetch($this->fk_project);
		if ($res <= 0) {
			return -1;
		}

		$contacts = $project->liste_contact(0, 'external', '', 1, 'contact', 1);
		if (empty($contacts)) {
			return 0;
		}

		$count = 0;
		foreach ($contacts as $contact) {
			$already = $this->recipientExists($contact['id']);
			if ($already) {
				continue;
			}

			$this->addRecipient($user, $contact['socid'], $contact['id'], $contact['libelle_fonction'], 'External', 1, 0, 0);
			$count++;
		}

		return $count;
	}

	/**
	 * Check if recipient already exists
	 *
	 * @param int $contactId Contact id
	 * @return bool
	 */
	protected function recipientExists($contactId)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX."bordereaudoc_contact WHERE fk_bordereaudoc = ".((int) $this->id)." AND fk_contact =".((int) $contactId);
		$sql .= " AND entity = ".((int) $this->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}

		return ($this->db->num_rows($resql) > 0);
	}

	/**
	 * Add a recipient
	 *
	 * @param User   $user       Current user
	 * @param int    $socid      Thirdparty id
	 * @param int    $contactId  Contact id
	 * @param string $typeContact Type label
	 * @param string $nature     Nature label
	 * @param int    $email      Send email flag
	 * @param int    $mail       Send mail flag
	 * @param int    $hand       Hand delivery flag
	 * @return int
	 */
	public function addRecipient(User $user, $socid, $contactId, $typeContact = '', $nature = '', $email = 1, $mail = 0, $hand = 0)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX."bordereaudoc_contact (datec, tms, fk_bordereaudoc, fk_soc, fk_contact, nature_contact, type_contact, send_email, send_mail, send_hand, active, entity, fk_user_creat)";
		$sql .= " VALUES (".$this->db->idate(dol_now()).", ".$this->db->idate(dol_now()).", ".((int) $this->id).", ".((int) $socid).", ".((int) $contactId).", ";
		$sql .= "'".$this->db->escape($nature)."', '".$this->db->escape($typeContact)."', ".((int) $email).", ".((int) $mail).", ".((int) $hand).", 1, ".((int) $this->entity).", ".((int) $user->id).")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();

			return -1;
		}

		return $this->db->last_insert_id(MAIN_DB_PREFIX.'bordereaudoc_contact');
	}

	/**
	 * Update recipient flags
	 *
	 * @param int   $lineId Line id
	 * @param array $data   Data to update
	 * @return int
	 */
	public function updateRecipientFlags($lineId, array $data)
	{
		$fields = array();
		foreach (array('send_email', 'send_mail', 'send_hand', 'active') as $key) {
			if (isset($data[$key])) {
				$fields[] = $key.' = '.((int) $data[$key]);
			}
		}

		if (empty($fields)) {
			return 0;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'bordereaudoc_contact SET '.implode(', ', $fields).' WHERE rowid = '.((int) $lineId);
		$sql .= " AND fk_bordereaudoc = ".((int) $this->id)." AND entity = ".((int) $this->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();

			return -1;
		}

		return 1;
	}

	/**
	 * Get recipients list
	 *
	 * @param int $onlyActive Restrict to active
	 * @return array
	 */
	public function getRecipients($onlyActive = 0)
	{
		$sql = 'SELECT bc.rowid, bc.fk_soc, bc.fk_contact, bc.nature_contact, bc.type_contact, bc.send_email, bc.send_mail, bc.send_hand, bc.active, s.nom as socname, c.lastname, c.firstname, c.civility as civility';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'bordereaudoc_contact as bc';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON bc.fk_soc = s.rowid';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."socpeople as c ON bc.fk_contact = c.rowid";
		$sql .= ' WHERE bc.fk_bordereaudoc = '.((int) $this->id).' AND bc.entity = '.((int) $this->entity);
		if ($onlyActive) {
			$sql .= ' AND bc.active = 1';
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();

			return array();
		}

		$lines = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$lines[] = $obj;
		}

		return $lines;
	}
}
