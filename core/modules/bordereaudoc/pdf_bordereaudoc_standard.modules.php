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
 * \file       core/modules/bordereaudoc/pdf_bordereaudoc_standard.modules.php
 * \ingroup    diffusionplans
 * \brief      PDF model for bordereaudoc.
 */

dol_include_once('/diffusionplans/core/modules/bordereaudoc/modules_bordereaudoc.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

class pdf_bordereaudoc_standard extends ModelePDFBordereaudoc
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Model name
	 */
	public $name;

	/**
	 * @var string Model description
	 */
	public $description;

	/**
	 * @var string Document type
	 */
	public $type = 'pdf';

	/**
	 * @var string Document format
	 */
	public $format;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs;

		$this->db = $db;
		$this->name = 'standard';
		$this->description = 'Standard';
		$this->option_freetext = getDolGlobalString('DIFFUSIONPLANS_FREE_TEXT', '');
		$this->format = array(210, 297);

		$langs->loadLangs(array('main', 'companies', 'projects', 'diffusionplans@diffusionplans'));
	}

	/**
	 * Write bordereaudoc PDF file
	 *
	 * @param Bordereaudoc $object Bordereaudoc
	 * @param Translate $outputlangs Output langs
	 * @param string $srctemplatepath Not used
	 * @param int $hidedetails Not used
	 * @param int $hidedesc Not used
	 * @param int $hideref Not used
	 * @return int
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs, $mysoc;

		if (empty($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'companies', 'projects', 'diffusionplans@diffusionplans'));

		if ($mysoc->country_code) {
			$outputlangs->load('dict');
		}

		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$objectref = dol_sanitizeFileName($object->ref);
		$dir = (!empty($conf->diffusionplans->multidir_output[$object->entity]) ? $conf->diffusionplans->multidir_output[$object->entity] : $conf->document_root[$object->entity]).'/'.$objectref;
		dol_mkdir($dir);
		$file = $dir.'/'.$objectref.'.pdf';

		$pdf = pdf_getInstance($this->format);
		$default_font = pdf_getPDFFont($outputlangs);
		$pdf->SetTitle($outputlangs->transnoentities('Bordereaudoc').' '.$object->ref);
		$pdf->SetSubject($outputlangs->transnoentities('Bordereaudoc'));
		$pdf->SetCreator('Dolibarr');
		$pdf->SetAuthor($outputlangs->transnoentities($mysoc->name));
		pdf_setPrintHeader($pdf, $outputlangs);
		pdf_setPrintFooter($pdf, $outputlangs);
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
		$pdf->AddPage();
		$pdf->SetFont($default_font, 'B', $default_font_size + 2);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell(0, 6, $outputlangs->trans('Bordereaudoc').' '.$object->ref, 0, 'L', 0, 1);
		$pdf->Ln(2);

		if (!empty($object->title)) {
			$pdf->SetFont($default_font, '', $default_font_size + 1);
			$pdf->MultiCell(0, 5, $object->title, 0, 'L', 0, 1);
			$pdf->Ln(1);
		}

		$pdf->SetFont($default_font, '', $default_font_size);
		$pdf->MultiCell(0, 5, $outputlangs->trans('Project').': '.($object->fk_project ? $object->fk_project : ''), 0, 'L', 0, 1);
		$pdf->Ln(1);

		if (!empty($object->description)) {
			$pdf->SetFont($default_font, 'B', $default_font_size);
			$pdf->MultiCell(0, 5, $outputlangs->trans('Description'), 0, 'L', 0, 1);
			$pdf->SetFont($default_font, '', $default_font_size);
			$pdf->writeHTMLCell(0, 0, '', '', $object->description, 0, 1, 0, true, 'L', true);
			$pdf->Ln(2);
		}

		$recipients = $object->getRecipients(1);
		if (!empty($recipients)) {
			$pdf->SetFont($default_font, 'B', $default_font_size);
			$pdf->MultiCell(0, 5, $outputlangs->trans('BordereaudocRecipients'), 0, 'L', 0, 1);
			$pdf->SetFont($default_font, '', $default_font_size);
			$table = '<table border="1" cellpadding="3">';
			$table .= '<tr>';
			$table .= '<th>'.$outputlangs->trans('ThirdParty').'</th>';
			$table .= '<th>'.$outputlangs->trans('Contact').'</th>';
			$table .= '<th>'.$outputlangs->trans('Nature').'</th>';
			$table .= '<th>'.$outputlangs->trans('Type').'</th>';
			$table .= '<th>'.$outputlangs->trans('SendByEmail').'</th>';
			$table .= '<th>'.$outputlangs->trans('SendByMail').'</th>';
			$table .= '<th>'.$outputlangs->trans('SendByHand').'</th>';
			$table .= '</tr>';
			foreach ($recipients as $line) {
				$table .= '<tr>';
				$table .= '<td>'.dol_escape_htmltag($line->socname).'</td>';
				$table .= '<td>'.dol_escape_htmltag(dolGetFirstLastname($line->firstname, $line->lastname)).'</td>';
				$table .= '<td>'.dol_escape_htmltag($line->nature_contact).'</td>';
				$table .= '<td>'.dol_escape_htmltag($line->type_contact).'</td>';
				$table .= '<td>'.($line->send_email ? $outputlangs->trans('Yes') : $outputlangs->trans('No')).'</td>';
				$table .= '<td>'.($line->send_mail ? $outputlangs->trans('Yes') : $outputlangs->trans('No')).'</td>';
				$table .= '<td>'.($line->send_hand ? $outputlangs->trans('Yes') : $outputlangs->trans('No')).'</td>';
				$table .= '</tr>';
			}
			$table .= '</table>';
			$pdf->writeHTML($table, true, false, true, false, '');
			$pdf->Ln(2);
		}

		$docs = $object->getDocumentIndex(1);
		if (!empty($docs)) {
			$pdf->SetFont($default_font, 'B', $default_font_size);
			$pdf->MultiCell(0, 5, $outputlangs->trans('BordereaudocDocuments'), 0, 'L', 0, 1);
			$pdf->SetFont($default_font, '', $default_font_size);
			$list = '<ul>';
			foreach ($docs as $doc) {
				$link = dol_buildpath('/custom/diffusionplans/public/bordereaudocdownload.php?hash='.$doc->hash, 2);
				$list .= '<li>'.dol_escape_htmltag($doc->filename).' - <a href="'.$link.'">'.$link.'</a></li>';
			}
			$list .= '</ul>';
			$pdf->writeHTML($list, true, false, true, false, '');
		}

		$pdf->Close();
		$pdf->Output($file, 'F');
		$object->last_main_doc = 'diffusionplans/'.$objectref.'/'.$objectref.'.pdf';
		$object->model_pdf = $this->name;

		return 1;
	}
}
