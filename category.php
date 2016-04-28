<?php
// This file is part of Exabis Eportfolio
//
// (c) 2016 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Eportfolio is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

require_once __DIR__.'/inc.php';
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login($courseid);

block_exaport_setup_default_categories();

$url = '/blocks/exaport/category.php';
$PAGE->set_url($url, ['courseid' => $courseid]);

// Get userlist for sharing category
if (optional_param('action', '', PARAM_ALPHA) == 'userlist' || optional_param('action', '', PARAM_ALPHA) == 'structureuserlist') {
	require_once __DIR__.'/lib/sharelib.php';
	echo json_encode(exaport_get_shareable_courses_with_users(''));
	exit;
}
// Get grouplist for sharing category
if (optional_param('action', '', PARAM_ALPHA) == 'grouplist' || optional_param('action', '', PARAM_ALPHA) == 'structuregrouplist') {
	require_once __DIR__.'/lib/sharelib.php';
	echo json_encode(exaport_get_shareable_courses_with_groups(''));
	exit;
}

if (optional_param('action', '', PARAM_ALPHA) == 'addstdcat') {
	block_exaport_import_categories('lang_categories');
	redirect('view_items.php?courseid='.$courseid);
}
if (optional_param('action', '', PARAM_ALPHA) == 'movetocategory') {
	confirm_sesskey();

	$category = $DB->get_record("block_exaportcate", array(
		'id' => required_param('id', PARAM_INT),
		'userid' => $USER->id
	));
	if (!$category) {
		die(block_exaport_get_string('category_not_found'));
	}

	if (!$targetCategory = block_exaport_get_category(required_param('categoryid', PARAM_INT))) {
		die('target category not found');
	}
	
	$DB->update_record('block_exaportcate', (object)array(
		'id' => $category->id,
		'pid' => $targetCategory->id
	));

	echo 'ok';
	exit;
}

if (optional_param('action', '', PARAM_ALPHA) == 'delete') {
	$id = required_param('id', PARAM_INT);
	
	$category = $DB->get_record("block_exaportcate", array(
		'id' => $id,
		'userid' => $USER->id
	));
	if (!$category) die(block_exaport_get_string('category_not_found'));
	
	if (optional_param('confirm', 0, PARAM_INT)) {
		confirm_sesskey();
		
		function block_exaport_recursive_delete_category($id) {
			global $DB;

			// delete subcategories
			if ($entries = $DB->get_records('block_exaportcate', array("pid" => $id))) {
				foreach ($entries as $entry) {
					block_exaport_recursive_delete_category($entry->id);
				}
			}
			$DB->delete_records('block_exaportcate', array('pid'=>$id));

			// delete itemsharing
			if ($entries = $DB->get_records('block_exaportitem', array("categoryid" => $id))) {
				foreach ($entries as $entry) {
					$DB->delete_records('block_exaportitemshar', array('itemid'=>$entry->id));
				}
			}
			
			// delete items
			$DB->delete_records('block_exaportitem', array('categoryid'=>$id));
		}
		block_exaport_recursive_delete_category($category->id);
		
		if (!$DB->delete_records('block_exaportcate', array('id'=>$category->id)))
		{
			$message = "Could not delete your record";
		}
		else
		{
			
			block_exaport_add_to_log($courseid, "bookmark", "delete category", "", $category->id);
			
			redirect('view_items.php?courseid='.$courseid.'&categoryid='.$category->pid);
		}
	}

	$optionsyes = array('action'=>'delete', 'courseid' => $courseid, 'confirm'=>1, 'sesskey'=>sesskey(), 'id'=>$id);
	$optionsno = array(
		'courseid'=>$courseid, 
		'categoryid' => optional_param('back', '', PARAM_TEXT)=='same' ? $category->id : $category->pid
	);
	
	$strbookmarks = get_string("myportfolio", "block_exaport");
	$strcat = get_string("categories", "block_exaport");

	block_exaport_print_header("myportfolio");
	
	echo '<br />';
	echo $OUTPUT->confirm(get_string("deletecategoryconfirm", "block_exaport", $category), new moodle_url('category.php', $optionsyes), new moodle_url('view_items.php', $optionsno));
	echo block_exaport_wrapperdivend();
	$OUTPUT->footer();

	exit;
}


require_once("$CFG->libdir/formslib.php");

class simplehtml_form extends moodleform {
	//Add elements to form
	public function definition() {
		global $CFG;
		global $DB;
		global $USER;

		$id = optional_param('id', 0, PARAM_INT);
		$category = $DB->get_record_sql('
			SELECT c.id, c.name, c.pid, c.internshare, c.shareall, c.structure_share, c.structure_shareall
			FROM {block_exaportcate} c
			WHERE c.userid = ? AND id = ?
			', array($USER->id, $id));
		if (!$category) {	
			$category = new stdClass;
			$category->shareall = 0;
			$category->structure_shareall = 0;
			$category->id = 0;
		};
		
		$mform = $this->_form; // Don't forget the underscore! 
 
		$mform->addElement('hidden', 'id');
		$mform->setType('id', PARAM_INT);
		$mform->addElement('hidden', 'pid');
		$mform->setType('pid', PARAM_INT);
		$mform->addElement('hidden', 'courseid');
		$mform->setType('courseid', PARAM_INT);
		$mform->addElement('hidden', 'back');
		$mform->setType('back', PARAM_TEXT);

		$mform->addElement('text', 'name', get_string('name'));
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', block_exaport_get_string('titlenotemtpy'), 'required', null, 'client');
		
		$mform->addElement('filemanager', 'iconfile', get_string('iconfile', 'block_exaport'), null, array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size, 'accepted_types' => array('image', 'web_image')));

		if (extension_loaded('gd') && function_exists('gd_info')) {
			$mform->addElement('checkbox', 'iconmerge', get_string('iconfile_merge', 'block_exaport'), get_string('iconfile_merge_description', 'block_exaport'));
		};
		
		// sharing
		if (has_capability('block/exaport:shareintern', context_system::instance())) {
			$mform->addElement('checkbox', 'internshare', get_string('share', 'block_exaport'));
			$mform->setType('internshare', PARAM_INT);
			$mform->addElement('html', '<div id="internaccess-settings" class="fitem""><div class="fitemtitle"></div><div class="felement">');
			
			$mform->addElement('html', '<div style="padding: 4px 0;"><table width=100%>');
			// share to all
			if (block_exaport_shareall_enabled()) {
				$mform->addElement('html', '<tr><td>');
				$mform->addElement('html', '<input type="radio" name="shareall" value="1"'.($category->shareall==1 ? ' checked="checked"' : '').'/>');
				$mform->addElement('html', '</td><td>'.get_string('internalaccessall', 'block_exaport').'</td></tr>');
				$mform->setType('shareall', PARAM_INT);
				$mform->addElement('html', '</td></tr>');
			}

			// share to users
			$mform->addElement('html', '<tr><td>');
			$mform->addElement('html', '<input type="radio" name="shareall" value="0"'.(!$category->shareall ? ' checked="checked"' : '').'/>');
			$mform->addElement('html', '</td><td>'.get_string('internalaccessusers', 'block_exaport').'</td></tr>');
			$mform->addElement('html', '</td></tr>');
			if ($category->id > 0) {
				$sharedUsers = $DB->get_records_menu('block_exaportcatshar', array("catid" => $category->id), null, 'userid, userid AS tmp');
				$mform->addElement('html', '<script> var sharedusersarr = [];');
				foreach($sharedUsers as $i => $user)
					$mform->addElement('html', 'sharedusersarr['.$i.'] = '.$user.';');
				$mform->addElement('html', '</script>');
			}
			$mform->addElement('html', '<tr id="internaccess-users"><td></td><td><div id="sharing-userlist">userlist</div></td></tr>');

			// share to groups
			$mform->addElement('html', '<tr><td>');
			$mform->addElement('html', '<input type="radio" name="shareall" value="2"'.($category->shareall==2 ? ' checked="checked"' : '').'/>');
			$mform->addElement('html', '</td><td>'.get_string('internalaccessgroups', 'block_exaport').'</td></tr>');
			$mform->addElement('html', '</td></tr>');
			if ($category->id > 0) {
				$sharedUsers = $DB->get_records_menu('block_exaportcatgroupshar', array("catid" => $category->id), null, 'groupid, groupid AS tmp');
				$mform->addElement('html', '<script> var sharedgroupsarr = [];');
				foreach($sharedUsers as $i => $user)
					$mform->addElement('html', 'sharedgroupsarr['.$i.'] = '.$user.';');
				$mform->addElement('html', '</script>');
			}/**/
			$mform->addElement('html', '<tr id="internaccess-groups"><td></td><td><div id="sharing-grouplist">grouplist</div></td></tr>');			
			$mform->addElement('html', '</table></div>');
			$mform->addElement('html', '</div></div>');
		};
		
		// sharing as a structure
		/*
		if (1) {
			$mform->addElement('checkbox', 'structure_share', get_string('share_structure', 'block_exaport'), get_string('share_structure_description', 'block_exaport'));
			$mform->setType('structure_share', PARAM_INT);
			$mform->addElement('html', '<div id="structureshare-settings" class="fitem""><div class="fitemtitle"></div><div class="felement">');
			
			$mform->addElement('html', '<div style="padding: 4px 0;"><table width=100%>');
			// share to all
			if (block_exaport_shareall_enabled()) {
				$mform->addElement('html', '<tr><td>');
				$mform->addElement('html', '<input type="radio" name="structure_shareall" value="1"'.($category->structure_shareall==1 ? ' checked="checked"' : '').'/>');
				$mform->addElement('html', '</td><td>'.get_string('internalaccessall', 'block_exaport').'</td></tr>');
				$mform->setType('shareall', PARAM_INT);
				$mform->addElement('html', '</td></tr>');
			}
			// share to users
			$mform->addElement('html', '<tr><td>');
			$mform->addElement('html', '<input type="radio" name="structure_shareall" value="0"'.(!$category->structure_shareall ? ' checked="checked"' : '').'/>');
			$mform->addElement('html', '</td><td>'.get_string('internalaccessusers', 'block_exaport').'</td></tr>');
			$mform->addElement('html', '</td></tr>');
			if ($category->id > 0) {
				$sharedUsers = $DB->get_records_menu('block_exaportcat_structshar', array("catid" => $category->id), null, 'userid, userid AS tmp');
				$mform->addElement('html', '<script> var structure_sharedusersarr = [];');
				foreach($sharedUsers as $i => $user)
					$mform->addElement('html', 'structure_sharedusersarr['.$i.'] = '.$user.';');
				$mform->addElement('html', '</script>');
			}
			$mform->addElement('html', '<tr id="structure_sharing-users"><td></td><td><div id="structure_sharing-userlist">userlist</div></td></tr>');
			// share to groups
			$mform->addElement('html', '<tr><td>');
			$mform->addElement('html', '<input type="radio" name="structure_shareall" value="2"'.($category->structure_shareall==2 ? ' checked="checked"' : '').'/>');
			$mform->addElement('html', '</td><td>'.get_string('internalaccessgroups', 'block_exaport').'</td></tr>');
			$mform->addElement('html', '</td></tr>');
			if ($category->id > 0) {
				$sharedUsers = $DB->get_records_menu('block_exaportcat_strgrshar', array("catid" => $category->id), null, 'groupid, groupid AS tmp');
				$mform->addElement('html', '<script> var structure_sharedgroupsarr = [];');
				foreach($sharedUsers as $i => $user)
					$mform->addElement('html', 'structure_sharedgroupsarr['.$i.'] = '.$user.';');
				$mform->addElement('html', '</script>');
			}
			$mform->addElement('html', '<tr id="structure_sharing-groups"><td></td><td><div id="structure_sharing-grouplist">grouplist</div></td></tr>');			
			
			$mform->addElement('html', '</table></div>');
			$mform->addElement('html', '</div></div>');
		};
		*/

		$this->add_action_buttons();
	}
	//Custom validation should be added here
	function validation($data, $files) {
		return array();
	}
}

//Instantiate simplehtml_form 
$mform = new simplehtml_form();

//Form processing and displaying is done here
if ($mform->is_cancelled()) {
	redirect('view_items.php?courseid='.$courseid.'&categoryid='.
		(optional_param('back', '', PARAM_TEXT)=='same' ? optional_param('id', 0, PARAM_INT) : optional_param('pid', 0, PARAM_INT)));
} else if ($newEntry = $mform->get_data()) {
	$newEntry->userid = $USER->id;
	$newEntry->shareall = optional_param('shareall', 0, PARAM_INT);
	if (optional_param('internshare', 0, PARAM_INT) > 0) {
		$newEntry->internshare = optional_param('internshare', 0, PARAM_INT);
	} else {
		$newEntry->internshare = 0;
	}
	// structure share
	$newEntry->structure_shareall = optional_param('structure_shareall', 0, PARAM_INT);
	if (optional_param('structure_share', 0, PARAM_INT) > 0) {
		$newEntry->structure_share = optional_param('structure_share', 0, PARAM_INT);
	} else {
		$newEntry->structure_share = 0;
		$newEntry->structure_shareall = 0;
	}

	if ($newEntry->id) {
		$DB->update_record("block_exaportcate", $newEntry);
	} else {
		$newEntry->id = $DB->insert_record("block_exaportcate", $newEntry);
	}
	// SHARE
	// Share to users.
	if (!empty($_POST["shareusers"])){
		$shareusers = $_POST["shareusers"];
		if (function_exists("clean_param_array")) 
			$shareusers=clean_param_array($shareusers,PARAM_SEQUENCE,false);
	} else {
		$shareusers = "";
	}	
	// delete all shared users
	$DB->delete_records("block_exaportcatshar", array('catid' => $newEntry->id));
	// add new shared users
	if ($newEntry->internshare && !$newEntry->shareall && is_array($shareusers)) {
		foreach ($shareusers as $shareuser) {
			$shareuser = clean_param($shareuser, PARAM_INT);
			$shareItem = new stdClass();
			$shareItem->catid = $newEntry->id;
			$shareItem->userid = $shareuser;
			$DB->insert_record("block_exaportcatshar", $shareItem);
		};
	};
	// Share to groups.
	if (!empty($_POST["sharegroups"])){
		$sharegroups = $_POST["sharegroups"];
		if (function_exists("clean_param_array")) 
			$sharegroups=clean_param_array($sharegroups,PARAM_SEQUENCE,false);
	} else {
		$sharegroups = "";
	}	
	// delete all shared users
	$DB->delete_records("block_exaportcatgroupshar", array('catid' => $newEntry->id));
	// add new shared groups
	if ($newEntry->internshare && $newEntry->shareall==2 && is_array($sharegroups)) {
		foreach ($sharegroups as $sharegroup) {
			$sharegroup = clean_param($sharegroup, PARAM_INT);
			$shareItem = new stdClass();
			$shareItem->catid = $newEntry->id;
			$shareItem->groupid = $sharegroup;
			$DB->insert_record("block_exaportcatgroupshar", $shareItem);
		};
	};
	
	// Structure SHARE
	if (!empty($_POST["structure_shareusers"])){
		$structure_shareusers = $_POST["structure_shareusers"];
		if (function_exists("clean_param_array")) 
			$structure_shareusers=clean_param_array($structure_shareusers,PARAM_SEQUENCE,false);
	} else {
		$structure_shareusers = "";
	}	
	// delete all shared users
	$DB->delete_records("block_exaportcat_structshar", array('catid' => $newEntry->id));
	// add new shared users
	if ($newEntry->structure_share && !$newEntry->structure_shareall && is_array($structure_shareusers)) {
		foreach ($structure_shareusers as $shareuser) {
			$shareuser = clean_param($shareuser, PARAM_INT);
			$shareItem = new stdClass();
			$shareItem->catid = $newEntry->id;
			$shareItem->userid = $shareuser;
			$DB->insert_record("block_exaportcat_structshar", $shareItem);
		};
	};
 	// Share to groups.
	if (!empty($_POST["structure_sharegroups"])){
		$structure_sharegroups = $_POST["structure_sharegroups"];
		if (function_exists("clean_param_array")) 
			$structure_sharegroups=clean_param_array($structure_sharegroups,PARAM_SEQUENCE,false);
	} else {
		$structure_sharegroups = "";
	}	
	//  delete all shared groups
	$DB->delete_records("block_exaportcat_strgrshar", array('catid' => $newEntry->id));
	// add new shared groups
	if ($newEntry->structure_share && $newEntry->structure_shareall==2 && is_array($structure_sharegroups)) {
		foreach ($structure_sharegroups as $sharegroup) {
			$sharegroup = clean_param($sharegroup, PARAM_INT);
			$shareItem = new stdClass();
			$shareItem->catid = $newEntry->id;
			$shareItem->groupid = $sharegroup;
			$DB->insert_record("block_exaportcat_strgrshar", $shareItem);
		};
	}; 
	
	// icon for item
	$context = context_user::instance($USER->id);
	$upload_filesizes = block_exaport_get_filesize_by_draftid($newEntry->iconfile);
	// merge with folder icon
	if (isset($newEntry->iconmerge) && $newEntry->iconmerge == 1 && $upload_filesizes > 0) {
		$fs = get_file_storage();
		$image = $DB->get_record_sql('SELECT * FROM {files} WHERE contextid = ? AND component = "user" AND filearea="draft" AND itemid = ? AND filename<>"."', array($context->id, $newEntry->iconfile));
		if ($image) {
			$fileimage = $fs->get_file($context->id, 'user', 'draft', $newEntry->iconfile, '/', $image->filename);
			//$image->mimetype
			$imagecontent = $fileimage->get_content();
			// merge images
			$im_icon = imagecreatefromstring($imagecontent);
			$im_folder = imagecreatefrompng($CFG->dirroot.'/blocks/exaport/pix/folder_tile.png');
			
			imagealphablending($im_folder, false);
			imagesavealpha($im_folder, true);
			
			// max width/height
			$max_width = 150;
			$max_height = 80;
			$skew = 10;
			$im_icon = skewScaleImage($im_icon, $max_width, $max_height, $skew);
			// imagealphablending($im_icon, false);
			// imagesavealpha($im_icon, true);
			
			// imagecopymerge($im_folder, $im_icon, 10, 9, 0, 0, 181, 180, 100);
			$swidth = imagesx($im_folder);
			$sheight = imagesy($im_folder);  
			$owidth = imagesx($im_icon);			
			$oheight = imagesy($im_icon);
			$x = 0;
			$y = 0;
			$opacity = 75; // overlay's opacity (in percent)

			// coordinates - only for current folder icon.
			imagecopymerge($im_folder, $im_icon, $swidth/2 - $owidth/2, $sheight/2 - $oheight/2 + 10, 0, 0, $owidth, $oheight, $opacity);

			ob_start();
			imagepng($im_folder);
			$image_data = ob_get_contents();
			ob_end_clean();
			
			// for testing
			// header("Content-Type: image/png");
			// imagepng($im_folder);
			// exit;
			
			// simple checking to PNG
			if (stripos($image_data, 'png') == 1) {
				// delete old file			
				$fileimage->delete();
				// Create file containing new image
				$fileinfo = array(
					'contextid' => $context->id, 
					'component' => 'user',	
					'filearea' => 'draft',	 
					'itemid' => $image->itemid,  
					'filepath' => '/',		   
					'filename' => $image->filename);
				$fs->create_file_from_string($fileinfo, $image_data);
			};
			imagedestroy($im_icon);
			imagedestroy($im_folder);
		};
	};
	unset($newEntry->iconmerge);
	// checking userquoata
	if (block_exaport_file_userquotecheck($upload_filesizes, $newEntry->id) && block_exaport_get_maxfilesize_by_draftid_check($newEntry->iconfile)) {
		file_save_draft_area_files($newEntry->iconfile, $context->id, 'block_exaport', 'category_icon', $newEntry->id, array('maxbytes' => $CFG->block_exaport_max_uploadfile_size));
	};
	
	redirect('view_items.php?courseid='.$courseid.'&categoryid='.
		($newEntry->back=='same' ? $newEntry->id : $newEntry->pid));
} else {
	block_exaport_print_header("myportfolio");
	
	$category = null;
	if ($id = optional_param('id', 0, PARAM_INT)) {
		$category = $DB->get_record_sql('
			SELECT c.id, c.name, c.pid, c.internshare, c.shareall, c.structure_share, c.structure_shareall
			FROM {block_exaportcate} c
			WHERE c.userid = ? AND id = ?
		', array($USER->id, $id));
	}
	if (!$category) $category = new stdClass;
	
	$category->courseid = $courseid;
	if (!isset($category->id))
		$category->id = null;
	$category->back = optional_param('back', '', PARAM_TEXT);
	if (empty($category->pid)) $category->pid = optional_param('pid', 0, PARAM_INT);
	
	// Filemanager for editing icon picture 
	$draftitemid = file_get_submitted_draft_itemid('iconfile');
	$context = context_user::instance($USER->id);
	file_prepare_draft_area($draftitemid, $context->id, 'block_exaport', 'category_icon', $category->id,
							array('subdirs' => false, 'maxfiles' => 1, 'maxbytes' => $CFG->block_exaport_max_uploadfile_size));				 
	$category->iconfile = $draftitemid;   

	$mform->set_data($category);
	$mform->display();
  echo block_exaport_wrapperdivend();
  
$PAGE->requires->js('/blocks/exaport/javascript/category.js', true);

// Translations
$translations = array(
	'name', 'role', 'nousersfound',
	'internalaccessgroups', 'grouptitle', 'membersnumber', 'nogroupsfound', 
	'internalaccess', 'externalaccess', 'internalaccessall', 'internalaccessusers', 'view_sharing_noaccess', 'sharejs', 'notify',
	'checkall',
);

$translations = array_flip($translations);
foreach ($translations as $key => &$value) {
	$value = block_exaport_get_string($key);
}
unset($value);
?>
<script type="text/javascript">
//<![CDATA[
	ExabisEportfolio.setTranslations(<?php echo json_encode($translations); ?>);
//]]>
</script>
<?php /**/

	echo $OUTPUT->footer();	
	
}

function skewScaleImage($src_img, $max_width = 100, $max_height = 100, $skew = 10) {
	$w = imagesx($src_img); 
	$h = imagesy($src_img); 
	// Scale
	if ($h > $max_height) {
		$koeff = $h / $max_height;
		$new_width = $w / $koeff;
		$src_img = imagescale($src_img, $new_width, $max_height);
		$h = $max_height;
		$w = imagesx($src_img);
	}
	if ($w > $max_width) {
		$src_img = imagescale($src_img, $max_width);
		$w = $max_width;
		$h = imagesy($src_img);
	}
	// Skew it
	// $skew = 10; // deg of skewing
	$new_w = abs($h * tan(deg2rad($skew)) + $w); 
	$step = tan(deg2rad($skew));
	// $step = abs($h / $w / $koeff);
	// $step = abs((($new_w - $w) / ($koeff * $h * 100 / $max_height )));
	$dst_img = imagecreatetruecolor($new_w, $h);
	$bg_colour = imagecolorallocate($dst_img, 0, 0, 0); 
	imagecolortransparent($dst_img, $bg_colour);
	imagefill($dst_img, 0, 0, $bg_colour);

	for ($i = 0 ; $i < $h ; $i ++)
	{
		imagecopyresampled($dst_img, $src_img, $new_w - ($w + $step*$i), $i, 0, $i, $w, 1, $w, 1); 
	}

	return $dst_img; 	
}
