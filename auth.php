<?php
require_once 'Iamstuartwilson/StravaApi.php';
require_once 'config.inc.php';

$api = new Iamstuartwilson\StravaApi(STRAVA_API_ID, STRAVA_API_SECRET);

$strava_access_token = $redis->hget('strava', 'access');
$strava_refresh_token = $redis->hget('strava', 'refresh');
$strava_expires_at = $redis->hget('strava', 'expires');

if(!empty($strava_access_token) && !empty($strava_access_token) && !empty($strava_expires_at)){
	header('Location: ' . MAIN_URL);
}else{
	if(!empty($_GET['code'])){
		// 코드로 토큰 변환
		$response = $api->tokenExchange($_GET['code']);
		$redis->hset('strava', 'access', $response->access_token);
		$redis->hset('strava', 'refresh', $response->refresh_token);
		$redis->hset('strava', 'expires', $response->expires_at);

		header('Location: ' . MAIN_URL);
	}else{
		header('Location: ' . $api->authenticationUrl(CALLBACK_URL, 'auto', 'read_all,profile:read_all,activity:read_all'));
	}
}
