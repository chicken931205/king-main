<?php
/*

File: king-include/king-ajax-click-wall.php
Description: Server-side response to Ajax single clicks on wall posts

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

require_once QA_INCLUDE_DIR . 'king-app/users.php';
require_once QA_INCLUDE_DIR . 'king-app/limits.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';



if ( qa_is_logged_in() ) {
	$userid = qa_get_logged_in_userid();
} else {
	$userid = qa_remote_ip_address();
}
            $input = qa_post_text('input');

			$aiselect = qa_post_text('selectElement');
			$promter = qa_post_text('promter');
			$imsize = qa_post_text('radioBut');
			$chkk = true;
			if ( qa_opt('enable_membership') && ( qa_opt('ailimits') || qa_opt('ulimits') ) && qa_get_logged_in_level() <= QA_USER_LEVEL_ADMIN ) {
				$chkk = kingai_check();
			}
			if (qa_opt('enable_credits') && qa_opt('post_ai')) {
				$chkk = king_spend_credit(qa_opt('post_ai'));
			}
			if ($input && $chkk) {
				$npvalue = (null !== qa_post_text('npvalue')) ? qa_post_text('npvalue') : '';
				$imagen = qa_opt('kingai_imgn');

				if ('de' !== $aiselect && 'de3' !== $aiselect ) {

					$sdapi = qa_opt('king_sd_api');
					$aistyle = qa_post_text('style');
					$aisteps = qa_opt('king_sd_steps');
					$URL = "https://kingstudio.io/api/king-text2img"; 

					if ( isset($aistyle) && 'none' !== $aistyle ) {
						$style_preset = $aistyle;
					} else {
						$style_preset = '';
					}

					if (qa_opt('ennsfw')) {
						$ennsfw = true;
					} else {
						$ennsfw = false;
					}
					if (qa_opt('sdnsfw')) {
						$sdnsfw = true;
					} else {
						$sdnsfw = false;
					}
					
					$initialData = array(
						"prompt" => $input . ', ' . $style_preset,
						"size" => (int)$imagen,
						"steps" => (int)$aisteps,
						"aisize" => $imsize,
						"model" => $aiselect,
						"nvalue" =>$npvalue,
						"ennsfw" => $ennsfw,
						"sdnsfw" => $sdnsfw,
					);
					$ch = curl_init($URL);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HTTPHEADER, [
						"Authorization: Bearer $sdapi",
						"Accept: application/json",
						"Content-Type: application/json",

					]);
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($initialData));
					curl_setopt($ch, CURLOPT_TIMEOUT, 400);
					$response = curl_exec($ch);
					$out = json_decode($response, true);

					if (isset($out['error'])) {
						$output = json_encode(array('success' => false, 'message' => $out['error']));
					} else {
						if ( qa_opt('enable_membership') && ( qa_opt('ailimits') || qa_opt('ulimits') ) ) {
							kingai_imagen($imagen);
						}
						$output = json_encode(array('success' => true, 'message' => $out));


					}
				} else {
					$openaiapi = qa_opt('king_leo_api');
					$url = 'https://api.openai.com/v1/images/generations';

					if ('de3' === $aiselect) {
						$params = array(
							'model' => 'dall-e-3',
							'prompt' => $input,
							'n' => 1,
							'size' => $imsize,
						);
					} else {
						$params = array(
							'prompt' => $input,
							'n' => (int)$imagen,
							'size' => $imsize,
						);
					}
					$params_json = json_encode($params);
					$headers = array(
						'Content-Type: application/json',
						'Authorization: Bearer ' . $openaiapi,
					);

					$ch = curl_init($url);

					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
					curl_setopt($ch, CURLOPT_POSTFIELDS, $params_json);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt($ch, CURLOPT_TIMEOUT, 400);
					$response_body = curl_exec($ch);

					curl_close($ch);

					$response_obj = json_decode($response_body, true);

					if ( qa_opt('enable_membership') && ( qa_opt('ailimits') || qa_opt('ulimits') ) ) {
						kingai_imagen($imagen);
					}
					$result['out'] = array_column($response_obj['data'], 'url');
					$result['format'] = "url";

					$output = json_encode(array('success' => true, 'message' => $result));
					
				}
                echo "QA_AJAX_RESPONSE\n1\n";

                echo $output."\n";
				
			} else {
				$outputz = json_encode(array('success' => false, 'message' => qa_lang_html('misc/nocredits')));
				
				echo "QA_AJAX_RESPONSE\n0\n";

                echo $outputz."\n";
			}

