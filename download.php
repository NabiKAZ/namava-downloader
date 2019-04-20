<?php
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
$contents = get_contents('https://www.namava.ir/');
preg_match('/<span class="hidden-xs margin-left-5">\r\n(.*?)\r\n.*<\/span>/', $contents, $match);
$fullname = trim(@$match[1]);
if ($fullname != '') {
    echo "Your username: $fullname\n";
} else {
    echo "> Login to namava.ir\n";
    echo "Input username: ";
    $username = trim(fgets(STDIN));
    echo "Input password: ";
    $password = trim(fgets(STDIN));
    echo "Logging...\n";
    preg_match('/<input name="__RequestVerificationToken" type="hidden" value="(.*?)" \/>/', $contents, $match);
    $token = $match[1];
    $post_data = array(
        '__RequestVerificationToken' => $token,
        'Username' => $username,
        'Password' => $password,
        'Remember' => 'true',
        'Item1.Remember' => 'false',
        'redirectTo' => 'https://www.namava.ir/user/profile',
    );
    $contents = get_contents('https://www.namava.ir/Authentication/PostLogin?redirectTo=https://www.namava.ir/user/profile', $post_data);
    preg_match('/<div class="alert alert-danger">(.*?)<\/div>/', $contents, $match);
    if (isset($match[1])) {
        die("Error: " . $match[1] . "\n");
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

$domain = 'https://www.namava.ir';
$page_url = $domain . '/1/1/' . $video_id;
$contents = get_contents($page_url);
preg_match('/<h1.*id="post-name">(.*?)<\/h1>/', $contents, $match);
if (!isset($match[1])) {
    die("\nSorry! Not found any video with this ID.\n");
}
$title = $match[1];
echo "Title: $title\n";

preg_match('/<img class="img-border img-style" src="(.*?)\?.*\/>/', $contents, $match);
$cover = $match[1];

echo "===========================================================\n";

$contents_watch = get_contents('https://www.namava.ir/play/' . $video_id);

$subtitle = null;
preg_match('/{"file":"([^}]+?)"[^}]+Farsi[^}]+?}/', $contents_watch, $match_subtitle);
if (isset($match_subtitle[1])) {
    $subtitle = $match_subtitle[1];
}

preg_match('/file:\'(.*?)\',/', $contents_watch, $match);
$m3u8_url = $match[1];
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
$cmd = 'ffmpeg ' . $cmd_proxy . ' -i "' . $qualities[$input]['url'] . '" -c copy -y "' . $video_file . '"';
$log_file = $base_path . $file_name . '.log';
$info_file = $base_path . $file_name . '.info';
$cover_file = $base_path . $file_name . '.jpg';
$subtitle_file = $base_path . $file_name . '.srt';

$info = array();
$info['video_id'] = $video_id;
$info['title'] = $title;
$info['page_url'] = $page_url;
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
    global $config_path;
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
        'Origin: https://www.namava.ir',
        'Accept-Encoding: gzip, deflate',
        'Accept-Language: en-US,en;q=0.9',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.84 Safari/537.36',
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Cache-Control: max-age=0',
        'Referer: https://www.namava.ir/Authentication/PostLogin?redirectTo=https%3A%2F%2Fwww.namava.ir%2Fuser%2Fprofile',
        'Proxy-Connection: keep-alive',
    ));
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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