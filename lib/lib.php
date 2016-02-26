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

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/filelib.php';

if (block_exaport_check_competence_interaction()){
	// TODO: don't use any of the exacomp functions, use \block_exacomp\api::method() instead!
	if(file_exists($CFG->dirroot . '/blocks/exacomp/lib/lib.php'))
		require_once $CFG->dirroot . '/blocks/exacomp/lib/lib.php';
	else
		require_once $CFG->dirroot . '/blocks/exacomp/lib/div.php';
}

require_once __DIR__.'/common.php';

use block_exaport\globals as g;

/*** FILE FUNCTIONS **********************************************************************/

function block_exaport_get_item_file($item) {
	$fs = get_file_storage();
	
	// list all files, excluding directories!
	$areafiles = $fs->get_area_files(context_user::instance($item->userid)->id, 'block_exaport', 'item_file', $item->id, 'itemid', false);
	
	// file found?
	if (empty($areafiles))
		return null;
	else
		// return first file (there should be only one file anyway)
		return reset($areafiles);
}

/**
 * @param $itemcomment
 * @return stored_file
 * @throws dml_exception
 */
function block_exaport_get_item_comment_file($commentid) {
	$fs = get_file_storage();

	// list all files, excluding directories!
	$areafiles = $fs->get_area_files(context_system::instance()->id, 'block_exaport', 'item_comment_file', $commentid, null, false);

	if (empty($areafiles))
		return null;
	else
		return reset($areafiles);
}

function block_exaport_add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0) {
	if (!function_exists('get_log_manager')) {
		// old style
		return add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0);
	}
	
	// hack for new style
	
	// This is a nasty hack that allows us to put all the legacy stuff into legacy storage,
	// this way we may move all the legacy settings there too.
	$manager = get_log_manager();
	if (method_exists($manager, 'legacy_add_to_log')) {
		$manager->legacy_add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
	}
}

function block_exaport_file_remove($item) {
	$fs = get_file_storage();
	// associated file (if it's a file item)
	$fs->delete_area_files(context_user::instance($item->userid)->id, 'block_exaport', 'item_file', $item->id);
	// item content (intro) inside the html editor
	$fs->delete_area_files(context_user::instance($item->userid)->id, 'block_exaport', 'item_content', $item->id);
}

/*** GENERAL FUNCTIONS **********************************************************************/

function block_exaport_require_login($courseid) {
	global $CFG;

	require_login($courseid);
	require_capability('block/exaport:use', context_system::instance());

	if (empty($CFG->block_exaport_allow_loginas)) {
		// login as not allowed => check
		global $USER;
		if (!empty($USER->realuser)) {
			print_error("loginasmode", "block_exaport");
		}
	}
}

function block_exaport_shareall_enabled() {
	global $CFG;
	return empty($CFG->block_exaport_disable_shareall);
}

function block_exaport_external_comments_enabled() {
	global $CFG;
	return empty($CFG->block_exaport_disable_external_comments);
}

function block_exaport_setup_default_categories() {
	global $DB, $USER,$CFG;
	if (block_exaport_course_has_desp() && !$DB->record_exists('block_exaportcate', array('userid'=>$USER->id))
		&& !empty($CFG->block_exaport_create_desp_categories)) {
		block_exaport_import_categories("desp_categories");
	}
}
function block_exaport_import_categories($categoriesSTR){
	global $DB, $USER;
	$categories = trim(get_string($categoriesSTR, "block_exaport"));
	
	if (!$categories) return;
	
	$categories = explode("\n", $categories);
	$categories = array_map('trim', $categories);
	
	$newentry = new stdClass();
	$newentry->timemodified = time();
	$newentry->userid = $USER->id;
	$newentry->pid = 0;
	
	$lastMainId = null;
	foreach ($categories as $category) {
		
		if ($category[0] == '-' && $lastMainId) {
			// subcategory
			$newentry->name = trim($category, '-');
			$newentry->pid = $lastMainId;
			//$categoryDB = $DB->get_records('block_exaportcate', array("name"=>trim($category,'-')));
			if(!$DB->record_exists('block_exaportcate', array("name"=>trim($category,'-'))))
				$DB->insert_record("block_exaportcate", $newentry);
		} else {
			$newentry->name = $category;
			$newentry->pid = 0;
			//$categoryDB = $DB->get_records('block_exaportcate', array("name"=>$category));
			if(!$DB->record_exists('block_exaportcate', array("name"=>$category)))
				$lastMainId = $DB->insert_record("block_exaportcate", $newentry);
			else {
				$lastMainId = $DB->get_field('block_exaportcate', 'id', array("name"=>$category));
			}
		}
	}
}
function block_exaport_feature_enabled($feature) {
	global $CFG;
	if ($feature == 'views')
		return true;
	if ($feature == 'copy_to_course')
		return !empty($CFG->block_exaport_feature_copy_to_course);
	die('wrong feature');
}

/*** OTHER FUNCTIONS **********************************************************************/

function block_exaport_has_categories($userid) {
	global $CFG, $DB;
	if ($DB->count_records_sql("SELECT COUNT(*) FROM {block_exaportcate} WHERE userid=? AND pid=0", array($userid)) > 0) {
		return true;
	} else {
		return false;
	}
}

function block_exaport_moodleimport_file_area_name($userid, $assignmentid, $courseid) {
	global $CFG;

	return $courseid . '/' . $CFG->moddata . '/assignment/' . $assignmentid . '/' . $userid;
}

function block_exaport_print_file(stored_file $file) {
	global $CFG, $OUTPUT;

	$url = moodle_url::make_pluginfile_url ( $file->get_contextid (), $file->get_component (), $file->get_filearea (), $file->get_itemid (), $file->get_filepath (), $file->get_filename () );

	$icon = new pix_icon(file_mimetype_icon($file->get_mimetype()), '');
	if (in_array($file->get_mimetype(), array('image/gif', 'image/jpeg', 'image/png'))) {	// Image attachments don't get printed as links
		return "<img src=\"$url\" alt=\"" . s($file->get_filename()) . "\" />";
	} else {
		return '<p><img src="' . $CFG->wwwroot . '/pix/' . $icon->pix . '.gif" class="icon" alt="' . $icon->pix . '" />&nbsp;' . $OUTPUT->action_link($url, $filename) . "</p>";
	}
}

function block_exaport_course_has_desp() {
	global $COURSE, $DB;
	
	if (isset($COURSE->has_desp))
		return $COURSE->has_desp;
	
	// desp block installed?
	if (!is_dir(__DIR__.'/../../desp'))
		return $COURSE->has_desp = false;

	$context = context_course::instance($COURSE->id);
	
	return $COURSE->has_desp = $DB->record_exists('block_instances', array('blockname'=>'desp', 'parentcontextid'=>$context->id));
}
function block_exaport_wrapperdivstart(){
	return html_writer::start_tag('div',array('id'=>'exaport'));
}
function block_exaport_wrapperdivend(){
	return html_writer::end_tag('div');
}

function block_exaport_init_js_css() {
	global $PAGE, $CFG;

	// only allowed to be called once
	static $js_inited = false;
	if ($js_inited) return;
	$js_inited = true;

	// $PAGE->requires->css('/blocks/exaport/css/jquery-ui.css');

	$PAGE->requires->jquery();
	$PAGE->requires->jquery_plugin('ui');
	$PAGE->requires->jquery_plugin('ui-css');

	$PAGE->requires->js('/blocks/exaport/javascript/jquery.json.js', true);

	$PAGE->requires->js('/blocks/exaport/javascript/exaport.js', true);

	$scriptName = preg_replace('!\.[^\.]+$!', '', basename($_SERVER['PHP_SELF']));
	if (file_exists($CFG->dirroot.'/blocks/exaport/css/'.$scriptName.'.css'))
		$PAGE->requires->css('/blocks/exaport/css/'.$scriptName.'.css');
	if (file_exists($CFG->dirroot.'/blocks/exaport/javascript/'.$scriptName.'.js'))
		$PAGE->requires->js('/blocks/exaport/javascript/'.$scriptName.'.js', true);

}

/**
 * Print moodle header
 * @param string $item_identifier translation-id for this page
 * @param string $sub_item_identifier translation-id for second level if needed
 */
function block_exaport_print_header($item_identifier, $sub_item_identifier = null) {

	if (!is_string($item_identifier)) {
		echo 'noch nicht unterst�tzt';
	}

	global $CFG, $COURSE, $PAGE, $USER;

	block_exaport_init_js_css();

	$strbookmarks = block_exaport_get_string("mybookmarks");

	// navigationspfad
	$navlinks = array();
	$navlinks[] = array('name' => $strbookmarks, 'link' => "view.php?courseid=" . $COURSE->id, 'type' => 'title');
	$nav_item_identifier = $item_identifier;

	$icon = $item_identifier;
	$currenttab = $item_identifier;

	// haupttabs
	$tabs = array();

	if (block_exaport_course_has_desp()) {
		$tabs[] = new tabobject('back', $CFG->wwwroot . '/blocks/desp/index.php?courseid=' . $COURSE->id, get_string("back_to_desp", "block_exaport"), '', true);
	}

	$tabs[] = new tabobject('personal', $CFG->wwwroot . '/blocks/exaport/view.php?courseid=' . $COURSE->id, get_string("personal", "block_exaport"), '', true);
	// $tabs[] = new tabobject('categories', $CFG->wwwroot . '/blocks/exaport/view_categories.php?courseid=' . $COURSE->id, get_string("categories", "block_exaport"), '', true);
	$tabs[] = new tabobject('bookmarks', $CFG->wwwroot . '/blocks/exaport/view_items.php?courseid=' . $COURSE->id, block_exaport_get_string("bookmarks"), '', true);
	$tabs[] = new tabobject('views', $CFG->wwwroot . '/blocks/exaport/views_list.php?courseid=' . $COURSE->id, get_string("views", "block_exaport"), '', true);
	$tabs[] = new tabobject('exportimport', $CFG->wwwroot . '/blocks/exaport/exportimport.php?courseid=' . $COURSE->id, get_string("exportimport", "block_exaport"), '', true);
	$tabs[] = new tabobject('sharedbookmarks', $CFG->wwwroot . '/blocks/exaport/shared_views.php?courseid=' . $COURSE->id, block_exaport_get_string("sharedbookmarks"), '', true);
	if (has_sharablestructure($USER->id))
		$tabs[] = new tabobject('sharedstructures', $CFG->wwwroot . '/blocks/exaport/shared_structures.php?courseid=' . $COURSE->id, block_exaport_get_string("sharedstructures"), '', true);

	// tabs f�r das untermen�
	$tabs_sub = array();
	// ausgew�hlte tabs f�r untermen�s
	$activetabsubs = Array();

	if (strpos($item_identifier, 'views') === 0) {
		$id = optional_param('id', 0, PARAM_INT);
		if ($id>0) {
			$activetabsubs[] = $sub_item_identifier;
			
			$tabs_sub[] = new tabobject('title', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=title&action=edit'),get_string("viewtitle", "block_exaport"), '', true);		
			$tabs_sub[] = new tabobject('layout', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=layout&action=edit'),get_string("viewlayout", "block_exaport"), '', true);
			$tabs_sub[] = new tabobject('content', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&action=edit'),get_string("viewcontent", "block_exaport"), '', true);
			if (has_capability('block/exaport:shareextern', context_system::instance()) || has_capability('block/exaport:shareintern', context_system::instance())) {
				$tabs_sub[] = new tabobject('share', s($CFG->wwwroot . '/blocks/exaport/views_mod.php?courseid=' . $COURSE->id.'&id='.$id.'&sesskey='.sesskey().'&type=share&action=edit'),get_string("viewshare", "block_exaport"), '', true);			
			}
		}
	} elseif (strpos($item_identifier, 'personal') === 0) {
		$activetabsubs[] = $sub_item_identifier;
		$tabs_sub[] = new tabobject('personalinfo', $CFG->wwwroot . '/blocks/exaport/view.php?courseid=' . $COURSE->id, get_string("explainpersonal", "block_exaport"), '', true);
		$tabs_sub[] = new tabobject('resume', s($CFG->wwwroot . '/blocks/exaport/resume.php?courseid=' . $COURSE->id), get_string("resume", "block_exaport"), '', true);
	} elseif (strpos($item_identifier, 'bookmarks') === 0) {
		$activetabsubs[] = $item_identifier;
		$currenttab = 'bookmarks';
	} elseif (strpos($item_identifier, 'exportimport') === 0) {
		$currenttab = 'exportimport';

		// unterpunkt?
		if ($tmp = substr($item_identifier, strlen($currenttab))) {
			$nav_item_identifier = $tmp;
		}

		if (strpos($nav_item_identifier, 'export') !== false)
			$icon = 'export';
		else
			$icon = 'import';
	}


	$item_name = get_string($nav_item_identifier, "block_exaport");
	if ($item_name[0] == '[')
		$item_name = get_string($nav_item_identifier);
	$navlinks[] = array('name' => $item_name, 'link' => null, 'type' => 'misc');

	//$navigation = build_navigation($navlinks);
	foreach($navlinks as $navlink){
		$PAGE->navbar->add($navlink["name"], $navlink["link"]);
	}
	
	$PAGE->set_title($item_name);
   	$PAGE->set_heading(get_string(block_exaport_course_has_desp()?"desp_pluginname":'pluginname', "block_exaport"));
 
	 // header
	global $OUTPUT;
	
  	echo $OUTPUT->header();
	echo block_exaport_wrapperdivstart();
	if (block_exaport_course_has_desp()) {
		// include the desp css
		echo '<link href="'.$CFG->wwwroot.'/blocks/desp/styles.css" rel="stylesheet" type="text/css" />';
	}

	$OUTPUT->heading("<img src=\"{$CFG->wwwroot}/blocks/exaport/pix/" . $icon . ".png\" width=\"16\" height=\"16\" alt='icon-$item_identifier' /> " . $strbookmarks . ': ' . $item_name);

	print_tabs(array($tabs, $tabs_sub), $currenttab, null, $activetabsubs);

	if (block_exaport_course_has_desp() && (strpos($currenttab,'bookmarks') === 0) ) {
		
		
			
			   echo '<div id="messageboxses1"';
				//if (file_exists("../desp/images/message_ses1.gif")){ echo ' style="min-height:145px; background: url(\'../desp/images/message_ses1.gif\') no-repeat left top; "';}
				echo '>
					<div id="messagetxtses1">
						'.get_string("desp_einleitung", "block_exaport").'
					</div>
				</div>
				
				<br /><br />';
	}
	
}

function block_exaport_get_string($string, $param=null) {
	$manager = get_string_manager();
	
	if (block_exaport_course_has_desp() && $manager->string_exists('desp_'.$string, 'block_exaport'))
		return $manager->get_string('desp_'.$string, 'block_exaport', $param);

	if ($manager->string_exists($string, "block_exaport"))
		return $manager->get_string($string, 'block_exaport', $param);

	return $manager->get_string($string, '', $param);	
}
function todo_string($string) {
	$manager = get_string_manager();
	
	if ($manager->string_exists($string, "block_exaport"))
		return $manager->get_string($string, 'block_exaport');

	return '[['.$string.']]';	
}

function block_exaport_print_footer() {
	global $COURSE;
	print_footer($COURSE);
}

/**
 * Parse user submitted item_type and return a correct type
 * @param string $type
 * @param boolean $all_allowd Is the type 'all' allowed? E.g. for Item-List
 * @return string correct type
 */
function block_exaport_check_item_type($type, $all_allowed) {
	if (in_array($type, Array('link', 'file', 'note')))
		return $type;
	else
		return $all_allowed ? 'all' : false;
}

/**
 * Convert item type to plural
 * @param string $type
 * @return string Plural. E.g. file->files, note->notes, all->all (has no plural)
 */
function block_exaport_get_plural_item_type($type) {
	return $type == 'all' ? $type : $type . 's';
}

/**
 * Parse user submitted item sorting and check if allowed/available!
 * @param $sort the sorting in a format like "category.desc"
 * @return Array(sortcolumn, asc|desc)
 */
function block_exaport_parse_sort($sort, array $allowedSorts, array $defaultSort = null) {
	if (!is_array($sort))
		$sort = explode('.', $sort);

	$column = $sort[0];
	$order = isset($sort[1]) ? $sort[1] : '';

	if (!in_array($column, $allowedSorts)) {
		if ($defaultSort) {
			return $defaultSort;
		} else {
			return array(reset($allowedSorts), 'asc');
		}
	}

	// sortorder never desc allowed!
	if ($column == 'sortorder')
		return array($column, 'asc');

	if ($order != "desc")
		$order = "asc";

	return array($column, $order);
}

function block_exaport_parse_item_sort($sort, $catsortallowed) {
	if($catsortallowed)
		return block_exaport_parse_sort($sort, array('date', 'name', 'category', 'type'), array('date', 'desc'));

	return block_exaport_parse_sort($sort, array('date', 'name', 'type'), array('date', 'desc'));
}

function block_exaport_item_sort_to_sql($sort, $catsortallowed) {
	$sort = block_exaport_parse_item_sort($sort, $catsortallowed);

	$column = $sort[0];
	$order = $sort[1];

	if ($column == "date") {
		$sql_sort = "i.timemodified " . $order;
	} elseif ($column == "category") {
		$sql_sort = "cname " . $order . ", i.timemodified";
	} else {
		$sql_sort = "i." . $column . " " . $order . ", i.timemodified";
	}

	return ' order by ' . $sql_sort;
}

function block_exaport_parse_view_sort($sort, $for_shared = false) {
	return block_exaport_parse_sort($sort, array('name', 'timemodified'));
}

function block_exaport_view_sort_to_sql($sort) {
   	global $CFG;
	$sort = block_exaport_parse_view_sort($sort);

	$column = $sort[0];
	$order = $sort[1];
	
	if((strcmp($column, "name")==0) && (strcmp($CFG->dbtype, "sqlsrv")==0)){
		$sql_sort = "cast(v.".$column." AS varchar(max)) ".$order.", v.timemodified DESC";
	}
	else if((strcmp($column, "timemodified")==0) && (strcmp($CFG->dbtype, "sqlsrv")==0)){
		$sql_sort = "v.timemodified DESC";
	}
	else{
		$sql_sort = "v." . $column . " " . $order . ", v.timemodified DESC";
	}

	return ' order by ' . $sql_sort;
}

function block_exaport_get_user_preferences_record($userid = null) {
	global $DB;

	if (is_null($userid)) {
		global $USER;
		$userid = $USER->id;
	}
	$conditions = array("user_id" => $userid);
	return $DB->get_record('block_exaportuser', $conditions);
}

function block_exaport_get_user_preferences($userid = null) {
	global $DB;
	if (is_null($userid)) {
		global $USER;
		$userid = $USER->id;
	}

	$userpreferences = block_exaport_get_user_preferences_record($userid);

	if (!$userpreferences || !$userpreferences->user_hash) {
		do {
			$hash = substr(md5(uniqid(rand(), true)), 3, 8);
		} while ($DB->record_exists("block_exaportuser", array("user_hash" => $hash)));

		block_exaport_set_user_preferences($userid, array('user_hash' => $hash, 'description' => ''));

		$userpreferences = block_exaport_get_user_preferences_record($userid);
	}

	return $userpreferences;
}

function block_exaport_check_competence_interaction() {
	global $CFG;

	return !empty($CFG->block_exaport_enable_interaction_competences) &&
		class_exists('\block_exacomp\api') && \block_exacomp\api::active();
}

function block_exaport_check_item_competences($item) {
	return (bool)\block_exacomp\api::get_active_comp_for_exaport_item($item->id);
}

function block_exaport_build_comp_table($item, $role="teacher") {
	global $DB;

	$sql = "SELECT CONCAT(CONCAT(da.id,'_'),d.id) as uniquid,d.title, d.id FROM {block_exacompdescriptors} d, {block_exacompcompactiv_mm} da WHERE d.id=da.compid AND da.eportfolioitem=1 AND da.activityid=?";
	$descriptors = $DB->get_records_sql($sql, array($item->id));
	$content = "<table class='compstable flexible boxaligncenter generaltable'>
				<tr><td><h2>" . $item->name . "</h2></td></tr>";
	
	if($role == "teacher") {
		$dis_teacher = " ";
		$dis_student = " disabled ";
	} else {
		$dis_teacher = " disabled ";
		$dis_student = " ";
	}
	
	$trclass = "even";
	foreach ($descriptors as $descriptor) {
		if ($trclass == "even") {
			$trclass = "odd";
			$bgcolor = ' style="background-color:#efefef" ';
		} else {
			$trclass = "even";
			$bgcolor = ' style="background-color:#ffffff" ';
		}
		$content .= '<tr '. $bgcolor .'><td>' . $descriptor->title . '</td></tr>';
		/*<td>
			<input'.$dis_teacher.'type="checkbox" name="data[' . $descriptor->id . ']" checked="###checked' . $descriptor->id . '###" />
			</td>
			<td><input'.$dis_student.'type="checkbox" name="eval[' . $descriptor->id . ']" checked="###eval' . $descriptor->id . '###" /></td></tr>';*/
	}
	//$content .= "</table><input type='submit' value='" . get_string("auswahl_speichern", "block_exais_competences") . "' /></form>";
	$content .= '</table>';
	//get teacher comps
	/*
	$competences = block_exaport_get_competences($item, 1);
	foreach ($competences as $competence) {
			$content = str_replace('###checked' . $competence->descid . '###', 'checked', $content);
		}
	$content = preg_replace('/checked="###checked([0-9_])+###"/', '', $content);
	//get student comps
	$competences = block_exaport_get_competences($item, 0);
	foreach ($competences as $competence) {
			$content = str_replace('###eval' . $competence->descid . '###', 'checked', $content);
		}
	$content = preg_replace('/checked="###eval([0-9_])+###"/', '', $content);
	*/
	echo $content;
}
function block_exaport_set_competences($values, $item, $reviewerid, $role=1 ) {
	global $DB;

	$DB->delete_records('block_exacompcompuser_mm',array("activityid"=>$item->id, "eportfolioitem"=>1, "role"=>$role, "userid"=>$item->userid));
	
	foreach ($values as $value) {
		$data = array(
			"activityid" => $item->id,
			"eportfolioitem" => 1,
			"compid" => $value,
			"userid" => $item->userid,
			"reviewerid" => $reviewerid,
			"role" => $role
		);
		print_r($data);
		$DB->insert_record('block_exacompcompuser_mm', $data);
	}
}
function block_exaport_get_active_compids($item) {
	return \block_exacomp\api::get_active_comp_for_exaport_item($item->id);
}

function block_exaport_get_comp_output($item) {
	$compids = block_exaport_get_active_compids($item);
}
function block_exaport_build_comp_tree($type, $item_or_resume) {
	global $CFG, $USER;

	if ($type == 'skillscomp' || $type == 'goalscomp') {
		$forresume = true;
		$resume = $item_or_resume;
		$item = null;
		$active_descriptors = $resume->descriptors;
	} elseif ($type == 'item') {
		$forresume = false;
		$resume = null;
		$item = $item_or_resume;
		$active_descriptors = isset($item->compids_array) ? $item->compids_array : [];
	} else {
		throw new \block_exaport\moodle_exception("wrong \$type: $type");
	}

	if ($forresume) {
		$content = '<form id="treeform" method="post" action="'.$CFG->wwwroot.'/blocks/exaport/resume.php?courseid='.$resume->courseid.'&id='.$resume->id.'&sesskey='.sesskey().'#'.$type.'">';
	} else {
		$content = '<form id="treeform">';
	}

	$print_tree = function($items, $level = 0) use (&$print_tree, $forresume, $active_descriptors) {
		if (!$items) return '';

		$content = '';
		if ($level == 0) {
			$content .= '<ul id="comptree" class="treeview">';
		} else {
			$content .= '<ul>';
		}

		foreach ($items as $item) {
			if ($item instanceof \block_exacomp\descriptor && in_array($item->id, $active_descriptors)) {
				$checked = 'checked="checked"';
			} else {
				$checked = '';
			}

			$content .= '<li>';
			if ($item instanceof \block_exacomp\descriptor) {
				$content .= '<input type="checkbox" name="desc' . ($forresume ? '[]' : '') . '" ' . $checked . ' value="' . $item->id . '">';
			}
			$content .=
				$item->title.
				($item->achieved ? ' '.g::$OUTPUT->pix_icon("i/badge", block_exaport\trans(['de:Erreichte Kompetenz', 'en:Achieved Competency'])) : '').
				$print_tree($item->get_subs(), $level+1).
				'</li>';
		}

		$content .= '</ul>';

		return $content;
	};

	$compTree = \block_exacomp\api::get_comp_tree_for_exaport($USER->id);

	// no filtering
	/*
	if ($compTree && $forresume) {
		$filter_tree = function($item) use (&$filter_tree, $forresume, $active_descriptors) {
			$item->set_subs(array_filter($item->get_subs(), $filter_tree));

			if ($item instanceof \block_exacomp\descriptor) {
				// achieved, or children achieved
				return ($item->get_subs() || $item->achieved);
			} else {
				return !!$item->get_subs();
			}
		};
		$compTree = array_filter($compTree, $filter_tree);
	}
	*/

	/*
	if (!$compTree) {
		$content .= '<div><h4 style="text-align:center; padding: 40px;">'.block_exaport\trans(['de:Eine Kurse hat leider keine Kompetenzen für den Kurs aktiviert', "en:"]).'</h4></div>';
	} else {
		$content .= $print_tree($compTree);
	}
	*/
	$content .= $print_tree($compTree);

	if ($forresume) {
		$content .= '<input type="hidden" value="edit" name="action">';
		$content .= '<input type="hidden" value="'.$type.'" name="type">';
		$content .= '<input type="hidden" value="'.sesskey().'" name="sesskey">';
		$content .= '<input type="submit" id="id_submitbutton" type="submit" value="'.get_string('savechanges').'" name="submitbutton">';
		$content .= '<input type="submit" id="id_cancel" class="btn-cancel" onclick="skipClientValidation = true; return true;" value="'.get_string('cancel').'" name="cancel">';
	} else {
		$content .= '<input type="button" id="id_submitbutton2" value="'.get_string('savechanges').'" name="savecompetencesbutton" onClick="jQueryExaport.colorbox.close();">';
	}
	$content .= '</form>';

	return $content;
}
function block_exaport_assignmentversion(){
	global $DB;
	$modassign=new stdClass();
	if($DB->record_exists('modules', array('name'=>'assign', 'visible'=>1))){
		$modassign->new=1;
		$modassign->title="assign";
		$modassign->filearea="submission_files";
		$modassign->component="assignsubmission_file";
	}else{
		$modassign->new=0;
		$modassign->title="assignment";
		$modassign->filearea="submission";
		$modassign->component='mod_assignment';
	};
	return $modassign;
}
function block_exaport_set_user_preferences($userid, $preferences = null) {
	global $DB;

	if (is_null($preferences) && (is_array($userid) || is_object($userid))) {
		global $USER;
		$preferences = $userid;
		$userid = $USER->id;
	}

	$newuserpreferences = new stdClass();

	if (is_object($preferences)) {
		$newuserpreferences = $preferences;
	} elseif (is_array($preferences)) {
		$newuserpreferences = (object) $preferences;
	} else {
		echo 'error #fjklfdsjkl';
	}

	if ($olduserpreferences = block_exaport_get_user_preferences_record($userid)) {
		$newuserpreferences->id = $olduserpreferences->id;
		$DB->update_record('block_exaportuser', $newuserpreferences);
	} else {
		$newuserpreferences->user_id = $userid;
		$DB->insert_record("block_exaportuser", $newuserpreferences);
	}
}

function block_exaport_get_item_where() {
	// extra where for epop
	return "(i.isoez=0 OR (i.isoez=1 AND (i.intro<>'' OR i.url<>'' OR i.attachment<>'')))";
}

function block_exaport_get_category($id) {
	global $USER, $DB;
	
	if ($id == 0)
		return block_exaport_get_root_category();
		
	return $DB->get_record("block_exaportcate", array(
		'id' => $id,
		'userid' => $USER->id
	));
}

function block_exaport_get_root_category() {
	global $DB, $USER;
	return (object) array(
		'id' => 0,
		'pid' => -999,
		'name' => block_exaport_get_string('root_category'),
		'item_cnt' => $DB->get_field_sql('
			SELECT COUNT(i.id) AS item_cnt
			FROM {block_exaportitem} i
			WHERE i.userid = ? AND i.categoryid = 0 AND '.block_exaport_get_item_where().'
		', array($USER->id))

	);
}

function block_exaport_get_shareditems_category($name = null, $userid = null) {
	global $DB, $USER;
	return (object) array(
		'id' => -1,
		'pid' => 0,
		'name' => $name != null ? $name : block_exaport_get_string('shareditems_category'),
		'item_cnt' => '',
		'userid' => $userid ? $userid : ''
/* 		'item_cnt' => $DB->get_field_sql('
			SELECT COUNT(i.id) AS item_cnt
			FROM {block_exaportitem} i
			WHERE i.userid = ? AND i.categoryid = 0 AND '.block_exaport_get_item_where().'
		', array($USER->id))  */
	);
}

function block_exaport_badges_enabled() {
	global $CFG;
	// checking with exacomp
	if (block_exaport_check_competence_interaction() && block_exacomp_moodle_badges_enabled()) {
		return true;
	} else if (version_compare($CFG->release, '2.5') >= 0) {
		// For working badges without exacomp installation:
		require_once($CFG->libdir . '/badgeslib.php');
		require_once($CFG->dirroot . '/badges/lib/awardlib.php');
		return true;
	};
	return false;
}

function block_exaport_get_all_user_badges() {
	global $USER;
	if (block_exaport_badges_enabled()) {
		if (!function_exists('block_exacomp_get_all_user_badges')){
			
			// for using badges without exacomp installation
			$records = badges_get_user_badges($USER->id);
			return $records;
			// old code
			print_error("please update exabis competencies to latest version");
			exit;
		}else
			return block_exacomp_get_all_user_badges();
	} else
		return null;
}
function block_exaport_get_user_category($title, $userid) {
	global $DB;
	
	return $DB->get_record('block_exaportcate', array('userid'=>$userid,'name'=>$title));
}
function block_exaport_create_user_category($title, $userid, $parentid = 0) {
	global $DB;
	
	if(!$DB->record_exists('block_exaportcate', array('userid'=>$userid,'name'=>$title,'pid'=>$parentid))) {
		$id = $DB->insert_record('block_exaportcate',array('userid'=>$userid,'name'=>$title,'pid'=>$parentid));
		return $DB->get_record('block_exaportcate',array('id'=>$id));
	}
	return false;
}

/**
 * Autofill the view with all existing artefacts
 * @param integer $viewid
 * @param string $existingartefacts
 * @return string Artefacts
 */
function fill_view_with_artefacts($viewid, $existingartefacts='') {
	global $DB, $USER;

	$artefacts = block_exaport_get_portfolio_items(1);
	if ($existingartefacts<>'') {
		$existingartefactsarray = explode(',', $existingartefacts);
		$filledartefacts = $existingartefacts;
	} else {
		$existingartefactsarray = array();
		$filledartefacts = '';
	}
	if (count($artefacts)>0) {
		$y = 1;
		foreach ($artefacts as $artefact) {
			if (!in_array($artefact->id, $existingartefactsarray)) {
				$block = new stdClass();
				$block->itemid = $artefact->id;
				$block->viewid = $viewid;
				$block->type = 'item';
				$block->positionx = 1;
				$block->positiony = $y;
				$block->id = $DB->insert_record('block_exaportviewblock', $block);
				$y++;
				$filledartefacts .= ','.$artefact->id;
			}
		}
		if ($existingartefacts == '') {
			$filledartefacts = substr($filledartefacts, 1);
		};
	}; /**/
	return $filledartefacts;
}

/**
 * Autoshare the view to teachers
 * @param integer $viewid
 * @return nothing
 */
function block_exaport_share_view_to_teachers($viewid) {
	global $DB;
	if ($viewid > 0) {
		$allteachers = block_exaport_get_course_teachers();
		$allsharedusers = block_exaport_get_shared_users($viewid);
		$diff = array_diff($allteachers, $allsharedusers);
		$view = $DB->get_record_sql('SELECT * FROM {block_exaportview} WHERE id = ?', array('id'=>$viewid));
		if (!$view->shareall) {
			$view->shareall = 0;
		};
		if (!$view->externaccess) {
			$view->externaccess = 0;
		};
		if (!$view->externcomment) {
			$view->externcomment = 0;
		};
		$DB->update_record('block_exaportview', $view);
		// Add all teachers to shared users (if it is not there yet).
		if ((count($allteachers) > 0) && (count($diff) > 0)) {
			foreach ($diff as $userid) {
				// If course has a teacher.
				if ($userid > 0) {
					$shareItem = new stdClass();
					$shareItem->viewid = $view->id;
					$shareItem->userid = $userid;
					$DB->insert_record("block_exaportviewshar", $shareItem);
				};
			};
		};
	};
}

function block_exaport_get_view_blocks($view) {
	global $DB, $USER;

	$portfolioItems = block_exaport_get_portfolio_items();
	$badges = block_exaport_get_all_user_badges();

	$query = "select b.*".
			" from {block_exaportviewblock} b".
			" where b.viewid = ? ORDER BY b.positionx, b.positiony";

	$allBlocks = $DB->get_records_sql($query, array($view->id));
	$blocks = array();

	foreach ($allBlocks as $block) {
		if ($block->type == 'item') {					
			if (!isset($portfolioItems[$block->itemid])) {
				// Could be shared sometime (because found in block_exaportviewblock with viewid)
				if (!$potentialitem = $DB->get_record("block_exaportitem", array('id' => $block->itemid))) {
					// item not found
					continue;
				} else {
					$items = block_exaport_get_portfolio_items(0, $block->itemid);					
					$portfolioItems[$block->itemid] = $items[$block->itemid];
					$block->unshared = 1;
				}
			}
			if (!$block->width)
				$block->width = 320;
			if (!$block->height)
				$block->height = 240;
			$portfolioItems[$block->itemid]->intro = process_media_url($portfolioItems[$block->itemid]->intro, $block->width, $block->height);
			$block->item = $portfolioItems[$block->itemid];
		} elseif ($block->type == 'badge') {
			// find bage by id
			$badge = null;
			foreach ($badges as $tmp) {
				if ($tmp->id == $block->itemid) {
					$badge = $tmp;
					break;
				}
			}
			if (!$badge) {
				// badge not found
				continue;
			}
				
			if (!$badge->courseid) { // For badges with courseid = NULL
				$badge->imageUrl = (string)moodle_url::make_pluginfile_url(1, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
			} else {
				$context = context_course::instance($badge->courseid);	
				$badge->imageUrl = (string)moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
			}
			

			$block->badge = $badge;
		} else {
			$block->print_text = file_rewrite_pluginfile_urls($block->text, 'draftfile.php', context_user::instance($USER->id)->id, 'user', 'draft', $view->draft_itemid);
			$block->itemid = null;
		}

		// clean html texts for output
		if (isset($block->print_text) && $block->print_text) {
			$block->print_text = clean_text($block->print_text, FORMAT_HTML);
		}
		if (isset($block->intro) && $block->intro) {
			$block->intro = clean_text($block->intro, FORMAT_HTML);
		}

		$blocks[$block->id] = $block;
	}

	return $blocks;
}

function block_exaport_get_portfolio_items($epopwhere = 0, $itemid = null) {
	global $DB, $USER;
	if ($epopwhere == 1) {
		$addwhere = " AND ".block_exaport_get_item_where();
	} else {
		$addwhere = "";
	};
	// only needed item by id
	if ($itemid) {
		$where = ' i.id = '.$itemid;	
	} else {
		$where = " i.userid=? ".$addwhere;
	}
	$query = "select i.id, i.name, i.type, i.intro as intro, i.url AS link, ic.name AS cname, ic.id AS catid, ic2.name AS cname_parent, i.userid, COUNT(com.id) As comments".
			" from {block_exaportitem} i".
			" left join {block_exaportcate} ic on i.categoryid = ic.id".
			" left join {block_exaportcate} ic2 on ic.pid = ic2.id".
			" left join {block_exaportitemcomm} com on com.itemid = i.id".
			" where ".$where.
			" GROUP BY i.id, i.name, i.type, i.intro, i.url, ic.id, ic.name, ic2.name, i.userid".
			" ORDER BY i.name";
	//echo $query."<br><br>";
	$portfolioItems = $DB->get_records_sql($query, array($USER->id));
	if (!$portfolioItems) {
		$portfolioItems = array();
	}

	// add shared items
	$shared_items = exaport_get_shared_items_for_user($USER->id, true);
	$portfolioItems = $portfolioItems + $shared_items;

	foreach ($portfolioItems as &$item) {
		if (null == $item->cname) {
			$item->category = format_string(block_exaport_get_root_category()->name);
			$item->catid = 0;
		} elseif (null == $item->cname_parent) {
			$item->category = format_string($item->cname);
		} else {
			//$item->category = format_string($item->cname_parent) . " &rArr; " . format_string($item->cname);
			$catid= $item->catid;
			$catname = $item->cname;
			$item->category = "";
			do{
				$conditions = array("userid" => $USER->id, "id" => $catid);
				$cats=$DB->get_records_select("block_exaportcate", "userid = ? AND id = ?",$conditions, "name ASC");
				foreach($cats as $cat){
					if($item->category == "")
						$item->category =format_string($cat->name);
					else
						$item->category =format_string($cat->name)." &rArr; ".$item->category;
					$catid = $cat->pid;
				}

			}while ($cat->pid != 0);
		}

		if ($item->intro) {
			$item->intro = file_rewrite_pluginfile_urls($item->intro, 'pluginfile.php', context_user::instance($item->userid)->id, 'block_exaport', 'item_content', 'portfolio/id/'.$item->userid.'/itemid/'.$item->id);
			$item->intro = clean_text($item->intro, FORMAT_HTML);
		}

		//get competences of the item
		$item->userid = $USER->id;

		$comp = block_exaport_check_competence_interaction();
		if($comp){
			$compids = block_exaport_get_active_compids($item);

			if($compids){
				$competences = "";
				foreach($compids as $compid){
					$conditions = array("id" => $compid);
					$competencesdb = $DB->get_record('block_exacompdescriptors', $conditions, $fields='*', $strictness=IGNORE_MISSING);

					if($competencesdb != null){
						$competences .= $competencesdb->title.'<br>';
					}
				}
				$competences = str_replace("\r", "", $competences);
				$competences = str_replace("\n", "", $competences);
				$competences = str_replace("\"", "&quot;", $competences);
				$competences = str_replace("'", "&prime;", $competences);
					
				$item->competences = $competences;
			}
		}

		unset($item->userid);

		unset($item->cname);
		unset($item->cname_parent);
	}
	//	print_r($portfolioItems);

	return $portfolioItems;
}

/**
 * Function gets teachers array of course
 * @return array
 */
function block_exaport_get_course_teachers($exceptMyself = true) {
	global $DB, $USER;

	$courseid = optional_param('courseid', 0, PARAM_INT);
	$context = context_course::instance($courseid);

	// Role id='3' - teachers. '4'- assistents.
	$query = "SELECT u.id as userid, u.id AS tmp
	FROM {user} u
	JOIN {role_assignments} ra ON ra.userid = u.id
	WHERE ra.contextid=? AND ra.roleid = '3'";
	$exastudTeachers = $DB->get_records_sql($query, [$context->id]);

	// if exacomp is not installed this function returns an emtpy array
	$exacompTeachers = get_enrolled_users($context,'block/exacomp:teacher');

	$teachers = $exastudTeachers + $exacompTeachers;

	if ($exceptMyself) {
		unset($teachers[$USER->id]);
	}

	$teachers = array_keys($teachers);

	return $teachers;
}

/**
 * Function gets all shared users
 * @param $viewid
 * @return array
 */
function block_exaport_get_shared_users($viewid) {
	global $DB, $USER;
	$sharedusers = array ();
	if ($viewid > 0) {
		$query = "SELECT userid FROM {block_exaportviewshar} s WHERE s.viewid=".$viewid;
		$users = $DB->get_records_sql($query);
		foreach($users as $user) {
			$sharedusers[] = $user->userid;
		};
	};
	sort($sharedusers);
	return $sharedusers;
};

function block_exaport_file_userquotecheck($addingfiles = 0, $id=0) {
	global $DB, $USER, $CFG;
	$result = $DB->get_record_sql("SELECT SUM(filesize) as allfilesize FROM {files} WHERE contextid = ? and component='block_exaport'", array(context_user::instance($USER->id)->id));
	if ($result->allfilesize + $addingfiles > $CFG->block_exaport_userquota) {
		$courseid = optional_param('courseid', 0, PARAM_INT);
		$categoryid = optional_param('categoryid', 0, PARAM_INT);
		$type = optional_param('type', 0, PARAM_RAW);
		print_error('userquotalimit', '', new moodle_url('/blocks/exaport/item.php', 
				array('sesskey' => sesskey(),
						'courseid' => $courseid,
						'action' => 'edit',
						'type' => $type,
						'id' => $id, 
						'categoryid' => $categoryid)), null);
		//throw new file_exception('userquotalimit');
	}		
	return true;	
}

function block_exaport_get_filesize_by_draftid($draftid = 0) {
	global $DB, $USER, $CFG;
	$result = $DB->get_record_sql("SELECT SUM(filesize) AS allfilesize FROM {files} WHERE contextid = ? AND component = 'user' AND filearea='draft' AND itemid = ?", array(context_user::instance($USER->id)->id, $draftid));
	if ($result) {
		return $result->allfilesize;
	} else {
		return 0;
	}
}

function block_exaport_get_maxfilesize_by_draftid_check($draftid = 0) {
	global $DB, $USER, $CFG;
	$result = $DB->get_record_sql("SELECT MAX(filesize) AS maxfilesize FROM {files} WHERE contextid = ? AND component = 'user' AND filearea='draft' AND itemid = ?", array(context_user::instance($USER->id)->id, $draftid));
	if (($CFG->block_exaport_max_uploadfile_size > 0) && ($result->maxfilesize > $CFG->block_exaport_max_uploadfile_size)) {
		print_error('maxbytes', 'exaport', 'blocks/exaport/view_items.php', null);
		//throw new file_exception('maxbytes');
	}		
	return true;	
}

function block_exaport_is_valid_media_by_filename ($filename) {
	global $DB, $USER, $CFG;
	$path_parts = pathinfo($filename);
	switch ($path_parts['extension']) {
		case 'avi':
		case 'mp4':
		case 'flv':
		case 'swf':
		case 'mpg':
		case 'mpeg':
		case '3gp':
		case 'webm':
		case 'ogg':
			return true;
		default: return false;
	}
}

// do user has sharable structures or do not
function has_sharablestructure($userid) {
	global $DB, $USER;
	// shared to all
	if ($DB->get_records_sql('SELECT * FROM {block_exaportcate} WHERE structure_share=1 AND structure_shareall=1 AND userid<>'.$USER->id))
		return true;
	// shared to user
	if ($DB->get_records_sql('SELECT * FROM {block_exaportcat_structshar} WHERE userid='.$userid.' AND userid<>'.$USER->id))
		return true;
	// shared to user's group
	$usergroups = $DB->get_records('groups_members', array('userid' => $userid), '', 'groupid');
	if ((is_array($usergroups)) && (count($usergroups) > 0)) { 
		foreach ($usergroups as $id => $group) {
			$usergroups[$id] = $group->groupid;
		};
		$usergroups_list = implode(',', $usergroups);
		$userstructures = $DB->get_records_sql('SELECT cs.* FROM {block_exaportcat_strgrshar} cs LEFT JOIN {block_exaportcate} c ON cs.catid=c.id WHERE c.userid<>'.$USER->id.' AND groupid IN ('.$usergroups_list.')');
		if (count($userstructures) > 0) 
			return true;
	}; 
	
	return false;
}
function block_exaport_item_is_editable($itemid) {
	global $CFG, $DB, $USER;
	
	if(!block_exaport_item_is_resubmitable($itemid))
		return false;
	
	$allowEdit = true;

	$itemExample = $DB->get_record(\block_exacomp\DB_ITEMEXAMPLE,array("itemid" => $itemid));
	
	if(!$CFG->block_exaport_app_alloweditdelete && block_exaport_check_competence_interaction()) {
		//check item grading and teacher comment
		if(isset($itemExample)) {
			if(isset($itemExample->teachervalue) && $itemExample->teachervalue != null) {
				$allowEdit = false;
			}else {
				$itemcomments = $DB->get_records ( 'block_exaportitemcomm', array (
						'itemid' => $itemid
				), 'timemodified ASC', 'entry, userid', 0, 2 );
				if ($itemcomments) {
					foreach ( $itemcomments as $itemcomment ) {
						if ($USER->id != $itemcomment->userid) {
							$allowEdit = false;
						}
					}
				}
			}
		}
	}
	return $allowEdit;
}
function block_exaport_item_is_resubmitable($itemid) {
	global $DB, $USER, $COURSE;

	if (!block_exaport_check_competence_interaction()) {
		return false;
	}

	if(	$itemExample = $DB->get_record(\block_exacomp\DB_ITEMEXAMPLE,array("itemid" => $itemid)) ) {
		if($eval = $DB->get_record(\block_exacomp\DB_EXAMPLEEVAL, array('exampleid'=>$itemExample->exampleid,'studentid'=>$USER->id,'courseid'=>$COURSE->id))) {
			if(!$eval->resubmission)
				return false;
		}
	}
	
	return true;
}
function block_exaport_has_grading_permission($itemid) {
	global $DB;
	
	if (!block_exaport_check_competence_interaction()) {
		return false;
	}

	// check if item is a submission for an exacomp example
	$itemExample = $DB->get_record(\block_exacomp\DB_ITEMEXAMPLE,array("itemid" => $itemid));
	if (!$itemExample) {
		return false;
	}

	$item = $DB->get_record('block_exaportitem', array('id'=>$itemid));
	if (!$item || !$item->courseid) {
		return false;
	}

	$coursecontext = context_course::instance($item->courseid);
	return has_capability('block/exacomp:teacher', $coursecontext);
}

function block_exaport_delete_user_data($userid){
	global $DB;

	$result = $DB->delete_records('block_exaportcate', array('userid'=>$userid));
	$result = $DB->delete_records('block_exaportcatshar', array('userid'=>$userid));
	$result = $DB->delete_records('block_exaportcat_structshar', array('userid'=>$userid));
	$result = $DB->delete_records('block_exaportitem', array('userid'=>$userid));
	$result = $DB->delete_records('block_exaportitemcomm', array('userid'=>$userid));
	$result = $DB->delete_records('block_exaportitemshar', array('userid'=>$userid));
	$result = $DB->delete_records('block_exaportview', array('userid'=>$userid));
	$result = $DB->delete_records('block_exaportviewshar', array('userid'=>$userid));

	$result = $DB->delete_records('block_exaportresume', array('user_id'=>$userid));
	$result = $DB->delete_records('block_exaportuser', array('user_id'=>$userid));
}
