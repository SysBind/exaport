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

/**
 * $.disablescroll
 * Author: Josh Harrison - aloof.co
 *
 * Disables scroll events from mousewheels, touchmoves and keypresses.
 * Use while jQuery is animating the scroll position for a guaranteed super-smooth ride!
 */(function(e){"use strict";function r(t,n){this.opts=e.extend({handleWheel:!0,handleScrollbar:!0,handleKeys:!0,scrollEventKeys:[32,33,34,35,36,37,38,39,40]},n);this.$container=t;this.$document=e(document);this.lockToScrollPos=[0,0];this.disable()}var t,n;n=r.prototype;n.disable=function(){var e=this;e.opts.handleWheel&&e.$container.on("mousewheel.disablescroll DOMMouseScroll.disablescroll touchmove.disablescroll",e._handleWheel);if(e.opts.handleScrollbar){e.lockToScrollPos=[e.$container.scrollLeft(),e.$container.scrollTop()];e.$container.on("scroll.disablescroll",function(){e._handleScrollbar.call(e)})}e.opts.handleKeys&&e.$document.on("keydown.disablescroll",function(t){e._handleKeydown.call(e,t)})};n.undo=function(){var e=this;e.$container.off(".disablescroll");e.opts.handleKeys&&e.$document.off(".disablescroll")};n._handleWheel=function(e){e.preventDefault()};n._handleScrollbar=function(){this.$container.scrollLeft(this.lockToScrollPos[0]);this.$container.scrollTop(this.lockToScrollPos[1])};n._handleKeydown=function(e){for(var t=0;t<this.opts.scrollEventKeys.length;t++)if(e.keyCode===this.opts.scrollEventKeys[t]){e.preventDefault();return}};e.fn.disablescroll=function(e){!t&&(typeof e=="object"||!e)&&(t=new r(this,e));t&&typeof e=="undefined"?t.disable():t&&t[e]&&t[e].call(t)};window.UserScrollDisabler=r})(jQuery);

(function(){

window.jQueryExaport = jQuery;
var $ = jQuery;


$.empty = function(obj) {
	if (!obj) {
		return true;
	}

	for ( key in obj) {
		return false;
	}
	return true;
};

window.block_exaport = window.ExabisEportfolio = $E = {
	courseid: 1,

	translations: null,

	translate: function(key)
	{
		if (this.translations[key] == undefined) {
			return '[[js['+key+']js]]';
		} else {
			return this.translations[key];
		}
	},

	setTranslations: function(translations) {
		this.translations = translations;
	},

	userlist_loaded: false,
	structure_userlist_loaded: false,
	load_userlist: function(type, target)
	{
		target = typeof target !== 'undefined' ? target : '';
		if (target == 'structure_') {
			if (this.structure_userlist_loaded) {
				return;
			}
			this.structure_userlist_loaded = true;
		} else {
			if (this.userlist_loaded) {
				return;
			}
			this.userlist_loaded = true;
		}
		$('#'+target+'sharing-userlist').html('loading userlist...');

		$.getJSON(document.location.href, {action: target+'userlist'}, function(courses){
			var html = '';

			if (!$.empty(courses)) {
				$.each(courses, function(tmp, course){
					html += '<fieldset class="course-group"><legend class="course-group-title">' +
							($E.courseid == course.id ? '<b>' : '') +
							course.fullname +
							($E.courseid == course.id ? '</b>' : '') +
							'</legend>';

					html += '<div class="course-group-content">';
					if (!$.empty(course.users)) {
						html += "<table width=\"70%\">";
						html += "<tr><th align=\"center\">&nbsp;</th>";
						if (type == 'views_mod' && target == '') html += "<th align=\"center\">&nbsp;</th>";
						html += "<th align=\"left\">"+$E.translate('name')+"</th><th align=\"right\">"+$E.translate('role')+"</th></tr>";

						html += '<tr><td align=\"center\" width="5%">';
						html += '<input class="shareusers-check-all" courseid="'+course.id+'" type="checkbox" />';
						html += "<br />"+$E.translate('checkall');
						html += "</td></tr>";

						$.each(course.users, function(tmp, user){
							html += '<tr><td align=\"center\" width="5%">';
							html += '<input class="shareusers" type="checkbox" courseid="'+course.id+'" name="'+target+'shareusers['+user.id+']" value="'+user.id+'"' +
								(user.shared_to ? ' checked="checked"' : '') +
								' />';
							if (type == 'views_mod' && target == '') {
								html += "<br />"+$E.translate('sharejs');
								html += '</td><td align=\"center\" width="5%" style="padding-right: 20px;">';
								html += '<input class="notifyusers" type="checkbox" disabled="disabled" name="notifyusers['+user.id+']" value="'+user.id+'" />';
								html += "<br />"+$E.translate('notify');
							}
							html += "</td><td align=\"center\" width='45%'>" + user.name + "</td><td align=\"center\" width='45%'>" + user.rolename + "</td></tr>";
						});

						html += "</table>";
					} else {
						html += $E.translate('nousersfound');
					}
					html += '</div>';
					html += "</fieldset>";
				});
			} else {
				html += '<b>'+$E.translate('nousersfound')+'</b>';
			}

			$('#'+target+'sharing-userlist').html(html);

			// set default checkboxes for category
			if (typeof sharedusersarr != 'undefined') { // In view sharing this array is undefined
				if (sharedusersarr.length > 0) {
					$.each(sharedusersarr, function(tmp, userid){
						$('#mform1 #internaccess-users input:checkbox[value='+userid+']').attr("checked", true);
					})
				}
			}
			if (typeof structure_sharedusersarr != 'undefined') {
				if (structure_sharedusersarr.length > 0) {
					$.each(structure_sharedusersarr, function(tmp, userid){
						$('#mform1 #structure_sharing-users input:checkbox[value='+userid+']').attr("checked", true);
					})
				}
			}
			// CHECK ALL buttons
			$('#'+target+'sharing-userlist .shareusers-check-all').click(function(){
				// check/uncheck all users in this course
				$('#'+target+'sharing-userlist .shareusers:checkbox[courseid='+$(this).attr('courseid')+']')
					.prop('checked', $(this).is(':checked'))
					// execute click handler
					.each(function(){
						// wrapped in each, because triggerHandler only works on first element
						$(this).triggerHandler('click');
					});
			});

			/*
			$('#sharing-userlist .shareusers:checkbox, #sharing-userlist .notifyusers:checkbox').click(function(){
				// check/uncheck this user in other courses
				$('#sharing-userlist :checkbox[name="'+this.name+'"]').attr('checked', this.checked);
			});
			*/

			// stop slow loading
			$('#'+target+'sharing-userlist .shareusers:checkbox').click(function(){
				// enable/disable notifyuser, according to shared users checkbox
				var $notifyboxes = $(this).closest('tr').find('.notifyusers');

				$notifyboxes.attr('disabled', !this.checked);
				if (!this.checked) {
					$notifyboxes.prop('checked', false);
				}

				// check/uncheck all users
				var $courseCheckboxes = $('#'+target+'sharing-userlist .shareusers:checkbox[courseid='+$(this).attr('courseid')+']');
				$('#'+target+'sharing-userlist .shareusers-check-all[courseid='+$(this).attr('courseid')+']').prop('checked', $courseCheckboxes.not(':checked').length == 0);
			});
			$('.course-group-content').each(function(){
				var flag = 0;
				$(this).find( 'table > tbody > tr > td > input.shareusers').each(function(){
					if (flag==1)
						return false;
					if ($(this).prop('checked')==false)
						flag = 1;

					var $notifyboxes = $(this).closest('tr').find('.notifyusers');
					$notifyboxes.attr('disabled', !this.checked);
					if (!this.checked) {
						$notifyboxes.prop('checked', false);
					}
				});
				if (flag == 0) {
					$(this).find('table > tbody > tr > td > input.shareusers-check-all').prop('checked', true);
				}
			});

			// open/close course group
			$('.course-group-title').on('click', function(){
				$(this).closest('.course-group').toggleClass('course-group-open');
			});
			// open all shared courses
			$('.course-group').has('input:checked').addClass('course-group-open');
		});
	},

	grouplist_loaded: false,
	structure_grouplist_loaded: false,
	load_grouplist: function(type, target)
	{
		target = typeof target !== 'undefined' ? target : '';
		if (target == 'structure_') {
			if (this.structure_grouplist_loaded) {
				return;
			}
			this.structure_grouplist_loaded = true;
		} else {
			if (this.grouplist_loaded) {
				return;
			}
			this.grouplist_loaded = true;
		};

		$('#'+target+'sharing-grouplist').html('loading grouplist...');

		$.getJSON(document.location.href, {action: target+'grouplist'}, function(courses){
			var html = '';

			if (!$.empty(courses)) {
				$.each(courses, function(tmp, course){
					html += '<fieldset class="course-group"><legend class="course-group-title">' +
							($E.courseid == course.id ? '<b>' : '') +
							course.fullname +
							($E.courseid == course.id ? '</b>' : '') +
							'</legend>';

					html += '<div class="course-group-content">';
					if (!$.empty(course.groups)) {
						html += "<table width=\"70%\">";
						html += "<tr><th align=\"center\">&nbsp;</th>";
						if (type == 'views_mod') html += "<th align=\"center\">&nbsp;</th>";
						html += "<th align=\"left\">"+$E.translate('grouptitle')+"</th><th align=\"right\">"+$E.translate('membersnumber')+"</th></tr>";

						html += '<tr><td align=\"center\" width="5%">';
						html += '<input class="sharegroups-check-all" courseid="'+course.id+'" type="checkbox" />';
						html += "<br />"+$E.translate('checkall');
						html += "</td></tr>";

						$.each(course.groups, function(tmp, group){
							html += '<tr><td align=\"center\" width="5%">';
							html += '<input class="sharegroups" type="checkbox" courseid="'+course.id+'" name="'+target+'sharegroups['+group.id+']" value="'+group.id+'"' +
								(group.shared_to ? ' checked="checked"' : '') +
								' />';
							html += "</td><td align=\"center\" width='45%'>" + group.title + "</td><td align=\"center\" width='45%'>" + group.members + "</td></tr>";
						});

						html += "</table>";
					} else {
						html += $E.translate('nogroupsfound');
					}
					html += '</div>';
					html += "</fieldset>";
				});
			} else {
				html += '<b>'+$E.translate('nogroupsfound')+'</b>';
			}

			$('#'+target+'sharing-grouplist').html(html);
			// set default checkboxes for category
			if (typeof sharedgroupsarr != 'undefined') { // In view sharing this array is undefined
				if (sharedgroupsarr !== undefined && sharedgroupsarr.length > 0) {
					$.each(sharedgroupsarr, function(tmp, groupid){
						$('#mform1 #internaccess-groups input:checkbox[value='+groupid+']').attr("checked", true);
					})
				}
			}
			if (typeof structure_sharedgroupsarr != 'undefined') {
				if (structure_sharedgroupsarr !== undefined && structure_sharedgroupsarr.length > 0) {
					$.each(structure_sharedgroupsarr, function(tmp, groupid){
						$('#mform1 #structure_sharing-groups input:checkbox[value='+groupid+']').attr("checked", true);
					})
				}
			};

			$('#'+target+'sharing-grouplist .sharegroups-check-all').click(function(){
				// check/uncheck all groups in this course
				$('#'+target+'sharing-grouplist .sharegroups:checkbox[courseid='+$(this).attr('courseid')+']')
					.prop('checked', $(this).is(':checked'))
					// execute click handler
					.each(function(){
						// wrapped in each, because triggerHandler only works on first element
						$(this).triggerHandler('click');
					});
			});

			// stop slow loading
			$('#'+target+'sharing-grouplist .sharegroups:checkbox').click(function(){
				// check/uncheck all groups
				var $courseCheckboxes = $('#'+target+'sharing-grouplist .sharegroups:checkbox[courseid='+$(this).attr('courseid')+']');
				$('#'+target+'sharing-grouplist .sharegroups-check-all[courseid='+$(this).attr('courseid')+']').prop('checked', $courseCheckboxes.not(':checked').length == 0);
			});
			$('.course-group-content').each(function(){
				var flag = 0;
				$(this).find( 'table > tbody > tr > td > input.sharegroups').each(function(){
					if (flag==1)
						return false;
					if ($(this).prop('checked')==false)
						flag = 1;
				});
				if (flag == 0) {
					$(this).find('table > tbody > tr > td > input.sharegroups-check-all').prop('checked', true);
				}
			});

			// open/close course group
			$('.course-group-title').on('click', function(){
				$(this).closest('.course-group').toggleClass('course-group-open');
			});
			// open all shared courses
			$('.course-group').has('input:checked').addClass('course-group-open');
		});
	},

	popup: function(config) {

		var popup = this.last_popup = new M.core.dialogue({
			headerContent: config.headerContent || config.title || 'Popup',

			bodyContent: '',
			visible: true, //by default it is not displayed
			modal: false, // sollte true sein, aber wegen moodle bug springt dann das fenster immer nach oben
			zIndex: 1000,
			height: config.height || '80%',
			width: config.width || '85%',
		});

		// disable scrollbars
		$(window).disablescroll();

		popup.$body = $(popup.bodyNode.getDOMNode());
		popup.$body.css('overflow', 'auto');

		// body mit jquery injecten, dadurch werden z.b. auch javascripts ausgeführt
		// bei anabe im popup constructor eben nicht
		if (config.bodyContent) {
			popup.$body.html(config.bodyContent);
		}

		// hack my own overlay, because moodle dialogue modal is not working
		var overlay = $('<div style="opacity:0.7; filter: alpha(opacity=20); background-color:#000; width:100%; height:100%; z-index:10; top:0; left:0; position:fixed;"></div>')
			.appendTo('body');
		// hide popup when clicking overlay
		overlay.click(function(){
			popup.hide();
		});

		var orig_hide = popup.hide;
		popup.hide = function() {

			if (config.onhide) {
				config.onhide();
			}

			// remove overlay, when hiding popup
			overlay.remove();

			// enable scrolling
			$(window).disablescroll('undo');

			// call original popup.hide()
			orig_hide.call(popup);
		};

		popup.remove = function(){
			if (this.$body.is(':visible')) {
				this.hide();
			}

			this.destroy();
		};

		return popup;
	},
};

$(function(){
	if ($('body').attr('class').match(/course-([^\s]+)/)) {
		$E.courseid = RegExp.$1;
	}
});
	
})();
