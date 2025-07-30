<?php

/*
* 原项目开源地址：https://github.com/xhboke/IP
* 二开开源地址：https://github.com/2031301686/ip-sign/
* 更新地址：https://wxsnote.cn/2762.html
* 二开作者：天无神话-王先生笔记
* 博客地址：https://wxsnote.cn/2762.html
*/

header("Content-type: image/JPEG");
use UAParser\Parser;
require_once './vendor/autoload.php';

// ==================================================
// 【全局配置区 - 所有可调整参数集中在此处】
// ==================================================

// 1. 核心功能配置
$config = [
    // Redis配置
    'redis_enabled'      => true,          // 是否启用Redis缓存（true/false）
    'redis_host'         => '127.0.0.1',   // Redis主机地址
    'redis_port'         => 6379,          // Redis端口
    'redis_password'     => '',            // Redis密码（无密码留空）
    'redis_db'           => 0,             // Redis数据库编号
    'redis_timeout'      => 2,             // Redis连接超时（秒）
    'cache_expire'       => 86400,         // 缓存过期时间（秒，默认1天）
    'cache_key_prefix'   => 'ip_location:',// 缓存键前缀（方便区分缓存）

    // IP归属地API配置
    'api' => [
        'gaode' => [
            'enabled'    => true,           // 是否启用高德API
            'key'        => '',             // 高德API密钥（必填）
            'secret_key' => ''              // 高德Secret（非必填）
        ],
        'tencent' => [
            'enabled'    => true,           // 是否启用腾讯API
            'key'        => '',             // 腾讯API密钥（必填）
            'secret_key' => ''              // 腾讯Secret（非必填）
        ],
        'pconline' => [
            'enabled'    => true            // 是否启用太平洋API（无需密钥）
        ]
    ],

    // 默认文本（无数据时显示）
    'default_text' => [
        'location' => '神秘星球',           // 归属地获取失败时显示
        'unknown_os' => '未知操作系统'      // OS解析失败时显示
    ]
];

// 2. 图片生成配置
$image = [
    'bg_path'       => './xhxh.jpg',      // 背景图片路径
    'font_path'     => 'msyh.ttf',        // 字体文件路径
    'text_color'    => [
        'primary' => [255, 0, 0],         // 主文本颜色（红）
        'secondary' => [0, 0, 0]          // 次要文本颜色（黑）
    ],
    'font_size' => [
        'title' => 16,                    // 标题字体大小
        'content' => 13                   // 内容字体大小
    ],
    'text_position' => [
        // 文本在图片中的坐标（x, y）
        'welcome'    => [10, 40],         // 欢迎语位置
        'date'       => [10, 72],         // 日期位置
        'ip'         => [10, 104],        // IP位置
        'os'         => [10, 140],        // 操作系统位置
        'note'       => [10, 175],        // 笔记提示位置
        'custom'     => [10, 200]         // 自定义内容位置
    ]
];

// 3. 文本内容配置（可修改显示文案）
$text = [
    'welcome_prefix' => '欢迎您来自 ',    // 欢迎语前缀
    'welcome_suffix' => ' 的朋友',       // 欢迎语后缀
    'date_prefix'    => '今天是',         // 日期前缀
    'ip_prefix'      => '您的IP是:',      // IP前缀
    'os_prefix'      => '您使用的是',    // 操作系统前缀
    'os_suffix'      => '操作系统',       // 操作系统后缀
    'note_content'   => '欢迎您访问王先生笔记' // 提示内容
];

// ==================================================
// 【功能函数区】
// ==================================================

// 获取真实IP
function wxs_get_ip() {
    if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches[0] : '';
}

// 连接Redis服务器
function wxs_redis($config) {
    if (!$config['redis_enabled']) {
        return false;
    }
    if (!class_exists('Redis')) {
        return false;
    }
    
    $redis = new Redis();
    try {
        $connected = $redis->connect(
            $config['redis_host'],
            $config['redis_port'],
            $config['redis_timeout']
        );
        if (!$connected) {
            return false;
        }
        if (!empty($config['redis_password']) && !$redis->auth($config['redis_password'])) {
            return false;
        }
        $redis->select($config['redis_db']);
        return $redis;
    } catch (Exception $e) {
        return false;
    }
}

// 高德API调用
function wxs_gd_api($ip, $api_config) {
    if (!$api_config['enabled'] || empty($api_config['key'])) {
        return ['province' => '', 'city' => ''];
    }
    $url = 'http://restapi.amap.com/v3/ip?key=' . $api_config['key'] . '&ip=' . $ip;
    if (!empty($api_config['secret_key'])) {
        $url .= '&sig=' . md5('ip=' . $ip . '&key=' . $api_config['key'] . $api_config['secret_key']);
    }
    
    $data = wxs_curl_get($url);
    $data = json_decode($data, true);
    return [
        'province' => $data['province'] ?? '',
        'city' => $data['city'] ?? ''
    ];
}

// 腾讯API调用
function wxs_tx_api($ip, $api_config) {
    if (!$api_config['enabled'] || empty($api_config['key'])) {
        return ['province' => '', 'city' => ''];
    }
    $url = 'http://apis.map.qq.com/ws/location/v1/ip?ip=' . $ip . '&key=' . $api_config['key'];
    if (!empty($api_config['secret_key'])) {
        $url .= '&sig=' . md5('/ws/location/v1/ip?ip=' . $ip . '&key=' . $api_config['key'] . $api_config['secret_key']);
    }
    
    $data = wxs_curl_get($url);
    $data = json_decode($data, true);
    return [
        'province' => $data['result']['ad_info']['province'] ?? '',
        'city' => $data['result']['ad_info']['city'] ?? ''
    ];
}

// 太平洋API调用
function wxs_tpy_api($ip, $api_config) {
    if (!$api_config['enabled']) {
        return ['province' => '', 'city' => ''];
    }
    $url = 'http://whois.pconline.com.cn/ipJson.jsp?json=true&ip=' . $ip;
    $data = wxs_curl_get($url);
    $data = mb_convert_encoding($data, 'UTF-8', 'GBK');
    $data = json_decode($data, true);
    return [
        'province' => $data['pro'] ?? '',
        'city' => $data['city'] ?? ''
    ];
}

// 获取IP归属地
function wxs_get_location_by_ip($ip, $api_configs, $default_location) {
    // 1. 尝试高德API
    $gd_data = wxs_gd_api($ip, $api_configs['gaode']);
    if (!empty($gd_data['province']) && !empty($gd_data['city'])) {
        return $gd_data['province'] == $gd_data['city'] ? $gd_data['city'] : $gd_data['province'] . '-' . $gd_data['city'];
    }
    
    // 2. 尝试腾讯API
    $tx_data = wxs_tx_api($ip, $api_configs['tencent']);
    if (!empty($tx_data['province']) && !empty($tx_data['city'])) {
        return $tx_data['province'] == $tx_data['city'] ? $tx_data['city'] : $tx_data['province'] . '-' . $tx_data['city'];
    }
    
    // 3. 尝试太平洋API
    $tpy_data = wxs_tpy_api($ip, $api_configs['pconline']);
    if (!empty($tpy_data['province']) && !empty($tpy_data['city'])) {
        return $tpy_data['province'] == $tpy_data['city'] ? $tpy_data['city'] : $tpy_data['province'] . '-' . $tpy_data['city'];
    }
    
    // 所有API失败
    return $default_location;
}

// CURL请求工具
function wxs_curl_get($url, $timeout = 6) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

// ==================================================
// 【主逻辑区】
// ==================================================

// 1. 基础变量获取
$ip = wxs_get_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$custom_text = $_GET["s"] ?? '';
$custom_text = base64_decode(str_replace(" ", "+", $custom_text));
$weekarray = ['日', '一', '二', '三', '四', '五', '六'];
$current_date = date('Y年n月j日') . ' 星期' . $weekarray[date("w")];

// 2. UA解析（操作系统）
$os = $config['default_text']['unknown_os'];
if (!empty($ua)) {
    $parser = Parser::create();
    $result = $parser->parse($ua);
    if (isset($_GET['os_name']) && isset($_GET['os_version'])) {
        $os = $_GET['os_name'] . ' ' . $_GET['os_version'];
    } else {
        $os = $result->os->toString() ?: $os;
    }
}

// 3. IP归属地获取（带缓存）
$redis = wxs_redis($config);
$cache_key = $config['cache_key_prefix'] . $ip;
$address = null;

// 尝试从缓存获取
if ($redis) {
    $cached_address = $redis->get($cache_key);
    if ($cached_address !== false) {
        $address = $cached_address;
    }
}

// 缓存未命中，查询API并缓存
if (empty($address)) {
    $address = wxs_get_location_by_ip(
        $ip,
        $config['api'],
        $config['default_text']['location']
    );
    if ($redis) {
        $redis->setex($cache_key, $config['cache_expire'], $address);
    }
}

// 4. 生成图片
$im = imagecreatefromjpeg($image['bg_path']);
// 定义颜色
$color_primary = ImageColorAllocate($im, ...$image['text_color']['primary']);
$color_secondary = ImageColorAllocate($im, ...$image['text_color']['secondary']);
// 输出文本
imagettftext(
    $im,
    $image['font_size']['title'],
    0,
    $image['text_position']['welcome'][0],
    $image['text_position']['welcome'][1],
    $color_primary,
    $image['font_path'],
    $text['welcome_prefix'] . $address . $text['welcome_suffix']
);
imagettftext(
    $im,
    $image['font_size']['title'],
    0,
    $image['text_position']['date'][0],
    $image['text_position']['date'][1],
    $color_primary,
    $image['font_path'],
    $text['date_prefix'] . $current_date
);
imagettftext(
    $im,
    $image['font_size']['title'],
    0,
    $image['text_position']['ip'][0],
    $image['text_position']['ip'][1],
    $color_primary,
    $image['font_path'],
    $text['ip_prefix'] . $ip
);
imagettftext(
    $im,
    $image['font_size']['title'],
    0,
    $image['text_position']['os'][0],
    $image['text_position']['os'][1],
    $color_primary,
    $image['font_path'],
    $text['os_prefix'] . $os . $text['os_suffix']
);
imagettftext(
    $im,
    $image['font_size']['title'],
    0,
    $image['text_position']['note'][0],
    $image['text_position']['note'][1],
    $color_primary,
    $image['font_path'],
    $text['note_content']
);
imagettftext(
    $im,
    $image['font_size']['content'],
    0,
    $image['text_position']['custom'][0],
    $image['text_position']['custom'][1],
    $color_secondary,
    $image['font_path'],
    $custom_text
);

// 输出图片并销毁资源
ImageJPEG($im);
ImageDestroy($im);
?>
