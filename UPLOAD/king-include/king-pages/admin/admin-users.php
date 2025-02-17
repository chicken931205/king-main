<?php
/*

	File: king-include/king-page-admin-approve.php
	Description: Controller for admin page showing new users waiting for approval


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: LICENCE.html
*/

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once QA_INCLUDE_DIR.'king-app/admin.php';
	require_once QA_INCLUDE_DIR.'king-db/admin.php';


//	Check we're not using single-sign on integration

	if (QA_FINAL_EXTERNAL_USERS)
		qa_fatal_error('User accounts are handled by external code');


//	Find most flagged questions, answers, comments

	$userid=qa_get_logged_in_userid();
	$start = qa_get_start();
	$users=qa_db_select_with_pending(qa_db_top_users_selectspec($start, 20));
	$userfields=qa_db_select_with_pending(qa_db_userfields_selectspec());
	$usercount = qa_opt('cache_userpointscount');
	$pagesize = 20;
	$users = array_slice($users, 0, $pagesize);	
	$usershtml = qa_userids_handles_html($users);
//	Check admin privileges (do late to allow one DB query)

	if (qa_get_logged_in_level()<QA_USER_LEVEL_MODERATOR) {
		$qa_content=qa_content_prepare();
		$qa_content['error']=qa_lang_html('users/no_permission');
		return $qa_content;
	}



//	Check to see if any were approved or blocked here

	$pageerror=qa_admin_check_clicks();


//	Prepare content for theme

	$qa_content=qa_content_prepare();

	$qa_content['title']=qa_lang_html('admin/approve_users_title');
	$qa_content['error']=isset($pageerror) ? $pageerror : qa_admin_page_error();

	$categoryslugs=qa_request_parts(1);
	$countslugs=count($categoryslugs);


	$output = '<script type="text/javascript">
$(document).on("submit", ".king-editusers-form", function(event)
{
    event.preventDefault();    
   var id = $(this).closest("form").attr("id");
    $.ajax({
        url: $(this).attr("action"),
        type: $(this).attr("method"),
        data: new FormData(this),
        processData: false,
        contentType: false,
		success: function (data)
        {
			$(\'.\'+id+\'\').fadeOut();
        },
        error: function (xhr, desc, err)
        {
alert(err)

        }
    });        
});
	        </script>';
	if (qa_check_form_security_code('editusers', qa_post_text('code')) && isset($_POST['deleteid'])) {
		require_once QA_INCLUDE_DIR.'king-app/users-edit.php';
		qa_delete_user($_POST['deleteid']);
	}
	if (count($users)) {
	$output .= '<table class="editusers-table">';
	$output .= '<tr><th>'.qa_lang_html('misc/userid').'</th><th>'.qa_lang_html('misc/username').'</th><th>'.qa_lang_html('admin/emails_title').'</th><th>'.qa_lang_html('misc/posts').'</th><th>'.qa_lang_html('question/edit_button').'</th><th>'.qa_lang_html('question/delete_button').'</th></tr>';		
		foreach ($users as $user) {
				$avatarhtml = get_avatar($user['avatarblobid'], '40');
			
			$qposts = qa_db_read_one_value(qa_db_query_sub("SELECT qposts FROM ^userpoints WHERE userid=#", $user['userid']));
			$output .= '<tr class="kingeditli editusers-'.$user['userid'].'">';
			$output .= '<td><strong>'.$user['userid'].'</strong></td>';
			$output .= '<td>'.$avatarhtml.'<a href="'.qa_path_html('user/'.$user['handle']).'" target="_blank">'.$user['handle'] . '</a></td>';
			$output .= '<td><a href="mailto:'.qa_html($user['email']).'">'.qa_html($user['email']).'</a></td>';
			$output .= '<td>'.$qposts.'</td>';
			$output .= '<td><a class="king-edit-button" href="'.qa_path_html('user/'.$user['handle'].'/profile', array('state' => 'edit')).'" target="_blank">'.qa_lang_html('question/edit_button').'</a>';
			$output .= '<td>';
			if ( $user['level'] !== '120' ) {
				$output .= '<form method="POST" class="king-editusers-form" id="editusers-'.$user['userid'].'">';
				$output .= '<input type="hidden" name="deleteid" value="'.$user['userid'].'">';
				$output .= '<input type="submit" class="king-edit-button" name="submit" value="'.qa_lang_html('question/delete_button').'">';
				$output .= '<input type="hidden" name="code" value="'.qa_get_form_security_code('editusers').'">';
				$output .= '</form>';
			}
			$output .= '</td>';			
			$output .= '</tr>';
		}
		$output .= '</tr></table>';
	} else {
		$qa_content['title']=qa_lang_html('admin/no_unapproved_found');
	}
	$qa_content['custom']=$output;
	$qa_content['page_links'] = qa_html_page_links(qa_request(), $start, $pagesize, $usercount, qa_opt('pages_prev_next'));
	$qa_content['navigation']['sub']=qa_admin_sub_navigation();
	$qa_content['navigation']['kingsub']=king_sub_navigation();

	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/
