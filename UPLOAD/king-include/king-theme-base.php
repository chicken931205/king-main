<?php
/*

	File: king-include/king-theme-base.php
	Description: Default theme class, broken into lots of little functions for easy overriding


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


/*
	How do I make a theme which goes beyond CSS to actually modify the HTML output?

	Create a file named king-theme.php in your new theme directory which defines a class qa_html_theme
	that extends this base class qa_html_theme_base. You can then override any of the methods below,
	referring back to the default method using double colon (qa_html_theme_base::) notation.

	Plugins can also do something similar by using a layer. For more information and to see some example
	code, please consult the online KINGMEDIA documentation.
*/

class qa_html_theme_base
{
	public $template;
	public $content;
	public $rooturl;
	public $request;
	public $isRTL; // (boolean) whether text direction is Right-To-Left

	protected $indent = 0;
	protected $lines = 0;
	protected $context = array();

	// whether to use new block layout in rankings (true) or fall back to tables (false)
	protected $ranking_block_layout = false;


	public function __construct($template, $content, $rooturl, $request)
/*
	Initialize the object and assign local variables
*/
	{
		$this->template = $template;
		$this->content = $content;
		$this->rooturl = $rooturl;
		$this->request = $request;

		$this->isRTL = isset($content['direction']) && $content['direction'] === 'rtl';
	}

	/**
	 * @deprecated PHP4-style constructor deprecated from 1.7; please use proper `__construct`
	 * function instead.
	 */
	public function qa_html_theme_base($template, $content, $rooturl, $request)
	{
		self::__construct($template, $content, $rooturl, $request);
	}


	public function output_array($elements)
/*
	Output each element in $elements on a separate line, with automatic HTML indenting.
	This should be passed markup which uses the <tag/> form for unpaired tags, to help keep
	track of indenting, although its actual output converts these to <tag> for W3C validation
*/
	{
		foreach ($elements as $element) {
			$delta = substr_count((string)$element, '<') - substr_count((string)$element, '<!') - 2*substr_count((string)$element, '</') - substr_count((string)$element, '/>');

			if ($delta < 0)
				$this->indent += $delta;

			echo str_repeat("\t", max(0, $this->indent)).str_replace('/>', '>', (string)$element)."\n";

			if ($delta > 0)
				$this->indent += $delta;

			$this->lines++;
		}
	}


	public function output() // other parameters picked up via func_get_args()
/*
	Output each passed parameter on a separate line - see output_array() comments
*/
	{
		$args = func_get_args();
		$this->output_array($args);
	}


	public function output_raw($html)
/*
	Output $html at the current indent level, but don't change indent level based on the markup within.
	Useful for user-entered HTML which is unlikely to follow the rules we need to track indenting
*/
	{
		if (strlen((string)$html))
			echo str_repeat("\t", max(0, $this->indent)).$html."\n";
	}


	public function output_split($parts, $class, $outertag='span', $innertag='span', $extraclass=null)
/*
	Output the three elements ['prefix'], ['data'] and ['suffix'] of $parts (if they're defined),
	with appropriate CSS classes based on $class, using $outertag and $innertag in the markup.
*/
	{
		if (empty($parts) && strtolower($outertag) != 'td')
			return;

		$this->output(
			'<'.$outertag.' class="'.$class.(isset($extraclass) ? (' '.$extraclass) : '').'">',
			(strlen($parts['prefix'] ?? '') ? ('<'.$innertag.' class="'.$class.'-pad">'.$parts['prefix'].'</'.$innertag.'>') : '').
			(strlen($parts['data'] ?? '') ? ('<'.$innertag.' class="'.$class.'-data">'.$parts['data'].'</'.$innertag.'>') : '').
			(strlen($parts['suffix'] ?? '') ? ('<'.$innertag.' class="'.$class.'-pad">'.$parts['suffix'].'</'.$innertag.'>') : ''),
			'</'.$outertag.'>'
		);
	}


	public function set_context($key, $value)
/*
	Set some context, which be accessed via $this->context for a function to know where it's being used on the page
*/
	{
		$this->context[$key] = $value;
	}


	public function clear_context($key)
/*
	Clear some context (used at the end of the appropriate loop)
*/
	{
		unset($this->context[$key]);
	}


	public function reorder_parts($parts, $beforekey=null, $reorderrelative=true)
/*
	Reorder the parts of the page according to the $parts array which contains part keys in their new order. Call this
	before main_parts(). See the docs for qa_array_reorder() in king-util/sort.php for the other parameters.
*/
	{
		require_once QA_INCLUDE_DIR.'king-util/sort.php';

		qa_array_reorder($this->content, $parts, $beforekey, $reorderrelative);
	}


	/**
	 * Output the widgets (as provided in $this->content['widgets']) for $region and $place.
	 * @param $region
	 * @param $place
	 */
	public function widgets($region, $place)
	{
		$widgetsHere = isset($this->content['widgets'][$region][$place]) ? $this->content['widgets'][$region][$place] : array();
		
		if (is_array($widgetsHere) && count($widgetsHere) > 0) {

			$this->output('<div class="king-widgets-' . $region . ' king-widgets-' . $region . '-' . $place . '">');
			
			foreach ($widgetsHere as $key => $module ) {
				$wtitle = isset($this->content['wtitle'][$region][$place][$key]) ? $this->content['wtitle'][$region][$place][$key] : null;
				$wextra = isset($this->content['wextra'][$region][$place][$key]) ? $this->content['wextra'][$region][$place][$key] : null;
				$this->output('<div class="king-widget-' . $region . ' king-widget-' . $region . '-' . $place . '">');
				$module->output_widget($region, $place, $this, $this->template, $this->request, $this->content, $wtitle, $wextra);
				$this->output('</div>');
			}

			$this->output('</div>', '');
		}
	}

	/**
	 * Pre-output initialization. Immediately called after loading of the module. Content and template variables are
	 * already setup at this point. Useful to perform layer initialization in the earliest and safest stage possible
	 */
	public function initialize() { }




//	From here on, we have a large number of class methods which output particular pieces of HTML markup
//	The calling chain is initiated from king-page.php, or king-ajax-*.php for refreshing parts of a page,
//	For most HTML elements, the name of the function is similar to the element's CSS class, for example:
//	search() outputs <div class="king-search">, q_list() outputs <div class="king-q-list">, etc...

	public function doctype()
	{
		$this->output('<!DOCTYPE html>');
	}

	public function html()
	{
		$this->output(
			'<html lang="en-US">',
			'<!-- Created by KingMedia -->'
		);
		$this->head();
		$this->body();
		$this->output(
			'<!-- Created by KingMedia with love <3 -->',
			'</html>'
		);
	}

	public function head()
	{
		$this->output(
			'<head>',
			'<meta http-equiv="content-type" content="' . $this->content['content_type'] . '"/>'
		);
		$this->head_title();
		$this->head_metas();
		$this->head_css();
		$this->head_custom_css();
		$this->output('<meta name="viewport" content="width=device-width, initial-scale=1.0">');
		$this->head_links();
		if ($this->template == 'question') {
			if (strlen($this->content['description'] ?? '')) {
				$pagetitle = isset($this->content['title']) ? strip_tags($this->content['title']) : '';
				$pagedesc = isset($this->content['q_view']['raw']['pcontent']) ? strip_tags(substr($this->content['q_view']['raw']['pcontent'],0,100)).'...' : '';

				$img       = king_get_uploads($this->content['description']);
				$this->output('<meta property="og:url" content="' . $this->content['canonical'] . '" />');
				$this->output('<meta property="og:type" content="article" />');
				$this->output('<meta property="og:title" content="' . qa_html($pagetitle) . '" />');
				$this->output('<meta property="og:description" content="'.qa_html($pagedesc).'" />');
				if ($img) {
					$this->output('<meta property="og:image" content="' . qa_html($img['furl']) . '"/>');
					$this->output('<meta name="twitter:image" content="' . qa_html($img['furl']) . '">');
					$this->output('<link rel="image_src" type="image/jpeg" href="' . qa_html($img['furl']) . '" />');
				}
				$this->output('<meta name="twitter:card" content="summary_large_image">');
				$this->output('<meta name="twitter:title" content="' . qa_html($pagetitle) . '">');
				$this->output('<meta name="twitter:description" content="' . qa_html($pagedesc) . '">');
				$this->output('<meta itemprop="description" content="'.qa_html($pagedesc).'">');
				$this->output('<meta itemprop="image" content="' . qa_html( ( isset($img['furl']) ? $img['furl'] :'') ) . '">');
				
			}
		}
		$this->head_lines();
		$this->head_script();
		$this->head_custom();
		$this->output('</head>');
	}
	public function head_custom_css() {
		if ( qa_opt( 'show_home_description' ) ) {
			$this->output( '<STYLE type="text/css"><!--' );
			$this->output( '' . qa_opt( 'home_description' ) . '' );
			$this->output( '//--></STYLE>' );
		}
	}
	public function head_title()
	{
		$pagetitle = strlen((string)$this->request) ? strip_tags($this->content['title'] ?? '') : '';
		$headtitle = (strlen((string)$pagetitle) ? ($pagetitle.' - ') : '').$this->content['site_title'];

		$this->output('<title>'.$headtitle.'</title>');
		
	}

	function head_metas()
	{
		if (qa_opt('show_custom_footer')) {
			$this->output('<meta name="description" content="'.qa_opt('custom_footer').'"/>');
		}
			
		if (strlen($this->content['keywords'] ?? '')) // as far as I know, meta keywords have zero effect on search rankings or listings
			$this->output('<meta name="keywords" content="'.$this->content['keywords'].'"/>');
	}

	public function head_links()
	{
		if (isset($this->content['canonical']))
			$this->output('<link rel="canonical" href="'.$this->content['canonical'].'"/>');

		if (isset($this->content['feed']['url']))
			$this->output('<link rel="alternate" type="application/rss+xml" href="'.$this->content['feed']['url'].'" title="'.@$this->content['feed']['label'].'"/>');

		// convert page links to rel=prev and rel=next tags
		if (isset($this->content['page_links']['items'])) {
			foreach ($this->content['page_links']['items'] as $page_link) {
				if (in_array($page_link['type'], array('prev', 'next')))
					$this->output('<link rel="' . $page_link['type'] . '" href="' . $page_link['url'] . '" />');
			}
		}
	}

	public function head_script()
	{
		if (isset($this->content['script'])) {
			foreach ($this->content['script'] as $scriptline)
				$this->output_raw($scriptline);
		}
	}

	public function head_css() {
		$this->output( '<LINK REL="stylesheet" TYPE="text/css" HREF="' . $this->rooturl . $this->css_name() . '"/>' );
		$this->output( '<link rel="stylesheet" href="' . $this->rooturl . 'font-awesome/css/all.min.css" type="text/css" media="all">' );
		$this->output( '<LINK REL="stylesheet" TYPE="text/css" HREF="' . qa_html( $this->rooturl ) . 'night.css"/>' );

		if ( isset( $this->content['css_src'] ) ) {
			foreach ( $this->content['css_src'] as $css_src ) {
				$this->output( '<LINK REL="stylesheet" TYPE="text/css" HREF="' . $css_src . '"/>' );
			}
		}

		if ( ! empty( $this->content['notices'] ) ) {
			$this->output(
				'<STYLE type="text/css" ><!--',
				'.king-body-js-on .king-notice {display:none;}',
				'//--></STYLE>'
			);
		}
	}

	public function css_name()
	{
		return 'king-styles.css?'.QA_VERSION;
	}

	public function head_lines()
	{
		if (isset($this->content['head_lines'])) {
			foreach ($this->content['head_lines'] as $line)
				$this->output_raw($line);
		}
	}

	public function head_custom()
	{
		// abstract method
	}

	public function body()
	{
		$this->output('<BODY');
		$this->body_tags();
		$this->output('>');
		$this->body_script();
		$this->body_content();
		$this->body_footer();
		$this->king_js();
		$this->output('</BODY>');
	}

	public function body_hidden()
	{
		$indent = $this->isRTL ? '9999px' : '-9999px';
		$this->output('<div style="position:absolute; left:'.$indent.'; top:-9999px;">');
		$this->waiting_template();
		$this->output('</div>');
	}

	public function waiting_template()
	{
		$this->output('<span id="king-waiting-template" class="king-waiting">...</span>');
	}

	public function body_script()
	{
		$this->output(
			'<script>',
			"var b=document.getElementsByTagName('body')[0];",
			"b.className=b.className.replace('king-body-js-off', 'king-body-js-on');",
			'</script>'
		);
	}

	public function body_header() {
		if ( isset( $this->content['body_header'] ) ) {
			if (king_add_free_mode()) {
			$this->output( '<DIV class="ads">' );
			$this->output_raw( $this->content['body_header'] );
			$this->output( '</DIV>' );
			}
		}
	}

	public function body_footer()
	{
		if (isset($this->content['body_footer'])) {
			$this->output_raw($this->content['body_footer']);
		}
		
		$this->king_js_codes();
	}


	public function king_js_codes() {
		
		if ($this->template == 'ask' || $this->template == 'video' || $this->template == 'news' || $this->template == 'poll' || $this->template == 'list' || $this->template == 'edit' || $this->template == 'trivia' || $this->template == 'music' ) {
			$this->output('<script src="' . qa_path_to_root() . 'king-content/js/dropzone.min.js"></script>');
			$this->output('<script src="' . qa_path_to_root() . 'king-content/js/angular/angular.min.js"></script>');
			$this->output('<script src="' . qa_path_to_root() . 'king-content/js/angular/sortable.min.js"></script>');
			$this->output('<script src="' . qa_path_to_root() . 'king-content/js/angular/jquery-ui.min.js"></script>');
			$this->output('<script src="' . qa_path_to_root() . 'king-content/js/angular/app.js"></script>');
			$this->output('<script src="' . qa_path_to_root() . 'king-content/js/angular/appi.js"></script>');
			$this->output('<script src="' . qa_path_to_root() . 'king-content/js/tinymce/tinymce.min.js"></script>');
		}
		$this->output('<script src="' . qa_path_to_root() . 'king-content/js/jquery.magnific-popup.min.js"></script>');		
		$this->output('<script src="' . qa_path_to_root() . 'king-content/js/owl.carousel.min.js"></script>');
		$this->output('<script src="' . qa_path_to_root() . 'king-content/js/videojs/video.min.js"></script>');
		$this->output('<link href="' . qa_path_to_root() . 'king-content/js/videojs/video-js.css" rel="stylesheet" />');
		$this->output('<script src="' . qa_path_to_root() . 'king-content/js/videojs/videojs-playlist.min.js"></script>');
		$this->output('<script src="' . qa_path_to_root() . 'king-content/js/videojs/videojs-playlist-ui.min.js"></script>');
		if ( $this->template == 'question' && ! qa_opt( 'hide_fb_comment' ) ) {
			$this->output('<div id="fb-root"></div>');
			$this->output('<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v13.0&appId=248450565173285&autoLogAppEvents=1" nonce="3jcWXFWL"></script>');
		}
		if ( $this->template == 'membership' ) {
			$this->output('<script src="' . qa_path_to_root() . 'king-content/king-stripe.js" defer></script>');
		}
		

	}
	public function body_content()
	{
		$this->body_prefix();
		$this->notices();
		$this->output('<div class="king-body-wrapper">', '');
		$this->widgets('full', 'top');
		$this->header();
		$this->widgets('full', 'high');
		$this->sidepanel();
		$this->main();
		$this->widgets('full', 'low');
		$this->footer();
		$this->widgets('full', 'bottom');
		$this->output('</div> <!-- END body-wrapper -->');
		$this->body_suffix();
	}

	public function body_tags()
	{
		$content = $this->content;
		$class = 'king-template-'.qa_html($this->template);
		$back = '';
		if (isset($this->content['categoryids'])) {
			foreach ($this->content['categoryids'] as $categoryid)
				$class .= ' king-category-'.qa_html($categoryid);
		}

		$class .= isset( $content['hlogin'] ) ? $content['hlogin'] : '';

		if (isset( $content['hlogin'] ) && qa_opt('login_back')) {
			$back = ' style="background-image: url('.qa_html( qa_path_to_root() . 'king-include/watermark/' . qa_opt('login_back') ).');"';
		}

		$this->output('class="'.$class.' king-body-js-off"'.$back.'');
	}

	public function body_prefix()
	{
		// abstract method
	}

	public function body_suffix()
	{
		// abstract method
	}

	public function notices()
	{
		if (!empty($this->content['notices'])) {
			foreach ($this->content['notices'] as $notice)
				$this->notice($notice);
		}
	}

	public function notice($notice)
	{
		$this->output('<div class="king-notice" id="'.$notice['id'].'">');

		if (isset($notice['form_tags']))
			$this->output('<form '.$notice['form_tags'].'>');

		$this->output_raw($notice['content']);

		$this->output('<button '.$notice['close_tags'].' type="submit" class="king-notice-close-button"><i class="far fa-times-circle"></i></button>');

		if (isset($notice['form_tags'])) {
			$this->form_hidden_elements(@$notice['form_hidden']);
			$this->output('</form>');
		}

		$this->output('</div>');
	}

	public function header()
	{
		$this->output('<div class="king-header">');

		$this->logo();
		$this->nav_user_search();
		$this->nav_main_sub();
		$this->header_clear();

		$this->output('</div> <!-- END king-header -->', '');
	}

	public function nav_user_search() {
		$this->search();
	}


	public function king_cats() {
		$this->output( '<div class="king-cat">' );
		$categories                         = qa_db_single_select(qa_db_category_nav_selectspec(null, true));
		$this->content['navigation']['cat'] = qa_category_navigation($categories);
		$this->nav( 'cat', 4 );
		$this->output( '</div>' );

	}
	public function favorite2() {
		$favorite = isset( $this->content['favorite'] ) ? $this->content['favorite'] : null;

		if ( isset( $favorite ) ) {
			$favoritetags = isset( $favorite['favorite_tags'] ) ? $favorite['favorite_tags'] : '';
			$this->output( '<span class="king-following" ' . $favoritetags . '>' );
			$this->favorite_inner_html2( $favorite );
			$this->output( '</span>' );
		}
	}
	public function favorite_inner_html2( $favorite ) {
		$this->favorite_button( @$favorite['favorite_add_tags'], 'king-favorite' );
		$this->favorite_button( @$favorite['favorite_remove_tags'], 'king-unfavorite' );
	}

	public function kingsubmit() {
		if ( ! qa_opt( 'disable_image' ) || ! qa_opt( 'disable_video' ) || ! qa_opt( 'disable_news' ) || ! qa_opt( 'disable_poll' ) || ! qa_opt( 'disable_list' ) ) {
			$this->output( '<li>' );
			$this->output( '<div class="king-submit">' );

			$this->output( '<span class="kingadd" data-toggle="dropdown" data-target=".king-submit" aria-expanded="false" role="button"><i class="fa-solid fa-circle-plus"></i></span>' );
			$this->output( '<div class="king-dropdown2">' );

			if ( ! qa_opt( 'disable_news' ) ) {
				$this->output( '<a href="' . qa_path_html( 'news' ) . '" class="kingaddnews"><i class="fas fa-newspaper"></i> ' . qa_lang_html( 'main/news' ) . '</a>' );
			}

			if ( ! qa_opt( 'disable_image' ) ) {
				$this->output( '<a href="' . qa_path_html( 'ask' ) . '" class="kingaddimg"><i class="fas fa-image"></i> ' . qa_lang_html( 'main/image' ) . '</a>' );
			}

			if ( ! qa_opt( 'disable_video' ) ) {
				$this->output( '<a href="' . qa_path_html( 'video' ) . '" class="kingaddvideo"><i class="fas fa-video"></i> ' . qa_lang_html( 'main/video' ) . '</a>' );
			}
			if ( ! qa_opt( 'disable_poll' ) ) {
				$this->output( '<a href="' . qa_path_html( 'poll' ) . '" class="kingaddpoll"><i class="fas fa-align-left"></i> ' . qa_lang_html( 'main/poll' ) . '</a>' );
			}

			if ( ! qa_opt( 'disable_list' ) ) {
				$this->output( '<a href="' . qa_path_html( 'list' ) . '" class="kingaddlist"><i class="fas fa-bars"></i> ' . qa_lang_html( 'main/list' ) . '</a>' );
			}
			if ( ! qa_opt( 'disable_trivia' ) ) {
				$this->output( '<a href="' . qa_path_html( 'trivia' ) . '" class="kingaddtrivia"><i class="fas fa-times"></i> ' . qa_lang_html( 'main/trivia' ) . '</a>' );
			}
			if ( ! qa_opt( 'disable_music' ) ) {
				$this->output( '<a href="' . qa_path_html( 'music' ) . '" class="kingaddmusic"><i class="fas fa-headphones-alt"></i> ' . qa_lang_html( 'main/music' ) . '</a>' );
			}
			if ( qa_opt( 'king_leo_enable' ) ) {
				$this->output( '<a href="' . qa_path_html( 'submitai' ) . '" class="kingaddai"><i class="fa-solid fa-atom"></i> ' . qa_lang_html( 'misc/king_ai' ) . '</a>' );
			}			
			$this->output( '</div>' );
			$this->output( '</div>' );
			$this->output( '</li>' );
		}
	}

	public function userpanel() {
		$userid = qa_get_logged_in_userid();
		require_once QA_INCLUDE_DIR . 'king-db/metas.php';
		if (qa_opt('enable_bookmark')) {
			$rlposts  = qa_db_usermeta_get( $userid, 'bookmarks' );
			$result = $rlposts ? unserialize( $rlposts ) : '';
			$count   = ! empty( $result ) ? count( $result ) : 0;
		}

		$this->output( '<li>' );
		$this->output( '<div class="king-havatar" data-toggle="dropdown" data-target=".king-dropdown" aria-expanded="false" >' );
		$this->output( get_avatar( qa_get_logged_in_user_field('avatarblobid'), 40 ) );
		$this->output( '</div>' );
		$this->output( '<div class="king-dropdown">' );
		$this->output( '<a href="' . qa_path_html('user/'.qa_get_logged_in_user_field('handle')) . '" ><h3>' . qa_get_logged_in_user_field('handle') . '</h3></a>' );
		if ( qa_opt('enable_credits') ) {
			$cre = qa_db_usermeta_get($userid, 'credit');
			$ucre = !empty( $cre ) ? $cre : 0;
			$this->output( '<a class="user-box-credits" href="'. qa_path_html( 'membership' ) .'"><i class="fa-solid fa-coins"></i>  <strong>' . qa_html( $ucre ) . '</strong> ' . qa_lang_html( 'misc/credits' ) . '</a>' );
		}
		$this->output( '<span class="user-box-point"><strong>' . qa_html( number_format( qa_get_logged_in_user_field('points') ) ) . '</strong> ' . qa_lang_html( 'admin/points_title' ) . '</span>' );
		$this->output(membership_badge($userid));
		$this->nav( 'user' );
		$this->output( '</div>' );
		$this->output( '</li>' );
		if (qa_opt('enable_bookmark')) {
			$this->output( '<li>' );
			$this->output( '<div class="king-rlater" data-toggle="modal" data-target="#rlatermodal" onclick="return bookmodal();">
						<i class="fa-solid fa-bookmark"></i>
						<input type="hidden" class="king-bmcountin" id="bcount" value="' . qa_html( $count ) . '" />
						<span class="king-bmcount" id="bcounter">' . qa_html( $count ) . '</span>
					</div>' );
			$this->output( '</li>' );
		}
	}

	public function userpanel2() {
		if ( ! qa_is_logged_in() ) {
			$login = @$this->content['navigation']['user']['login'];
			$this->output( '<div id="loginmodal" class="king-modal-login">' );
			$this->output( '<div class="king-modal-content">' );
			$this->output( '<button type="button" class="king-modal-close" data-dismiss="modal" aria-label="Close"><i class="icon fa fa-fw fa-times"></i></button>' );
			$this->output( '<div class="king-modal-header"><h4 class="modal-title">Login</h4></div>' );
			$this->output( '<div class="king-modal-form">' );
			$this->output( '<form action="' . qa_path_html( 'login' ) . '" method="post">
				<input type="text" class="modal-input" name="emailhandle" placeholder="' . trim( qa_lang_html( 'users/email_handle_label' ), ':' ) . '" />
				<input type="password" class="modal-input" name="password" placeholder="' . trim( qa_lang_html( 'users/password_label' ), ':' ) . '" />
				<div id="king-rememberbox"><input type="checkbox" name="remember" id="king-rememberme" value="1"/>
				<label for="king-rememberme" id="king-remember">' . qa_lang_html( 'users/remember' ) . '</label></div>
				<input type="hidden" name="code" value="' . qa_html( qa_get_form_security_code( 'login' ) ) . '"/>
				<input type="submit" value="Sign in" id="king-login" name="dologin" />
				</form>' );

			$this->output( '</div>' );
			$this->output( '<div class="king-modal-footer">' );
			$this->nav( 'user' );
			$this->output( '</div>' );
			$this->output( '<span class="modal-reglink" ><a href="' . qa_path_html( 'register' ) . '">' . qa_lang_html( 'main/nav_register' ) . '</a></span>' );
			$this->output( '</div>' );
			$this->output( '</div>' );
		}
	}
	public function nav_main_sub()
	{
		$this->nav('main');
		$this->nav('sub');
	}

	public function logo()
	{
		$this->output(
			'<div class="king-logo">',
			$this->content['logo'],
			'</div>'
		);
	}

	public function search()
	{
		$search = $this->content['search'];

		$this->output(
			'<div class="king-search">',
			'<div class="king-search-in">',
			'<form ' . qa_sanitize_html($search['form_tags']) . '>',
			qa_sanitize_html($search['form_extra'])
		);

		$this->search_field($search);
		$this->search_button($search);

		$this->output('</form>');
		$populartags=qa_db_single_select(qa_db_popular_tags_selectspec(0, 5));
		$this->output('<div id="king_live_results" class="liveresults">');
		$this->output('<h3>'.qa_lang_html('misc/discover').'</h3>');
		foreach ($populartags as $tag => $count) {
			$this->output('<a class="sresults" href="'.qa_path_html('tag/'.$tag).'" >'.qa_html($tag).'</a>');
		}
		$this->output('</div>');
		$this->output('</div>');
		$this->output('</div>');
	}

	public function search_field($search)
	{
		$this->output('<input type="text" '.$search['field_tags'].' value="'.@$search['value'].'" class="king-search-field" placeholder="'.qa_lang_html('misc/search').'" onkeyup="showResult(this.value)" autocomplete="off"/>');
	}

	public function search_button($search)
	{
		$this->output('<button type="submit" class="king-search-button"/><i class="fas fa-search fa-lg"></i></button>');
	}

	public function nav($navtype, $level = null)
	{
		$navigation = @$this->content['navigation'][$navtype];

		if (($navtype == 'user') || isset($navigation)) {

			if ($navtype == 'user')

			// reverse order of 'opposite' items since they float right
			{
				foreach (array_reverse($navigation, true) as $key => $navlink) {
					if (@$navlink['opposite']) {
						unset($navigation[$key]);
						$navigation[$key] = $navlink;
					}
				}
			}

			$this->set_context('nav_type', $navtype);
			$this->nav_list($navigation, 'nav-' . $navtype, $level);
			$this->nav_clear($navtype);
			$this->clear_context('nav_type');

		}
	}



	public function nav_list($navigation, $class, $level=null)
	{
		$this->output('<ul class="king-'.$class.'-list'.(isset($level) ? (' king-'.$class.'-list-'.$level) : '').'" id="'.$class.'">');

		$index = 0;

		foreach ($navigation as $key => $navlink) {
			$this->set_context('nav_key', $key);
			$this->set_context('nav_index', $index++);
			$this->nav_item($key, $navlink, $class, $level);
		}

		$this->clear_context('nav_key');
		$this->clear_context('nav_index');
		if ( ! qa_opt('hide_trange') && $this->template == 'home'  && $class == 'nav-sub' ) {
			$this->output('<div class="king-flter" onclick="toggleSwitcher(\'#secndnav\', this)" role="button"><i class="fa-solid fa-chart-simple"></i> '.qa_lang_html('main/filter').'</div>');
		}
		$this->output('</ul>');


		if ( ! qa_opt('hide_trange') && $this->template == 'home'  && $class == 'nav-sub' ) {
			$sort = qa_get('sort');
			$ftime = qa_get('time');
			$format = qa_get('format');
			$filter = qa_get('filter');
			// Time options
			$timeOptions = [
				'all' => '',
				'year' => 'year',
				'month' => 'month',
				'week' => 'week'
			];
			
			$ftimeClasses = [
				'all' => '',
				'year' => '',
				'month' => '',
				'week' => ''
			];
			
			
			if (isset($timeOptions[$ftime])) {
				$ftimeClasses[$ftime] = 'active';
				$lang = qa_lang_html('misc/' . $ftime);
			} else {
				$ftimeClasses['all'] = 'active';
				$lang = qa_lang_html('misc/all');
			}
			
			$request = 'home';
			$isActive = !empty($ftime) || !empty($format) || !empty($filter);
			$this->output('<div class="king-secondnav '. ( $isActive ? 'active' : '' ) .'" id="secndnav">');
			$this->output('<div class="king-nav-time">');
			$this->output('<div class="aiplabel">' . qa_lang_html('main/timer') . '</div>');
			$this->output('<div class="king-time-select" data-toggle="dropdown" aria-expanded="false" role="button">' . $lang . '<i class="fa-solid fa-angle-down"></i></div>');
			$this->output('<div class="king-time-drop">');
			
			foreach ($timeOptions as $key => $value) {
				$this->output('<a href="' . qa_path_html($request, array_filter(['sort' => $sort, 'time' => $value, 'format' => $format, 'filter' => $filter]), null, null, 'nav-sub') . '" class="' . $ftimeClasses[$key] . '">' . qa_lang_html('misc/' . $key) . '</a>');
			}
			
			$this->output('</div>');
			$this->output('</div>');
			
			// Format options
			$formatOptions = ['', 'news', 'image', 'video', 'poll', 'list', 'trivia', 'music'];
			$formatLabels = ['none', 'news', 'image', 'video', 'poll', 'list', 'trivia', 'music'];
			$formatactiveClasses = array_fill_keys($formatOptions, '');
			
			// Ensure the format is a valid option
			if (in_array($format, $formatOptions)) {
				$formatactiveClasses[$format] = 'active';
				$lang2 = qa_lang_html('main/' . ( empty( $format ) ? 'none' : $format));
			} else {
				$formatactiveClasses[''] = 'active';
				$lang2 = qa_lang_html('main/none');
			}
			
			$this->output('<div class="king-nav-time">');
			$this->output('<div class="aiplabel">' . qa_lang_html('main/format') . '</div>');
			$this->output('<div class="king-time-select" data-toggle="dropdown" aria-expanded="false" role="button">' . $lang2 . '<i class="fa-solid fa-angle-down"></i></div>');
			$this->output('<div class="king-time-drop">');
			
			foreach ($formatOptions as $index => $value) {
				if ( ! qa_opt('disable_' . $formatLabels[$index]) ) {
					$this->output('<a href="' . qa_path_html($request, array_filter(['sort' => $sort, 'time' => $ftime, 'filter' => $filter, 'format' => $value]), null, null, 'nav-sub') . '" class="' . $formatactiveClasses[$value] . '">' . qa_lang_html('main/' . $formatLabels[$index]) . '</a>');
				}
			}
			
			$this->output('</div>');
			$this->output('</div>');
			
			if(!$sort) {
				$filterOptions = [
					'' => 'latest',
					'ascending' => 'oldest'
				];				
			} else {
				$filterOptions = [
					'' => 'descending',
					'ascending' => 'ascending'
					
				];
			}

			
			$filterClasses = array_fill_keys(array_keys($filterOptions), '');
			
			if (isset($filterOptions[$filter])) {
				$filterClasses[$filter] = 'active';
			} else {
				$filterClasses[''] = 'active'; // Default filter
			}
			
			$this->output('<div class="king-nav-time">');
			$this->output('<div class="aiplabel">' . qa_lang_html('main/order') . '</div>');
			$this->output('<div class="king-time-select" data-toggle="dropdown" aria-expanded="false" role="button">' . qa_lang_html( 'main/' . $filterOptions[$filter] ) . '<i class="fa-solid fa-angle-down"></i></div>');
			$this->output('<div class="king-time-drop">');
			
			foreach ($filterOptions as $key => $label) {
				$this->output('<a href="' . qa_path_html($request, array_filter(['sort' => $sort, 'time' => $ftime, 'format' => $format, 'filter' => $key]), null, null, 'nav-sub') . '" class="' . $filterClasses[$key] . '">' . qa_lang_html('main/' . $label) . '</a>');
			}
			
			$this->output('</div>');
			$this->output('</div>');

			$this->output('</div>');
			
		}


	}

	public function nav_clear($navtype)
	{
		$this->output(
			'<div class="king-nav-'.$navtype.'-clear">',
			'</div>'
		);
	}

	public function nav_item($key, $navlink, $class, $level = null)
	{
		$suffix = strtr($key, array( // map special character in navigation key
			'$' => '',
			'/' => '-',
		));

		$this->output('<li class="king-' . $class . '-item' . (@$navlink['opposite'] ? '-opp' : '') .
			(@$navlink['state'] ? (' king-' . $class . '-' . $navlink['state']) : '') . ' king-' . $class . '-' . $suffix . '">');
		$this->nav_link($navlink, $class);

		$subnav = isset($navlink['subnav']) ? $navlink['subnav'] : array();
		if (is_array($subnav) && count($subnav) > 0) {
			$this->nav_list($subnav, $class, 1 + $level);
		}

		$this->output('</li>');
	}

	public function nav_link( $navlink, $class ) {
		if ( isset( $navlink['url'] ) ) {
			$this->output(
				'<a href="' . $navlink['url'] . '" class="king-' . $class . '-link' .
				( @$navlink['selected'] ? ( ' king-' . $class . '-selected' ) : '' ) .
				( @$navlink['favorited'] ? ( ' king-' . $class . '-favorited' ) : '' ) .
				'"' . ( strlen( $navlink['popup'] ?? '' ) ? ( ' title="' . $navlink['popup'] . '"' ) : '' ) .
				( isset( $navlink['target'] ) ? ( ' target="' . $navlink['target'] . '"' ) : '' ) . '>' . $navlink['label'] .
				'</a>'
			);
		} else {
			$this->output(
				'<span class="king-' . $class . '-nolink' . ( @$navlink['selected'] ? ( ' king-' . $class . '-selected' ) : '' ) .
				( @$navlink['favorited'] ? ( ' king-' . $class . '-favorited' ) : '' ) . '"' .
				( strlen( $navlink['popup'] ?? '' ) ? ( ' title="' . $navlink['popup'] . '"' ) : '' ) .
				'>' . $navlink['label'] . '</span>'
			);
		}

		if ( strlen( $navlink['note'] ?? '' ) ) {
			$this->output( '<span class="king-' . $class . '-note">' . $navlink['note'] . '</span>' );
		}
	}

	public function logged_in()
	{
		$this->output_split(@$this->content['loggedin'], 'king-logged-in', 'div');
	}

	public function header_clear()
	{
		$this->output(
			'<div class="king-header-clear">',
			'</div>'
		);
	}

	public function sidepanel() {
		if (isset($this->content['widgets']['side'])) {
			$this->output( '<DIV CLASS="rightsidebar">' );
			$this->widgets( 'side', 'top' );
			$this->widgets( 'side', 'high' );
			$this->widgets( 'side', 'low' );
			$this->output_raw( @$this->content['sidepanel'] );
			$this->sidebar();
			$this->widgets( 'side', 'bottom' );
			$this->output( '</div>' );
		}
	}

	public function sidebar()
	{
		$sidebar = @$this->content['sidebar'];

		if (!empty($sidebar)) {
			$this->output('<div class="king-sidebar king-widget-wb">');
			$this->output_raw($sidebar);
			$this->output('</div>', '');
		}
	}


	public function main()
	{
		$content = $this->content;

		$this->output('<div class="king-main'.(@$this->content['hidden'] ? ' king-main-hidden' : '').'">');

		$this->widgets('main', 'top');

		$this->page_title_error();

		$this->widgets('main', 'high');

		$this->main_parts($content);

		$this->widgets('main', 'low');

		$this->page_links();
		$this->suggest_next();

		$this->widgets('main', 'bottom');

		$this->output('</div> <!-- END king-main -->', '');
	}

	public function page_title_error()
	{
		if (isset($this->content['title'])) {
			$favorite = isset($this->content['favorite']) ? $this->content['favorite'] : null;

			if (isset($favorite))
				$this->output('<form ' . $favorite['form_tags'] . '>');

			$this->output('<h1>');
			$this->favorite();
			$this->title();
			$this->output('</h1>');

			if (isset($favorite)) {
				$formhidden = isset($favorite['form_hidden']) ? $favorite['form_hidden'] : null;
				$this->form_hidden_elements($formhidden);
				$this->output('</form>');
			}
		}
		if (isset($this->content['error']))
			$this->error($this->content['error']);
	}

	public function favorite()
	{
		$favorite = isset($this->content['favorite']) ? $this->content['favorite'] : null;
		if (isset($favorite)) {
			$favoritetags = isset($favorite['favorite_tags']) ? $favorite['favorite_tags'] : '';
			$this->output('<span class="king-favoriting" ' . $favoritetags . '>');
			$this->favorite_inner_html($favorite);
			$this->output('</span>');
		}
	}

	public function title()
		{
			if (isset($this->content['title'])) {
				$this->output($this->content['title']);
			}
		}

	public function favorite_inner_html($favorite)
	{
		$this->favorite_button(@$favorite['favorite_add_tags'], 'king-favorite');
		$this->favorite_button(@$favorite['favorite_remove_tags'], 'king-unfavorite');
	}

	public function favorite_button( $tags, $class ) {
		if ( isset( $tags ) ) {
			if ( 'king-favorite' == $class ) {
				$follow = qa_lang_html( 'main/nav_follow' );
			} else {
				$follow = qa_lang_html( 'main/nav_unfollow' );
			}

			$this->output( '<button ' . $tags . ' type="submit" value="' . $follow . '" class="' . $class . '-button"><i class="fa-solid fa-heart"></i></button>' );
		}
	}

	public function error($error)
	{
		if (strlen((string)$error)) {
			$this->output(
				'<div class="king-error">',
				$error,
				'</div>'
			);
		}
	}

	public function main_parts($content)
	{
		foreach ($content as $key => $part) {
			$this->set_context('part', $key);
			$this->main_part($key, $part);
		}

		$this->clear_context('part');
	}

	public function main_part( $key, $part ) {
		$partdiv = (
			( strpos( $key, 'custom' ) === 0 ) ||
			( strpos( $key, 'form' ) === 0 ) ||
			( strpos( $key, 'q_list' ) === 0 ) ||
			( strpos( $key, 'q_view' ) === 0 ) ||
			( strpos( $key, 'ranking' ) === 0 ) ||
			( strpos( $key, 'message_list' ) === 0 ) ||
			( strpos( $key, 'message_pm' ) === 0 ) ||
			( strpos( $key, 'nav_list' ) === 0 )
		);

		if ( $partdiv ) {
			$this->output( '<div class="king-part-' . strtr( $key, '_', '-' ) . ' king-inner">' );
		}

		if ( strpos( $key, 'custompage' ) === 0 ) {
			$this->custom_page( $part );
		} elseif ( strpos( $key, 'custom' ) === 0 )  {
			$this->output_raw( $part );
		} elseif ( strpos( $key, 'form' ) === 0 ) {
			$this->form( $part );
		} elseif ( strpos( $key, 'q_list' ) === 0 ) {
			$this->q_list_and_form( $part );
		} elseif ( strpos( $key, 'q_view' ) === 0 ) {
			$this->q_view( $part );
		} elseif ( strpos( $key, 'ranking' ) === 0 ) {
			$this->ranking( $part );
		} elseif ( strpos( $key, 'message_list' ) === 0 ) {
			$this->message_list_and_form( $part );
		} elseif ( strpos( $key, 'message_pm' ) === 0 ) {
			$this->message_list_pm( $part );
		} elseif ( strpos( $key, 'nav_list' ) === 0 ) {
			$this->part_title( $part );
			$this->nav_list( $part['nav'], $part['type'], 1 );
		} elseif ( strpos( $key, 'shorts' ) === 0 )  {
			$this->king_shorts( $part );
		}

		if ( $partdiv ) {
			$this->output( '</div>' );
		}
	}

	public function footer() {
		$this->output( '<footer class="king-footer">' );
		$this->widgets( 'full', 'bottom' );
		$this->output( '<ul class="socialicons">' );
		$this->footerlinks();
		$this->output( '</ul>' );
		$this->nav( 'footer' );
		$this->attribution();
		$this->footer_clear();

		$this->output( '</footer> <!-- END king-footer -->', '' );
		$this->userpanel2();
		if (qa_opt('enable_bookmark')) {
			$this->king_bookmarks();
		}
		
	}
	public function king_bookmarks() {
		if ( qa_is_logged_in() ) {
			$this->output( '<div id="rlatermodal" class="king-modal-login">' );
			$this->output( '<div class="king-modal-content">' );
			$this->output( '<button type="button" class="king-modal-close" data-dismiss="modal" aria-label="Close"><i class="icon fa fa-fw fa-times"></i></button>' );
			$this->output( '<div class="king-modal-header"><h2 class="modal-title">' . qa_lang_html( 'misc/bookmarks' ) . '</h2></div>' );
			$this->output( '<div id="king-rlater-inside"><div class="nopost"><i class="far fa-frown-open fa-4x"></i> '.qa_lang_html('main/no_unselected_qs_found').'</div></div>' );
			$this->output( '</div>' );
			$this->output( '</div>' );
		}
	}
	public function footerlinks() {
		if ( qa_opt( 'footer_fb' ) ) {
			$this->output( '<li class="facebook"><a href="' . qa_opt( 'footer_fb' ) . '" target="_blank" data-toggle="tooltip" data-placement="top"  title="' . qa_lang_html( 'misc/footer_fb' ) . '"><i class="fab fa-facebook-f"></i></a></li>' );
		}

		if ( qa_opt( 'footer_twi' ) ) {
			$this->output( '<li class="twitter"><a href="' . qa_opt( 'footer_twi' ) . '" target="_blank" data-toggle="tooltip" data-placement="top"  title="' . qa_lang_html( 'misc/footer_twi' ) . '"><i class="fa-brands fa-x-twitter"></i></a></li>' );
		}

		if ( qa_opt( 'footer_google' ) ) {
			$this->output( '<li class="instagram"><a href="' . qa_opt( 'footer_google' ) . '" target="_blank" data-toggle="tooltip" data-placement="top"  title="' . qa_lang_html( 'misc/footer_insta' ) . '"><i class="fab fa-instagram"></i></a></li>' );
		}

		if ( qa_opt( 'footer_ytube' ) ) {
			$this->output( '<li class="youtube"><a href="' . qa_opt( 'footer_ytube' ) . '" target="_blank" data-toggle="tooltip" data-placement="top"  title="' . qa_lang_html( 'misc/footer_ytube' ) . '"><i class="fab fa-youtube"></i></a></li>' );
		}

		if ( qa_opt( 'footer_pin' ) ) {
			$this->output( '<li class="pinterest"><a href="' . qa_opt( 'footer_pin' ) . '" target="_blank" data-toggle="tooltip" data-placement="top"  title="' . qa_lang_html( 'misc/footer_pin' ) . '"><i class="fab fa-pinterest-p"></i></a></li>' );
		}
		if ( qa_opt( 'footer_rss' ) ) {
			$this->output( '<li class="pinterest"><a href="' . qa_path_html( 'feed' ) . '" data-toggle="tooltip" data-placement="top"  title="' . qa_lang_html( 'admin/feeds_title' ) . '"><i class="fa-solid fa-rss"></i></a></li>' );
		}
	}

	public function attribution() {
		$this->output(
			'<DIV CLASS="king-attribution" id="insertfooter">',
			''.date( "Y" ).' ©  <A HREF="/">' . $this->content['site_title'] . '</A> | All rights reserved',
			'</DIV>'
		);
	}
	public function main_partsc( $content ) {
		foreach ( $content as $key => $part ) {
			$this->main_partc( $key, $part );
		}
	}
	public function main_partc( $key, $part ) {
		$partdiv = (
			( strpos( $key, 'custom' ) === 0 ) ||
			( strpos( $key, 'a_form' ) === 0 ) ||
			( strpos( $key, 'a_list' ) === 0 )

		);

		if ( $partdiv ) {
			$this->output( '<div class="king-part-' . strtr( $key, '_', '-' ) . '">' );
		}

		// to help target CSS to page parts

		if ( strpos( $key, 'custom' ) === 0 ) {
			$this->output_raw( $part );
		} elseif ( strpos( $key, 'a_list' ) === 0 ) {
			$this->a_list( $part );
		} elseif ( strpos( $key, 'a_form' ) === 0 ) {
			$this->a_form( $part );
		}

		if ( $partdiv ) {
			$this->output( '</div>' );
		}
	}
	public function custom_page( $part ) {
		if (isset($this->content['title'])) {
			$this->output( '<DIV CLASS="pheader">' );
			$this->output( '<H1>' );
			$this->title();
			$this->output( '</H1>' );
			$this->output( '</DIV>' );
		}
		$this->output_raw( $part );
	}
	public function footer_clear()
	{
		$this->output(
			'<div class="king-footer-clear">',
			'</div>'
		);
		if (qa_opt('king_analytic')) {
			$this->output(''.qa_opt('king_analytic_box').'');
		}		
	}

	public function section($title)
	{
		$this->part_title(array('title' => $title));
	}

	public function part_title($part)
	{
		if (strlen($part['title'] ?? '') || strlen($part['title_tags'] ?? ''))
			$this->output('<h2'.rtrim(' '.@$part['title_tags']).'>'.@$part['title'].'</h2>');
	}

	public function part_footer($part)
	{
		if (isset($part['footer']))
			$this->output($part['footer']);
	}

	public function form($form)
	{
		if (!empty($form)) {
			$this->part_title($form);

			if (isset($form['tags']))
				$this->output('<form '.$form['tags'].'>');

			$this->form_body($form);

			if (isset($form['tags']))
				$this->output('</form>');
		}
	}

	public function form_columns($form)
	{
		if (isset($form['ok']) || !empty($form['fields']) )
			$columns = ($form['style'] == 'wide') ? 3 : 1;
		else
			$columns = 0;

		return $columns;
	}

	public function form_spacer($form, $columns)
	{
		$this->output(
			'<tr>',
			'<td colspan="'.$columns.'" class="king-form-'.$form['style'].'-spacer">',
			'&nbsp;',
			'</td>',
			'</tr>'
		);
	}

	public function form_body($form)
	{
		if (@$form['boxed'])
			$this->output('<div class="king-form-table-boxed">');

		$columns = $this->form_columns($form);

		if ($columns) {
			$this->output('<table class="king-form-'.$form['style'].'-table">');
		}
		if (!empty( $form['desc'] ) ) {
			$this->output('<div class="king-form-desc ">'.qa_html( $form['desc'] ).'</div>');
		}
		$this->form_ok($form, $columns);
		$this->form_fields($form, $columns);
		$this->form_buttons($form, $columns);

		if ($columns)
			$this->output('</table>');

		$this->form_hidden($form);

		if (@$form['boxed']) {
			$this->output('</div>');
		}
			
	}


	public function form_ok($form, $columns)
	{
		if (!empty($form['ok'])) {
			$this->output(
				'<tr>',
				'<td colspan="'.$columns.'" class="king-form-'.$form['style'].'-ok">',
				$form['ok'],
				'</td>',
				'</tr>'
			);
		}
	}

	public function form_reorder_fields(&$form, $keys, $beforekey=null, $reorderrelative=true)
/*
	Reorder the fields of $form according to the $keys array which contains the field keys in their new order. Call
	before any fields are output. See the docs for qa_array_reorder() in king-util/sort.php for the other parameters.
*/
	{
		require_once QA_INCLUDE_DIR.'king-util/sort.php';

		if (is_array($form['fields']))
			qa_array_reorder($form['fields'], $keys, $beforekey, $reorderrelative);
	}

	public function form_fields($form, $columns)
	{
		if (!empty($form['fields'])) {
			foreach ($form['fields'] as $key => $field) {
				$this->set_context('field_key', $key);

				if (($field['type']  ?? '') == 'blank')
					$this->form_spacer($form, $columns);
				else
					$this->form_field_rows($form, $columns, $field);
			}

			$this->clear_context('field_key');
		}
	}

	public function form_field_rows($form, $columns, $field)
	{
		$style = $form['style'];

		if (isset($field['style'])) { // field has different style to most of form
			$style = $field['style'];
			$colspan = $columns;
			$columns = ($style == 'wide') ? 3 : 1;
		}
		else
			$colspan = null;

		$prefixed = (($field['type']  ?? '') == 'checkbox') && ($columns == 1) && !empty($field['label']);
		$suffixed = (($field['type']  ?? '') == 'select' || ($field['type']  ?? '') == 'number') && $columns == 1 && !empty($field['label']) && !@$field['loose'];
		$skipdata = ($field['tight']  ?? '');
		$tworows = ($columns == 1) && (!empty($field['label'])) && (!$skipdata) &&
			( (!($prefixed||$suffixed)) || (!empty($field['error'])) || (!empty($field['note'])) );

		if (isset($field['id'])) {
			if ($columns == 1)
				$this->output('<tbody id="'.$field['id'].'">', '<tr>');
			else
				$this->output('<tr id="'.$field['id'].'">');
		}
		else
			$this->output('<tr>');

		if ($columns > 1 || !empty($field['label']))
			$this->form_label($field, $style, $columns, $prefixed, $suffixed, $colspan);

		if ($tworows) {
			$this->output(
				'</tr>',
				'<tr>'
			);
		}

		if (!$skipdata)
			$this->form_data($field, $style, $columns, !($prefixed||$suffixed), $colspan);

		$this->output('</tr>');

		if ($columns == 1 && isset($field['id']))
			$this->output('</tbody>');
	}

	public function form_label($field, $style, $columns, $prefixed, $suffixed, $colspan)
	{
		$extratags = '';

		if ($columns > 1 && (@$field['type'] == 'select-radio' || @$field['rows'] > 1))
			$extratags .= ' style="vertical-align:top;"';

		if (isset($colspan))
			$extratags .= ' colspan="'.$colspan.'"';

		$this->output('<td class="king-form-'.$style.'-label"'.$extratags.'>');

		if ($prefixed) {
			$this->output('<label>');
			$this->form_field($field, $style);
		}

		$this->output(@$field['label']);

		if ($prefixed)
			$this->output('</label>');

		if ($suffixed) {
			$this->output('&nbsp;');
			$this->form_field($field, $style);
		}

		$this->output('</td>');
	}

	public function form_data($field, $style, $columns, $showfield, $colspan)
	{
		if ($showfield || (!empty($field['error'])) || (!empty($field['note']))) {
			$this->output(
				'<td class="king-form-'.$style.'-data"'.(isset($colspan) ? (' colspan="'.$colspan.'"') : '').'>'
			);

			if ($showfield)
				$this->form_field($field, $style);

			if (!empty($field['error'])) {
				if (@$field['note_force'])
					$this->form_note($field, $style, $columns);

				$this->form_error($field, $style, $columns);
			}
			elseif (!empty($field['note']))
				$this->form_note($field, $style, $columns);

			$this->output('</td>');
		}
	}

	public function form_field($field, $style)
	{
		$this->form_prefix($field, $style);

		$this->output_raw( ( $field['html_prefix'] ?? '' ) );

		switch ( ( $field['type'] ?? '' ) ) {
			case 'checkbox':
				$this->form_checkbox($field, $style);
				break;
			case 'color':
				$this->form_color($field, $style);
				break;
			case 'static':
				$this->form_static($field, $style);
				break;

			case 'password':
				$this->form_password($field, $style);
				break;

			case 'delaccount':
				$this->form_password($field, $style);
				break;

			case 'number':
				$this->form_number($field, $style);
				break;

			case 'select':
				$this->form_select($field, $style);
				break;

			case 'select-radio':
				$this->form_select_radio($field, $style);
				break;

			case 'image':
				$this->form_image($field, $style);
				break;

			case 'custom':
				$this->output_raw( ( $field['html'] ?? '' ));
				break;

			default:
				if ( ( $field['type'] ?? '' ) == 'textarea' || ( $field['rows'] ?? '' ) > 1)
					$this->form_text_multi_row($field, $style);
				else
					$this->form_text_single_row($field, $style);
				break;
		}

		$this->output_raw( ( $field['html_suffix'] ?? '' ) );

		$this->form_suffix($field, $style);
	}

	public function form_reorder_buttons(&$form, $keys, $beforekey=null, $reorderrelative=true)
/*
	Reorder the buttons of $form according to the $keys array which contains the button keys in their new order. Call
	before any buttons are output. See the docs for qa_array_reorder() in king-util/sort.php for the other parameters.
*/
	{
		require_once QA_INCLUDE_DIR.'king-util/sort.php';

		if (is_array($form['buttons']))
			qa_array_reorder($form['buttons'], $keys, $beforekey, $reorderrelative);
	}

	public function form_buttons($form, $columns)
	{
		if (!empty($form['buttons'])) {
			$style = @$form['style'];

			if ($columns) {
				$this->output(
					'<tr>',
					'<td colspan="'.$columns.'" class="king-form-'.$style.'-buttons">'
				);
			}

			foreach ($form['buttons'] as $key => $button) {
				$this->set_context('button_key', $key);

				if (empty($button))
					$this->form_button_spacer($style);
				else {
					$this->form_button_data($button, $key, $style);
					$this->form_button_note($button, $style);
				}
			}

			$this->clear_context('button_key');

			if ($columns) {
				$this->output(
					'</td>',
					'</tr>'
				);
			}
		}
	}

	public function form_button_data($button, $key, $style)
	{
		$baseclass = 'king-form-'.$style.'-button king-form-'.$style.'-button-'.$key;

		$this->output('<button'.rtrim(' '.@$button['tags']).'  title="'.@$button['popup'].'" type="submit"'.
			(isset($style) ? (' class="'.$baseclass.'"') : '').'>'.@$button['label'].' </button>');
	}

	public function form_button_note($button, $style)
	{
		if (!empty($button['note'])) {
			$this->output(
				'<span class="king-form-'.$style.'-note">',
				$button['note'],
				'</span>',
				'<br/>'
			);
		}
	}

	public function form_button_spacer($style)
	{
		$this->output('<span class="king-form-'.$style.'-buttons-spacer">&nbsp;</span>');
	}

	public function form_hidden($form)
	{
		$this->form_hidden_elements(@$form['hidden']);
	}

	public function form_hidden_elements($hidden)
	{
		if (!empty($hidden)) {
			foreach ($hidden as $name => $value)
				$this->output('<input type="hidden" name="'.$name.'" value="'.$value.'"/>');
		}
	}

	public function form_prefix($field, $style)
	{
		if (!empty($field['prefix']))
			$this->output('<span class="king-form-'.$style.'-prefix">'.$field['prefix'].'</span>');
	}

	public function form_suffix($field, $style)
	{
		if (!empty($field['suffix']))
			$this->output('<span class="king-form-'.$style.'-suffix">'.$field['suffix'].'</span>');
	}

	public function form_checkbox($field, $style)
	{
		$this->output('<input '.@$field['tags'].' type="checkbox" value="1"'.(@$field['value'] ? ' checked' : '').' class="king-form-'.$style.'-checkbox"/>');
	}
	public function form_color($field, $style)
	{
		$this->output('<input '.@$field['tags'].' type="color" value="'.@$field['value'].'" class="king-form-'.$style.'-color"/>');
	}
	public function form_static($field, $style)
	{
		$this->output('<span class="king-form-'.$style.'-static">'.@$field['value'].'</span>');
	}

	public function form_password($field, $style)
	{
		$this->output('<input '.@$field['tags'].' type="password" value="'.@$field['value'].'" class="king-form-'.$style.'-text"/>');
	}

	public function form_number($field, $style)
	{
		$this->output('<input '.@$field['tags'].' type="text" value="'.@$field['value'].'" class="king-form-'.$style.'-number"/>');
	}

	/**
	 * Output a <select> element. The $field array may contain the following keys:
	 *   options: (required) a key-value array containing all the options in the select.
	 *   tags: any attributes to be added to the select.
	 *   value: the selected value from the 'options' parameter.
	 *   match_by: whether to match the 'value' (default) or 'key' of each option to determine if it is to be selected.
	 */
	public function form_select($field, $style)
	{
		$this->output('<select ' . (isset($field['tags']) ? $field['tags'] : '') . ' class="king-form-' . $style . '-select">');

		// Only match by key if it is explicitly specified. Otherwise, for backwards compatibility, match by value
		$matchbykey = isset($field['match_by']) && $field['match_by'] === 'key';

		foreach ($field['options'] as $key => $value) {
			$selected = isset($field['value']) && (
				($matchbykey && $key === $field['value']) ||
				(!$matchbykey && $value === $field['value'])
			);
			$this->output('<option value="' . $key . '"' . ($selected ? ' selected' : '') . '>' . $value . '</option>');
		}

		$this->output('</select>');
	}

	public function form_select_radio($field, $style)
	{
		$radios = 0;

		foreach ($field['options'] as $tag => $value) {
			if ($radios++)
				$this->output('<br/>');

			$this->output('<label><input '.@$field['tags'].' type="radio" value="'.$tag.'"'.(($value == @$field['value']) ? ' checked' : '').' class="king-form-'.$style.'-radio"/> '.$value.'</label>');
		}
	}

	public function form_image($field, $style)
	{
		$this->output('<div class="king-form-'.$style.'-image">'.@$field['html'].'</div>');
	}

	public function form_text_single_row($field, $style)
	{
		$this->output('<input '.( $field['tags'] ?? '' ).' type="text" value="'.( $field['value'] ?? '' ).'" class="king-form-'.$style.'-text"/>');
	}

	public function form_text_multi_row( $field, $style ) {
		$this->output( '<TEXTAREA ' . ( $field['tags'] ?? '' ) . ' ROWS="5" COLS="40" CLASS="king-form-' . $style . '-text">' . ( $field['value'] ?? '' ) . '</TEXTAREA>' );
	}

	public function form_error($field, $style, $columns)
	{
		$tag = ($columns > 1) ? 'span' : 'div';

		$this->output('<'.$tag.' class="king-form-'.$style.'-error">'.$field['error'].'</'.$tag.'>');
	}

	public function form_note($field, $style, $columns)
	{
		$tag = ($columns > 1) ? 'span' : 'div';

		$this->output('<'.$tag.' class="king-form-'.$style.'-note">'.@$field['note'].'</'.$tag.'>');
	}

	public function ranking($ranking)
	{
		$this->part_title($ranking);

		if (!isset($ranking['type']))
			$ranking['type'] = 'items';
		$class = 'king-top-'.$ranking['type'];

		if (!$this->ranking_block_layout) {
			// old, less semantic table layout
			$this->ranking_table($ranking, $class);
		}
		else {
			// new block layout
			foreach ($ranking['items'] as $item) {
				$this->output('<span class="king-ranking-item '.$class.'-item">');
				$this->ranking_item($item, $class);
				$this->output('</span>');
			}
		}

		$this->part_footer($ranking);
	}

	public function ranking_item($item, $class, $spacer=false) // $spacer is deprecated
	{
		if (!$this->ranking_block_layout) {
			// old table layout
			$this->ranking_table_item($item, $class, $spacer);
			return;
		}

		if (isset($item['count']))
			$this->ranking_count($item, $class);

		if (isset($item['avatar']))
			$this->avatar($item, $class);

		$this->ranking_label($item, $class);

		if (isset($item['score']))
			$this->ranking_score($item, $class);
	}

	public function ranking_cell($content, $class)
	{
		$tag = $this->ranking_block_layout ? 'span': 'td';
		$this->output('<'.$tag.' class="'.$class.'">' . $content . '</'.$tag.'>');
	}

	public function ranking_count($item, $class)
	{
		$this->ranking_cell($item['count'].' &#215;', $class.'-count');
	}

	public function ranking_label($item, $class)
	{
		$this->ranking_cell($item['label'], $class.'-label');
	}

	public function ranking_score($item, $class)
	{
		$this->ranking_cell($item['score'], $class.'-score');
	}

	/**
	 * @deprecated Table-based layout of users/tags is deprecated from 1.7 onwards and may be
	 * removed in a future version. Themes can switch to the new layout by setting the member
	 * variable $ranking_block_layout to false.
	 */
	public function ranking_table($ranking, $class)
	{
		$rows = min($ranking['rows'], count($ranking['items']));

		if ($rows > 0) {
			$this->output('<table class="'.$class.'-table">');
			$columns = ceil(count($ranking['items']) / $rows);

			for ($row = 0; $row < $rows; $row++) {
				$this->set_context('ranking_row', $row);
				$this->output('<tr>');

				for ($column = 0; $column < $columns; $column++) {
					$this->set_context('ranking_column', $column);
					$this->ranking_table_item(@$ranking['items'][$column*$rows+$row], $class, $column>0);
				}

				$this->clear_context('ranking_column');
				$this->output('</tr>');
			}
			$this->clear_context('ranking_row');
			$this->output('</table>');
		}
	}

	/**
	 * @deprecated See ranking_table above.
	 */
	public function ranking_table_item($item, $class, $spacer)
	{
		if ($spacer)
			$this->ranking_spacer($class);

		if (empty($item)) {
			$this->ranking_spacer($class);
			$this->ranking_spacer($class);

		} else {
			if (isset($item['count']))
				$this->ranking_count($item, $class);

			if (isset($item['avatar']))
				$item['label'] = $item['avatar'].' '.$item['label'];

			$this->ranking_label($item, $class);

			if (isset($item['score']))
				$this->ranking_score($item, $class);
		}
	}

	/**
	 * @deprecated See ranking_table above.
	 */
	public function ranking_spacer($class)
	{
		$this->output('<td class="'.$class.'-spacer">&nbsp;</td>');
	}

	public function message_list_pm($list)
	{
		if (!empty($list)) {
			$this->part_title($list);

			$this->error(@$list['error']);

			if (!empty($list['form'])) {
				$this->output('<form '.$list['form']['tags'].'>');
				unset($list['form']['tags']); // we already output the tags before the messages
				$this->message_list_form($list);
			}

			$this->message_pm($list);

			if (!empty($list['form'])) {
				$this->output('</form>');
			}
		} 
			
		
	}
	public function message_pm($list)
	{
		if (isset($list['messages'])) {
			$this->output('<div class="king-pm-list kingscroll" id="pmessages" '.@$list['tags'].'>');
			
			foreach ($list['messages'] as $message) {
				$this->pmmessage_item($message);

			}

			$this->output('</div> <!-- END king-message-list -->', '');
		}
	}

	public function pmmessage_item( $message ) {
		$userid = qa_get_logged_in_userid();
				$this->output( '<div class="king-pm-item '.( ( $userid == $message['raw']['fromuserid'] ) ? ' pm-owner' : '' ).'" ' . @$message['tags'] . '>');
				$this->post_avatar_meta( $message, 'king-pmessage' );
				$this->output( '<b>' . $message['raw']['fromhandle'] . '</b>' );
				$this->output( $message['content'] );
				$this->message_buttons( $message );
				$this->output( '</div> <!-- END king-message-item -->', '' );
	}

	public function message_list_and_form($list)
	{
		if (!empty($list)) {
			$this->part_title($list);

			$this->error(@$list['error']);

			if (!empty($list['form'])) {
				$this->output('<form '.$list['form']['tags'].'>');
				unset($list['form']['tags']); // we already output the tags before the messages
				$this->message_list_form($list);
			}
			$this->message_list($list);

			if (!empty($list['form'])) {
				$this->output('</form>');
			}
		}
	}

	public function message_list_form($list)
	{
		if (!empty($list['form'])) {
			$this->output('<div class="king-message-list-form">');
			$this->form($list['form']);
			$this->output('</div>');
		}
	}

	public function message_list($list)
	{
		if (isset($list['messages'])) {
			$this->output('<div class="king-message-list" '.@$list['tags'].'>');

			foreach ($list['messages'] as $message) {
				if (isset($list['wpage'])) {
					$this->message_item($message, $list['wpage']);
				} else {
					$this->message_item($message);
				}
				

			}

			$this->output('</div> <!-- END king-message-list -->', '');
		}
	}

	public function message_item( $message, $wpage=null ) {
		if ($wpage == 'wall') {
			$tofor = 'from';
		} elseif ($wpage == 'out') {
			$tofor = 'to';
		} elseif ($wpage == 'in') {
			$tofor = 'from';
		}
		
		if ($wpage !== 'wall') {
			$this->output( '<a href="'.qa_path_html('message/'.$message['raw'][$tofor.'handle']).'">' );
		}
		$this->output( '<div class="king-message-item" ' . @$message['tags'] . '>');
		if (isset($message['raw'])) {
			$this->output( get_avatar( $message['raw'][$tofor.'avatarblobid'], 50 ) );
			$this->output( '<b>' . $message['raw'][$tofor.'handle'] . '</b>' );
		}
		$this->message_content( $message );
		$this->message_buttons( $message );
		$this->output( '</div> <!-- END king-message-item -->', '' );
		if ($wpage !== 'wall') {
			$this->output( '</a>' );
		}
	}

	public function message_content($message)
	{
		if (!empty($message['content'])) {
			$this->output('<div class="king-message-content">');
			$this->output_raw($message['content']);
			$this->output('</div>');
		}
	}

	public function message_buttons($item)
	{
		if (!empty($item['form'])) {
			$this->output('<div class="king-message-buttons">');
			$this->form($item['form']);
			$this->output('</div>');
		}
	}

	public function list_vote_disabled($items)
	{
		$disabled = false;

		if (count($items)) {
			$disabled = true;

			foreach ($items as $item) {
				if (@$item['vote_on_page'] != 'disabled')
					$disabled = false;
			}
		}

		return $disabled;
	}


	public function q_list_and_form( $q_list ) {
		if ( ! empty( $q_list ) ) {
			$this->part_title( $q_list );
			$this->q_list( $q_list );
		}
	}

	public function q_list_form($q_list)
	{
		if (!empty($q_list['form'])) {
			$this->output('<div class="king-q-list-form">');
			$this->form($q_list['form']);
			$this->output('</div>');
		}
	}

	public function q_list( $q_list ) {
		if ( isset( $q_list['qs'] ) ) {
			$this->q_list_items( $q_list['qs'] );
		}
	}

	public function q_list_items($q_items)
	{
		foreach ($q_items as $q_item)
			$this->q_list_item($q_item);
	}

	public function q_list_item($q_item)
	{
		$this->output('<div class="king-q-list-item'.rtrim(' '.@$q_item['classes']).'" '.@$q_item['tags'].'>');

		$this->q_item_stats($q_item);
		$this->q_item_main($q_item);
		$this->q_item_clear();

		$this->output('</div> <!-- END king-q-list-item -->', '');
	}

	public function q_item_stats($q_item)
	{
		$this->output('<div class="king-q-item-stats">');

		$this->voting($q_item);
		$this->a_count($q_item);

		$this->output('</div>');
	}

	public function q_item_main($q_item)
	{
		$this->output('<div class="king-q-item-main">');

		$this->view_count($q_item);
		$this->q_item_title($q_item);
		$this->q_item_content($q_item);

		$this->post_avatar_meta($q_item, 'king-q-item');
		$this->post_tags($q_item, 'king-q-item');
		$this->q_item_buttons($q_item);

		$this->output('</div>');
	}

	public function q_item_clear()
	{
		$this->output(
			'<div class="king-q-item-clear">',
			'</div>'
		);
	}

	public function q_item_title($q_item)
	{
		$this->output(
			'<div class="king-q-item-title">',
			'<a href="'.$q_item['url'].'">'.$q_item['title'].'</a>',
			// add closed note in title
			empty($q_item['closed']['state']) ? '' : ' ['.$q_item['closed']['state'].']',
			'</div>'
		);
	}

	public function q_item_content($q_item)
	{
		if (!empty($q_item['content'])) {
			$this->output('<div class="king-q-item-content">');
			$this->output_raw($q_item['content']);
			$this->output('</div>');
		}
	}
	public function king_shorts($shorts)
	{
		$this->output('<form method="post" action="'.qa_self_html().'">');
		$this->output('<div class="king-shorts owl-carousel">');
		foreach ($shorts as $short) {
			$this->king_short($short);
		}
		$this->output('</div>');
		$this->form_hidden_elements(array('code' => qa_get_form_security_code('vote')));
		$this->output('</form>');
	}
	public function king_short($short)
	{
		
		$postid = $short['raw']['postid'];
		$cont = king_get_uploads( $short['raw']['content'] );
		$extra  = qa_db_postmeta_get( $postid, 'qa_q_extra' );
		$vidurl = king_get_uploads( $extra );
		$furl   = qa_path_absolute(qa_q_request($postid, $short['raw']['title']), null, null);
		$this->output('<div class="shorts-item" data-source="' . qa_html( $vidurl['furl'] ) . '">');
		$this->output('<div class="shorts-item-in">');
		$this->output('<video class="short-video video-js vjs-theme-sea" controls preload="auto" data-setup="{}" poster="' . qa_html( isset( $cont['furl'] ) ? $cont['furl'] : '' ) . '" loop playsinline><source src="' . qa_html( $vidurl['furl'] ) . '" type="' . qa_html( $vidurl['format'] ) . '" /></video>');
		$this->output('</div>');
		$this->output('<div class="shorts-item-inright">');
		$this->voting($short);
		$this->output('<a href="' . qa_html( $furl ) . '?state=answer" data-toggle="tooltip" data-placement="right" title="' . qa_lang_html( 'misc/postcomments' ) . '"><i class="fa-solid fa-comment"></i><span>' . qa_html($short['raw']['acount']) . '</span></a>');
		$this->output('<a href="' . qa_html( $furl ) . '" class="ajax-popup-share magnefic-button" data-toggle="tooltip" data-placement="right" title="' . qa_lang_html( 'misc/king_share' ) . '"><i class="fas fa-share-alt"></i><span>' . qa_lang_html( 'misc/king_share' ) . '</span></a>');
		$this->output('</div>');
		$this->output('</div>');
	}

	public function q_item_buttons($q_item)
	{
		if (!empty($q_item['form'])) {
			$this->output('<div class="king-q-item-buttons">');
			$this->form($q_item['form']);
			$this->output('</div>');
		}
	}

	public function voting($post)
	{
		if (isset($post['vote_view'])) {
			$this->output('<div class="king-voting '.(($post['vote_view'] == 'updown') ? 'king-voting-updown' : 'king-voting-net').'" '.@$post['vote_tags'].'>');
			$this->voting_inner_html($post);
			$this->output('</div>');
		}
	}

	public function voting_inner_html( $post ) {
		$this->vote_buttonsup( $post );
		$this->vote_count( $post );
		$this->vote_buttonsdown( $post );
	}

public function vote_buttonsup( $post ) {
		$this->output( '<DIV CLASS="' . (  ( 'updown' == $post['vote_view'] ) ? 'king-vote-buttons-updown' : 'king-vote-buttons-netup' ) . '">' );

		switch ( @$post['vote_state'] ) {
			case 'voted_up':
				$this->post_hover_button( $post, 'vote_up_tags', '+', 'king-vote-one-button king-voted-up' );
				break;

			case 'voted_up_disabled':
				$this->post_disabled_button( $post, 'vote_up_tags', '+', 'king-vote-one-button king-vote-up' );
				break;

			case 'up_only':
				$this->post_hover_button( $post, 'vote_up_tags', '+', 'king-vote-first-button king-vote-up' );

				break;

			case 'enabled':
				$this->post_hover_button( $post, 'vote_up_tags', '+', 'king-vote-first-button king-vote-up' );

				break;

			default:
				$this->post_disabled_button( $post, 'vote_up_tags', '', 'king-vote-first-button king-vote-up' );

				break;
		}

		$this->output( '</DIV>' );
	}

	/**
	 * @param $post
	 */
	public function vote_buttonsdown( $post ) {
		$this->output( '<DIV CLASS="' . (  ( 'updown' == $post['vote_view'] ) ? 'king-vote-buttons-updown' : 'king-vote-buttons-netdown' ) . '">' );

		switch ( @$post['vote_state'] ) {
			case 'voted_down':
				$this->post_hover_button( $post, 'vote_down_tags', '&ndash;', 'king-vote-one-button king-voted-down' );
				break;

			case 'voted_down_disabled':
				$this->post_disabled_button( $post, 'vote_down_tags', '&ndash;', 'king-vote-one-button king-vote-down' );
				break;

			case 'up_only':
				$this->post_disabled_button( $post, 'vote_down_tags', '', 'king-vote-second-button king-vote-down' );
				break;

			case 'enabled':
				$this->post_hover_button( $post, 'vote_down_tags', '&ndash;', 'king-vote-second-button king-vote-down' );
				break;

			default:

				$this->post_disabled_button( $post, 'vote_down_tags', '', 'king-vote-second-button king-vote-down' );
				break;
		}

		$this->output( '</DIV>' );
	}

	public function vote_count($post)
	{
		// You can also use $post['upvotes_raw'], $post['downvotes_raw'], $post['netvotes_raw'] to get
		// raw integer vote counts, for graphing or showing in other non-textual ways

		$this->output('<div class="king-vote-count '.(($post['vote_view'] == 'updown') ? 'king-vote-count-updown' : 'king-vote-count-net').'"'.@$post['vote_count_tags'].'>');

		if ($post['vote_view'] == 'updown') {
			$this->output_split($post['upvotes_view'], 'king-upvote-count');
			$this->output_split($post['downvotes_view'], 'king-downvote-count');

		}
		else
			$this->output_split($post['netvotes_view'], 'king-netvote-count');

		$this->output('</div>');
	}

	public function vote_clear()
	{
		$this->output(
			'<div class="king-vote-clear">',
			'</div>'
		);
	}

	public function a_count($post)
	{
		// You can also use $post['answers_raw'] to get a raw integer count of answers

		$this->output_split(@$post['answers'], 'king-a-count', 'span', 'span',
			@$post['answer_selected'] ? 'king-a-count-selected' : (@$post['answers_raw'] ? null : 'king-a-count-zero'));
	}

	public function view_count($post)
	{
		// You can also use $post['views_raw'] to get a raw integer count of views

		$this->output_split(@$post['views'], 'king-view-count');
	}

	public function avatar($item, $class, $prefix=null)
	{
		if (isset($item['avatar'])) {
			if (isset($prefix))
				$this->output($prefix);

			$this->output(
				'<span class="'.$class.'-avatar">',
				$item['avatar'],
				'</span>'
			);
		}
	}

	public function a_selection($post)
	{
		$this->output('<div class="king-a-selection">');

		if (isset($post['select_tags']))
			$this->post_hover_button($post, 'select_tags', '', 'king-a-select');
		elseif (isset($post['unselect_tags']))
			$this->post_hover_button($post, 'unselect_tags', '', 'king-a-unselect');
		elseif ($post['selected'])
			$this->output('<div class="king-a-selected">&nbsp;</div>');

		if (isset($post['select_text']))
			$this->output('<div class="king-a-selected-text">'.@$post['select_text'].'</div>');

		$this->output('</div>');
	}

	public function post_hover_button( $post, $element, $value, $class ) {
		if ( isset( $post[$element] ) ) {
			$this->output( '<button ' . $post[$element] . ' type="submit" value="' . $value . '" class="' . $class . '-button"></button>' );
		}
	}

	public function post_disabled_button( $post, $element, $value, $class ) {
		if ( isset( $post[$element] ) ) {
			$this->output( '<button ' . $post[$element] . ' type="submit" value="' . $value . '" class="' . $class . '-disabled" disabled="disabled"></button>' );
		}
	}

	public function post_avatar_meta( $post, $class, $avatarprefix = null, $metaprefix = null, $metaseparator = '<br/>' ) {
		$this->output( '<span class="' . $class . '-avatar-meta">' );
		$this->post_avatar( $post, $class, $avatarprefix );
		$this->output( '</span>' );
	}

	/**
	 * @deprecated Deprecated from 1.7; please use avatar() instead.
	 */
	public function post_avatar($post, $class, $prefix=null)
	{
		$this->avatar($post, $class, $prefix);
	}




	public function post_meta_when($post, $class)
	{
		$this->output_split(@$post['when'], $class.'-when');
	}

	public function post_meta_where($post, $class)
	{
		$this->output_split(@$post['where'], $class.'-where');
	}

	public function post_meta_who( $post, $class ) {
		if ( isset( $post['who'] ) ) {
			$this->output( '<SPAN CLASS="' . $class . '-who">' );

			if ( strlen( @$post['who']['prefix'] ) ) {
				$this->output( '<SPAN CLASS="' . $class . '-who-pad">' . $post['who']['prefix'] . '</SPAN>' );
			}

			if ( isset( $post['who']['data'] ) ) {
				$this->output( '<SPAN CLASS="' . $class . '-who-data">' . $post['who']['data'] . '</SPAN>' );
			}

			if ( isset( $post['who']['title'] ) ) {
				$this->output( '<SPAN CLASS="' . $class . '-who-title">' . $post['who']['title'] . '</SPAN>' );
			}

			// You can also use $post['level'] to get the author's privilege level (as a string)

			if ( isset( $post['who']['points'] ) ) {
				$post['who']['points']['prefix'] = '' . $post['who']['points']['prefix'];
				$post['who']['points']['suffix'] .= '';
				$this->output_split( $post['who']['points'], $class . '-who-points' );
			}

			if ( strlen( @$post['who']['suffix'] ) ) {
				$this->output( '<SPAN CLASS="' . $class . '-who-pad">' . $post['who']['suffix'] . '</SPAN>' );
			}

			$this->output( '</SPAN>' );
		}
	}

	public function post_meta_flags($post, $class)
	{
		$this->output_split(@$post['flags'], $class.'-flags');
	}

	public function post_tags($post, $class)
	{
		if (!empty($post['q_tags'])) {
			$this->output('<div class="'.$class.'-tags">');
			$this->post_tag_list($post, $class);
			$this->output('</div>');
		}
	}

	public function post_tag_list($post, $class)
	{
		$this->output('<ul class="'.$class.'-tag-list">');

		foreach ($post['q_tags'] as $taghtml)
			$this->post_tag_item($taghtml, $class);

		$this->output('</ul>');
	}

	public function post_tag_item($taghtml, $class)
	{
		$this->output('<li class="'.$class.'-tag-item">'.$taghtml.'</li>');
	}

	public function page_links()
	{
		$page_links = @$this->content['page_links'];

		if (!empty($page_links)) {
			$this->output('<div class="king-page-links">');

			$this->page_links_label(@$page_links['label']);
			$this->page_links_list(@$page_links['items']);
			$this->page_links_clear();

			$this->output('</div>');
		}
	}

	public function page_links_label($label)
	{
		if (!empty($label))
			$this->output('<span class="king-page-links-label">'.$label.'</span>');
	}

	public function page_links_list($page_items)
	{
		if (!empty($page_items)) {
			$this->output('<ul class="king-page-links-list">');

			$index = 0;

			foreach ($page_items as $page_link) {
				$this->set_context('page_index', $index++);
				$this->page_links_item($page_link);

				if ($page_link['ellipsis'])
					$this->page_links_item(array('type' => 'ellipsis'));
			}

			$this->clear_context('page_index');

			$this->output('</ul>');
		}
	}

	public function page_links_item($page_link)
	{
		$this->output('<li class="king-page-links-item">');
		$this->page_link_content($page_link);
		$this->output('</li>');
	}

	public function page_link_content($page_link)
	{
		$label = @$page_link['label'];
		$url = @$page_link['url'];

		switch ($page_link['type']) {
			case 'this':
				$this->output('<span class="king-page-selected">'.$label.'</span>');
				break;

			case 'prev':
				$this->output('<a href="'.$url.'" class="king-page-prev">&laquo; '.$label.'</a>');
				break;

			case 'next':
				$this->output('<a href="'.$url.'" class="king-page-next">'.$label.' &raquo;</a>');
				break;

			case 'ellipsis':
				$this->output('<span class="king-page-ellipsis">...</span>');
				break;

			default:
				$this->output('<a href="'.$url.'" class="king-page-link">'.$label.'</a>');
				break;
		}
	}

	public function page_links_clear()
	{
		$this->output(
			'<div class="king-page-links-clear">',
			'</div>'
		);
	}

	public function suggest_next()
	{
		$suggest = @$this->content['suggest_next'];

		if (!empty($suggest)) {
			$this->output('<div class="king-suggest-next">');
			$this->output($suggest);
			$this->output('</div>');
		}
	}

	public function q_view($q_view)
	{
		if (!empty($q_view)) {
			$this->output('<div class="king-q-view'.(@$q_view['hidden'] ? ' king-q-view-hidden' : '').rtrim(' '.@$q_view['classes']).'"'.rtrim(' '.@$q_view['tags']).'>');

			if (isset($q_view['main_form_tags']))
				$this->output('<form '.$q_view['main_form_tags'].'>'); // form for voting buttons

			$this->q_view_stats($q_view);

			if (isset($q_view['main_form_tags'])) {
				$this->form_hidden_elements(@$q_view['voting_form_hidden']);
				$this->output('</form>');
			}

			$this->q_view_extra($q_view);
			$this->q_view_clear();

			$this->output('</div> <!-- END king-q-view -->', '');
		}
	}

	public function postcontent($q_view)
	{
		$pid   = $q_view['raw']['postid'];
		$text2 = $q_view['raw']['postformat'];
		$wai = qa_db_postmeta_get( $pid, 'wai' );
		$blockwordspreg = qa_get_block_words_preg();
		if ( 'N' === $text2 ) {
			$thumb  = $q_view['raw']['content'];
			$thumb2 = ( null !== $thumb ) ? king_get_uploads( $thumb ) : '' ;
			if ( $thumb2 ) {
				$this->output('<img src="'.$thumb2['furl'].'" class="king-news-thumb" />');
			}
			
		}


		if ( ! $wai ) {
			$this->output( '<div class="post-content">' . qa_block_words_replace( $q_view['raw']['pcontent'], $blockwordspreg ) . '</div>' );
		} else {
			$this->output('<div class="aicontnt">');
			$this->output('<div class="ailup">');
			$this->output( '<span class="aiplabel">' . qa_lang('misc/prompt') . '</span>' );
			$this->output( '<div class="post-content" id="post-content">' . qa_block_words_replace( $q_view['raw']['pcontent'], $blockwordspreg ) . '</div>' );
			$this->output( '<button id="copyp" onclick="copyText()"><i class="fa-solid fa-copy"></i> '.qa_lang('misc/copyp').'</button>' );
			$this->output('</div>');

			$np = qa_db_postmeta_get( $pid, 'nprompt' );
			if ($np) {
				$this->output('<div class="ailup">');
				$this->output( '<span class="aiplabel">' . qa_lang('misc/ai_nprompt') . '</span>' );
				$this->output( $np );
				$this->output('</div>');
			}
			$this->output('<div class="ailup">');
			$mdl = qa_db_postmeta_get( $pid, 'nprompt' );
			$this->output( '<span class="aiplabel">' . qa_lang('misc/model') . '</span>' );
			$this->output( qa_lang('misc/'.$wai) );
			$this->output('</div>');

			$this->output('<div class="ailup">');
			$asize = qa_db_postmeta_get( $pid, 'asize' );
			$this->output( '<span class="aiplabel">' . qa_lang('misc/aisizes') . '</span>' );
			$this->output( $asize );
			$this->output('</div>');

			$stle = qa_db_postmeta_get( $pid, 'stle' );
			if ($np) {
				$this->output('<div class="ailup">');
				$this->output( '<span class="aiplabel">' . qa_lang('misc/ai_filter') . '</span>' );
				$this->output( $stle );
				$this->output('</div>');
			}
			$this->output('</div>');
		}

		

		
		if ( 'poll' == $text2 ) {
			$this->get_poll( $pid );
		} elseif ( 'list' == $text2 ) {
			$this->get_list( $pid );
		} elseif ( 'trivia' == $text2 ) {
			$this->get_trivia( $pid );
		}
	}

	public function get_list($pid)
	{
		$lsources = get_poll($pid);

		if ($lsources) {
			foreach ($lsources as $lsource) {
				$lists = unserialize($lsource['content']);	
				require_once QA_INCLUDE_DIR . 'king-app-video.php';

				if ($lists) {
					$parent_i = 0;

					$this->output('<ul class="king-lists">');
					foreach ($lists as $list) {
						$parent_i++;
						$total = count($lists);
						$rotate  = round( ( $parent_i * 100 ) / ( $total ) );
						$this->output('<li class="list-item">');
						$this->output('<div class="list-title">');
						$this->output('<div class="poll-circle">
							<svg class="circle" viewbox="0 0 40 40">
								<circle class="circle-back" fill="none" cx="20" cy="20" r="15.9"></circle>
								<circle class="circle-chart"  stroke-dasharray="'.qa_html($rotate).',100" stroke-linecap="round" fill="none" cx="20" cy="20" r="15.9"></circle>
							</svg>
						</div>');
						$this->output('<span class="list-id">#' . qa_html($parent_i) . '</span><h2>'.$list['choices'].'</h2></div>');

						if ($list['img']) {
							$text2 = king_get_uploads($list['img']);
							$this->output('<img src="' . $text2['furl'] . '" class="list-img" alt=""/>');
						} elseif ($list['video']) {
							$this->output('<span class="list-video">' . embed_replace($list['video']) . '</span>');
						}

						$this->output('<span class="list-desc">' . $list['desc'] . '</span>');
						$this->output('</li>');
					}
					$this->output('</ul>');
				}
			}
		}
	}
	public function get_poll($pid)
	{
		$apolls   = get_poll($pid);
		$parent_i = 0;
		if (qa_is_logged_in()) {
			$userid = qa_get_logged_in_userid();
		} else {
			$userid = qa_remote_ip_address();
		}
		if ($apolls) {
			foreach ($apolls as $apoll) {
				$pollz = $apoll;
				$parent_i++;
				$total = count($apolls);
				$rotate  = round( ( $parent_i * 100 ) / ( $total ) );
				$contentz = unserialize($apoll['content']);
				if (isset($contentz['pa'])) {
					$polls   = $contentz['pa'];
				} else {
					$polls   = $contentz;
				}
				
				$answers = unserialize((string)$apoll['answers']);
				if (is_array($answers) && array_key_exists($userid, $answers)) {
					$show = ' voted';
				} else {
					$show = ' not-voted';
				}
				if ($polls) {
					$this->output('<div class="king-polls-up">');
				if (isset($contentz['ptitle'])) {
					$this->output('<div class="list-title">');
					$this->output('<div class="poll-circle">
							<svg class="circle" viewbox="0 0 40 40">
								<circle class="circle-back" fill="none" cx="20" cy="20" r="15.9"></circle>
								<circle class="circle-chart"  stroke-dasharray="'.qa_html($rotate).',100" stroke-linecap="round" fill="none" cx="20" cy="20" r="15.9"></circle>
							</svg>
						</div>');
					$this->output('<span>' . qa_html($parent_i) . qa_lang('misc/trivia_of') . qa_html($total) . '</span><h2>'.$contentz['ptitle'].'</h2></div>');
				}
				if (isset($contentz['pimg'])) {
					$pimg = king_get_uploads($contentz['pimg']);
					$this->output('<img class="poll-img" src="' . $pimg['furl'] . '" alt=""/>');
				}
				$this->output('<ol class="king-polls polls-' . $pollz['extra'] . '' . $show . '" id="kpoll_' . qa_js($pollz['id']) . '">');
					foreach ($polls as $poll) {

						$results_percent = $this->poll_results_percent($answers, $poll['id']);
						$this->output('<li data-id="' . qa_js($poll['id']) . '" data-pollid="' . qa_js($pollz['id']) . '" data-voted="' . $results_percent['voted'] . '" data-votes="' . $results_percent['votes'] . '" onclick="return pollclick(this);" id="kingpoll">');
						$this->output('<div class="poll-item">');
						$this->output('<div class="poll-results">');
						$this->output('<span class="poll-result" style="height: ' . $results_percent['percent'] . '%; width: ' . $results_percent['percent'] . '%;"></span>');
						$this->output('<div class="poll-numbers"><span class="poll-result-percent">' . $results_percent['percent'] . '%</span><span class="poll-result-voted"> <i class="fas fa-poll-h"></i> ' . $results_percent['voted'] . '</span></div>');
						$this->output('</div>');
						if ($poll['img'] && $pollz['extra'] !== 'grid1') {
							$img = king_get_uploads($poll['img']);
							$this->output('<img class="poll-img" src="' . $img['furl'] . '" alt=""/>');
						}
						$this->output('<div class="poll-title">' . $poll['choices'] . '</div>');
						$this->output('</div>');
						$this->output('</li>');
					}
					$this->output('</ol>');
				$this->output('</div>');
				}
			}
		}
	}
	public function get_trivia($pid)
	{
		$apolls   = get_poll($pid, 'trivia');
		$parent_i = 0;
		if ($apolls) {
			foreach ($apolls as $apoll) {
				$parent_i++;
				$total = count($apolls);
				$rotate  = round( ( $parent_i * 100 ) / ( $total ) );
				$pollz = $apoll;
				$contentz = unserialize($apoll['content']);
				
				if (isset($contentz['pa'])) {
					$polls   = $contentz['pa'];
				} else {
					$polls   = $contentz;
				}
				
				if ($polls) {
					$this->output('<div class="king-polls-up">');
				if (isset($contentz['ptitle'])) {
					$this->output('<div class="list-title">');
					$this->output('<div class="poll-circle">
							<svg class="circle" viewbox="0 0 40 40">
								<circle class="circle-back" fill="none" cx="20" cy="20" r="15.9"></circle>
								<circle class="circle-chart"  stroke-dasharray="'.qa_html($rotate).',100" stroke-linecap="round" fill="none" cx="20" cy="20" r="15.9"></circle>
							</svg>
						</div>');
					$this->output('<span>' . qa_html($parent_i) . qa_lang('misc/trivia_of') . qa_html($total) . '</span><h2>'.$contentz['ptitle'].'</h2></div>');
				}
				if ($contentz['pimg']) {
					$pimg = king_get_uploads($contentz['pimg']);
					$this->output('<img class="poll-img" src="' . $pimg['furl'] . '" alt=""/>');
				}
				$this->output('<ol class="king-polls polls-' . $pollz['extra'] . '" data-parent="'.qa_html($total).'" data-voted="0" data-postid="'.$pid.'">');
					foreach ($polls as $row => $poll) {
						if ( $contentz['correct'] === 'correct'.$row ) {
							$crrct = 1;
						} else {
							$crrct = 0;
						}
						$this->output('<li data-pollid="' . qa_js($pollz['id']) . '" id="kingpoll">');

						$this->output('<div class="poll-item" data-id="'.$crrct.'" onclick="return triviaclick(this);">');
						if ($poll['img'] && $pollz['extra'] !== 'grid1') {
								$img = king_get_uploads($poll['img']);
								$this->output('<img class="poll-img" src="' . $img['furl'] . '" alt=""/>');
						}
						$this->output('<div class="poll-title">' . $poll['choices'] . '</div>');
						$this->output('</div>');
						$this->output('</li>');
					}
					$this->output('</ol>');
				$this->output('</div>');
				}
			}
			$this->output('<div id="king-quiz-result"></div>');
		}

	}
	public function poll_results_percent($answers, $pollid)
	{

		if (is_array($answers)) {
			$votes   = count($answers);
			$results = array_count_values($answers);
			if (in_array($pollid, $answers)) {
				$voted = $results[$pollid];
			} else {
				$voted = 0;
			}

			$results_percent   = round(($voted * 100) / ($votes));
			$output['percent'] = $results_percent;
			$output['voted']   = $voted;
			$output['votes']   = $votes + 1;
			return $output;
		} else {
			$output['percent'] = '0';
			$output['voted']   = '0';
			$output['votes']   = '1';
			return $output;
		}

	}
	public function get_musics($q_view)
	{
		$pid   = $q_view['raw']['postid'];
		$lsources = get_poll($pid, 'music', true);
		if ($lsources) {


		$lists = unserialize($lsources['content']);	
		require_once QA_INCLUDE_DIR . 'king-app-video.php';

		$thumb  = $this->content['description'];
		$poster = king_get_uploads( $thumb );
		if ($lists) {
			$out =array();
			foreach ($lists as $list) {
				$mp3 = king_get_uploads($list['music']);

				$out[] = ['name'=>$list['ptitle'],'sources'=> [['src' => $mp3['furl'], 'type' => $mp3['format'], ]], 'poster'=>$poster['furl']];
			}
			$this->output('<script type="application/json" class="king-playlist-data">' . json_encode( $out ) . '</script>');
		}
		}
	}
	public function viewtop()
	{
		$q_view   = @$this->content['q_view'];
		$favorite = @$this->content['favorite'];

		if ($this->template == 'question') {
			$this->output('<DIV CLASS="share-bar">');

			if (isset($q_view['main_form_tags'])) {
				$this->output('<FORM ' . $q_view['main_form_tags'] . '>');
			}

			$this->voting($q_view);
			if (isset($q_view['main_form_tags'])) {
				$this->form_hidden_elements(@$q_view['voting_form_hidden']);
				$this->output('</FORM>');
			}
			if (isset($favorite)) {
				$this->output('<FORM ' . $favorite['form_tags'] . '>');
			}

			$this->favorite();
			if (isset($favorite)) {
				$this->form_hidden_elements(@$favorite['form_hidden']);
				$this->output('</FORM>');
			}

			$this->output('<div class="share-link" data-toggle="modal" data-target="#sharemodal" role="button" ><i data-toggle="tooltip" data-placement="top" class="fas fa-share" title="' . qa_lang_html('misc/king_share') . '"></i></div>');
			if (qa_get_logged_in_level()>=QA_USER_LEVEL_ADMIN) {
				if ($q_view['raw']['featured']) {
					$fclass=' selected';
				} else {
					$fclass=' not-selected';
				}
				$this->output('<div class="share-link addfeatured'.qa_html($fclass).'" onclick="return featuredclick(this);" data-pid="'.qa_html($q_view['raw']['postid']).'" data-toggle="tooltip" data-placement="top" title="' . qa_lang_html('misc/featured') . '"><i class="fas fa-star"></i></div>');
			}
			if (qa_opt('enable_bookmark')) {
				$this->output( post_bookmark( $q_view['raw']['postid'], 'share-link' ) );
			}
			$this->q_view_buttons($q_view);

			$this->output('</DIV>');

		}


	}
	public function featured()
	{
		if ($this->template == 'home' && 'grids-hide' !== qa_opt('king_grids')) {

			if ( 'grids-3' === qa_opt('king_grids') || 'grids-6' === qa_opt('king_grids') ) {
				$pnum = 4;
			}  elseif('grids-4' === qa_opt('king_grids') || 'grids-7' === qa_opt('king_grids') || 'grids-8' === qa_opt('king_grids') ) {
				$pnum = 6;
			} else {
				$pnum = 5;
			}
			$fposts = qa_db_read_all_assoc(qa_db_query_sub('SELECT * FROM ^posts WHERE featured=$ ORDER BY postid LIMIT #', true, $pnum ));
			if (qa_opt('king_grid_size')) {
				$style= ' style="grid-auto-rows: '. ( qa_opt('king_grid_size') / 2 ).'px;"';
			} else {
				$style = '';
			}

			
			$this->output('<div class="king-featureds '.qa_opt('king_grids').'">');
				$this->output('<div class="king-featured-grid"'.$style.'>');
				foreach ($fposts as $row => $fpost) {
					$furl=qa_path_absolute(qa_q_request($fpost['postid'], $fpost['title']), null, null);
					$img = king_get_uploads($fpost['content']);
					$number = $row + 1;
					$this->output('<div class="featured-posts grid-'.$number.'">
							<a href="'.qa_html($furl).'">
								<div class="featured-post">
									<div class="king-box-bg" data-king-img-src="'.(isset($img['furl']) ? $img['furl'] : '').'"></div>
								</div>
							</a>       
							<div class="featured-content">
								<div class="mslider-post-meta">' .get_post_format($fpost['postformat']) . '</div>
								<a href="'.qa_html($furl).'" class="featured-title">'.qa_html($fpost['title']).'</a>
								<div class="featured-meta">
									<span><i class="fa fa-eye" aria-hidden="true"></i> '.qa_html($fpost['views']).' </span>
									<span><i class="fa fa-comment" aria-hidden="true"></i> '.qa_html($fpost['acount']).'</span>
									<span><i class="fas fa-chevron-up"></i> '.qa_html($fpost['netvotes']).'</span>
								</div>
							</div>
						</div>
					');

				}
			$this->output('</div>');
			$this->output('</div>');
		}
	}
	public function pboxes($q_view)
	{
		$this->output('<DIV CLASS="pboxes">');
		$user = qa_db_select_with_pending(
			qa_db_user_account_selectspec($q_view['raw']['userid'], true)
		);
		$this->output(get_user_html($user, '600', 'postuser', '90'));
		$this->output('</DIV>');
		$this->reactions($q_view);
	}

	public function reactions($q_view)
	{
		require_once QA_INCLUDE_DIR . 'king-db/metas.php';
		$pid = $q_view['raw']['postid'];
		if ( qa_is_logged_in() ) {
			$userid = qa_get_logged_in_userid();
		} else {
			$userid = qa_remote_ip_address();
		}
		$reactions = array( '1', '2', '3', '4', '5', '6', '7', '8' );
		$total  = qa_db_postmeta_get( $pid, 'reactotal' );
		$this->output('<DIV CLASS="pboxes">');
		$this->output('<ul class="reactions" data-postid="'.$pid.'" data-valid="0">');
		
		foreach ($reactions as $reaction) {
			$slct = 'reac_' . $reaction;
			$query  = qa_db_postmeta_get( $pid, $slct );
			$result = isset($query) ? unserialize( $query ) : array();
			if (is_array( $result ) && in_array( $userid, $result )) {
				$uvoted = 1;
			} else {
				$uvoted = 0;
			}
			$voted = count($result);
			$percent = 0;
			if ($voted) {
			$percent = round(( $voted * 100 ) / $total );
			}
			$vclass = $uvoted == 1 ? 'voted' : '';
			$this->output('<li class="reaction-item '.$vclass.'" id="reac'.$reaction.'">');
			$this->output('<div class="reaction-in">');
			$this->output('<span class="reaction-result" style="height:'.$percent.'%;"></span>');
			$this->output('<span class="reaction-percent">'.$percent.'%</span>');
			$this->output('<span class="reaction-voted"></span>');
			$this->output('</div>');
			$this->output('<div class="reaction" data-id="'.$reaction.'" data-voted="'.$voted.'" data-uvoted="'.$uvoted.'" onclick="return reacclick(this);">' . qa_lang_html('misc/reac_'.$reaction) . '</div>');
			$this->output('</li>');
		}
		$this->output('</ul>');
		$this->output('</DIV>');
	}


	public function q_view_stats($q_view)
	{
		$this->output('<div class="king-q-view-stats">');

		$this->voting($q_view);
		$this->a_count($q_view);

		$this->output('</div>');
	}



	public function q_view_content($q_view)
	{
		$content = isset($q_view['content']) ? $q_view['content'] : '';

		$this->output('<div class="king-q-view-content">');
		$this->output_raw($content);
		$this->output('</div>');
	}

	public function q_view_follows($q_view)
	{
		if (!empty($q_view['follows']))
			$this->output(
				'<div class="king-q-view-follows">',
				$q_view['follows']['label'],
				'<a href="'.$q_view['follows']['url'].'" class="king-q-view-follows-link">'.$q_view['follows']['title'].'</a>',
				'</div>'
			);
	}

	public function q_view_closed($q_view)
	{
		if (!empty($q_view['closed'])) {
			$haslink = isset($q_view['closed']['url']);

			$this->output(
				'<div class="king-q-view-closed">',
				$q_view['closed']['label'],
				($haslink ? ('<a href="'.$q_view['closed']['url'].'"') : '<span').' class="king-q-view-closed-content">',
				$q_view['closed']['content'],
				$haslink ? '</a>' : '</span>',
				'</div>'
			);
		}
	}

	public function q_view_extra( $q_view ) {
		$kiframe = qa_db_postmeta_get( $q_view['raw']['postid'], 'kiframe' );
		if ( ! empty( $q_view['extra'] ) ) {
			require_once QA_INCLUDE_DIR . 'king-app-video.php';
			$extraz = $q_view['extra']['content'];
			$extras = @unserialize( $extraz );

			if ( $extras ) {
				$this->output('<div class="king-gallery owl-carousel">');
				foreach ( $extras as $extra ) {
					$text2 = king_get_uploads( $extra );
					$this->output( '<a href="' . $text2['furl'] . '">');
					$this->output( '<img class="gallery-img king-lazy" width="' . $text2['width'] . '" height="' . $text2['height'] . '" data-king-img-src="' . $text2['furl'] . '" alt=""/>' );
					$this->output( '</a>');
				}
				$this->output('</div>');
			} elseif ( is_numeric( $extraz ) ) {
				$vidurl = king_get_uploads( $extraz );
				$thumb  = $this->content['description'];
				$poster = king_get_uploads( $thumb );
				$this->output('<video id="my-video" class="video-js vjs-theme-forest" controls preload="auto"  width="960" height="540" data-setup="{}" poster="' . $poster['furl'] . '" >');
				$this->output('<source src="' . $vidurl['furl'] . '" type="video/mp4" />');
				$this->output('</video>');
			} else {
				if ( $extraz ) {
					$this->output_raw( $extraz = embed_replace( $extraz ) );
				}
			}
		} elseif ( $kiframe ) {
			$this->output_raw( $kiframe );
		}

	}
	public function music_view( $q_view ) {
		
			require_once QA_INCLUDE_DIR . 'king-app-video.php';
			$extraz = isset($q_view['extra']) ? $q_view['extra']['content'] : '';
					
			$this->output('<DIV CLASS="king-playlist-uo">');
			if ( ! $extraz ) {
				$vidurl = king_get_uploads( $extraz );
				$thumb  = $q_view['raw']['content'];
				$poster = king_get_uploads( $thumb );

				$this->output('<img src="'.$poster['furl'].'" class="king-playlist-thumb" />');
				$this->output('<div class="vjs-playlist king-playlist-post" id="king-playlist" style="display:block;"></div>');
				$this->output('<div class="king-playlist">');
				$this->output('<div class="vjs-playlist" id="king-playlist" style="display:none;"></div>');
				$this->output( '<audio class="video-js vjs-theme-sea" autoplay controls="controls" preload height="60" data-setup="{}" >' );
				$this->output( '</audio >' );
				$this->get_musics( $q_view );
				$this->output('</div>');

			} else {
				$this->output_raw( $extraz = embed_replace( $extraz ) );
			}
			$this->output('</DIV>');
	}
	public function maincom( $q_view ) {
		$content = $this->content;

		/*if (isset($content['main_form_tags']))
		$this->output('<FORM '.$content['main_form_tags'].'>');*/

		if ( 'question' == $this->template ) {
			$this->output( '<DIV CLASS="maincom">' );
			$this->output( '<ul class="nav nav-tabs">' );
			if ( ! qa_opt( 'hide_default_comment' ) ) {
				$this->output( '<li class="active"><a href="#comments" data-toggle="tab"><i class="fa-solid fa-comment-dots"></i> ' . qa_lang_html( 'misc/postcomments' ) . '</a></li>' );
				$active = '';
			} else {
				$active = 'active';
			}
			if ( ! qa_opt( 'hide_fb_comment' ) ) {
				$this->output( '<li><a href="#fbcomments" data-toggle="tab"><i class="fab fa-facebook"></i> ' . qa_lang_html( 'misc/postcomments' ) . '</a></li>' );
			}
			$this->output( '</ul>' );
			$this->output( '<div class="tab-content">' );
			if ( ! qa_opt( 'hide_default_comment' ) ) {
				$this->output( '<div class="tab-pane active" id="comments">' );
				$this->main_partsc( $content );
				$this->output( '</div>' );
			}
			if ( ! qa_opt( 'hide_fb_comment' ) ) {
				$this->output( '<div class="tab-pane ' . $active . '" id="fbcomments">' );
				$this->fbcomment($q_view);
				$this->output( '</div>' );
			}

			$this->output( '</div>' );
			$this->output( '</div>' );
		}
		/*if (isset($content['main_form_tags']))
	$this->output('</FORM>');*/
	}
	public function fbcomment($q_view) {
		$this->output( '<DIV CLASS="fbcomments">' );
		$this->output( '<div class="fb-comments" data-href="' . $this->content['canonical'] . '" data-width="100%" data-numposts="10"></div>' );		
		$this->output( '</DIV>' );
	}
	/**
	 * @param $q_item
	 */
	public function post_metas( $q_item ) {
		$this->output( '<div class="post-meta">' );
		if ( isset( $q_item['avatar'] ) ) {
			$this->output( '<div class="king-p-who">' );
			$this->output( '' . get_avatar($q_item['raw']['avatarblobid'], '27') . $q_item['who']['data'] . '' );
			$this->output( '</div>' );
		}
		$this->output( '<div>' );
		$this->output( '<span><i class="fa fa-comment" aria-hidden="true"></i> ' . $q_item['raw']['acount'] . '</span>' );
		$this->output( '<span><i class="fa fa-eye" aria-hidden="true"></i> ' . $q_item['raw']['views'] . '</span>' );
		$this->output( '<span><i class="fas fa-chevron-up"></i> ' . $q_item['raw']['netvotes'] . '</span>' );
		$this->output( '</div>' );
		$this->output( '</div>' );
	}

	public function q_view_buttons( $q_view ) {
		if ( isset( $q_view['main_form_tags'] ) ) {
			$this->output( '<DIV CLASS="king-q-view-buttons">' );
			$this->output( '<FORM ' . $q_view['main_form_tags'] . '>' );
		}

		if ( ! empty( $q_view['form'] ) ) {
			$this->form( $q_view['form'] );
		}

		if ( isset( $q_view['main_form_tags'] ) ) {
			$this->form_hidden_elements( @$q_view['buttons_form_hidden'] );
			$this->output( '</FORM>' );
			$this->output( '</DIV>' );
		}
	}

	public function q_view_clear()
	{
		$this->output(
			'<div class="king-q-view-clear">',
			'</div>'
		);
	}

	public function a_form($a_form)
	{
		$this->output('<div class="king-a-form"'.(isset($a_form['id']) ? (' id="'.$a_form['id'].'"') : '').
			(@$a_form['collapse'] ? ' style="display:none;"' : '').'>');

		$this->form($a_form);
		$this->c_list(@$a_form['c_list'], 'king-a-item');

		$this->output('</div> <!-- END king-a-form -->', '');
	}

	public function a_list($a_list)
	{
		if (!empty($a_list)) {
			$this->part_title($a_list);

			$this->output('<div class="king-a-list'.($this->list_vote_disabled($a_list['as']) ? ' king-a-list-vote-disabled' : '').'" '.@$a_list['tags'].'>', '');
			$this->a_list_items($a_list['as']);
			$this->output('</div> <!-- END king-a-list -->', '');
		}
	}

	public function a_list_items($a_items)
	{
		foreach ($a_items as $a_item)
			$this->a_list_item($a_item);
	}

	public function a_list_item( $a_item ) {
		$extraclass = @$a_item['classes'] . ( $a_item['hidden'] ? ' king-a-list-item-hidden' : ( $a_item['selected'] ? ' king-a-list-item-selected' : '' ) );
		$this->output( '<DIV CLASS="king-a-list-item ' . $extraclass . '" ' . @$a_item['tags'] . '>' );
		$this->a_item_main( $a_item );
		$this->a_item_clear();
		$this->output( '</DIV> <!-- END king-a-list-item -->', '' );
	}

	public function a_item_main( $a_item ) {
		$this->output( '<div class="king-a-item-main">' );

		$this->output( '<DIV CLASS="commentmain">' );

		if ( $a_item['hidden'] ) {
			$this->output( '<DIV CLASS="king-a-item-hidden">' );
		} elseif ( $a_item['selected'] ) {
			$this->output( '<DIV CLASS="king-a-item-selected">' );
		}

		$this->error( @$a_item['error'] );
		$this->output( '<DIV CLASS="a-top">' );
		$this->post_avatar_meta( $a_item, 'king-a-item' );

		$this->post_meta_who( $a_item, 'meta' );
		$this->a_item_content( $a_item );
		$this->output( '</DIV>' );

		$this->output( '<DIV CLASS="a-alt">' );
		$this->a_selection( $a_item );

		if ( isset( $a_item['main_form_tags'] ) ) {
			$this->output( '<form ' . $a_item['main_form_tags'] . '>' );
		}

		// form for voting buttons

		$this->voting( $a_item );

		if ( isset( $a_item['main_form_tags'] ) ) {
			$this->form_hidden_elements( @$a_item['voting_form_hidden'] );
			$this->output( '</form>' );
		}

		if ( isset( $a_item['main_form_tags'] ) ) {
			$this->output( '<form ' . $a_item['main_form_tags'] . '>' );
		}

		// form for buttons on answer

		$this->a_item_buttons( $a_item );

		if ( isset( $a_item['main_form_tags'] ) ) {
			$this->form_hidden_elements( @$a_item['buttons_form_hidden'] );
			$this->output( '</FORM>' );
		}

		$this->post_meta_when( $a_item, 'meta' );
		$this->output( '</DIV>' );

		$this->output( '</DIV>' );

		if ( $a_item['hidden'] || $a_item['selected'] ) {
			$this->output( '</DIV>' );
		}

		if ( isset( $a_item['main_form_tags'] ) ) {
			$this->output( '<FORM ' . $a_item['main_form_tags'] . '>' );
		}

		// form for buttons on answer
		$this->c_list( @$a_item['c_list'], 'king-a-item' );

		if ( isset( $a_item['main_form_tags'] ) ) {
			$this->form_hidden_elements( @$a_item['buttons_form_hidden'] );
			$this->output( '</FORM>' );
		}

		$this->c_form( @$a_item['c_form'] );

		$this->output( '</DIV> <!-- END king-a-item-main -->' );
	}
	public function socialshare($q_view) {

		$headtitle = urlencode($q_view['raw']['title']);
		$shareurl  = qa_path_html( qa_q_request( $q_view['raw']['postid'], $q_view['raw']['title'] ), null, qa_opt( 'site_url' ) );

		$this->output( '<div id="sharemodal" class="king-modal-login">' );
		$this->output( '<div class="king-modal-content">' );
		$this->output( '<div class="social-share">' );
		$this->output( '<h3>' . qa_lang_html( 'misc/king_share' ) . '</h3>' );
		$this->output( '<a class="post-share share-fb" data-toggle="tooltip" data-placement="top" title="Facebook" href="#" target="_blank" rel="nofollow" onclick="window.open(\'https://www.facebook.com/sharer/sharer.php?u=' . $shareurl . '\',\'facebook-share-dialog\',\'width=626,height=436\');return false;"><i class="fab fa-facebook-square"></i></i></a>' );
		$this->output( '<a class="social-icon share-tw" href="#" data-toggle="tooltip" data-placement="top" title="Twitter" rel="nofollow" target="_blank" onclick="window.open(\'http://twitter.com/share?text=' . $headtitle . '&amp;url=' . $shareurl . '\',\'twitter-share-dialog\',\'width=626,height=436\');return false;"><i class="fa-brands fa-x-twitter"></i></a>' );
		$this->output( '<a class="social-icon share-pin" href="#" data-toggle="tooltip" data-placement="top" title="Pin this" rel="nofollow" target="_blank" onclick="window.open(\'//pinterest.com/pin/create/button/?url=' . $shareurl . '&amp;description=' . $headtitle . '\',\'pin-share-dialog\',\'width=626,height=436\');return false;"><i class="fab fa-pinterest-square"></i></a>' );
		$this->output( '<a class="social-icon share-em" href="mailto:?subject=' . $headtitle . '&amp;body=' . $shareurl . '" data-toggle="tooltip" data-placement="top" title="Email this"><i class="fas fa-envelope"></i></a>' );
		$this->output( '<a class="social-icon share-tb" href="#" data-toggle="tooltip" data-placement="top" title="Tumblr" rel="nofollow" target="_blank" onclick="window.open( \'http://www.tumblr.com/share/link?url=' . $shareurl . '&amp;name=' . $headtitle . '\',\'tumblr-share-dialog\',\'width=626,height=436\' );return false;"><i class="fab fa-tumblr-square"></i></a>' );
		$this->output( '<a class="social-icon share-linkedin" href="#" data-toggle="tooltip" data-placement="top" title="LinkedIn" rel="nofollow" target="_blank" onclick="window.open( \'http://www.linkedin.com/shareArticle?mini=true&amp;url=' . $shareurl . '&amp;title=' . $headtitle . '&amp;source=' . $headtitle . '\',\'linkedin-share-dialog\',\'width=626,height=436\');return false;"><i class="fab fa-linkedin"></i></a>' );
		$this->output( '<a class="social-icon share-vk" href="#" data-toggle="tooltip" data-placement="top" title="Vk" rel="nofollow" target="_blank" onclick="window.open(\'http://vkontakte.ru/share.php?url=' . $shareurl . '\',\'vk-share-dialog\',\'width=626,height=436\');return false;"><i class="fab fa-vk"></i></a>' );
		$this->output( '<a class="social-icon share-wapp" href="whatsapp://send?text=' . $shareurl . '" data-action="share/whatsapp/share" data-toggle="tooltip" data-placement="top" title="whatsapp"><i class="fab fa-whatsapp-square"></i></a>' );
		$this->output( '<h3>' . qa_lang_html( 'misc/copy_link' ) . '</h3>' );
		$this->output( '<input type="text" id="modal-url" value="' . $shareurl . '">' );
		$this->output( '<span class="copied" style="display: none;">' . qa_lang_html( 'misc/copied' ) . '</span>' );

		if ( 'V' === $q_view['raw']['postformat'] && is_numeric( $q_view['extra']['content'] ) ) {
			$this->output( '<h3>' . qa_lang_html( 'misc/embed_code' ) . '</h3>' );
			$this->output( '<textarea type="textarea" rows="4" id="modal-url" value=""><iframe width="560" height="315" src="' . $shareurl . '/embed" title="' . $headtitle . '" frameborder="0" allowfullscreen></iframe></textarea>' );
		}
		$this->output( '</div>' );
		$this->output( '</div>' );
		$this->output( '</div>' );
	}
	public function a_item_clear()
	{
		$this->output(
			'<div class="king-a-item-clear">',
			'</div>'
		);
	}

	public function a_item_content($a_item)
	{
		$this->output('<div class="king-a-item-content">');
		$this->output_raw($a_item['content']);
		$this->output('</div>');
	}

	public function a_item_buttons( $a_item ) {
		if ( ! empty( $a_item['form'] ) ) {
			$this->output( '<DIV CLASS="king-a-item-buttons">' );
			$this->form( $a_item['form'] );
			$this->output( '</DIV>' );
		}
	}

	public function c_form($c_form)
	{
		$this->output('<div class="king-c-form"'.(isset($c_form['id']) ? (' id="'.$c_form['id'].'"') : '').
			(@$c_form['collapse'] ? ' style="display:none;"' : '').'>');

		$this->form($c_form);

		$this->output('</div> <!-- END king-c-form -->', '');
	}

	public function c_list($c_list, $class)
	{
		if (!empty($c_list)) {
			$this->output('', '<div class="'.$class.'-c-list"'.(@$c_list['hidden'] ? ' style="display:none;"' : '').' '.@$c_list['tags'].'>');
			$this->c_list_items($c_list['cs']);
			$this->output('</div> <!-- END king-c-list -->', '');
		}
	}

	public function c_list_items($c_items)
	{
		foreach ($c_items as $c_item)
			$this->c_list_item($c_item);
	}

	public function c_list_item($c_item)
	{
		$extraclass = @$c_item['classes'].(@$c_item['hidden'] ? ' king-c-item-hidden' : '');

		$this->output('<div class="king-c-list-item '.$extraclass.'" '.@$c_item['tags'].'>');

		$this->c_item_main($c_item);
		$this->c_item_clear();

		$this->output('</div> <!-- END king-c-item -->');
	}

	public function c_item_main( $c_item ) {
		$this->error( @$c_item['error'] );
		$this->post_avatar_meta( $c_item, 'king-c-item' );
		$this->post_meta_who( $c_item, 'meta' );

		if ( isset( $c_item['expand_tags'] ) ) {
			$this->c_item_expand( $c_item );
		} elseif ( isset( $c_item['url'] ) ) {
			$this->c_item_link( $c_item );
		} else {
			$this->c_item_content( $c_item );
		}

		$this->output( '<DIV CLASS="king-c-item-footer">' );
		$this->c_item_buttons( $c_item );
		$this->post_meta_when( $c_item, 'meta' );
		$this->output( '</DIV>' );
	}

	public function c_item_link($c_item)
	{
		$this->output(
			'<a href="'.$c_item['url'].'" class="king-c-item-link">'.$c_item['title'].'</a>'
		);
	}

	public function c_item_expand($c_item)
	{
		$this->output(
			'<a href="'.$c_item['url'].'" '.$c_item['expand_tags'].' class="king-c-item-expand">'.$c_item['title'].'</a>'
		);
	}

	public function c_item_content($c_item)
	{
		$this->output('<div class="king-c-item-content">');
		$this->output_raw($c_item['content']);
		$this->output('</div>');
	}

	public function c_item_buttons($c_item)
	{
		if (!empty($c_item['form'])) {
			$this->output('<div class="king-c-item-buttons">');
			$this->form($c_item['form']);
			$this->output('</div>');
		}
	}

	public function c_item_clear()
	{
		$this->output(
			'<div class="king-c-item-clear">',
			'</div>'
		);
	}


	public function q_title_list($q_list, $attrs=null)
/*
	Generic method to output a basic list of question links.
*/
	{
		$this->output('<ul class="king-q-title-list">');
		foreach ($q_list as $q) {
			$this->output(
				'<li class="king-q-title-item">',
				'<a href="' . qa_q_path_html($q['postid'], $q['title']) . '" ' . $attrs . '>' . qa_html($q['title']) . '</a>',
				'</li>'
			);
		}
		$this->output('</ul>');
	}

	public function q_ask_similar($q_list, $pretext='')
/*
	Output block of similar questions when asking.
*/
	{
		if (!count($q_list))
			return;

		$this->output('<div class="king-ask-similar">');

		if (strlen($pretext) > 0)
			$this->output('<p class="king-ask-similar-title">'.$pretext.'</p>');
		$this->q_title_list($q_list, 'target="_blank"');

		$this->output('</div>');
	}
}
