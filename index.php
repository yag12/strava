<?php
session_start();

require_once 'Iamstuartwilson/StravaApi.php';
require_once 'config.inc.php';

$api = new Iamstuartwilson\StravaApi(STRAVA_API_ID, STRAVA_API_SECRET);
$strava_access_token = !empty($_SESSION['strava_access_token']) ? $_SESSION['strava_access_token'] : null;
$strava_refresh_token = !empty($_SESSION['strava_refresh_token']) ? $_SESSION['strava_refresh_token'] : null;
$strava_expires_at = !empty($_SESSION['strava_expires_at']) ? $_SESSION['strava_expires_at'] : null;
if(!empty($strava_access_token) && !empty($strava_access_token) && !empty($strava_expires_at)){
	$api->setAccessToken($strava_access_token, $strava_refresh_token, $strava_expires_at);
	$response = $api->tokenExchangeRefresh();
	$_SESSION['strava_access_token'] = $response->access_token;
	$_SESSION['strava_refresh_token'] = $response->refresh_token;
	$_SESSION['strava_expires_at'] = $response->expires_at;
}else{
	header('Location: ' . CALLBACK_URL);
}


$pgnum = !empty($_GET['pgnum']) ? $_GET['pgnum'] : 1;
$pageset = 30;
$user_info = $api->get('/athlete');
$play_info = $api->get('/athletes/' . $user_info->id . '/stats');
$zone_info = $api->get('/athlete/zones');
$lists = $api->get('/athlete/activities', ['page' => $pgnum, 'per_page' => $pageset]);
$chart_key = array();
$chart_value = array(array(), array(), array());
if(!empty($lists)){
	foreach($lists as $row){
		foreach($zone_info->heart_rate->zones as $key=>$zone){
			if($zone->max == -1) $zone->max = 300;
			if($zone->min <= $row->average_heartrate && $zone->max > $row->average_heartrate){
				$row->zone_heartrate[0] = $key + 1;
			}
			if($zone->min <= $row->max_heartrate && $zone->max > $row->max_heartrate){
				$row->zone_heartrate[1] = $key + 1;
			}
		}

		if($row->type != 'Run') continue;
		$chart_key[] = date('m.d.', strtotime($row->start_date));
		$chart_value[0][] = $row->max_heartrate;
		$chart_value[1][] = $row->average_heartrate;
		$chart_value[2][] = $row->average_cadence * 2;
		$chart_value[3][] = floor($row->max_heartrate - $row->average_heartrate);
	}
}
?><!DOCTYPE html>
<html lang="ko">
<head>
	<title>운동 데이터</title>
	<meta charset="UTF-8">
	<script type="text/javascript" src="js/jquery-2.1.0.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui.js"></script>
	<script type="text/javascript" src="js/Chart.min.js"></script>

	<style>
	a:link { color:#4E7DAC; text-decoration:none; }
	a:visited { color:#4E7DAC; text-decoration:none; }
	a:hover { color:#6099D3; text-decoration:none; }

	input[type="text"] { border:0; width:80px; }
	input[type="number"] { border:0; text-align:right; width:54px; }
	input[type="button"] { width:100%; height:100%; }

	table { float:left; margin:0 16px 0 0; }
	table tr th { height:36px; text-align:center; background:#7575a3; color:#DDD; padding:0 12px; }
	table tr td { height:28px; vertical-align:middle; padding:0 12px; border:1px dashed #AAA; font-size:10pt; }
	table tr td.btn { height:28px; padding:8px 0 0 0; border:0; }
	table tr td.none { border:0; }

	div.chart_canvas { float:left;  width:100%; margin-top:36px; } 
	.zone1 { border-color:rgb(54, 162, 235); color:rgb(54, 162, 235); font-weight:bold; }
	.zone2 { border-color:rgb(75, 192, 192); color:rgb(75, 192, 192); font-weight:bold; }
	.zone3 { border-color:rgb(255, 205, 86); color:rgb(255, 205, 86); font-weight:bold; }
	.zone4 { border-color:rgb(255, 159, 64); color:rgb(255, 159, 64); font-weight:bold; }
	.zone5 { border-color:rgb(255, 99, 132); color:rgb(255, 99, 132); font-weight:bold; }
	fieldset { margin:12px 0 0 0; }
	</style>

	<script type="text/javascript">
	window.chartColors = {
		red: 'rgb(255, 99, 132)',
		orange: 'rgb(255, 159, 64)',
		yellow: 'rgb(255, 205, 86)',
		green: 'rgb(75, 192, 192)',
		blue: 'rgb(54, 162, 235)',
		purple: 'rgb(153, 102, 255)',
		grey: 'rgb(201, 203, 207)'
	};

	Number.prototype.fillZero = function(width){
    	let n = String(this);//문자열 변환
    	return n.length >= width ? n:new Array(width-n.length+1).join('0')+n;//남는 길이만큼 0으로 채움
	}

	$(document).ready(function(){
		$("input[class='timeformat']").change(function(){
			var time = $(this).val().replace(/\:/g, ""),
				h = 0, m = 0, s = 0,
				len = 0,
				time_val = "00:00:00";

			s = Number(time.substr(-2, 2));
			if(time.length > 2){
				len = time.length < 4 ? time.length - 2 : 2;
				m = Number(time.substr(-4, len));
			}
			if(time.length > 4 && time.length <= 6){
				len = time.length - 4;
				h = Number(time.substr(-6, len));
			}

			$(this).val(h.fillZero(2) + ":" + ((m > 60) ? "00" : m.fillZero(2)) + ":" + ((s > 60) ? "00" : s.fillZero(2)));
		});

		$("#pace_btn").click(function(){
			var distance = $("#distance").val(),
				time = $("#time").val(),
				cadence = $("#cadence").val(),
				pace_time = time.split(":"),
				pace_hour = typeof pace_time[0] == "undefined" ? 0 : Number(pace_time[0]),
				pace_min = typeof pace_time[1] == "undefined" ? 0 : Number(pace_time[1]),
				pace_sec = typeof pace_time[2] == "undefined" ? 0 : Number(pace_time[2]),
				pace = ((pace_hour * 60 * 60) + (pace_min * 60) + pace_sec) / distance,
				pace_min = Math.floor(pace / 60),
				pace_sec = Math.floor(pace % 60),
				step = 1000 / ((cadence * pace_min) + (cadence * (pace_sec / 60)));

			$("#pace").val(pace_min + "." + pace_sec);
			$("#speed").val((3600 / pace).toFixed(2));
			$("#step").val(step.toFixed(2));
		});

		$("table tr td a").click(function(){
			var span = $(this).parent().parent().find("span");

			$("#time").val($(span[0]).text());
			$("#distance").val($(span[1]).text());
			$("#cadence").val($(span[2]).text());
			$("#pace_btn").click();
		});

		new Chart($("#lists_chart"), {
			type: "bar",
			data: {
				labels: <?php echo json_encode(array_reverse($chart_key)); ?>,
				datasets: [{
					label: "최대심박수",
					data: <?php echo json_encode(array_reverse($chart_value[0])); ?>,
					backgroundColor: window.chartColors.red,
					borderColor: window.chartColors.red,
					type: "line",
					fill: false
				}, {
					label: "평균심박수",
					data: <?php echo json_encode(array_reverse($chart_value[1])); ?>,
					backgroundColor: window.chartColors.blue,
					borderColor: window.chartColors.blue,
					type: "line",
					fill: false
				}, {
					label: "평균케이던스",
					data: <?php echo json_encode(array_reverse($chart_value[2])); ?>,
					backgroundColor: window.chartColors.green,
					borderColor: window.chartColors.green,
					type: "line",
					fill: false
				}, {
					label: "심박수차이",
					data: <?php echo json_encode(array_reverse($chart_value[3])); ?>,
					backgroundColor: window.chartColors.purple,
					borderColor: window.chartColors.purple,
					hidden: true
				}]
			},
			options: {
				elements: {
					line: {
						tension: 0.000001
					}
				},
			}
		});

	});
	</script>
<body>
	<table>
		<tr>
			<th colspan="3">스트라바</th>
		</tr>
		<tr>
			<th>이름</th>
			<td><?php echo $user_info->lastname; ?> <?php echo $user_info->firstname; ?></td>
			<td rowspan="3"><img src="<?php echo $user_info->profile; ?>" width="100" /></td>
		</tr>
		<tr>
			<th>지역</th>
			<td><?php echo $user_info->country; ?> <?php echo $user_info->state; ?> <?php echo $user_info->city; ?></td>
		</tr>
		<tr>
			<th>생성일자</th>
			<td><?php echo date('Y-m-d H:i:s', strtotime($user_info->created_at)); ?></td>
		</tr>
		<tr>
			<th colspan="2">달리기</th>
			<th>심박 영역</th>
		</tr>
		<tr>
			<th>누적 수</th>
			<td><?php echo number_format($play_info->ytd_run_totals->count); ?> / <?php echo number_format($play_info->all_run_totals->count); ?></td>
			<td rowspan="14" valign="top">
				<?php foreach(array_reverse($zone_info->heart_rate->zones) as $key=>$zone): ?>
				<fieldset class="zone<?php echo 5-$key; ?>">
					<legend>Zone <?php echo 5-$key; ?></legend>
					<div>
						<?php echo $zone->min; ?>
						~
						<?php echo $zone->max < 220 ? $zone->max : ''; ?>
					</div>
				</fieldset>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th>누적 거리</th>
			<td><?php echo number_format($play_info->ytd_run_totals->distance / 1000, 3); ?> km / <?php echo number_format($play_info->all_run_totals->distance / 1000, 3); ?> km</td>
		</tr>
		<tr>
			<th>누적 시간</th>
			<td><?php echo gmdate('H시 i분', $play_info->ytd_run_totals->moving_time); ?></td>
		</tr>
		<tr>
			<th>누적 상승고도</th>
			<td><?php echo number_format($play_info->ytd_run_totals->elevation_gain / 1000, 3); ?> km</td>
		</tr>
		<tr>
			<th colspan="2">자전거</th>
		</tr>
		<tr>
			<th>누적 수</th>
			<td><?php echo number_format($play_info->ytd_ride_totals->count); ?> / <?php echo number_format($play_info->all_ride_totals->count); ?></td>
		</tr>
		<tr>
			<th>누적 거리</th>
			<td><?php echo number_format($play_info->ytd_ride_totals->distance / 1000, 3); ?> km / <?php echo number_format($play_info->all_ride_totals->distance / 1000, 3); ?> km</td>
		</tr>
		<tr>
			<th>누적 시간</th>
			<td><?php echo gmdate('H시 i분', $play_info->ytd_ride_totals->moving_time); ?></td>
		</tr>
		<tr>
			<th>누적 상승고도</th>
			<td><?php echo number_format($play_info->ytd_ride_totals->elevation_gain / 1000, 3); ?> km</td>
		</tr>
		<tr>
			<th colspan="2">수영</th>
		</tr>
		<tr>
			<th>누적 수</th>
			<td><?php echo number_format($play_info->ytd_swim_totals->count); ?> / <?php echo number_format($play_info->all_swim_totals->count); ?></td>
		</tr>
		<tr>
			<th>누적 거리</th>
			<td><?php echo number_format($play_info->ytd_swim_totals->distance / 1000, 3); ?> km / <?php echo number_format($play_info->all_swim_totals->distance / 1000, 3); ?> km</td>
		</tr>
		<tr>
			<th>누적 시간</th>
			<td><?php echo gmdate('H시 i분', $play_info->ytd_swim_totals->moving_time); ?></td>
		</tr>
		<tr>
			<th>누적 상승고도</th>
			<td><?php echo number_format($play_info->ytd_swim_totals->elevation_gain / 1000, 3); ?> km</td>
		</tr>
	</table>

	<table>
		<tr>
			<th colspan="10">
				<?php if($pgnum > 1): ?>
					<a href="./?pgnum=<?php echo $pgnum - 1; ?>">◀ </a>
				<?php else: ?>◁<?php endif; ?>
				내 최근 활동
				<?php if(sizeof($lists) < $pageset): ?>▷
				<?php else: ?><a href="./?pgnum=<?php echo $pgnum + 1; ?>">▶ </a><?php endif; ?>
			</th>
		</tr>
		<tr>
			<th>종류</th>
			<th>날짜</th>
			<th>제목</th>
			<th>시간</th>
			<th>거리</th>
			<th>고도</th>
			<th>케이던스</th>
			<th>평균심박수</th>
			<th>최대심박수</th>
			<th>→</th>
		</tr>
		<?php if(!empty($lists)): ?>
		<?php foreach($lists as $row): ?>
		<tr>
			<td><?php echo $row->type; ?></td>
			<td><?php echo date('Y-m-d H:i:s', strtotime($row->start_date)); ?></td>
			<td><a href="<?php echo \Iamstuartwilson\StravaApi::BASE_URL; ?>/activities/<?php echo $row->id; ?>" target="_blank"><?php echo $row->name; ?></a></td>
			<td><span><?php echo gmdate('H:i:s', $row->moving_time); ?></span></td>
			<td><span><?php echo number_format($row->distance / 1000, 3); ?></span> km</td>
			<td><?php echo number_format($row->total_elevation_gain); ?> m</td>
			<td><span><?php echo $row->average_cadence * 2; ?></span> rpm</td>
			<td class="zone<?php echo $row->zone_heartrate[0]; ?>"><?php echo ceil($row->average_heartrate); ?> bpm</td>
			<td class="zone<?php echo $row->zone_heartrate[1]; ?>"><?php echo $row->max_heartrate; ?> bpm</td>
			<td><a href="javascript:void(0);">→</a></td>
		</tr>
		<?php endforeach; ?>
		<?php else: ?>
		<tr>
			<td colspan="10">내 활동 내역이 없습니다.</td>
		</tr>
		<?php endif; ?>
	</table>

	<table>
		<colgroup>
			<col width="120" />
			<col width="" />
			<col width="" />
			<col width="120" />
			<col width="" />
		</colgroup>
		<tr>
			<th colspan="5">평균 페이스 구하기</th>
		</tr>
		<tr>
			<th>소요시간</th>
			<td><input type="text" id="time" value="" class="timeformat" placeholder="00:00:00" /></td>
			<td rowspan="3" class="none">▶</td>
			<th>평균 페이스</th>
			<td><input type="number" id="pace" value="0" readonly /> /km</td>
		</tr>
		<tr>
			<th>거리</th>
			<td><input type="number" id="distance" value="" step="0.01" placeholder="0.00" /> km</td>
			<th>평균 속도 </th>
			<td><input type="number" id="speed" value="0" readonly /> km/h</td>
		</tr>
		<tr>
			<th>케이던스</th>
			<td><input type="number" id="cadence" value="" placeholder="0" /> rpm</td>
			<th>평균 보폭</th>
			<td><input type="number" id="step" value="0" readonly /> m</td>
		</tr>
		<tr>
			<td colspan="5" class="btn"><input type="button" id="pace_btn" value="계산하기" /></td>
		</tr>
	</table>

	<div id="map" style="width:100%;height:400px;"></div>
	<div class="chart_canvas"><canvas id="lists_chart"></canvas></div>

</body>
</html>

