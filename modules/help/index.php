<?php

declare(strict_types=1);

/*
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

use Johncms\Api\ConfigInterface;
use League\Plates\Engine;
use Psr\Container\ContainerInterface;
use Zend\I18n\Translator\Translator;

defined('_IN_JOHNCMS') || die('Error: restricted access');

$id = isset($_REQUEST['id']) ? abs((int) ($_REQUEST['id'])) : 0;
$act = isset($_GET['act']) ? trim($_GET['act']) : '';
$mod = isset($_GET['mod']) ? trim($_GET['mod']) : '';

/** @var ContainerInterface $container */
$container = App::getContainer();

// Регистрируем языки модуля
$container->get(Translator::class)->addTranslationFilePattern('gettext', __DIR__ . '/locale', '/%s/default.mo');

/** @var Engine $view */
$view = $container->get(Engine::class);
$view->addFolder('help', __DIR__ . '/templates/');

// Обрабатываем ссылку для возврата
if (empty($_SESSION['ref'])) {
    /** @var ConfigInterface $config */
    $config = $container->get(ConfigInterface::class);
    $_SESSION['ref'] = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : $config['homeurl'];
}

// Сколько смайлов разрешено выбрать пользователям?
$user_smileys = 20;

// Названия директорий со смайлами
function smiliesCat()
{
    return [
        'animals'       => _t('Animals'),
        'brawl_weapons' => _t('Brawl, Weapons'),
        'emotions'      => _t('Emotions'),
        'flowers'       => _t('Flowers'),
        'food_alcohol'  => _t('Food, Alcohol'),
        'gestures'      => _t('Gestures'),
        'holidays'      => _t('Holidays'),
        'love'          => _t('Love'),
        'misc'          => _t('Miscellaneous'),
        'music'         => _t('Music, Dancing'),
        'sports'        => _t('Sports'),
        'technology'    => _t('Technology'),
    ];
}

// Выбор действия
$array = [
    'admsmilies',
    'avatars',
    'forum',
    'my_smilies',
    'set_my_sm',
    'smilies',
    'tags',
    'usersmilies',
];

if ($act && ($key = array_search($act, $array)) !== false && file_exists(__DIR__ . '/includes/' . $array[$key] . '.php')) {
    ob_start();
    require __DIR__ . '/includes/' . $array[$key] . '.php';
    echo $view->render('system::app/old_content', [
        'title'   => $textl ?? _t('Information, FAQ'),
        'content' => ob_get_clean(),
    ]);
} else {
    // Главное меню FAQ
    echo $view->render('help::index');
}
