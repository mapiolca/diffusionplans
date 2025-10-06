<?php
/* Copyright (C) 2007-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2007-2015 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2012      Christophe Battarel  <christophe.battarel@altairis.fr>
 * Copyright (C) 2024      MDW                  <mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024      Frédéric France      <frederic.france@free.fr>
 * Copyright (C) 2025      Pierre Ardoin      <developpeur@lesmetiersdubatiment.fr>
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
 *  \file       htdocs/core/lib/ajax.lib.php
 *  \brief      Page called to enhance interface with Javascript and Ajax features.
 */



/**
 *  On/off button to change a property status of an object
 *  This uses the ajax service objectonoff.php (May be called when MAIN_DIRECT_STATUS_UPDATE is set for some pages)
 *
 *  @param  CommonObject  $object     Object to set
 *  @param  string  $code       Name of property in object : 'status' or 'status_buy' for product by example
 *  @param  string  $field      Name of database field : 'tosell' or 'tobuy' for product by example
 *  @param  string  $text_on    Text if on ('Text' or 'Text:Picto on:Css picto on')
 *  @param  string  $text_off   Text if off ('Text' or 'Text:Picto off:Css picto off')
 *  @param  array<string,string[]>   $input      Array of type->list of CSS element to switch. Example: array('disabled'=>array(0=>'cssid'))
 *  @param	string	$morecss	More CSS
 *  @param	string	$htmlname	Name of HTML component. Keep '' or use a different value if you need to use this component several time on the same page for the same field.
 *  @param	int		$forcenojs	Force the component to work as link post (without javascript) instead of ajax call
 *  @param	string	$moreparam	When $forcenojs=1 then we can add more parameters to the backtopage URL. String must url encoded. Example: 'abc=def&fgh=ijk'
 *  @return string              html for button on/off
 *  @see ajax_constantonoff() to update that value of a constant
 */
function ajax_diffusion_onoff($object, $code, $field, $text_on, $text_off, $input = array(), $morecss = '', $htmlname = '', $forcenojs = 0, $moreparam = '')
{
	global $conf, $langs;

	if (empty($htmlname)) {
		$htmlname = $code;
	}

	$out = '';

	if (!empty($conf->use_javascript_ajax) && empty($forcenojs)) {
		$out .= '<script>
        $(function() {
            var input = '.json_encode($input).';

            // Set constant
            $("#set_'.$htmlname.'_'.$object->id.'").click(function() {
				console.log("Click managed by ajax_diffusion_onoff");
                $.get( "'.DOL_URL_ROOT.'/custom/diffusionplans/ajax/objectonoff.php", {
                    action: \'set\',
                    field: \''.dol_escape_js($field).'\',
                    value: \'1\',
                    element: \''.dol_escape_js((empty($object->module) || $object->module == $object->element) ? $object->element : $object->element.'@'.$object->module).'\',
                    id: \''.((int) $object->id).'\',
					token: \''.currentToken().'\'
                },
                function() {
                    $("#set_'.$htmlname.'_'.$object->id.'").hide();
                    $("#del_'.$htmlname.'_'.$object->id.'").show();
                    // Enable another element
                    if (input.disabled && input.disabled.length > 0) {
                        $.each(input.disabled, function(key,value) {
                            $("#" + value).removeAttr("disabled");
                            if ($("#" + value).hasClass("butActionRefused") == true) {
                                $("#" + value).removeClass("butActionRefused");
                                $("#" + value).addClass("butAction");
                            }
                        });
                    // Show another element
                    } else if (input.showhide && input.showhide.length > 0) {
                        $.each(input.showhide, function(key,value) {
                            $("#" + value).show();
                        });
                    }
                });
            });

            // Del constant
            $("#del_'.$htmlname.'_'.$object->id.'").click(function() {
				console.log("Click managed by ajax_diffusion_onoff");
                $.get( "'.DOL_URL_ROOT.'/custom/diffusionplans/ajax/objectonoff.php", {
                    action: \'set\',
                    field: \''.dol_escape_js($field).'\',
                    value: \'0\',
                    element: \''.dol_escape_js((empty($object->module) || $object->module == $object->element) ? $object->element : $object->element.'@'.$object->module).'\',
                    id: \''.((int) $object->id).'\',
					token: \''.currentToken().'\'
                },
                function() {
                    $("#del_'.$htmlname.'_'.$object->id.'").hide();
                    $("#set_'.$htmlname.'_'.$object->id.'").show();
                    // Disable another element
                    if (input.disabled && input.disabled.length > 0) {
                        $.each(input.disabled, function(key,value) {
                            $("#" + value).prop("disabled", true);
                            if ($("#" + value).hasClass("butAction") == true) {
                                $("#" + value).removeClass("butAction");
                                $("#" + value).addClass("butActionRefused");
                            }
                        });
                    // Hide another element
                    } else if (input.showhide && input.showhide.length > 0) {
                        $.each(input.showhide, function(key,value) {
                            $("#" + value).hide();
                        });
                    }
                });
            });
        });
    </script>';
	}

	$switchon = 'switch_on';
	$switchoff = 'switch_off';
	$cssswitchon = '';
	$cssswitchoff = '';
	$tmparray = explode(':', $text_on);
	if (!empty($tmparray[1])) {
		$text_on = $tmparray[0];
		$switchon = $tmparray[1];
		if (!empty($tmparray[2])) {
			$cssswitchon = $tmparray[2];
		}
	}
	$tmparray = explode(':', $text_off);
	if (!empty($tmparray[1])) {
		$text_off = $tmparray[0];
		$switchoff = $tmparray[1];
		if (!empty($tmparray[2])) {
			$cssswitchoff = $tmparray[2];
		}
	}

	if (empty($conf->use_javascript_ajax) || $forcenojs) {
		$out .= '<a id="set_'.$htmlname.'_'.$object->id.'" class="linkobject '.($object->$code == 1 ? 'hideobject' : '').($morecss ? ' '.$morecss : '').'" href="'.DOL_URL_ROOT.'/custom/diffusionplans/ajax/objectonoff.php?action=set&token='.newToken().'&id='.((int) $object->id).'&element='.urlencode($object->element).'&field='.urlencode($field).'&value=1&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$object->diffusion.($moreparam ? '&'.$moreparam : '')).'">'.img_picto($langs->trans($text_off), $switchoff, '', 0, 0, 0, '', $cssswitchoff).'</a>';
		$out .= '<a id="del_'.$htmlname.'_'.$object->id.'" class="linkobject '.($object->$code == 1 ? '' : 'hideobject').($morecss ? ' '.$morecss : '').'" href="'.DOL_URL_ROOT.'/custom/diffusionplans/ajax/objectonoff.php?action=set&token='.newToken().'&id='.((int) $object->id).'&element='.urlencode($object->element).'&field='.urlencode($field).'&value=0&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$object->diffusion.($moreparam ? '&'.$moreparam : '')).'">'.img_picto($langs->trans($text_on), $switchon, '', 0, 0, 0, '', $cssswitchon).'</a>';
	} else {
		$out .= '<span id="set_'.$htmlname.'_'.$object->id.'" class="linkobject '.($object->$code == 1 ? 'hideobject' : '').($morecss ? ' '.$morecss : '').'">'.img_picto($langs->trans($text_off), $switchoff, '', 0, 0, 0, '', $cssswitchoff).'</span>';
		$out .= '<span id="del_'.$htmlname.'_'.$object->id.'" class="linkobject '.($object->$code == 1 ? '' : 'hideobject').($morecss ? ' '.$morecss : '').'">'.img_picto($langs->trans($text_on), $switchon, '', 0, 0, 0, '', $cssswitchon).'</span>';
	}

	return $out;
}