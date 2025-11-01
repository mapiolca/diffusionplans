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
// FR: Charge le gestionnaire de liens de contacts pour réutiliser les données préparées.
// EN: Load the contact link manager to reuse the preloaded contact data.
dol_include_once('/diffusionplans/class/diffusioncontact.class.php');
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
		$langs->loadLangs(array("main", "bills"));

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
			       $attachmentSummaries = $this->loadDiffusionAttachments($dir, $currentPdfName);
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
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("PdfTitle")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				// Set certificate
				$cert = empty($user->conf->CERTIFICATE_CRT) ? '' : $user->conf->CERTIFICATE_CRT;
				// If user has no certificate, we try to take the company one
				if (!$cert) {
					$cert = getDolGlobalString('CERTIFICATE_CRT');
				}
				// If a certificate is found
				if ($cert) {
					$info = array(
						'Name' => $this->emetteur->name,
						'Location' => getCountry($this->emetteur->country_code, ''),
						'Reason' => 'DIFFUSION',
						'ContactInfo' => $this->emetteur->email
					);
					$pdf->setSignature($cert, $cert, $this->emetteur->name, '', 2, $info);
				}

				// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right


				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;

				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 90 + $top_shift;
				$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 + $top_shift : 10);

				$tab_height = $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext;

				$tab_height_newpage = 150;
				if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
					$tab_height_newpage -= $top_shift;
				}

				$nexY = $tab_top - 1;

				// Display notes
				$notetoshow = empty($object->note_public) ? '' : $object->note_public;
				// Extrafields in note
				$extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
				if (!empty($extranote)) {
					$notetoshow = dol_concatdesc($notetoshow, $extranote);
				}

				$pagenb = $pdf->getPage();
				if ($notetoshow) {
					$tab_top -= 2;

					$tab_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
					$pageposbeforenote = $pagenb;

					$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
					$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

					$pdf->startTransaction();

					$pdf->SetFont('', '', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
					// Description
					$pageposafternote = $pdf->getPage();
					$posyafter = $pdf->GetY();

					if ($pageposafternote > $pageposbeforenote) {
						$pdf->rollbackTransaction(true);

						// prepare pages to receive notes
						while ($pagenb < $pageposafternote) {
							$pdf->AddPage();
							$pagenb++;
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
							if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
								$this->_pagehead($pdf, $object, 0, $outputlangs);
							}
							// $this->_pagefoot($pdf,$object,$outputlangs,1);
							$pdf->setTopMargin($tab_top_newpage);
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						}

						// back to start
						$pdf->setPage($pageposbeforenote);
						$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
						$pageposafternote = $pdf->getPage();

						$posyafter = $pdf->GetY();

						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {	// There is no space left for total+free text
							$pdf->AddPage('', '', true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							$pdf->setTopMargin($tab_top_newpage);
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
							//$posyafter = $tab_top_newpage;
						}


						// apply note frame to previous pages
						$i = $pageposbeforenote;
						while ($i < $pageposafternote) {
							$pdf->setPage($i);


							$pdf->SetDrawColor(128, 128, 128);
							// Draw note frame
							if ($i > $pageposbeforenote) {
								$height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
							} else {
								$height_note = $this->page_hauteur - ($tab_top + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);
							}

							// Add footer
							$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
							$this->_pagefoot($pdf, $object, $outputlangs, 1);

							$i++;
						}

						// apply note frame to last page
						$pdf->setPage($pageposafternote);
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
						$height_note = $posyafter - $tab_top_newpage;
						$pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
					} else {
						// No pagebreak
						$pdf->commitTransaction();
						$posyafter = $pdf->GetY();
						$height_note = $posyafter - $tab_top;
						$pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);


						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {
							// not enough space, need to add page
							$pdf->AddPage('', '', true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
							if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
								$this->_pagehead($pdf, $object, 0, $outputlangs);
							}

							$posyafter = $tab_top_newpage;
						}
					}

					$tab_height -= $height_note;
					$tab_top = $posyafter + 6;
				} else {
					$height_note = 0;
				}

								$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot);

				$descriptionText = trim($object->description);
				if ($descriptionText !== '') {
					$pdf->SetXY($this->marge_gauche, $tab_top);
					$pdf->SetFont('', '', $default_font_size);
					$pdf->writeHTMLCell(
						$this->page_largeur - $this->marge_gauche - $this->marge_droite,
						0,
						$this->marge_gauche,
						$tab_top,
						dol_htmlentitiesbr($descriptionText),
						0,
						1,
						false,
						true,
						'L'
					);
					$bottomlasttab = $pdf->GetY();
				} else {
					$bottomlasttab = $tab_top;
				}

			       // Display diffusion contacts and attachments summary
			       $summaryStartY = max($pdf->GetY(), $bottomlasttab + 2);
			       $availableWidth = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
			       $afterContactsY = $this->renderContactsSection($pdf, $object, $contactSummaries, $outputlangs, $summaryStartY, $availableWidth);
			       $this->renderAttachmentsSection($pdf, $attachmentSummaries, $outputlangs, $afterContactsY + 4, $availableWidth);

				// Display payment area
				/*
				if ($deja_regle)
				{
					$posy = $this->drawPaymentsTable($pdf, $object, $posy, $outputlangs);
				}
				*/

				// Pagefoot
				$this->_pagefoot($pdf, $object, $outputlangs);
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
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "FAC_OUTPUTDIR");
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
		global $mysoc;

		$result = array();

		if (empty($object->id)) {
			return $result;
		}

		$companystatic = new Societe($this->db);
		$contactstatic = new Contact($this->db);
		$userstatic = new User($this->db);

		$contactLinks = array();
		if (!empty($object->pdf_contact_links) && is_array($object->pdf_contact_links)) {
			// FR: Utilise les liens déjà collectés sur la fiche pour éviter une requête supplémentaire.
			// EN: Reuse the links gathered on the card to avoid running an extra query.
			$contactLinks = $object->pdf_contact_links;
		} else {
			// FR: Récupère les liens en base si la fiche n'a pas préparé la liste.
			// EN: Load the links from database when the card did not preload them.
			$contactLinkLoader = new DiffusionContact($this->db);
			$contactLinks = $contactLinkLoader->fetchDiffusionContactLinks($object->id);
		}

		foreach ($contactLinks as $contactRow) {
			$contactData = is_array($contactRow) ? $contactRow : (array) $contactRow;
			$source = isset($contactData['contact_source']) ? (string) $contactData['contact_source'] : '';
			$contactId = isset($contactData['fk_contact']) ? (int) $contactData['fk_contact'] : 0;

			if ($contactId <= 0 || $source === '') {
				continue;
			}

			$thirdpartyName = '';
			$contactName = '';
			$email = '';
			$phone = '';
			$mobile = '';

			if ($source === 'internal') {
				if ($userstatic->fetch($contactId) > 0) {
					$contactName = $userstatic->getFullName($outputlangs);
					$email = $userstatic->email;
					$phone = $userstatic->office_phone;
					$mobile = $userstatic->user_mobile;
				}
				if (!empty($mysoc->name)) {
					$thirdpartyName = $mysoc->name;
				}
			} else {
				$externalContactFetched = $contactstatic->fetch($contactId) > 0;
				if ($externalContactFetched) {
					$contactName = $contactstatic->getFullName($outputlangs);
					$email = $contactstatic->email;
					$phone = $contactstatic->phone_pro;
					if (empty($phone) && !empty($contactstatic->phone_perso)) {
						$phone = $contactstatic->phone_perso;
					}
					$mobile = $contactstatic->phone_mobile;

					if (!empty($contactstatic->socid) && $contactstatic->socid > 0) {
						if ($companystatic->fetch($contactstatic->socid) > 0) {
							$thirdpartyName = $companystatic->name;
						}
					}
				}

				if (empty($thirdpartyName) && $externalContactFetched && (int) $contactstatic->socid < 0 && !empty($mysoc->name)) {
					$thirdpartyName = $mysoc->name;
				}
			}

			if (empty($thirdpartyName) && !empty($mysoc->name) && $source === 'internal') {
				$thirdpartyName = $mysoc->name;
			}

			if (empty($phone) && !empty($mobile)) {
				$phone = $mobile;
			}

			$typeLabel = '';
			$typeLabelKey = isset($contactData['type_label']) ? (string) $contactData['type_label'] : '';
			if ($typeLabelKey !== '') {
				$translated = $outputlangs->transnoentitiesnoconv($typeLabelKey);
				$typeLabel = !empty($translated) ? (string) $translated : $typeLabelKey;
			}

			$result[] = array(
			'id' => $contactId,
			'source' => $source,
			'type_label' => $typeLabel,
			'thirdparty_name' => $thirdpartyName,
			'contact_name' => $contactName,
			'email' => $email,
			'phone' => $phone,
			'mobile' => $mobile,
			'mail_status' => isset($contactData['mail_status']) ? (int) $contactData['mail_status'] : 0,
			'letter_status' => isset($contactData['letter_status']) ? (int) $contactData['letter_status'] : 0,
			'hand_status' => isset($contactData['hand_status']) ? (int) $contactData['hand_status'] : 0,
			);
		}

		return $result;
	}

	/**
	 * List attachments stored in the diffusion directory.
	 *
	 * @param string $dir Absolute directory path
	 * @param string $currentPdfName Generated PDF file name
	 * @return array<int,array<string,mixed>>
	 */
	protected function loadDiffusionAttachments($dir, $currentPdfName)
	{
		$attachments = array();

		if (empty($dir)) {
			return $attachments;
		}

		$fileList = dol_dir_list($dir, 'files', 0, '', '(\\.meta$|\\.tmp$|\\.preview\\.png$)', 'name', SORT_ASC, 1);
		foreach ($fileList as $fileinfo) {
			if (!empty($currentPdfName) && dol_strtolower($fileinfo['name']) == dol_strtolower($currentPdfName)) {
				continue;
			}
			$attachments[] = $fileinfo;
		}

		return $attachments;
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

	       // FR: Déclaration des colonnes, de leur libellé traduit et du formatage associé.
	       // EN: Declare columns with their translated labels and formatting metadata.
	       // FR: Largeurs harmonisées pour équilibrer noms, emails et indicateurs.
	       // EN: Balanced widths to harmonise names, emails and delivery flags.
	       $columns = array(
		       array('key' => 'thirdparty_name', 'label' => 'ThirdParty', 'width' => $width * 0.20, 'align' => 'L'),
		       array('key' => 'contact_name', 'label' => 'Contact', 'width' => $width * 0.20, 'align' => 'L'),
		       array('key' => 'type_label', 'label' => 'ContactType', 'width' => $width * 0.12, 'align' => 'L'),
			array('key' => 'email', 'label' => 'Email', 'width' => $width * 0.26, 'align' => 'L'),
			array('key' => 'phone', 'label' => 'Phone', 'width' => $width * 0.10, 'align' => 'L'),
			array('key' => 'mail_status', 'label' => 'methodMail', 'width' => $width * 0.04, 'align' => 'C', 'status' => true),
			array('key' => 'letter_status', 'label' => 'methodLetter', 'width' => $width * 0.04, 'align' => 'C', 'status' => true),
			array('key' => 'hand_status', 'label' => 'methodHand', 'width' => $width * 0.04, 'align' => 'C', 'status' => true),
		);

	       $pdf->SetFont('', 'B', $defaultFontSize - 1);
	       $x = $this->marge_gauche;
	       for ($i = 0; $i < count($columns); $i++) {
		       $column = $columns[$i];
		       // FR: Affiche l'en-tête de colonne avec la traduction appropriée.
		       // EN: Output the column header with the proper translation.
		       $pdf->SetXY($x, $y);
		       $pdf->MultiCell($column['width'], 5, $outputlangs->transnoentities($column['label']), 0, $column['align'], 0, 0);
		       $x += $column['width'];
	       }
		$y += 5;
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
		       $rowHeight = 5;
		       for ($j = 0; $j < count($columns); $j++) {
			       $column = $columns[$j];
			       $text = $this->formatContactColumnValue($contact, $column, $outputlangs);
			       // FR: Calcule la hauteur nécessaire pour gérer les textes multilignes.
			       // EN: Compute the row height required to handle multi-line text.
			       $numLines = $pdf->getNumLines($outputlangs->convToOutputCharset($text), $column['width']);
			       $rowHeight = max($rowHeight, $numLines * 4.5);
		       }

		       $x = $this->marge_gauche;
		       for ($j = 0; $j < count($columns); $j++) {
			       $column = $columns[$j];
			       $text = $this->formatContactColumnValue($contact, $column, $outputlangs);
			       // FR: Écrit chaque cellule en respectant l'alignement prévu.
			       // EN: Write each cell while respecting the expected alignment.
			       $pdf->SetXY($x, $y);
			       $pdf->MultiCell($column['width'], $rowHeight, $outputlangs->convToOutputCharset($text), 0, $column['align'], 0, 0);
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
	       if (!empty($column['status'])) {
		       // FR: Transforme les indicateurs booléens en libellés Oui/Non traduits.
		       // EN: Turn boolean flags into translated Yes/No labels.
		       return !empty($contact[$key]) ? $outputlangs->transnoentities('Yes') : $outputlangs->transnoentities('No');
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
		       // FR: Construit une description avec le nom du fichier et sa taille formatée.
		       // EN: Build a description containing the file name and its formatted size.
		       $sizeLabel = dol_print_size(isset($fileinfo['size']) ? $fileinfo['size'] : 0, 1, 1, 0, $outputlangs);
		       $lineLabel = $outputlangs->transnoentities('DiffusionAttachmentLine', $fileinfo['name'], $sizeLabel);
		       $pdf->SetXY($this->marge_gauche, $y);
		       // FR: Préfixe chaque élément avec une puce pour faciliter la lecture.
		       // EN: Prefix each entry with a bullet to ease readability.
		       $pdf->MultiCell($width, 5, '- '.$outputlangs->convToOutputCharset($lineLabel), 0, 'L');
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


			// Show sender frame
			if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillFrom").":", 0, $ltrdirection);
				$pdf->SetXY($posx, $posy);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
				$pdf->SetTextColor(0, 0, 60);
			}

			// Show sender name
			if (!getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
				$pdf->SetXY($posx + 2, $posy + 3);
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
				$posy = $pdf->getY();
			}

			// Show sender information
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, $ltrdirection);

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
			if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx + 2, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("To").":", 0, $ltrdirection);
				$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);
			}

			// Show recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, $ltrdirection);

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($posx + 2, $posy);
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, $ltrdirection);
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
		return pdf_pagefoot($pdf, $outputlangs, 'INVOICE_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}

	/**
	 *	Define Array Column Field
	 *
	 *	@param	CommonObject	$object    		common object
	 *	@param	Translate		$outputlangs    langs
	 *	@param	int<0,1>		$hidedetails	Do not show line details
	 *	@param	int<0,1>		$hidedesc		Do not show desc
	 *	@param	int<0,1>		$hideref		Do not show ref
	 *	@return	void
	 */
	public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $hookmanager;

		// Default field style for content
		$this->defaultContentsFieldsStyle = array(
			'align' => 'R', // R,C,L
			'padding' => array(1, 0.5, 1, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		// Default field style for content
		$this->defaultTitlesFieldsStyle = array(
			'align' => 'C', // R,C,L
			'padding' => array(0.5, 0, 0.5, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		/*
		 * For example
		$this->cols['theColKey'] = array(
			'rank' => $rank, // int : use for ordering columns
			'width' => 20, // the column width in mm
			'title' => array(
				'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				'label' => ' ', // the final label : used fore final generated text
				'align' => 'L', // text alignment :  R,C,L
				'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'content' => array(
				'align' => 'L', // text alignment :  R,C,L
				'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
		);
		*/

		$rank = 0; // do not use negative rank
		$this->cols['desc'] = array(
			'rank' => $rank,
			'width' => false, // only for desc
			'status' => true,
			'title' => array(
				'textkey' => 'Designation', // use lang key is useful in somme case with module
				'align' => 'L',
				// 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				// 'label' => ' ', // the final label
				'padding' => array(0.5, 0.5, 0.5, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'content' => array(
				'align' => 'L',
				'padding' => array(1, 0.5, 1, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
		);

		// PHOTO
		// $rank += 10;
		// $this->cols['photo'] = array(
		// 	'rank' => $rank,
		// 	'width' => (!getDolGlobalInt('MAIN_DOCUMENTS_WITH_PICTURE_WIDTH') ? 20 : getDolGlobalInt('MAIN_DOCUMENTS_WITH_PICTURE_WIDTH')), // in mm
		// 	'status' => false,
		// 	'title' => array(
		// 		'textkey' => 'Photo',
		// 		'label' => ' '
		// 	),
		// 	'content' => array(
		// 		'padding' => array(0, 0, 0, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		// 	),
		// 	'border-left' => false, // remove left line separator
		// );

		// if (getDolGlobalInt('MAIN_GENERATE_INVOICES_WITH_PICTURE') && !empty($this->atleastonephoto)) {
		// 	$this->cols['photo']['status'] = true;
		// }


		$rank += 10;
		$this->cols['vat'] = array(
			'rank' => $rank,
			'status' => false,
			'width' => 16, // in mm
			'title' => array(
				'textkey' => 'VAT'
			),
			'border-left' => true, // add left line separator
		);

		if (!getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT') && !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN')) {
			$this->cols['vat']['status'] = true;
		}

		$rank += 10;
		$this->cols['subprice'] = array(
			'rank' => $rank,
			'width' => 19, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'PriceUHT'
			),
			'border-left' => true, // add left line separator
		);

		$rank += 10;
		$this->cols['qty'] = array(
			'rank' => $rank,
			'width' => 16, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'Qty'
			),
			'border-left' => true, // add left line separator
		);

		$rank += 10;
		$this->cols['unit'] = array(
			'rank' => $rank,
			'width' => 11, // in mm
			'status' => false,
			'title' => array(
				'textkey' => 'Unit'
			),
			'border-left' => true, // add left line separator
		);
		if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
			$this->cols['unit']['status'] = true;
		}

		$rank += 10;
		$this->cols['discount'] = array(
			'rank' => $rank,
			'width' => 13, // in mm
			'status' => false,
			'title' => array(
				'textkey' => 'ReductionShort'
			),
			'border-left' => true, // add left line separator
		);
		if ($this->atleastonediscount) {
			$this->cols['discount']['status'] = true;
		}

		$rank += 1000; // add a big offset to be sure is the last col because default extrafield rank is 100
		$this->cols['totalexcltax'] = array(
			'rank' => $rank,
			'width' => 26, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'TotalHTShort'
			),
			'border-left' => true, // add left line separator
		);

		// Add extrafields cols
		if (!empty($object->lines)) {
			$line = reset($object->lines);
			$this->defineColumnExtrafield($line, $outputlangs, $hidedetails);
		}

		$parameters = array(
			'object' => $object,
			'outputlangs' => $outputlangs,
			'hidedetails' => $hidedetails,
			'hidedesc' => $hidedesc,
			'hideref' => $hideref
		);

		$reshook = $hookmanager->executeHooks('defineColumnField', $parameters, $this); // Note that $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		} elseif (empty($reshook)) {
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrderInternal
			$this->cols = array_replace($this->cols, $hookmanager->resArray); // array_replace is used to preserve keys
		} else {
			$this->cols = $hookmanager->resArray;
		}
	}
}
