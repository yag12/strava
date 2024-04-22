<?php
require_once 'Iamstuartwilson/StravaApi.php';
require_once 'config.inc.php';

$api = new Iamstuartwilson\StravaApi(STRAVA_API_ID, STRAVA_API_SECRET);
$strava_access_token = $redis->hget('strava', 'access');
$strava_refresh_token = $redis->hget('strava', 'refresh');
$strava_expires_at = $redis->hget('strava', 'expires');

$api->setAccessToken($strava_access_token, $strava_refresh_token, $strava_expires_at);
$response = $api->tokenExchangeRefresh();
$redis->hset('strava', 'access', $response->access_token);
$redis->hset('strava', 'refresh', $response->refresh_token);
$redis->hset('strava', 'expires', $response->expires_at);

$pgnum = 1;
$pageset = 100;
$lists = $api->get('/athlete/activities', ['page' => $pgnum, 'per_page' => $pageset]);
$rows = array();
if(!empty($lists)){
	foreach($lists as $row){
		if($row->type != 'Run') continue;
		$rows[$row->sport_type][] = $row;
	}
}

foreach($rows as $type => $sport){
	echo "-------------------------------------------------------------------------------------\n";
	foreach($sport as $row){
		$pace = $row->elapsed_time / ($row->distance / 1000);
		$step = 1000 / ((ceil($row->average_cadence * 2) *  floor($pace / 60)) + (ceil($row->average_cadence * 2) * (floor($pace % 60) / 60)));
	
		echo $row->sport_type . "[" . date('m.d H:i', strtotime($row->start_date)) . "]\t";
		echo number_format($row->distance / 1000, 1) . "km\t";
		echo gmdate('H:i:s', $row->elapsed_time) . "\t";
		echo sprintf('%02d', floor($pace / 60)) . ":" . sprintf('%02d', floor($pace % 60)) . "\t";
		echo ceil($row->average_cadence * 2) . "rpm\t";
		echo ceil($row->average_heartrate) . "/" . ceil($row->max_heartrate) . "bpm\t";
		echo number_format($step, 2) . "m\t";
		echo "\n";
	}
	echo "-------------------------------------------------------------------------------------\n";
}
