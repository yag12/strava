<?php
session_start();

require_once 'Iamstuartwilson/StravaApi.php';
require_once 'config.inc.php';

$api = new Iamstuartwilson\StravaApi(STRAVA_API_ID, STRAVA_API_SECRET);
$strava_access_token = !empty($_SESSION['strava_access_token']) ? $_SESSION['strava_access_token'] : null;
$strava_refresh_token = !empty($_SESSION['strava_refresh_token']) ? $_SESSION['strava_refresh_token'] : null;
$strava_expires_at = !empty($_SESSION['strava_expires_at']) ? $_SESSION['strava_expires_at'] : null;

if(!empty($strava_access_token) && !empty($strava_access_token) && !empty($strava_expires_at)){
	header('Location: ' . MAIN_URL);
}else{
	if(!empty($_GET['code'])){
		// 코드로 토큰 변환
		$response = $api->tokenExchange($_GET['code']);
		$_SESSION['strava_access_token'] = $response->access_token;
		$_SESSION['strava_refresh_token'] = $response->refresh_token;
		$_SESSION['strava_expires_at'] = $response->expires_at;

		header('Location: ' . MAIN_URL);
	}else{
		header('Location: ' . $api->authenticationUrl(CALLBACK_URL, 'auto', 'read_all,profile:read_all,activity:read_all'));
	}
}
