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

namespace block_exaport;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__.'/../lib/lib.php';

use block_exaport\globals as g;

class api {
	static function active() {
		// check if block is active
		if (!g::$DB->get_record('block',array('name'=>'exaport', 'visible'=>1))) {
			return false;
		}
		
		return true;
	}

	static function delete_user_data($userid){
		global $DB;
		
		$DB->delete_records('block_exaportcate', array('userid'=>$userid));
		$DB->delete_records('block_exaportcatshar', array('userid'=>$userid));
		$DB->delete_records('block_exaportcat_structshar', array('userid'=>$userid));
		$DB->delete_records('block_exaportitem', array('userid'=>$userid));
		$DB->delete_records('block_exaportitemcomm', array('userid'=>$userid));
		$DB->delete_records('block_exaportitemshar', array('userid'=>$userid));
		$DB->delete_records('block_exaportview', array('userid'=>$userid));
		$DB->delete_records('block_exaportviewshar', array('userid'=>$userid));

		$DB->delete_records('block_exaportresume', array('user_id'=>$userid));
		$DB->delete_records('block_exaportuser', array('user_id'=>$userid));

		return true;
	}
}
