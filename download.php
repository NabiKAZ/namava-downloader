<?php

if (php_sapi_name() !== "cli") exit("403");

echo "Namava Downloader - version 0.1.0 - Copyright 2017\n";
echo "By Nabi KaramAliZadeh <www.nabi.ir> <nabikaz@gmail.com>\n";
echo "Signup here: https://www.namava.ir/\n";
echo "Project link: https://github.com/NabiKAZ/namava-downloader\n";
echo "===========================================================\n";

$config_path = 'config/';
$base_path = 'download/';
$proxy = '';

login:
@mkdir($config_path);
$contents = get_contents('https://www.namava.ir/api/v1.0/users/info');
$results = json_decode($contents);
$fullname = trim(@$results->result->firstName . ' ' . @$results->result->lastName);
if ($fullname != '') {
    echo "Your username: $fullname\n";
} else {
    echo "> Login to namava.ir\n";
    echo "Input username: ";
    $username = trim(fgets(STDIN));
	$username = str_replace(array(' ', '.', '-'), '', $username);
	if (substr($username, 0, 2) == '00') {
		$username = '+98' . substr($username, 4);
	}
	if (substr($username, 0, 1) == '0') {
		$username = '+98' . substr($username, 1);
	}
	if (substr($username, 0, 1) != '+') {
		$username = '+98' . $username;
	}
    echo "Input password: ";
    $password = trim(fgets(STDIN));
    echo "Logging...\n";
    $post_data = array(
        'UserName' => $username,
        'Password' => $password,
    );
    $contents = get_contents('https://www.namava.ir/api/v1.0/accounts/by-phone/login', $post_data);
    $results = json_decode($contents);
    if ($results->succeeded == false) {
        die("Error: " . $results->error->message . "\n");
    } else {
        echo "Logged In.\n";
        echo "IMPORTANT: Your private cookies is stored in the '$config_path' directory, Be careful about its security!\n";
        echo "===========================================================\n";
        goto login;
    }
}
echo "===========================================================\n";

echo "Input Video ID: ";
$video_id = trim(fgets(STDIN));

$contents = get_contents('https://www.namava.ir/api2/movie/' . $video_id);
$results = json_decode($contents);
if (!isset($results->Name)) {
    die("\nSorry! Not found any video with this ID.\n");
}
$title = $results->Name;
echo "Title: $title\n";
$cover = $results->ImageAbsoluteUrl;

echo "===========================================================\n";

$subtitle = null;
$key = array_search('Farsi.srt', array_column($results->MediaInfoModel->Tracks, 'Label'));
if ($key !== false && isset($results->MediaInfoModel->Tracks[$key]->FileFullName)) {
    $subtitle = $results->MediaInfoModel->Tracks[$key]->FileFullName;
}

$m3u8_url = $results->MediaInfoModel->Domain . $results->MediaInfoModel->File;
$contents = get_contents($m3u8_url);
preg_match_all('/#.*BANDWIDTH=(.*?),RESOLUTION=(.*?),.*\n(.*?)\n/', $contents, $matches);

$qualities = array();
foreach ($matches[1] as $key => $value) {
    $qualities[] = array(
        'bandwidth' => $matches[1][$key],
        'resolution' => $matches[2][$key],
        'url' => $matches[3][$key],
    );
}

$qualities = multisort($qualities, 'bandwidth', SORT_ASC);
$qualities = array_combine(range(1, count($qualities)), array_values($qualities));

echo "Select Quality:\n";
foreach ($qualities as $key => $value) {
    echo $key . ") BANDWIDTH=" . $value['bandwidth'] . " - RESOLUTION=" . $value['resolution'] . "\n";
}
echo "Input option number: ";
$input = trim(fgets(STDIN));

@mkdir($base_path);
$file_name = $video_id . '_' . $qualities[$input]['bandwidth'];
$video_file = $base_path . $file_name . '.mp4';
if (isset($proxy) && $proxy) {
    $cmd_proxy = '-http_proxy http://' . $proxy;
} else {
    $cmd_proxy = '';
}
$cmd = 'ffmpeg -headers "User-Agent: "' . $cmd_proxy . ' -i "' . $qualities[$input]['url'] . '" -c copy -y "' . $video_file . '"';
$log_file = $base_path . $file_name . '.log';
$info_file = $base_path . $file_name . '.info';
$cover_file = $base_path . $file_name . '.jpg';
$subtitle_file = $base_path . $file_name . '.srt';

$info = array();
$info['video_id'] = $video_id;
$info['title'] = $title;
$info['bandwidth'] = $qualities[$input]['bandwidth'];
$info['resolution'] = $qualities[$input]['resolution'];
$info = json_encode($info);
file_put_contents($info_file, $info);

if ($cover) {
	file_put_contents($cover_file, get_contents($cover));
}

if ($subtitle) {
    file_put_contents($subtitle_file, normalize_subtitle(get_contents($subtitle)));
}

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    pclose(popen('start /B ' . $cmd . '<nul >nul 2>"' . $log_file . '"', 'r'));
} else {
    shell_exec($cmd . '</dev/null >/dev/null 2>"' . $log_file . '" &');
}

echo "===========================================================\n";
echo "Video file: $video_file\n";
echo "Cover file: " . ($cover ? $cover_file : 'N/A') . "\n";
echo "Subtitle file: " . ($subtitle ? $subtitle_file : 'N/A') . "\n";
echo "Log file: $log_file\n";
echo "Info file: $info_file\n";
echo "Start downloading in the background...\n";
echo "You can see stats download with 'stats.php' in the web page.\n";
echo "For stop process, kill it.\n";
echo "Bye!\n";


function get_contents($url, $data = null)
{
    global $config_path, $proxy;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $config_path . 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, $config_path . 'cookies.txt');
    if (isset($proxy) && $proxy) {
        list($proxyIp, $proxyPort) = explode(':', $proxy);
        curl_setopt($ch, CURLOPT_PROXY, $proxyIp);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authority: www.namava.ir',
        'Accept: application/json, text/plain, */*',
        'X-Application-Type: WebClient',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36',
        'Content-Type: application/json;charset=UTF-8',
        'Origin: https://www.namava.ir',
        'Sec-Fetch-Site: same-origin',
        'Sec-Fetch-Mode: cors',
        'Referer: https://www.namava.ir/auth/login-phone',
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: en-US,en;q=0.9,fa;q=0.8',
    ));
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    return curl_exec($ch);
}

function multisort($mdarray, $mdkey, $sort = SORT_ASC)
{
    foreach ($mdarray as $key => $row) {
        // replace 0 with the field's index/key
        $dates[$key] = $row[$mdkey];
    }
    array_multisort($dates, $sort, $mdarray);
    return $mdarray;
}

function normalize_subtitle($sub)
{
	$sub = str_replace("WEBVTT\r\n", '', $sub);

	preg_match_all('/^(\r\n.+ --> .+)/m', $sub, $matches);
	$matches = end($matches);

	$n = 0;
	foreach ($matches as $match) {
		$n++;
		$sub = str_replace($match, "\r\n" . $n . $match, $sub);
	}

	return $sub;
}
