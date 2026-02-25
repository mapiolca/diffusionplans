<?php
/* Copyright (C) 2004-2014	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin				<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand			<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2014	Juanjo Menent				<jmenent@2byte.es>
 * Copyright (C) 2012		Christophe Battarel			<christophe.battarel@altairis.fr>
 * Copyright (C) 2012		Cédric Salvador				<csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014	Raphaël Doursenaud			<rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015		Marcos García				<marcosgdf@gmail.com>
 * Copyright (C) 2017		Ferran Marcet				<fmarcet@2byte.es>
 * Copyright (C) 2018-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Alexandre Spangaro			<alexandre@inovea-conseil.com>
 * Copyright (C) 2025 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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
 * or see https://www.gnu.org/
 */

/**
 *  \file       core/modules/diffusionplans/doc/pdf_standard.modules.php
 *  \ingroup    diffusionplans
 *  \brief      File of class to generate document from standard template
 */

dol_include_once('/diffusionplans/core/modules/diffusionplans/modules_diffusion.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';


/**
 *	Class to manage PDF template standard_diffusion
 */
class pdf_standard_diffusion extends ModelePDFDiffusion
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var int The environment ID when using a multicompany module
	 */
	public $entity;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int     Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array{0:int,1:int} Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 7.0 = array(7, 0)
	 */
	public $phpmin = array(7, 0);

	/**
	 * Dolibarr version of the loaded document
	 * @var string Version, possible values are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'''|'development'|'dolibarr'|'experimental'
	 */
	public $version = 'dolibarr';

	/**
	 * Issuer
	 * @var Societe Object that emits
	 */
	public $emetteur;

	/**
	 * @var array<string,array{rank:int,width:float|int,status:bool,title:array{textkey:string,label:string,align:string,padding:array{0:float,1:float,2:float,3:float}},content:array{align:string,padding:array{0:float,1:float,2:float,3:float}}}>	Array of document table columns
	 */
	public $cols;


	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "project", "diffusion@diffusion"));

		$this->db = $db;
		$this->name = "standard";
		$this->description = $langs->trans('DocumentModelStandardPDF');
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		// Dimension page
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

		// Define position of columns
		$this->posxdesc = $this->marge_gauche + 1; // used for notes and other stuff

		$this->tabTitleHeight = 5; // default height

		//  Use new system for position of columns, view  $this->defineColumnField()

		$this->tva = array();
		$this->tva_array = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;

		if ($mysoc === null) {
			dol_syslog(get_class($this).'::__construct() Global $mysoc should not be null.'. getCallerInfoString(), LOG_ERR);
			return;
		}

		// Get source company
		$this->emetteur = $mysoc;
		if (empty($this->emetteur->country_code)) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
		}
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build and write pdf to disk
	 *
	 *  @param	Diffusion	$object				Source object to generate document from
	 *  @param	Translate	$outputlangs		Lang output object
	 *  @param	string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param	int<0,1>	$hidedetails		Do not show line details
	 *  @param	int<0,1>	$hidedesc			Do not show desc
	 *  @param	int<0,1>	$hideref			Do not show ref
	 *  @return	int<-1,1>						1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $hookmanager, $nblines;

		dol_syslog("write_file outputlangs->defaultlang=".(is_object($outputlangs) ? $outputlangs->defaultlang : 'null'));

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalInt('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load translation files required by the page
	       $langfiles = array("main", "bills", "products", "dict", "companies", "compta");
	       $outputlangs->loadLangs($langfiles);
	       $outputlangs->loadLangs(array('diffusionplans@diffusionplans'));

		// Show Draft Watermark
		if (getDolGlobalString('DIFFUSION_DRAFT_WATERMARK') && $object->status == $object::STATUS_DRAFT) {
			$this->watermark = getDolGlobalString('DIFFUSION_DRAFT_WATERMARK');
		}

		global $outputlangsbis;
		$outputlangsbis = null;
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && $outputlangs->defaultlang != getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE')) {
			$outputlangsbis = new Translate('', $conf);
			$outputlangsbis->setDefaultLang(getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE'));
			$outputlangsbis->loadLangs($langfiles);
		}

		$nblines = (is_array($object->lines) ? count($object->lines) : 0);

		$hidetop = 0;
		if (getDolGlobalString('MAIN_PDF_DISABLE_COL_HEAD_TITLE')) {
			$hidetop = getDolGlobalString('MAIN_PDF_DISABLE_COL_HEAD_TITLE');
		}

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		$this->atleastonephoto = false;
		/*
		if (getDolGlobalInt('MAIN_GENERATE_DIFFUSION_WITH_PICTURE'))) {
			$objphoto = new Product($this->db);

			for ($i = 0; $i < $nblines; $i++) {
				if (empty($object->lines[$i]->fk_product)) {
					continue;
				}

				$objphoto->fetch($object->lines[$i]->fk_product);
				//var_dump($objphoto->ref);exit;
				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
					$pdir[0] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product').$objphoto->id."/photos/";
					$pdir[1] = get_exdir(0, 0, 0, 0, $objphoto, 'product').dol_sanitizeFileName($objphoto->ref).'/';
				} else {
					$pdir[0] = get_exdir(0, 0, 0, 0, $objphoto, 'product'); // default
					$pdir[1] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product').$objphoto->id."/photos/"; // alternative
				}

				$arephoto = false;
				foreach ($pdir as $midir) {
					if (!$arephoto) {
						if ($conf->entity != $objphoto->entity) {
							$dir = $conf->product->multidir_output[$objphoto->entity].'/'.$midir; //Check repertories of current entities
						} else {
							$dir = $conf->product->dir_output.'/'.$midir; //Check repertory of the current product
						}

						foreach ($objphoto->liste_photos($dir, 1) as $key => $obj) {
							if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES')) {		// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
								if ($obj['photo_vignette'])	{
									$filename = $obj['photo_vignette'];
								} else {
									$filename = $obj['photo'];
								}
							} else {
								$filename = $obj['photo'];
							}

							$realpath = $dir.$filename;
							$arephoto = true;
							$this->atleastonephoto = true;
						}
					}
				}

				if ($realpath && $arephoto) {
					$realpatharray[$i] = $realpath;
				}
			}
		}
		*/

	       //if (count($realpatharray) == 0) $this->posxpicture=$this->posxtva;

	       $contactSummaries = array();
	       $attachmentSummaries = array();
	       $objectref = '';
	       $currentPdfName = '';

	       if (getMultidirOutput($object)) {
			$object->fetch_thirdparty();

		       $paths = $this->prepareDocumentPaths($object);
		       if ($paths === null) {
			       $this->error = $langs->transnoentities("ErrorConstantNotDefined", "FAC_OUTPUTDIR");
			       return 0;
		       }
		       $dir = $paths['dir'];
		       $file = $paths['file'];
		       $objectref = $paths['ref'];
		       $currentPdfName = basename($file);

		       if (!file_exists($dir)) {
			       if (dol_mkdir($dir) < 0) {
				       $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				       return 0;
			       }
		       }

		       if (file_exists($dir)) {
			       $contactSummaries = $this->loadDiffusionContacts($object, $outputlangs);
			       $attachmentSummaries = $this->loadDiffusionAttachments($object, $dir, $currentPdfName);
				// Add pdfgeneration hook
				if (!is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Set nblines with the new lines content after hook
				$nblines = (is_array($object->lines) ? count($object->lines) : 0);

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
				'@phan-var-force TCPDI|TCPDF $pdf';
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$pdf->SetAutoPageBreak(1, 0);

			    $heightforinfotot = $this->estimateSummaryHeight($contactSummaries, $attachmentSummaries);
				$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 12 : 22); // Height reserved to output the footer (value include bottom margin)

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));

				// Set path to the background PDF File
				if (getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
					$logodir = $conf->mycompany->dir_output;
					if (!empty($conf->mycompany->multidir_output[$object->entity])) {
						$logodir = $conf->mycompany->multidir_output[$object->entity];
					}
					$pagecount = $pdf->setSourceFile($logodir.'/'.getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfTitle"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Diffusion")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;
				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs, (is_object($outputlangsbis) ? $outputlangsbis : null));
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 90;
				$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 : 10);

				// Display notes
				$notetoshow = empty($object->note_public) ? '' : $object->note_public;

				// Extrafields in note
				$extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
				if (!empty($extranote)) {
					$notetoshow = dol_concatdesc($notetoshow, $extranote);
				}
				if (!empty($notetoshow)) {
					$tab_top -= 2;

					$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
					$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($notetoshow), 0, 1);
					$nexY = $pdf->GetY();
					$height_note = $nexY - $tab_top;

					// Rect takes a length in 3rd parameter
					$pdf->SetDrawColor(192, 192, 192);
					$pdf->RoundedRect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 2, $this->corner_radius, '1234', 'D');

					$tab_top = $nexY + 6;
				}

				$descriptionText = trim($object->description);
				$availableWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
				if ($descriptionText !== '') {
					$bottomlasttab = $this->renderDescriptionWithPagination($pdf, $object, $outputlangs, $descriptionText, $tab_top, $tab_top_newpage, $availableWidth, $heightforfooter, $default_font_size, $tplidx, $pagenb, (is_object($outputlangsbis) ? $outputlangsbis : null), true);
				} else {
					$bottomlasttab = $tab_top;
				}

				// Display diffusion contacts and attachments summary
				$summaryStartY = max($pdf->GetY(), $bottomlasttab + 2);
				$afterContactsY = $this->renderContactsSection($pdf, $object, $contactSummaries, $outputlangs, $summaryStartY, $availableWidth);
				$this->renderAttachmentsSection($pdf, $attachmentSummaries, $outputlangs, $afterContactsY + 4, $availableWidth);

				$nbpagesgenerated = $pdf->getNumPages();
				for ($pageid = 1; $pageid <= $nbpagesgenerated; $pageid++) {
					$pdf->setPage($pageid);
					$this->_pagefoot($pdf, $object, $outputlangs);
				}

				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();  // @phan-suppress-current-line PhanUndeclaredMethod
				}

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				dolChmod($file);

				$this->result = array('fullpath' => $file);

				return 1; // No error
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "DIFFUSION_OUTPUTDIR");
			return 0;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return list of active generation modules
	 *
	 *  @param  DoliDB  	$db					Database handler
	 *  @param  int<0,max>	$maxfilenamelength	Max length of value to show
	 *  @return string[]|int<-1,0>				List of templates
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		// phpcs:enable
		return parent::liste_modeles($db, $maxfilenamelength); // TODO: Change the autogenerated stub
	}


	/**
	 * Prepare directory and file paths for the generated document.
	 *
	 * @param Diffusion $object Diffusion object
	 * @return array<string,string>|null
	 */
	protected function prepareDocumentPaths($object)
	{
		$multidir = getMultidirOutput($object);
		if (empty($multidir)) {
			return null;
		}

		if (!empty($object->specimen)) {
			return array(
				'dir' => $multidir,
				'file' => $multidir.'/SPECIMEN.pdf',
				'ref' => 'SPECIMEN',
			);
		}

		$objectref = dol_sanitizeFileName($object->ref);
		if ($objectref === '') {
			return null;
		}

		$dir = $multidir.'/'.$objectref;

		return array(
			'dir' => $dir,
			'file' => $dir.'/'.$objectref.'.pdf',
			'ref' => $objectref,
		);
	}

	/**
	 * Load contacts linked to the diffusion.
	 *
	 * @param Diffusion $object Diffusion object
	 * @param Translate $outputlangs Output language handler
	 * @return array<int,array<string,mixed>>
	 */
	protected function loadDiffusionContacts($object, $outputlangs)
	{
		global $conf, $mysoc;

		$result = array();

		if (empty($object->id)) {
			return $result;
		}

		$companystatic = new Societe($this->db);
		$contactstatic = new Contact($this->db);
		$userstatic = new User($this->db);

		$sql = "SELECT dc.rowid, dc.fk_contact, dc.contact_source, dc.mail_status, dc.letter_status, dc.hand_status";
		$sql .= " FROM ".MAIN_DB_PREFIX."diffusion_contact as dc";
		$sql .= " WHERE dc.fk_diffusion = ".(int) $object->id;
		$sql .= " ORDER BY dc.rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return $result;

		}
		while ($obj = $this->db->fetch_object($resql)) {
			if (getDolGlobalInt('DIFFUSION_HIDE_NOSTATUS_CONTACT') && (int) $obj->mail_status !== 1 && (int) $obj->letter_status !== 1 && (int) $obj->hand_status !== 1) {
				continue;
			}

			$rawSource = strtolower(trim((string) $obj->contact_source));
			$contactId = (int) $obj->fk_contact;

			if ($contactId <= 0) {
				continue;
			}

			// Normalise source values (defensive: data may contain 'user'/'contact' or be empty)
			$source = '';
			if ($rawSource === 'internal' || $rawSource === 'user') {
				$source = 'internal';
			} elseif ($rawSource === 'external' || $rawSource === 'contact' || $rawSource === 'socpeople') {
				$source = 'external';
			}

			$thirdpartyName = '';
			$contactName = '';
			$email = '';
			$phone = '';
			$mobile = '';
			$contact = null;
			$resolved = false;

			// 1) Try direct fetch (expected case)
			if ($source === 'internal' || $source === '') {
				$contact = clone $userstatic;
				if ($contact->fetch($contactId) > 0) {
					$source = 'internal';
					$resolved = true;
				}
			}

			if (!$resolved && ($source === 'external' || $source === '')) {
				$contact = clone $contactstatic;
				if ($contact->fetch($contactId) > 0) {
					$source = 'external';
					$resolved = true;
				}
			}

			// 2) Fallback: fk_contact may actually be llx_element_contact.rowid
			if (!$resolved) {
				$fkSocpeople = 0;

				$sql2 = "SELECT ec.fk_socpeople";
				$sql2 .= " FROM ".MAIN_DB_PREFIX."element_contact as ec";
				$sql2 .= " WHERE ec.rowid = ".(int) $contactId;

				$resql2 = $this->db->query($sql2);
				if ($resql2) {
					$obj2 = $this->db->fetch_object($resql2);
					$fkSocpeople = ($obj2 ? (int) $obj2->fk_socpeople : 0);
					$this->db->free($resql2);
				}

				if ($fkSocpeople > 0) {
					// Prefer external contact if it exists
					$contact = clone $contactstatic;
					if ($contact->fetch($fkSocpeople) > 0) {
						$source = 'external';
						$resolved = true;
					} else {
						// Try internal user linked to this socpeople (common in Dolibarr)
						$userRowid = 0;

						$sql3 = "SELECT u.rowid";
						$sql3 .= " FROM ".MAIN_DB_PREFIX."user as u";
						$sql3 .= " WHERE u.fk_socpeople = ".(int) $fkSocpeople;
						if (!empty($conf->entity)) {
							$sql3 .= " ORDER BY (u.entity = ".(int) $conf->entity.") DESC, u.rowid ASC";
						}
						$sql3 .= " LIMIT 1";

						$resql3 = $this->db->query($sql3);
						if ($resql3) {
							$obj3 = $this->db->fetch_object($resql3);
							$userRowid = ($obj3 ? (int) $obj3->rowid : 0);
							$this->db->free($resql3);
						}

						if ($userRowid > 0) {
							$contact = clone $userstatic;
							if ($contact->fetch($userRowid) > 0) {
								$source = 'internal';
								$resolved = true;
							}
						}
					}
				}
			}

			if (!$resolved || !is_object($contact)) {
				continue;
			}

			// Use the resolved object id (can differ from stored fk_contact when it was an element_contact rowid)
			$resolvedId = (!empty($contact->id) ? (int) $contact->id : $contactId);

			if ($source === 'internal') {
				$contactName = $contact->getFullName($outputlangs);
				$email = (string) $contact->email;
				$phone = (string) $contact->office_phone;
				$mobile = (string) $contact->user_mobile;

				if (!empty($mysoc->name)) {
					$thirdpartyName = (string) $mysoc->name;
				}
			} else {
				$contactName = $contact->getFullName($outputlangs);
				$email = (string) $contact->email;
				$phone = !empty($contact->phone_pro) ? (string) $contact->phone_pro : (string) $contact->phone_perso;
				$mobile = (string) $contact->phone_mobile;

				if (!empty($contact->socid) && $contact->socid > 0) {
					$companyclone = clone $companystatic;
					if ($companyclone->fetch($contact->socid) > 0) {
						$thirdpartyName = (string) $companyclone->name;
					}
				}

				if ($thirdpartyName === '' && !empty($mysoc->name)) {
					$thirdpartyName = (string) $mysoc->name;
				}
			}

			if ($phone === '' && $mobile !== '') {
				$phone = $mobile;
			}

			$typeLabel = '';
			$typeLabelKey = !empty($obj->type_label) ? (string) $obj->type_label : '';
			if ($typeLabelKey !== '') {
				$translated = $outputlangs->transnoentitiesnoconv($typeLabelKey);
				$typeLabel = !empty($translated) ? (string) $translated : $typeLabelKey;
			}

			$result[] = array(
				'id' => $resolvedId,
				'source' => $source,
				'type_label' => $typeLabel,
				'thirdparty_name' => $thirdpartyName,
				'contact_name' => $contactName,
				'email' => $email,
				'phone' => $phone,
				'mobile' => $mobile,
				'mail_status' => (int) $obj->mail_status,
				'letter_status' => (int) $obj->letter_status,
				'hand_status' => (int) $obj->hand_status,
			);
		}

		$this->db->free($resql);

		return $result;
	}

	/**
	 * List attachments stored in the diffusion directory.
	 *
	 * @param Diffusion $object Diffusion object
	 * @param string $dir Absolute directory path
	 * @param string $currentPdfName Generated PDF file name
	 * @return array<int,array<string,mixed>>
	 */
	protected function loadDiffusionAttachments($object, $dir, $currentPdfName)
	{
		$attachments = array();
		$shareByFilename = array();

		if (empty($dir)) {
			return $attachments;
		}

		if (getDolGlobalInt('DIFFUSION_ALLOW_EXTERNAL_DOWNLOAD') && !empty($object->id)) {
			$shareByFilename = $this->getSharedLinksByFilename($object, $dir);
		}

		$fileList = dol_dir_list($dir, 'files', 0, '', '(\.meta$|\.tmp$|\.preview\.png$)', 'name', SORT_ASC, 1);
		foreach ($fileList as $fileinfo) {
			if (!empty($currentPdfName) && dol_strtolower($fileinfo['name']) == dol_strtolower($currentPdfName)) {
				continue;
			}

			$key = dol_strtolower((string) $fileinfo['name']);
			if (!empty($shareByFilename[$key])) {
				$fileinfo['public_share_link'] = $shareByFilename[$key];
			}
			$attachments[] = $fileinfo;
		}

		return $attachments;
	}

	/**
	 * Get public shared links indexed by lowercase filename.
	 *
	 * @param Diffusion $object Diffusion object
	 * @param string $dir Absolute directory path
	 * @return array<string,string>
	 */
	protected function getSharedLinksByFilename($object, $dir)
	{
		$links = array();
		$relativeDir = preg_replace('/^'.preg_quote(DOL_DATA_ROOT, '/').'/', '', (string) $dir);
		$relativeDir = preg_replace('/[\/]$/', '', (string) $relativeDir);
		$relativeDir = preg_replace('/^[\/]/', '', (string) $relativeDir);

		$sql = "SELECT filename, share";
		$sql .= " FROM ".MAIN_DB_PREFIX."ecm_files";
		$sql .= " WHERE src_object_type = '".$this->db->escape($object->table_element)."'";
		$sql .= " AND src_object_id = ".((int) $object->id);
		$sql .= " AND filepath = '".$this->db->escape($relativeDir)."'";
		$sql .= " AND share IS NOT NULL AND share <> ''";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if (!empty($obj->filename) && !empty($obj->share)) {
					$links[dol_strtolower((string) $obj->filename)] = $this->buildPublicShareUrl((string) $obj->share);
				}
			}
			$this->db->free($resql);
		}

		return $links;
	}

	/**
	 * Build public URL from a share token.
	 *
	 * @param string $shareToken Share token
	 * @return string
	 */
	protected function buildPublicShareUrl($shareToken)
	{
		global $dolibarr_main_url_root;

		$urlwithouturlroot = preg_replace('/'.preg_quote(DOL_URL_ROOT, '/').'$/i', '', trim((string) $dolibarr_main_url_root));
		$urlwithroot = $urlwithouturlroot.DOL_URL_ROOT;
		if (empty($urlwithroot)) {
			$urlwithroot = DOL_URL_ROOT;
		}

		return $urlwithroot.'/document.php?hashp='.urlencode($shareToken);
	}

	/**
	 * FR: Estime la hauteur réservée aux blocs contacts et pièces jointes.
	 * EN: Estimate height reserved to the contacts and attachments blocks.
	 *
	 * @param array<int,array<string,mixed>> $contacts
	 * @param array<int,array<string,mixed>> $attachments
	 * @return int
	 */
	protected function estimateSummaryHeight(array $contacts, array $attachments)
	{
		// FR: Valeurs de base utilisées pour calculer la hauteur des tableaux.
		// EN: Base metrics used to compute the height of the tables.
		$lineHeight = 5;
		$headerHeight = 6;
		$padding = 8;

		// FR: Au moins une ligne est conservée pour afficher le message d'absence de contact.
		// EN: Keep at least one row to display the "no contact" message when needed.
		$contactRows = count($contacts);
		if ($contactRows === 0) {
			$contactRows = 1;
		}
		$contactsHeight = $headerHeight + ($contactRows * $lineHeight) + 4;

		// FR: Même logique appliquée pour les pièces jointes listées.
		// EN: Apply the same logic for the listed attachments.
		$attachmentRows = count($attachments);
		if ($attachmentRows === 0) {
			$attachmentRows = 1;
		}
		$attachmentsHeight = $headerHeight + ($attachmentRows * $lineHeight);

		// FR: Retourne une hauteur minimale confortable pour les deux sections combinées.
		// EN: Return a comfortable minimal height for the combined sections.
		return max(50, $contactsHeight + $attachmentsHeight + $padding);
	}

	/**
	 * Render description with manual pagination to preserve footer space.
	 *
	 * @param TCPDF|TCPDI $pdf PDF handler
	 * @param Diffusion $object Diffusion object
	 * @param Translate $outputlangs Output language handler
	 * @param string $descriptionText Description content
	 * @param float $startY Start position on first page
	 * @param float $startYNewPage Start position on new pages
	 * @param float $width Available width
	 * @param float $heightforfooter Reserved height for footer area
	 * @param int $defaultFontSize Font size
	 * @param int|false $tplidx Background template index
	 * @param int $pagenb Current page number (incremented when new pages are added)
	 * @param ?Translate $outputlangsbis Secondary language
	 * @param bool $repeatPageHeadOnExtraPages Repeat page header on extra pages
	 * @return float
	 */
	protected function renderDescriptionWithPagination(&$pdf, $object, $outputlangs, $descriptionText, $startY, $startYNewPage, $width, $heightforfooter, $defaultFontSize, $tplidx, &$pagenb, $outputlangsbis = null, $repeatPageHeadOnExtraPages = true)
	{
		$pdf->SetFont('', '', $defaultFontSize);
		$pdf->SetXY($this->marge_gauche, $startY);
		$lineHeight = 4;
		$pageBottomLimit = $this->page_hauteur - $heightforfooter;
		$descriptionText = trim((string) $descriptionText);
		$descriptionText = str_replace(array("\\r\\n", "\\n", "\\r"), "\n", $descriptionText);

		if ($descriptionText === '') {
			return $pdf->GetY();
		}

		if (dol_textishtml($descriptionText)) {
			$descriptionHtml = convertBackOfficeMediasLinksToPublicLinks($descriptionText);
			$pageposbeforedesc = $pdf->getPage();
			$posybefore = $pdf->GetY();

			$pdf->setTopMargin($startYNewPage);
			$pdf->startTransaction();
			$pdf->SetAutoPageBreak(true, $heightforfooter);
			$pdf->writeHTMLCell($width, 0, $this->marge_gauche, $posybefore, $descriptionHtml, 0, 1, false, true, 'L', true);
			$pageposafterdesc = $pdf->getPage();
			$posyafter = $pdf->GetY();

			if ($pageposafterdesc > $pageposbeforedesc) {
				$pdf = $pdf->rollbackTransaction(true);

				while ($pagenb < $pageposafterdesc) {
					$pdf->AddPage();
					$pagenb++;
					if (!empty($tplidx)) {
						$pdf->useTemplate($tplidx);
					}
					if ($repeatPageHeadOnExtraPages) {
						$this->_pagehead($pdf, $object, $pagenb, $outputlangs, $outputlangsbis);
					}
					$pdf->setTopMargin($startYNewPage);
				}

				$pdf->setPage($pageposbeforedesc);
				$pdf->setTopMargin($startYNewPage);
				$pdf->SetAutoPageBreak(true, $heightforfooter);
				$pdf->SetFont('', '', $defaultFontSize);
				$pdf->writeHTMLCell($width, 0, $this->marge_gauche, $posybefore, $descriptionHtml, 0, 1, false, true, 'L', true);
				$pageposafterdesc = $pdf->getPage();
				$posyafter = $pdf->GetY();
			} else {
				$pdf->commitTransaction();
			}

			$pagenb = max($pagenb, $pageposafterdesc);
			$pdf->setPage($pageposafterdesc);
			$pdf->SetAutoPageBreak(true, 0);
			return $posyafter;
		}

		$sanitizedDescription = preg_replace('/\r\n|\r/', "\n", $descriptionText);
		$lines = explode("\n", $sanitizedDescription);

		for ($i = 0; $i < count($lines); $i++) {
			$line = $lines[$i];
			if ($line === '') {
				$requiredHeight = $lineHeight;
			} else {
				$encodedLine = $outputlangs->convToOutputCharset(dol_string_nohtmltag($line));
				$numLines = max(1, (int) $pdf->getNumLines($encodedLine, $width));
				$requiredHeight = $numLines * $lineHeight;
			}

			if (($pdf->GetY() + $requiredHeight) > $pageBottomLimit) {
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;
				if ($repeatPageHeadOnExtraPages) {
					$this->_pagehead($pdf, $object, $pagenb, $outputlangs, $outputlangsbis);
					$pdf->SetXY($this->marge_gauche, $startYNewPage);
				} else {
					$pdf->SetXY($this->marge_gauche, $this->marge_haute + 2);
				}
				$pdf->SetFont('', '', $defaultFontSize);
			}

			if ($line === '') {
				$pdf->MultiCell($width, $lineHeight, '', 0, 'L', 0, 1);
			} else {
				$pdf->MultiCell($width, $lineHeight, $outputlangs->convToOutputCharset(dol_string_nohtmltag($line)), 0, 'L', 0, 1);
			}
		}

		return $pdf->GetY();
	}

       /**
	* FR: Affiche le tableau des contacts liés à la diffusion.
	* EN: Render the contacts table for the diffusion.
	*
	* @param TCPDF|TCPDI $pdf PDF handler
	* @param Diffusion $object Diffusion object
	* @param array<int,array<string,mixed>> $contacts Contacts data
	* @param Translate $outputlangs Output language handler
	* @param float $startY Initial vertical position
	* @param float $width Available width
	* @return float
	*/
       protected function renderContactsSection(&$pdf, $object, array $contacts, $outputlangs, $startY, $width)
       {
	       unset($object);
	       $defaultFontSize = pdf_getPDFFontSize($outputlangs);

	       // FR: Insère le titre de la section contacts dans le document PDF.
	       // EN: Insert the contacts section title into the PDF document.
	       $pdf->SetFont('', 'B', $defaultFontSize);
	       $pdf->SetXY($this->marge_gauche, $startY);
	       $pdf->MultiCell($width, 6, $outputlangs->transnoentities('DiffusionContactsTitle'), 0, 'L');
	       $y = $pdf->GetY() + 1;

		// FR: Calcule des largeurs équilibrées pour la colonne Contact restaurée et les indicateurs de diffusion.
		// EN: Compute balanced widths for the restored Contact column and the delivery status indicators.
		$statusColumnWidth = $width * 0.1;
		$contactColumnWidth = $width * 0.35;
		$thirdpartyColumnWidth = $width - ($contactColumnWidth + (3 * $statusColumnWidth));

		// FR: Déclare les colonnes alignées sur la fiche diffusion tout en supprimant la nature du contact.
		// EN: Declare columns aligned with the diffusion card while dropping the contact nature column.
		$columns = array(
			array('key' => 'thirdparty_name', 'label' => 'ThirdParty', 'width' => $thirdpartyColumnWidth, 'align' => 'L'),
			array('key' => 'contact_name', 'label' => 'Contact', 'width' => $contactColumnWidth, 'align' => 'L'),
			array('key' => 'mail_status', 'label' => 'methodMail', 'width' => $statusColumnWidth, 'align' => 'C', 'status' => true),
			array('key' => 'letter_status', 'label' => 'methodLetter', 'width' => $statusColumnWidth, 'align' => 'C', 'status' => true),
			array('key' => 'hand_status', 'label' => 'methodHand', 'width' => $statusColumnWidth, 'align' => 'C', 'status' => true),
		);

		$pdf->SetFont('', 'B', $defaultFontSize - 1);
		$headerLineHeight = 4.5;
		$headerRowHeight = 5;
		for ($i = 0; $i < count($columns); $i++) {
			$column = $columns[$i];
			$label = $outputlangs->transnoentities($column['label']);
			$numLines = max(1, (int) $pdf->getNumLines($outputlangs->convToOutputCharset($label), $column['width']));
			$headerRowHeight = max($headerRowHeight, $numLines * $headerLineHeight);
		}

		$x = $this->marge_gauche;
		for ($i = 0; $i < count($columns); $i++) {
			$column = $columns[$i];
			// FR: Affiche l'en-tête de colonne avec la traduction appropriée.
			// EN: Output the column header with the proper translation.
			$label = $outputlangs->transnoentities($column['label']);
			$pdf->SetXY($x, $y);
			$pdf->MultiCell($column['width'], $headerLineHeight, $outputlangs->convToOutputCharset($label), 0, $column['align'], 0, 0, '', '', true, 0, false, true, $headerRowHeight, 'T', true);
			$x += $column['width'];
		}
		$y += $headerRowHeight;
		$pdf->SetDrawColor(200, 200, 200);
		$pdf->line($this->marge_gauche, $y, $this->marge_gauche + $width, $y);
		$y += 1;
	       $pdf->SetFont('', '', $defaultFontSize - 1);

	       if (empty($contacts)) {
		       // FR: Message affiché lorsqu'aucun contact n'est lié à la diffusion.
		       // EN: Message displayed when no contact is linked to the diffusion.
		       $pdf->SetXY($this->marge_gauche, $y);
		       $pdf->MultiCell($width, 5, $outputlangs->transnoentities('DiffusionNoContacts'), 0, 'L');
		       return $pdf->GetY();
	       }

	       for ($i = 0; $i < count($contacts); $i++) {
		       $contact = $contacts[$i];
		       $lineHeight = 4.5;
		       $rowHeight = 5;
		       for ($j = 0; $j < count($columns); $j++) {
			       $column = $columns[$j];
			       $text = $this->formatContactColumnValue($contact, $column, $outputlangs);
			       // FR: Calcule la hauteur nécessaire pour gérer les textes multilignes.
			       // EN: Compute the row height required to handle multi-line text.
			       $numLines = max(1, (int) $pdf->getNumLines($outputlangs->convToOutputCharset($text), $column['width']));
			       $rowHeight = max($rowHeight, $numLines * $lineHeight);
		       }

		       $x = $this->marge_gauche;
		       for ($j = 0; $j < count($columns); $j++) {
			       $column = $columns[$j];
			       $text = $this->formatContactColumnValue($contact, $column, $outputlangs);
			       // FR: Écrit chaque cellule en respectant l'alignement prévu.
			       // EN: Write each cell while respecting the expected alignment.
			       $pdf->SetXY($x, $y);
			       $pdf->MultiCell($column['width'], $lineHeight, $outputlangs->convToOutputCharset($text), 0, $column['align'], 0, 0, '', '', true, 0, false, true, $rowHeight, 'T', true);
			       $x += $column['width'];
		       }
			$y += $rowHeight;
			$pdf->line($this->marge_gauche, $y, $this->marge_gauche + $width, $y);
			$y += 0.5;
		}

		return $y;
	}

       /**
	* FR: Formate les valeurs affichées dans le tableau des contacts.
	* EN: Format value displayed in the contacts table.
	*
	* @param array<string,mixed> $contact Contact data
	* @param array<string,mixed> $column Column definition
	* @param Translate $outputlangs Output language handler
	* @return string
	*/
       protected function formatContactColumnValue(array $contact, array $column, $outputlangs)
       {
		$key = $column['key'];
		if ($key === 'thirdparty_name') {
			// FR: Affiche uniquement la raison sociale pour refléter la colonne « Tiers ».
			// EN: Display only the third-party name to mirror the "Third party" column.
			return !empty($contact[$key]) ? (string) $contact[$key] : '';
		}
		if ($key === 'contact_name') {
			// FR: Assemble le nom du contact et ses coordonnées principales.
			// EN: Combine the contact name with their primary contact details.
			$lines = array();
			if (!empty($contact[$key])) {
				$lines[] = (string) $contact[$key];
			}
			$details = array();
			if (!empty($contact['email'])) {
				$details[] = (string) $contact['email'];
			}
			$primaryPhone = '';
			if (!empty($contact['phone'])) {
				$primaryPhone = (string) $contact['phone'];
			} elseif (!empty($contact['mobile'])) {
				$primaryPhone = (string) $contact['mobile'];
			}
			if ($primaryPhone !== '') {
				$details[] = $primaryPhone;
			}
			if (!empty($details)) {
				$lines[] = implode(' - ', $details);
			}
			return implode("\n", $lines);
		}

		if (!empty($column['status'])) {
			// FR: Affiche une coche lorsque la méthode est activée.
			// EN: Show a check mark when the delivery method is enabled.
			return !empty($contact[$key]) ? $outputlangs->transnoentities("Yes") : $outputlangs->transnoentities("No");
		}


	       // FR: Retourne la valeur textuelle si elle existe, sinon une chaîne vide.
	       // EN: Return the textual value when it exists, otherwise an empty string.
	       return isset($contact[$key]) ? (string) $contact[$key] : '';
       }

       /**
	* FR: Affiche la liste des pièces jointes de la diffusion.
	* EN: Render the attachments list.
	*
	* @param TCPDF|TCPDI $pdf PDF handler
	* @param array<int,array<string,mixed>> $attachments Attachments data
	* @param Translate $outputlangs Output language handler
	* @param float $startY Initial vertical position
	* @param float $width Available width
	* @return float
	*/
       protected function renderAttachmentsSection(&$pdf, array $attachments, $outputlangs, $startY, $width)
       {
	       $defaultFontSize = pdf_getPDFFontSize($outputlangs);

	       // FR: Ajoute le titre de la section consacrée aux documents joints.
	       // EN: Add the title for the attachments section.
	       $pdf->SetFont('', 'B', $defaultFontSize);
	       $pdf->SetXY($this->marge_gauche, $startY);
	       $pdf->MultiCell($width, 6, $outputlangs->transnoentities('DiffusionAttachmentsTitle'), 0, 'L');
	       $y = $pdf->GetY() + 1;

	       $pdf->SetFont('', '', $defaultFontSize - 1);

	       if (empty($attachments)) {
		       // FR: Indique clairement l'absence de documents joints.
		       // EN: Clearly state that no documents are attached.
		       $pdf->SetXY($this->marge_gauche, $y);
		       $pdf->MultiCell($width, 5, $outputlangs->transnoentities('DiffusionNoDocuments'), 0, 'L');
		       return $pdf->GetY();
	       }

		for ($i = 0; $i < count($attachments); $i++) {
			$fileinfo = $attachments[$i];
			$sizeLabel = dol_print_size(isset($fileinfo['size']) ? $fileinfo['size'] : 0, 1, 1, 0, $outputlangs);
			$lineLabel = $outputlangs->transnoentities('DiffusionAttachmentLine', $fileinfo['name'], $sizeLabel);
			$lineToDisplay = '- '.dol_escape_htmltag($lineLabel);
			if (!empty($fileinfo['public_share_link'])) {
				$lineToDisplay = '- '.dol_escape_htmltag($fileinfo['name']).' - '.dol_escape_htmltag($outputlangs->transnoentities('DiffusionAttachmentPublicLink')).' : <a href="'.dol_escape_htmltag((string) $fileinfo['public_share_link']).'">'.dol_escape_htmltag((string) $fileinfo['public_share_link']).'</a>';
			}
			$pdf->SetXY($this->marge_gauche, $y);
			$pdf->writeHTMLCell($width, 0, $this->marge_gauche, $y, $outputlangs->convToOutputCharset($lineToDisplay), 0, 1, false, true, 'L', true);
			$y = $pdf->GetY();
		}


		return $y;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *	Show table for lines
	 *
	 *	@param	TCPDF|TCPDI	$pdf     		Object PDF
	 *	@param	float		$tab_top		Top position of table
	 *	@param	float		$tab_height		Height of table (rectangle)
	 *	@param	int			$nexY			Y (not used)
	 *	@param	Translate	$outputlangs	Langs object
	 *	@param	int<-1,1>	$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *	@param	int<0,1>	$hidebottom		Hide bottom bar of array
	 *	@param	string		$currency		Currency code
	 *	@param	?Translate	$outputlangsbis	Langs object bis
	 *	@return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '', $outputlangsbis = null)
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) {
			$hidetop = -1;
		}

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);

		if (empty($hidetop)) {
			$titre = $outputlangs->transnoentities("AmountInCurrency", $outputlangs->transnoentitiesnoconv("Currency".$currency));
			if (getDolGlobalInt('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
				$titre .= ' - '.$outputlangsbis->transnoentities("AmountInCurrency", $outputlangsbis->transnoentitiesnoconv("Currency".$currency));
			}

			$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top - 4);
			$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);

			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			if (getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')) {
				$pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_droite - $this->marge_gauche, $this->tabTitleHeight, 'F', array(), explode(',', getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')));
			}
		}

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Output Rect
		$this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $hidetop, $hidebottom); // Rect takes a length in 3rd parameter and 4th parameter


		$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);

		if (empty($hidetop)) {
			$pdf->line($this->marge_gauche, $tab_top + $this->tabTitleHeight, $this->page_largeur - $this->marge_droite, $tab_top + $this->tabTitleHeight); // line takes a position y in 2nd parameter and 4th parameter
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *	@param	TCPDF|TCPDI	$pdf     		Object PDF
	 *	@param	Diffusion	$object     	Object to show
	 *	@param	int<0,1>	$showaddress    0=no, 1=yes
	 *	@param	Translate	$outputlangs	Object lang for output
	 *	@param	?Translate	$outputlangsbis	Object lang for output bis
	 *	@return	float|int                   Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null)
	{
		// phpcs:enable
		global $conf, $langs;

		$ltrdirection = 'L';
		if ($outputlangs->trans("DIRECTION") == 'rtl') {
			$ltrdirection = 'R';
		}

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "bills", "propal", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		// Show Draft Watermark
		if (getDolGlobalString('DIFFUSIONPLANS_DRAFT_WATERMARK') && $object->status == $object::STATUS_DRAFT) {
			pdf_watermark($pdf, $outputlangs, $this->page_hauteur, $this->page_largeur, 'mm', dol_escape_htmltag(getDolGlobalString('DIFFUSIONPLANS_DRAFT_WATERMARK')));
		}

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			if ($this->emetteur->logo) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty(getMultidirOutput($object, 'mycompany'))) {
					$logodir = getMultidirOutput($object, 'mycompany');
				}
				if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
					$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
				} else {
					$logo = $logodir.'/logos/'.$this->emetteur->logo;
				}
				if (is_readable($logo)) {
					$height = pdf_getHeightForLogo($logo);
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
				} else {
					$pdf->SetTextColor(200, 0, 0);
					$pdf->SetFont('', 'B', $default_font_size - 2);
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
					$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
				}
			} else {
				$text = $this->emetteur->name;
				$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
			}
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$title = $outputlangs->transnoentities("PdfTitle");
		if (getDolGlobalInt('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - ';
			$title .= $outputlangsbis->transnoentities("PdfTitle");
		}
		$pdf->MultiCell($w, 3, $title, '', 'R');

		$pdf->SetFont('', 'B', $default_font_size);

		$posy += 5;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$textref = $outputlangs->transnoentities("Ref")." : ".$outputlangs->convToOutputCharset($object->ref);
		if ($object->status == $object::STATUS_DRAFT) {
			$pdf->SetTextColor(128, 0, 0);
			$textref .= ' - '.$outputlangs->transnoentities("NotValidated");
		}
		$pdf->MultiCell($w, 4, $textref, '', 'R');

		$posy += 1;
		$pdf->SetFont('', '', $default_font_size - 2);

		// @phan-suppress-next-line PhanUndeclaredProperty
		if (property_exists($object, 'ref_client') && $object->ref_client) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			// @phan-suppress-next-line PhanUndeclaredProperty
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefCustomer")." : ".dol_trunc($outputlangs->convToOutputCharset($object->ref_client), 65), '', 'R');
		}

		if (getDolGlobalInt('PDF_SHOW_PROJECT_TITLE')) {
			$object->fetchProject();
			if (!empty($object->project->ref)) {
				$posy += 3;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("Project")." : ".(empty($object->project->title) ? '' : $object->project->title), '', 'R');
			}
		}

		if (getDolGlobalInt('PDF_SHOW_PROJECT')) {
			$object->fetchProject();
			if (!empty($object->project->ref)) {
				$outputlangs->load("projects");
				$posy += 3;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefProject")." : ".(empty($object->project->ref) ? '' : $object->project->ref), '', 'R');
			}
		}

		$posy += 4;
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);

		$title = $outputlangs->transnoentities("Date");
		if (getDolGlobalInt('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
			$title .= ' - '.$outputlangsbis->transnoentities("Date");
		}
		$pdf->MultiCell($w, 3, $title." : ".dol_print_date($object->date_creation, "day", false, $outputlangs, true), '', 'R');

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && !empty($object->thirdparty->code_client)) {
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_ACCOUNTING_CODE') && !empty($object->thirdparty->code_compta_client)) {
			$posy += 3;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerAccountancyCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_compta_client), '', 'R');
		}

		// Get contact
		if (getDolGlobalInt('DOC_SHOW_FIRST_SALES_REP')) {
			$arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
			if (count($arrayidcontact) > 0) {
				$usertmp = new User($this->db);
				$usertmp->fetch($arrayidcontact[0]);
				$posy += 4;
				$pdf->SetXY($posx, $posy);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R');
			}
		}

		$posy += 1;

		$top_shift = 0;
		// Show list of linked objects
		$current_y = $pdf->getY();
		$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
		if ($current_y < $pdf->getY()) {
			$top_shift = $pdf->getY() - $current_y;
		}

		if ($showaddress) {
			// Sender properties
			$carac_emetteur = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy += $top_shift;
			$posx = $this->marge_gauche;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;
			$hideAddressFrames = ($pdf->getPage() > 1);

			// Show sender frame
			if (!$hideAddressFrames && !getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("SentFrom").":", 0, $ltrdirection);
				$pdf->SetXY($posx, $posy);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
				$pdf->SetTextColor(0, 0, 60);
			}

			// Show sender name
			if (!$hideAddressFrames && !getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
				$pdf->SetXY($posx + 2, $posy + 3);
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
				$posy = $pdf->getY();
			}
		
			// Show sender information
			if (!$hideAddressFrames) {
				$pdf->SetXY($posx + 2, $posy);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, $ltrdirection);
			}
			// If BILLING contact defined, we use it
			$usecontact = false;
			$arrayidcontact = $object->getIdContact('external', 'BILLING');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			// Recipient name
			if ($usecontact && $object->contact->socid != $object->thirdparty->id && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT')) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			if (is_object($thirdparty)) {
				$carac_client_name = pdfBuildThirdpartyName($thirdparty, $outputlangs);
			} else {
				$carac_client_name = null;
			}

			$mode = 'target';
			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, ($usecontact ? $object->contact : ''), ($usecontact ? 1 : 0), $mode, $object);

			// Show recipient
			$widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) {
				$widthrecbox = 84; // To work with US executive format
			}
			$posy = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy += $top_shift;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			// Show recipient frame
			if (!$hideAddressFrames && !getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx + 2, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("SentTo").":", 0, $ltrdirection);
				$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);
			}

			// Show recipient name
			if (!$hideAddressFrames) {
				$pdf->SetXY($posx + 2, $posy + 3);
				$pdf->SetFont('', 'B', $default_font_size);
				// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
				$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, $ltrdirection);
			}

			$posy = $pdf->getY();

			// Show recipient information
			if (!$hideAddressFrames) {
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->SetXY($posx + 2, $posy);
				// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
				$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, $ltrdirection);
			}
		}

		$pdf->SetTextColor(0, 0, 0);
		return $top_shift;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *	Show footer of page. Need this->emetteur object
	 *
	 *	@param	TCPDI|TCPDF		$pdf     		PDF
	 *	@param	CommonObject	$object			Object to show
	 *	@param	Translate		$outputlangs	Object lang for output
	 *	@param	int<0,1>		$hidefreetext	1=Hide free text
	 *	@return	int<0,1>						Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		global $conf;
		$showdetails = !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 0 : getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');
		return pdf_pagefoot($pdf, $outputlangs, 'DIFFUSION_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);

	}

}
