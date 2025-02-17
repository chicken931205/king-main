<?php
/*
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

if (!defined('QA_VERSION')) {
    // don't allow this page to be requested directly from browser
    header('Location: ../');
    exit;
}

require_once QA_INCLUDE_DIR . 'king-app/format.php';
require_once QA_INCLUDE_DIR . 'king-app/limits.php';
require_once QA_INCLUDE_DIR . 'king-app-video.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';
require_once QA_INCLUDE_DIR . 'king-util/sort.php';

//  Check whether this is a follow-on question and get some info we need from the database

$in = array();

$followpostid     = qa_get('follow');
$in['categoryid'] = qa_clicked('doask') ? qa_get_category_field_value('category') : qa_get('cat');
$userid           = qa_get_logged_in_userid();

list($categories, $followanswer, $completetags) = qa_db_select_with_pending(
    qa_db_category_nav_selectspec($in['categoryid'], true),
    isset($followpostid) ? qa_db_full_post_selectspec($userid, $followpostid) : null,
    qa_db_popular_tags_selectspec(0, QA_DB_RETRIEVE_COMPLETE_TAGS)
);

if (!isset($categories[$in['categoryid']])) {
    $in['categoryid'] = null;
}

if (@$followanswer['basetype'] != 'A') {
    $followanswer = null;
}

//  Check for permission error

$permiterror = qa_user_maximum_permit_error('permit_post_q', QA_LIMIT_QUESTIONS);

if ($permiterror || qa_opt( 'disable_video' )) {
    $qa_content = qa_content_prepare();

    // The 'approve', 'login', 'confirm', 'limit', 'userblock', 'ipblock' permission errors are reported to the user here
    // The other option ('level') prevents the menu option being shown, in qa_content_prepare(...)

    switch ($permiterror) {
        case 'login':
        $qa_content['error']=qa_lang_html('users/no_permission');
        $econtent = qa_insert_login_links(qa_lang_html('question/ask_must_login'), qa_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
        break;

        case 'confirm':
        $qa_content['error']=qa_lang_html('users/no_permission');
        $econtent = qa_insert_login_links(qa_lang_html('question/ask_must_confirm'), qa_request(), isset($followpostid) ? array('follow' => $followpostid) : null);
        break;

        case 'limit':
        $qa_content['error']=qa_lang_html('users/no_permission');
        $econtent=qa_lang_html('question/ask_limit');
        break;

        case 'membership':
        $qa_content['error']=qa_lang_html('users/no_permission');
        $econtent=qa_insert_login_links(qa_lang_html('misc/mem_message'));
        $qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-fingerprint fa-4x"></i><p>'.$econtent.'</p><a href="'. qa_path_html( 'membership' ) .'" class="meme-button">'.qa_lang_html('misc/see_plans').'</a></div>';
        break;

        case 'approve':
        $qa_content['error']=qa_lang_html('users/no_permission');
        $econtent=qa_lang_html('question/ask_must_be_approved');
        break;

        default:
        $econtent=qa_lang_html('users/no_permission');
        $qa_content['error']=qa_lang_html('users/no_permission');
        break;
    }
    if (empty($qa_content['custom'] )) {
        $qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-circle-user fa-4x"></i>'.$econtent.'</div>';
    }
    return $qa_content;
}
if ( qa_opt('enable_credits') && qa_opt('post_cre') && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN ) {
	require_once QA_INCLUDE_DIR . 'king-db/metas.php';
	$qa_content = qa_content_prepare();
	$pcre = qa_opt('post_cre');
	$cre = (INT)qa_db_usermeta_get($userid, 'credit');
	if( $pcre > $cre ) {
		$qa_content['custom'] = '<div class="nopost"><i class="fa-solid fa-fingerprint fa-2x"></i>'.qa_lang_html( 'misc/crepost' ).'</div>';
		return $qa_content;
	}
}
$captchareason = qa_user_captcha_reason();

$in['title'] = qa_get_post_title('title'); // allow title and tags to be posted by an external form
$in['extra'] = qa_post_text('extra');
if (qa_using_tags()) {
    $in['tags'] = qa_get_tags_field_value('tags');
}

if (qa_clicked('doask')) {
    require_once QA_INCLUDE_DIR . 'king-app/post-create.php';
    require_once QA_INCLUDE_DIR . 'king-util/string.php';

    $categoryids    = array_keys(qa_category_path($categories, @$in['categoryid']));
    $userlevel      = qa_user_level_for_categories($categoryids);
    $in['name']     = qa_post_text('name');
    $in['notify']   = strlen((string)qa_post_text('notify')) > 0;
    $in['nsfw']     = qa_post_text('nsfw');
    $in['prvt']   = qa_post_text('prvt');
    $in['email']    = qa_post_text('email');
    $in['queued']   = qa_user_moderation_reason($userlevel) !== false;
    $in['pcontent'] = qa_post_text('pcontent');
    $in['kiframe'] = qa_post_text('kiframe');
    qa_get_post_content('editor', 'content', $in['editor'], $in['content'], $in['format'], $in['text']);

    $errors = array();

    if (!qa_check_form_security_code('ask', qa_post_text('code'))) {
        $errors['page'] = qa_lang_html('misc/form_security_again');
    } else {
        $filtermodules = qa_load_modules_with('filter', 'filter_question');
        foreach ($filtermodules as $filtermodule) {
            $oldin = $in;
            $filtermodule->filter_question($in, $errors, null);
            qa_update_post_text($in, $oldin);
        }

        if (qa_using_categories() && count($categories) && (!qa_opt('allow_no_category')) && !isset($in['categoryid'])) {
            $errors['categoryid'] = qa_lang_html('question/category_required'); // check this here because we need to know count($categories)
        } elseif (qa_user_permit_error('permit_post_q', null, $userlevel)) {
            $errors['categoryid'] = qa_lang_html('question/category_ask_not_allowed');
        }

        if ($captchareason) {
            require_once QA_INCLUDE_DIR . 'king-app/captcha.php';
            qa_captcha_validate_post($errors);
        }
		if (qa_opt('enable_credits') && $pcre) {
			$chkk = king_spend_credit($pcre);
			if(!$chkk) {
				$errors['page'] = qa_lang_html('misc/crepost');
			}
		}
        if (empty($errors)) {
            $cookieid = isset($userid) ? qa_cookie_get() : qa_cookie_get_create(); // create a new cookie if necessary

            $videocontent = $in['extra'];
            $type         = kingsource($videocontent);

            if ($type == "dailymotion.com" || $type == "metacafe.com" || $type == "vine.co" || $type == "vid.me" || $type == "drive.google.com" || $type == "open.spotify.com" || $type == "twitter.com" || $type == "xvideos.com" || $type == "rutube.ru") {
                $thumb2 = get_thumb($videocontent);
            } elseif ($type == "instagram.com") {
                $thumb2 = getInstagramMediaUrl($videocontent);
            } elseif ($type == "vimeo.com") {
                $thumb2 = get_vimeo($videocontent);
            } elseif ($type == "xhamster.com") {
                $thumb2 = king_xhamster($videocontent);
            } elseif ($type == "ok.ru") {
                $thumb2 = king_okru($videocontent);
            } elseif ($type == "tiktok.com") {
                $thumb2 = king_tiktok($videocontent);
            } elseif ($type == "coub.com") {
                $thumb2 = coub_thumb($videocontent);
            } elseif ($type == "gfycat.com") {
                $thumb2 = king_gfycat($videocontent);
            } elseif ($type == "youtube.com") {
                $thumb2 = king_youtube($videocontent);
            } elseif ($type == "facebook.com") {
                $thumb2 = king_facebook($videocontent);
            } elseif ($type == "soundcloud.com") {
                $thumb2 = get_thumb($videocontent);
            } elseif ($type == "vk.com") {
                $thumb2 = king_vk($videocontent);
            } elseif ($type == "my.mail.ru") {
                $thumb2 = king_mailru($videocontent);
            } elseif ($type == "twitch.tv") {
                $thumb2 = king_twitch($videocontent);
            } else {
                $thumb = qa_post_text('submit_image');
                if ( empty( $thumb ) ) {
                    $thumb = qa_post_text('news_thumb');
                }
            }
            if ($thumb2) {
                $thumb = king_urlupload($thumb2);
            }
            $questionid = qa_question_create(
                $followanswer,
                $userid,
                qa_get_logged_in_handle(),
                $cookieid,
                $in['title'],
                $thumb,
                $in['format'],
                $in['text'],
                isset($in['tags']) ? qa_tags_to_tagstring($in['tags']) : '',
                $in['notify'],
                $in['email'],
                $in['categoryid'],
                $videocontent,
                $in['queued'],
                $in['name'],
                'V',
                $in['pcontent'],
                $in['nsfw']
            );

            if (isset($in['kiframe'])) {
                qa_db_postmeta_set($questionid, 'kiframe', $in['kiframe']);
            }
            if (isset($in['prvt'])) {
                require_once QA_INCLUDE_DIR . 'king-app/posts.php';
                qa_post_set_hidden($questionid, true, null);
            }

            qa_redirect(qa_q_request($questionid, $in['title'])); // our work is done here
        }
    }
}

//  Prepare content for theme

$qa_content = qa_content_prepare(false, array_keys(qa_category_path($categories, @$in['categoryid'])));

$qa_content['title'] = qa_lang_html(isset($followanswer) ? 'question/ask_follow_title' : 'main/video');
$qa_content['error'] = @$errors['page'];

$field['label'] = qa_lang_html('question/q_content_label');
$field['error'] = qa_html(@$errors['content']);

$custom = qa_opt('show_custom_ask') ? trim(qa_opt('custom_ask')) : '';
$vidupload = '';
if (qa_opt('links_in_new_window') || qa_opt('video_iframe')) {
    $vidupload .= '<ul class="nav-tabs" role="tablist">';
    $vidupload .= '<li class="active"><a href="#vidurl" aria-controls="vidurl" class="king-vidurl" role="tab" data-toggle="tab"><i class="fas fa-link"></i></a></li>';
    if (qa_opt('links_in_new_window')) {
        $vidupload .= '<li><a href="#vidup" aria-controls="vidup" class="king-vidup" role="tab" data-toggle="tab"><i class="fas fa-cloud-upload-alt"></i></a></li>';
    }
    if (qa_opt('video_iframe')) {
        $vidupload .= '<li><a href="#ifrme" aria-controls="ifrme" class="king-ifrme" role="tab" data-toggle="tab"><i class="fa-solid fa-crop-simple"></i></a></li>';
    }
    $vidupload .= '</ul>';
}
    $vidupload .= '<div id="vidurl" role="tabpanel" class="tabcontent active">
                    <div class="videoembedup">
                    <input placeholder="'.qa_lang_html('misc/video_url').'" name="extra" value="' . qa_html(@$in['extra']) . '"  id="content2" autocomplete="off" type="text" class="king-form-tall-text" onchange="video_add(this, this.value)" onInput="video_add(this, this.value)">
                    <div id="videoembed"><i class="fas fa-compact-disc"></i></div>
                    </div>
                </div>';
if (qa_opt('links_in_new_window')) {
    $vidupload .= '<div class="tabcontent" role="tabpanel" id="vidup">';
    $vidupload .= '<div class="vid-upload">';
    if ( ! qa_opt( 'video_ffmpeg' ) ) {

        $vidupload .= '<div id="newsthumb" class="dropzone king-poll-file"><div class="vid-upload-label">'.qa_lang_html('misc/select_thumb').'</div></div>';
    }

    $vidupload .= '<div id="viddropzone" class="dropzone king-poll-file"><div class="vid-upload-label">'.qa_lang_html('misc/video_file').'</div></div>';
    $vidupload .= '</div>';
    $vidupload .= '</div>';
}
if (qa_opt('video_iframe')) {
$vidupload .= '<div class="tabcontent" role="tabpanel" id="ifrme">';
$vidupload .= '<div class="vid-upload">';
$vidupload .= '<textarea name="kiframe" id="kiframe" autocomplete="off" type="textarea" value="" class="king-form-tall-text" placeholder="'.qa_lang_html('misc/video_iframe').'"></textarea>';
$vidupload .= '<div id="newsthumb" class="dropzone king-poll-file"><div class="vid-upload-label">'.qa_lang_html('misc/select_thumb').'</div></div>';
$vidupload .= '</div>';
$vidupload .= '</div>';
}
if (qa_opt('enable_aws')) {
    $awscla = 'pcontentaws';
} else {
    $awscla = 'pconteno';
}
$qa_content['form'] = array(
    'tags'    => 'name="ask" method="post" ENCTYPE="multipart/form-data" action="' . qa_self_html() . '"',

    'style'   => 'tall',

    'fields'  => array(
        'custom'   => array(
            'type' => 'custom',
            'html' => '<div class="snote">' . $custom . '</div>',
        ),

        'title'    => array(
            'label' => qa_lang_html('question/q_title_label'),
            'tags'  => 'name="title" id="title" autocomplete="off" minlength="'.qa_opt('min_len_q_title').'" required',
            'value' => qa_html(@$in['title']),
            'error' => qa_html(@$errors['title']),
        ),

        'similar'  => array(
            'type' => 'custom',
            'html' => '<span id="similar"></span>',
        ),

        'content'  => array(
            'type'  => 'custom',
            'html'  => $vidupload,
            'error' => qa_html(@$errors['content']),
        ),
        'pcontent' => array(
            'label' => qa_lang_html('question/q_content_post'),
            'type'  => 'custom',
            'html'  => '<div id="pcontent" class="'.$awscla.'">' . qa_html(@$in['pcontent']) . '</div>',
            'error' => qa_html(@$errors['pcontent']),
        ),

    ),

    'buttons' => array(
        'ask' => array(
            'tags'  => 'onclick="qa_show_waiting_after(this, false);"',
            'label' => qa_lang_html('question/ask_button'),
        ),
    ),

    'hidden'  => array(
        'code'  => qa_get_form_security_code('ask'),
        'doask' => '1',
    ),
);
script_options($qa_content);
if (!strlen($custom)) {
    unset($qa_content['form']['fields']['custom']);
}

if (qa_opt('do_ask_check_qs') || qa_opt('do_example_tags')) {
    $qa_content['script_rel'][] = 'king-content/king-ask.js?' . QA_VERSION;
    $qa_content['form']['fields']['title']['tags'] .= ' onchange="qa_title_change(this.value);"';

    if (strlen(@$in['title'])) {
        $qa_content['script_onloads'][] = 'qa_title_change(' . qa_js($in['title']) . ');';
    }
}

if (isset($followanswer)) {
    $viewer = qa_load_viewer($followanswer['content'], $followanswer['format']);

    $field = array(
        'type'  => 'static',
        'label' => qa_lang_html('question/ask_follow_from_a'),
        'value' => $viewer->get_html($followanswer['content'], $followanswer['format'], array('blockwordspreg' => qa_get_block_words_preg())),
    );

    qa_array_insert($qa_content['form']['fields'], 'title', array('follows' => $field));
}

if (qa_using_categories() && count($categories)) {
    $field = array(
        'label' => qa_lang_html('question/q_category_label'),
        'error' => qa_html(@$errors['categoryid']),
    );

    qa_set_up_category_field($qa_content, $field, 'category', $categories, $in['categoryid'], true, qa_opt('allow_no_sub_category'));

    if (!qa_opt('allow_no_category')) {
        // don't auto-select a category even though one is required
        $field['options'][''] = '';
    }

    qa_array_insert($qa_content['form']['fields'], 'content', array('category' => $field));
}


if (qa_using_tags()) {
    $field = array(
        'error' => qa_html(@$errors['tags']),
    );

    qa_set_up_tag_field(
        $qa_content,
        $field,
        'tags',
        isset($in['tags']) ? $in['tags'] : array(),
        array(),
        qa_opt('do_complete_tags') ? array_keys($completetags) : array(),
        qa_opt('page_size_ask_tags')
    );

    qa_array_insert($qa_content['form']['fields'], null, array('tags' => $field));
}

if (!isset($userid)) {
    qa_set_up_name_field($qa_content, $qa_content['form']['fields'], @$in['name']);
}

if ( qa_opt('enable_nsfw') || qa_opt('enable_pposts') ) {
	$nsfw = '';
	$prvt = '';
	if ( qa_opt('enable_pposts') ) {
		$prvt = '<input name="prvt" id="king_prvt" type="checkbox" class="hide" value="'.qa_html(@$in['prvt']).'"><label for="king_prvt" class="king-nsfw"><i class="fa-solid fa-user-ninja"></i> '.qa_lang('misc/prvt').'</label>';
	}
	if ( qa_opt('enable_nsfw') ) {
		$nsfw = '<input name="nsfw" id="king_nsfw" type="checkbox" value="'.qa_html(@$in['nsfw']).'"><label for="king_nsfw" class="king-nsfw">'.qa_lang_html('misc/nsfw').'</label>';
	}
	$field = array(
		'type' => 'custom',
		'html' => ''.$prvt.$nsfw.''
	);
	qa_array_insert($qa_content['form']['fields'], null, array('nsfw' => $field));
}

if ($captchareason) {
    require_once QA_INCLUDE_DIR . 'king-app/captcha.php';
    qa_set_up_captcha_field($qa_content, $qa_content['form']['fields'], @$errors, qa_captcha_reason_note($captchareason));
}

$qa_content['focusid'] = 'title';
$qa_content['header'] = $qa_content['title'];
return $qa_content;

/*
Omit PHP closing tag to help avoid accidental output
 */
