<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

use Johncms\NavChain;
use Johncms\System\Http\Request;
use Johncms\System\Legacy\Tools;
use Johncms\System\Users\User;
use Johncms\System\View\Extension\Assets;
use Johncms\System\View\Render;
use Johncms\System\i18n\Translator;

defined('_IN_JOHNCMS') || die('Error: restricted access');

/**
 * @var Assets $assets
 * @var PDO $db
 * @var Tools $tools
 * @var User $user
 * @var Render $view
 */

$assets = di(Assets::class);
$config = di('config')['johncms'];
$db = di(PDO::class);
$tools = di(Tools::class);
$user = di(User::class);
$view = di(Render::class);

/** @var Request $request */
$request = di(Request::class);

/** @var NavChain $nav_chain */
$nav_chain = di(NavChain::class);

// Регистрируем Namespace для шаблонов модуля
$view->addFolder('profile', __DIR__ . '/templates/');

// Register the module languages domain and folder
di(Translator::class)->addTranslationDomain('profile', __DIR__ . '/locale');

$id = $request->getQuery('id', 0, FILTER_SANITIZE_NUMBER_INT);
$user_id = $request->getQuery('user', $user->id, FILTER_SANITIZE_NUMBER_INT);
$act = $request->getQuery('act', 'index', FILTER_SANITIZE_STRING);
$mod = $request->getQuery('mod', '', FILTER_SANITIZE_STRING);

// Закрываем от неавторизованных юзеров
if (! $user->isValid()) {
    echo $view->render(
        'system::pages/result',
        [
            'title'   => __('User Profile'),
            'type'    => 'alert-danger',
            'message' => __('For registered users only'),
        ]
    );
    exit;
}

/** @var User $foundUser Получаем данные пользователя */
$foundUser = $tools->getUser((int) $user_id);

if (empty($foundUser->id) || ($foundUser->preg !== 1 && $user->rights < 7)) {
    echo $view->render(
        'system::pages/result',
        [
            'title'   => __('User Profile'),
            'type'    => 'alert-danger',
            'message' => __('This User does not exists'),
        ]
    );
    exit;
}

/**
 * Находится ли выбранный пользователь в контактах и игноре?
 *
 * @param int $id Идентификатор пользователя, которого проверяем
 * @return int Результат запроса:
 *                0 - не в контактах
 *                1 - в контактах
 *                2 - в игноре у меня
 */
function is_contact($id = 0)
{
    global $db, $user;

    static $user_id = null;
    static $return = 0;

    if (! $user->isValid() && ! $id) {
        return 0;
    }

    if (null === $user_id || $id != $user_id) {
        $user_id = $id;
        $req = $db->query("SELECT * FROM `cms_contact` WHERE `user_id` = '" . $user->id . "' AND `from_id` = '${id}'");

        if ($req->rowCount()) {
            $res = $req->fetch();
            if ($res['ban'] == 1) {
                $return = 2;
            } else {
                $return = 1;
            }
        } else {
            $return = 0;
        }
    }

    return $return;
}

// Переключаем режимы работы
$mods = [
    'activity',
    'ban',
    'edit',
    'images',
    'ip',
    'guestbook',
    'karma',
    'office',
    'password',
    'reset',
    'settings',
    'stat',
    'index',
];

if ($act && ($key = array_search($act, $mods, true)) !== false && file_exists(__DIR__ . '/includes/' . $mods[$key] . '.php')) {
    require __DIR__ . '/includes/' . $mods[$key] . '.php';
} else {
    pageNotFound();
}
