<?php
function imgToBase64($img) {
	if(($f = fopen($img, 'rb', 0)) && ($p = fread($f, filesize($img))) && fclose($f))
		return '<img src="data:image/gif;base64,' . chunk_split(base64_encode($p)) .'" width="80" height="15" />';
	return FALSE;
}
?>