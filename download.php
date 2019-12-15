<?php

if (php_sapi_name() !== "cli") exit("403");

echo "Namava Downloader - version 0.1.0 - Copyright 2017\n";
echo "By Nabi KaramAliZadeh <www.nabi.ir> <nabikaz@gmail.com>\n";
echo "Signup here: https://www.namava.ir/\n";
echo "Project link: https://github.com/NabiKAZ/namava-downloader\n";
echo "===========================================================\n";


////////////////////////////////////////////
///
///     INIT
///
///

$config_path = 'config/';
$base_path = 'download/';
$proxy = '';

    // custom user-agent for ffmpeg request
    // change this user-agent if this user agent blocked from namava.ir
$customFFMpegUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';


if(is_dir($config_path) == false)
    mkdir($config_path, 0777, true);

if(is_dir($base_path) == false)
    mkdir($base_path, 0777, true);


////////////////////////////////////////////
///
/// LOGIN
///
do{
    $contents = get_contents('https://www.namava.ir/');
    $contents = str_replace(array("\r\n", "\n\r", "\r", "\n"), '', $contents);
    preg_match('/<span class="hidden-xs margin-left-5">(.*?)<\/span>/', $contents, $match);
    $fullname = trim(@$match[1]);
    if ($fullname != '') {
        echo "Logged In.\n";
        echo "IMPORTANT: Your private cookies is stored in the '$config_path' directory, Be careful about its security!\n";
        echo "---> Your username: $fullname\n";
        echo "===========================================================\n";
        break;
    } else {
        echo "> Login to namava.ir\n";
        echo "Input username <Empty For End>: ";
        $username = trim(fgets(STDIN));

        // Exit if user name is empty
        if($username == false)
            die("Bye!\n");

        echo "\n";
        echo "                Input password: ";
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
            echo ("Error: " . $match[1] . "\n");
        }
    }
}while(true);


echo "===========================================================\n";

echo "Input Video ID: ";
$video_id = trim(fgets(STDIN));


$domain = 'https://www.namava.ir';
$page_url = $domain . '/1/1/' . $video_id;
$contents = get_contents($page_url);
preg_match('/<div.*id="post-name">(.*?)<\/div>/', $contents, $match);
if (!isset($match[1]))
{
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
if (isset($match_subtitle[1]))
{
    $subtitle = $match_subtitle[1];
}

preg_match('/file:\'(.*?)\',/', $contents_watch, $match);

if (!isset($match[1]))
{
    file_put_contents('test.html', $contents_watch);
    die("\nSorry! Not found any resolution in m3u file.\n");
}

$m3u8_url = $match[1];
$contents = get_contents($m3u8_url);
preg_match_all('/#.*BANDWIDTH=(.*?),RESOLUTION=(.*?),.*\n(.*?)\n/', $contents, $matches);

$qualities = array();
foreach ($matches[1] as $key => $value)
{
    $qualities[] = array(
        'bandwidth' => $matches[1][$key],
        'resolution' => $matches[2][$key],
        'url' => $matches[3][$key],
    );
}

$qualities = multisort($qualities, 'bandwidth', SORT_ASC);
$qualities = array_combine(range(1, count($qualities)), array_values($qualities));

echo "Select Quality:\n";
foreach ($qualities as $key => $value)
{
    echo $key . ") BANDWIDTH=" . $value['bandwidth'] . " - RESOLUTION=" . $value['resolution'] . "\n";
}

echo "Input option number: ";
$input = trim(fgets(STDIN));

if ($input == '')
    $input = 1;

$file_name = $video_id . '_' . $qualities[$input]['bandwidth'];
$video_file = $base_path . $file_name . '.mp4';

$cmd_proxy = '';
if (isset($proxy) && $proxy)
{
    $cmd_proxy = '-http_proxy http://' . $proxy;
}

$ffmpegUserAgentCommand = '';
if($customFFMpegUserAgent != false)
{
    $ffmpegUserAgentCommand = ' -user_agent "' . $customFFMpegUserAgent . '" ';
}

$video_m3u = $qualities[$input]['url'];

$cmd = 'ffmpeg ' . $ffmpegUserAgentCommand . ' ' . $cmd_proxy . ' -i "' . $video_m3u . '" -c copy -y "' . $video_file . '"';
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

$selectedResolution = $qualities[$input]['resolution'];

if ($cover)
{
    file_put_contents($cover_file, get_contents($cover));
}

if ($subtitle)
{
    file_put_contents($subtitle_file, normalize_subtitle(get_contents($subtitle)));
}

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
{
    $command = 'start /B ' . $cmd . '<nul >nul 2>"' . $log_file . '"';

    pclose(popen($command, 'r'));
}
else
{
    $command = $cmd . '</dev/null >/dev/null 2>"' . $log_file . '" &';

    shell_exec($command);
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


/////////////////////////////////////////////////////////////////
///
///
///     FUNCTIONS
///
///

function get_contents($url, $data = null)
{
    global $config_path,$proxy;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

	// for deflate response body
    curl_setopt($ch,CURLOPT_ENCODING , '');

    curl_setopt($ch, CURLOPT_HEADER, 1);

    // add this cookie for switch to last version of namava
    curl_setopt($ch, CURLOPT_COOKIE, 'namavaenv=legacy;');

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
        'Accept: text/html,application/xhtml+xml,application/xml,application/json, text/plain;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Cache-Control: max-age=0',
        'Referer: https://www.namava.ir/Authentication/PostLogin?redirectTo=https%3A%2F%2Fwww.namava.ir%2Fuser%2Fprofile',
        'Proxy-Connection: keep-alive',
    ));


    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($ch);

    // Get Response Header for debug...
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    return $body;
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
