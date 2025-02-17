<?php
/*

	File: king-include/king-page-unanswered.php
	Description: Controller for page listing recent questions without upvoted/selected/any answers


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

	require_once QA_INCLUDE_DIR.'king-db/selects.php';
	require_once QA_INCLUDE_DIR.'king-app/format.php';
	require_once QA_INCLUDE_DIR.'king-app/q-list.php';

	if (QA_ALLOW_UNINDEXED_QUERIES) {
		$categoryslugs=qa_request_parts(1);
	} else {
		$categoryslugs=null;
	}

	$by=qa_get('by');
	$start=qa_get_start();
	$userid=qa_get_logged_in_userid();

	switch ($by) {
		case 'images':
			$selectby='images';
			break;
		case 'news':
			$selectby='news';
			break;
		case 'poll':
			$selectby='poll';
			break;
		case 'list':
			$selectby='list';
			break;
		case 'trivia':
			$selectby='trivia';
			break;
		case 'music':
			$selectby='music';
			break;
		default:
			$selectby='postformat';
			break;
	}
	$pagesize = qa_opt('page_size_una_qs');
	list($questions, $fcount)=qa_db_select_with_pending(
		qa_db_unanswered_qs_selectspec($userid, $selectby, $start, $categoryslugs, false, false, $pagesize),
		qa_db_unanswered_qs_selectspec($userid, $selectby, 0, $categoryslugs, false, false, 999999),
	);



	$feedpathprefix=null;
	$linkparams=array('by' => $by);

	switch ($by) {
		case 'images':
				$sometitle=qa_lang_html('main/image');
				$nonetitle=qa_lang_html('main/no_unselected_qs_found');
				$output = '<span class="cat-title" ' . ( (null !==qa_opt('i_color')) ? 'style="background-color: ' . qa_opt('i_color') . ';"' : '') . '>'.qa_lang_html('main/image').'</span>';
				$count=qa_opt('cache_unselqcount');
			break;
		case 'news':
				$sometitle=qa_lang_html('main/news');
				$nonetitle=qa_lang_html('main/news');
				$output = '<span class="cat-title" ' . ( (null !==qa_opt('n_color')) ? 'style="background-color: ' . qa_opt('n_color') . ';"' : '') . '>'.qa_lang_html('main/news').'</span>';
				$count=qa_opt('cache_unupaqcount');

			break;
		case 'poll':
				$sometitle=qa_lang_html('main/poll');
				$nonetitle=qa_lang_html('main/poll');
				$output = '<span class="cat-title" ' . ( (null !==qa_opt('p_color')) ? 'style="background-color: ' . qa_opt('p_color') . ';"' : '') . '>'.qa_lang_html('main/poll').'</span>';
			break;
		case 'list':
				$sometitle=qa_lang_html('main/list');
				$nonetitle=qa_lang_html('main/list');
				$output = '<span class="cat-title" ' . ( (null !==qa_opt('l_color')) ? 'style="background-color: ' . qa_opt('l_color') . ';"' : '') . '>'.qa_lang_html('main/list').'</span>';
			break;
		case 'trivia':
				$sometitle=qa_lang_html('main/trivia');
				$nonetitle=qa_lang_html('main/trivia');
				$output = '<span class="cat-title" ' . ( (null !==qa_opt('t_color')) ? 'style="background-color: ' . qa_opt('t_color') . ';"' : '') . '>'.qa_lang_html('main/trivia').'</span>';
			break;
		case 'music':
				$sometitle=qa_lang_html('main/music');
				$nonetitle=qa_lang_html('main/music');
				$output = '<span class="cat-title" ' . ( (null !==qa_opt('m_color')) ? 'style="background-color: ' . qa_opt('m_color') . ';"' : '') . '>'.qa_lang_html('main/music').'</span>';
			break;
		default:
			$feedpathprefix=qa_opt('feed_for_unanswered') ? 'unanswered' : null;

			$linkparams = array();
				$sometitle=qa_lang_html('main/video');
				$nonetitle=qa_lang_html('main/no_una_questions_found');
				$output = '<span class="cat-title" ' . ( (null !==qa_opt('v_color')) ? 'style="background-color: ' . qa_opt('v_color') . ';"' : '') . '>'.qa_lang_html('main/video').'</span>';
				$count=qa_opt('cache_unaqcount');

			break;
	}

$count = count($fcount);
//	Prepare and return content for theme

	$qa_content=qa_q_list_page_content(
		$questions, // questions
		$pagesize, // questions per page
		$start, // start offset
		$count, // total count
		$sometitle, // title if some questions
		$nonetitle, // title if no questions
		null, // categories for navigation (null if not shown on this page)
		null, // selected category id (null if not relevant)
		false, // show question counts in category navigation
		QA_ALLOW_UNINDEXED_QUERIES ? 'type/' : null, // prefix for links in category navigation (null if no navigation)
		$feedpathprefix, // prefix for RSS feed paths (null to hide)
		null, // suggest what to do next
		$linkparams, // extra parameters for page links
		$linkparams // category nav params
	);
	if (!$count) {
		$qa_content['custom'] = '<div class="nopost"><i class="far fa-frown-open fa-4x"></i> '.qa_lang_html('main/no_unselected_qs_found').'</div>';
	}
	$qa_content['navigation']['sub']=qa_unanswered_sub_navigation($by, $categoryslugs);
	$qa_content['page_links'] = qa_html_page_links(qa_request(), $start, $pagesize, $count, qa_opt('pages_prev_next'), $linkparams, true);
	$qa_content['class']=' full-page';
	$qa_content['header'] = $output;
	return $qa_content;


/*
	Omit PHP closing tag to help avoid accidental output
*/