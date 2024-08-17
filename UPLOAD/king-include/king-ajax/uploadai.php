<?php
/*
Description: Upload ai image.

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

require_once QA_INCLUDE_DIR . 'king-app-video.php';
require_once QA_INCLUDE_DIR . 'king-db/selects.php';
$output = '';
$iurl = qa_post_text('iurl');
$format = qa_post_text('format');

if (empty($iurl)) {
    echo "QA_AJAX_RESPONSE\n0\n";
    return;
} else {
    if($format === 'url') {

        $thumb['thumb'] = king_urlupload($iurl, true, 600);
        $thumb['main'] = king_urlupload($iurl);
        $turl = king_get_uploads($thumb['main']);
        $thumb['url'] = $turl['furl'];
        $output =  json_encode($thumb);
    } else {
        $DestinationDirectory = QA_INCLUDE_DIR . 'uploads';
        $canvasData = base64_decode($iurl);
        $year_folder  = $DestinationDirectory . date("Y");

        $NewImageName = uniqid() . '-ai.png';
        $DestFolder = $DestinationDirectory . '/' . $NewImageName;
        file_put_contents($DestFolder, $canvasData);

        $thumb['thumb'] = king_urlupload($DestFolder, true, 600);
        $thumb['main'] = king_urlupload($DestFolder);
        $turl = king_get_uploads($thumb['main']);
        $thumb['url'] = $turl['furl'];
        $output =  json_encode($thumb);
        unlink( $DestFolder );
    }
	
echo "QA_AJAX_RESPONSE\n1\n";

echo $output."\n";
				
}

