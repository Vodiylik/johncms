<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

defined('_IN_JOHNCMS') || die('Error: restricted access');

/**
 * @var Johncms\System\Config\Config $config
 * @var PDO $db
 * @var Johncms\Api\ToolsInterface $tools
 * @var Johncms\Api\UserInterface $user
 */

require __DIR__ . '/../classes/download.php';

// Выводим файл
$req_down = $db->query("SELECT * FROM `download__files` WHERE `id` = '" . $id . "' AND (`type` = 2 OR `type` = 3)  LIMIT 1");
$res_down = $req_down->fetch();

if (! $req_down->rowCount() || ! is_file($res_down['dir'] . '/' . $res_down['name'])) {
    http_response_code(404);
    echo $view->render(
        'system::pages/result',
        [
            'title'         => _t('File not found'),
            'type'          => 'alert-danger',
            'message'       => _t('File not found'),
            'back_url'      => $url,
            'back_url_name' => _t('Downloads'),
        ]
    );
    exit;
}

$title_page = htmlspecialchars($res_down['rus_name']);

if ($res_down['type'] == 3 && $user->rights < 6 && $user->rights != 4) {
    http_response_code(403);
    echo $view->render(
        'system::pages/result',
        [
            'title'         => _t('The file is on moderation'),
            'type'          => 'alert-danger',
            'message'       => _t('The file is on moderation'),
            'back_url'      => $url,
            'back_url_name' => _t('Downloads'),
        ]
    );
    exit;
}

Download::navigation(['dir' => $res_down['dir'], 'refid' => 1, 'count' => 0]);
$extension = pathinfo($res_down['name'], PATHINFO_EXTENSION);

$urls = [
    'downloads' => $url,
];

$file_data = $res_down;

// Получаем список скриншотов
$text_info = '';
$screen = [];

if (is_dir(DOWNLOADS_SCR . $id)) {
    $dir = opendir(DOWNLOADS_SCR . $id);
    $ignore_files = ['.', '..', 'name.dat', '.svn', 'index.php'];
    while ($file = readdir($dir)) {
        if (! in_array($file, $ignore_files, true)) {
            $file_path = UPLOAD_PUBLIC_PATH . 'downloads/screen/' . $id . '/' . $file;
            $screen[] = [
                'file'    => $file_path,
                'preview' => '../assets/modules/downloads/preview.php?type=2&amp;img=' . rawurlencode($file_path),
            ];
        }
    }
    closedir($dir);
}

$file_data['screenshots'] = $screen;

switch ($extension) {
    case 'mp3':
        $getID3 = new getID3();
        $getID3->encoding = 'cp1251';
        $getid = $getID3->analyze($res_down['dir'] . '/' . $res_down['name']);
        $mp3info = true;

        if (! empty($getid['tags']['id3v2'])) {
            $tagsArray = $getid['tags']['id3v2'];
        } elseif (! empty($getid['tags']['id3v1'])) {
            $tagsArray = $getid['tags']['id3v1'];
        } else {
            $mp3info = false;
        }

        $mp3_properties = [
            [
                'name'  => _t('Channels'),
                'value' => $getid['audio']['channels'] . ' (' . $getid['audio']['channelmode'] . ')',
            ],
            [
                'name'  => _t('Sample rate'),
                'value' => ceil($getid['audio']['sample_rate'] / 1000) . ' KHz',
            ],
            [
                'name'  => _t('Bitrate'),
                'value' => ceil($getid['audio']['bitrate'] / 1000) . ' Kbit/s',
            ],
            [
                'name'  => _t('Duration'),
                'value' => date('i:s', (int) $getid['playtime_seconds']),
            ],
        ];

        if ($mp3info) {
            if (isset($tagsArray['artist'][0])) {
                $mp3_properties[] = [
                    'name'  => _t('Artist'),
                    'value' => Download::mp3tagsOut($tagsArray['artist'][0]),
                ];
            }
            if (isset($tagsArray['title'][0])) {
                $mp3_properties[] = [
                    'name'  => _t('Title'),
                    'value' => Download::mp3tagsOut($tagsArray['title'][0]),
                ];
            }
            if (isset($tagsArray['album'][0])) {
                $mp3_properties[] = [
                    'name'  => _t('Album'),
                    'value' => Download::mp3tagsOut($tagsArray['album'][0]),
                ];
            }
            if (isset($tagsArray['genre'][0])) {
                $mp3_properties[] = [
                    'name'  => _t('Genre'),
                    'value' => Download::mp3tagsOut($tagsArray['genre'][0]),
                ];
            }
            if (isset($tagsArray['year'][0])) {
                $mp3_properties[] = [
                    'name'  => _t('Year'),
                    'value' => Download::mp3tagsOut($tagsArray['year'][0]),
                ];
            }
        }

        $file_data['mp3_properties'] = $mp3_properties;
        $file_data['file_type'] = 'audio';
        break;

    case 'avi':
    case 'webm':
    case 'mp4':
        $file_data['file_type'] = 'video';
        break;

    case 'jpg':
    case 'jpeg':
    case 'gif':
    case 'png':
        $file_path = $res_down['dir'] . '/' . $res_down['name'];
        $screen[] = [
            'file'    => '/' . $file_path,
            'preview' => '/assets/modules/downloads/preview.php?type=2&amp;img=' . rawurlencode($file_path),
        ];
        $file_data['screenshots'] = $screen;
        $info_file = getimagesize($res_down['dir'] . '/' . $res_down['name']);
        $file_data['image_info'] = [
            'width'  => $info_file[0],
            'height' => $info_file[1],
        ];
        $file_data['file_type'] = 'image';
        break;
}

$file_data['description'] = $tools->checkout($res_down['about'], 1, 1);

// Выводим данные
$foundUser = $db->query('SELECT `name`, `id` FROM `users` WHERE `id` = ' . $res_down['user_id'])->fetch();
$file_data['upload_user'] = $foundUser;

// Рейтинг файла
$file_rate = explode('|', $res_down['rate']);
if ((isset($_GET['plus']) || isset($_GET['minus'])) && ! isset($_SESSION['rate_file_' . $id]) && $user->isValid()) {
    if (isset($_GET['plus'])) {
        $file_rate[0] = $file_rate[0] + 1;
    } else {
        $file_rate[1] = $file_rate[1] + 1;
    }

    $db->exec("UPDATE `download__files` SET `rate`='" . $file_rate[0] . '|' . $file_rate[1] . "' WHERE `id`=" . $id);
    $file_data['vote_accepted'] = true;
    $_SESSION['rate_file_' . $id] = true;
}

$sum = ($file_rate[1] + $file_rate[0]) ? round(100 / ($file_rate[1] + $file_rate[0]) * $file_rate[0]) : 50;

$file_data['rate'] = $file_rate;

// Запрашиваем дополнительные файлы
$req_file_more = $db->query('SELECT * FROM `download__more` WHERE `refid` = ' . $id . ' ORDER BY `time` ASC');
$total_files_more = $req_file_more->rowCount();

$file_data['main_file'] = Download::downloadLlink(
    [
        'format' => $extension,
        'res'    => $res_down,
    ]
);

$file_data['additional_files'] = [];

// Дополнительные файлы
if ($total_files_more) {
    $i = 0;
    while ($res_file_more = $req_file_more->fetch()) {
        $res_file_more['dir'] = $res_down['dir'];
        $res_file_more['text'] = $res_file_more['rus_name'];

        $file_data['additional_files'][] = Download::downloadLlink(
            [
                'format' => pathinfo($res_file_more['name'], PATHINFO_EXTENSION),
                'res'    => $res_file_more,
                'more'   => $res_file_more['id'],
            ]
        );
    }
}

// Управление закладками
if ($user->isValid()) {
    $bookmark = $db->query('SELECT COUNT(*) FROM `download__bookmark` WHERE `file_id` = ' . $id . '  AND `user_id` = ' . $user->id)->fetchColumn();

    if (isset($_GET['addBookmark']) && ! $bookmark) {
        $db->exec("INSERT INTO `download__bookmark` SET `file_id`='" . $id . "', `user_id` = " . $user->id);
        $bookmark = 1;
    } elseif (isset($_GET['delBookmark']) && $bookmark) {
        $db->exec("DELETE FROM `download__bookmark` WHERE `file_id`='" . $id . "' AND `user_id` = " . $user->id);
        $bookmark = 0;
    }
}

echo $view->render(
    'downloads::view',
    [
        'title'        => $title_page,
        'page_title'   => $title_page,
        'id'           => $id,
        'file'         => $file_data,
        'in_bookmarks' => $bookmark ?? 0,
        'urls'         => $urls ?? [],
    ]
);
