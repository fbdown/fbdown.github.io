<?php

require_once "vendor/autoload.php";

use Embed\Embed;
use App\Downloader;

$url = empty($_POST['url']) === false ? $_POST['url'] : null;
$data = [];

if(empty($url) === false){
	$downloader = new Downloader();
	$data = $downloader->getVideoInfo($url);
	if(empty($data['id']) === false){
		$data['thumb'] = 'http://graph.facebook.com/'.$data['id'].'/picture';
	}	
}

if(empty($data['id']) === true){
	echo '';
}
else{
	echo ' <div class="card">
      <div class="row ">
        <div class="col-md-4">
            <img src="'.$data['thumb'].'" class="w-100">
          </div>
          <div class="col-md-8 px-3">
            <div class="card-block px-3">
              <h4 class="card-title">'.$data['owner'].' - '.$data['title'].'</h4>
              <a href="'.$data['sd_link'].'" target="_blank" class="btn btn-outline-primary btn-sm">Download Video in Normal Quality</a>
		    <a href="'.$data['hd_link'].'" target="_blank" class="btn btn-outline-primary btn-sm">Download Video in HD Quality</a>
            </div>
          </div>

        </div>
      </div>
    </div>';
}
die();