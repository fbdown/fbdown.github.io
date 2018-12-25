<?php

namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;

class Downloader
{
	private static $promises = [];

	protected $body;


	public function getSourceCode($url)
    {
        $response = $this->httpRequest($url);
        $status = $response->getStatusCode();
        if ($status === 200) {
            return $this->body = $response->getBody()->getContents();
        }

        throw new Exception('Something went wrong, HTTP Status Code Returned: '.$status);
    }

    private function httpRequest($url, array $options = [], $isAsyncRequest = false)
    {
        if ($url == null || trim($url) == '') {
            return false;
        }

        $options = $this->getOptions($this->defaultHeaders(), $options, $isAsyncRequest);
        try {
        	$client = new Client();
            $response = $client->getAsync($url, $options);
            if ($isAsyncRequest) {
                self::$promises[] = $response;
            } else {
                $response = $response->wait();
            }
        } 
        catch (RequestException $e) {
            return false;
        }

        return $response;
    }

    private function getOptions(array $headers, $options = [], $isAsyncRequest = false)
    {
        $default_options = [
            RequestOptions::HEADERS     => $headers,
            RequestOptions::SYNCHRONOUS => !$isAsyncRequest,
        ];
        return array_merge($default_options, $options);
    }

    protected function defaultHeaders()
    {
        return [
            'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36',
            'Accept-Language' => 'en-US,en;q=0.8,sr;q=0.6,pt;q=0.4',
        ];
    }

    protected function decodeUnicode($str)
    {
        return preg_replace_callback(
            '/\\\\u([0-9a-f]{4})/i',
            [$this, 'replace_unicode_escape_sequence'],
            $str
        );
    }

    protected function cleanStr($str)
    {
        return html_entity_decode(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }

    protected function replace_unicode_escape_sequence($uni)
    {
        return mb_convert_encoding(pack('H*', $uni[1]), 'UTF-8', 'UCS-2BE');
    }

    public function generateUrl($url)
    {
        $id = '';
        if (is_int($url)) {
            $id = $url;
        } elseif (preg_match('/^http(?:s?):\/\/(?:www\.|web\.|m\.)?facebook\.com\/([A-z0-9\.]+)\/videos(?:\/[0-9A-z].+)?\/(\d+)(?:.+)?$/', $url, $matches)) {
            $id = empty($matches[2]) === false ? $matches[2] : null;
        }

        if(empty($id) === true){
        	return false;
        }

        return 'https://www.facebook.com/video.php?v='.$id;
    }

    public function getVideoInfo($url)
    {
    	$url = $this->generateUrl($url);
    	if(empty($url) === true){
    		return false;
    	}

        $this->getSourceCode($url);
        $title = $this->getTitle();
        if (strtolower($title) === "sorry, this content isn't available at the moment") {
            return false;
        }

        $description = $this->getDescription();
        $owner = $this->getValueByKey('ownerName');
        $created_time = $this->getCreatedTime();
        $hd_link = $this->getValueByKey('hd_src_no_ratelimit');
        $sd_link = $this->getValueByKey('sd_src_no_ratelimit');
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
		$id = $query['v'];

        return compact('title', 'description', 'owner', 'created_time', 'hd_link', 'sd_link', 'id');
    }

    public function getTitle()
    {
        $title = null;
        if (preg_match('/h2 class="uiHeaderTitle"?[^>]+>(.+?)<\/h2>/', $this->body, $matches)) {
            $title = $matches[1];
        } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/im', $this->body, $matches)) {
            $title = $matches[1];
        }

        return $this->cleanStr($title);
    }

    public function getDescription()
    {
        if (preg_match('/span class="hasCaption">(.+?)<\/span>/', $this->body, $matches)) {
            return $this->cleanStr($matches[1]);
        }
        return false;
    }

    public function getCreatedTime()
    {
        if (preg_match('/data-utime="(.+?)"/', $this->body, $matches)) {
            return $matches[1];
        }
        return false;
    }
    

    public function getValueByKey($key)
    {

        if (preg_match('/'.$key.':"(.*?)"/i', $this->body, $matches)) {
            $str = $this->decodeUnicode($matches[1]);
            return stripslashes(rawurldecode($str));
        }

        return false;
    }
}
