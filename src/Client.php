<?php

namespace AzoyaSso;

use Yii;
use yii\helpers\Url;

class Client
{

    private $ssoHost;
    private $appAlias;
    private $appKey;
    private $appSecret;

    private $ssoToken;
    private $ssoInfo = [];

    const SSO_HOME_URI = '/sso/index';
    const SSO_ISLOGIN_URI = '/sso-api/is-login';
    const SSO_USER_URI = '/sso-api/user';
    const SSO_LOGIN_URI = '/sso/login';
    const SSO_LOGOUT_URI = '/sso-api/logout';
    const SSO_SYNC_URI = '/sso-api/sync';
    const SSO_AJAX_SYNC_URI = '/sso-api/ajax-sync';
    const SSO_SET_LANGUAGE_URI = '/sso-api/set-language';
    const SSO_USERS_URI = '/sso-api/users';

    /**
     * 构造函数
     *
     * @return void
     **/
    public function __construct()
    {
        if (!isset(Yii::$app->params['sso_host']) || empty(Yii::$app->params['sso_host'])) {
            throw new \Exception('sso host is empty', 500);
        }
        if (!isset(Yii::$app->params['sso_app_alias']) || empty(Yii::$app->params['sso_app_alias'])) {
            throw new \Exception('sso app key is empty', 500);
        }
        if (!isset(Yii::$app->params['sso_app_key']) || empty(Yii::$app->params['sso_app_key'])) {
            throw new \Exception('sso app key is empty', 500);
        }
        if (!isset(Yii::$app->params['sso_app_secret']) || empty(Yii::$app->params['sso_app_secret'])) {
            throw new \Exception('sso app secret is empty', 500);
        }
        $this->ssoHost = Yii::$app->params['sso_host'];
        $this->appAlias = Yii::$app->params['sso_app_alias'];
        $this->appKey = Yii::$app->params['sso_app_key'];
        $this->appSecret = Yii::$app->params['sso_app_secret'];

        $session = Yii::$app->session;
        if ( !$session->isActive) { $session->open(); }

        $this->ssoToken = $session->get('sso_token');
    }

    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * init sso info
     *
     * @return void
     **/
    private function initUser()
    {
        if (! Yii::$app->session->has('sso_user') && Yii::$app->session->has('sso_token')) {
            $this->getInfoFromSSO();
        }
    }

    /**
     * sso 后台主页地址
     *
     * @return string
     **/
    protected function homeUrl()
    {
        return $this->ssoHost . self::SSO_HOME_URI;
    }

    /**
     * 判断站点是否有 sso token
     *
     * @return bool
     **/
    protected function hasToken()
    {
        $session = Yii::$app->session;

        return $session->has('sso_token');
    }

    /**
     * 获取登录地址
     *
     * @return string
     **/
    protected function loginUrl()
    {
        return $this->ssoHost . self::SSO_LOGIN_URI;
    }

    /**
     * 获取同步 token 地址
     *
     * @return string
     **/
    protected function syncUrl()
    {
        $callback = Url::base(true) . '/sso/sync';
        $to = base64_encode(Yii::$app->request->url);

        return $this->ssoHost . self::SSO_SYNC_URI . '?callback=' . $callback . '&to=' . $to;
    }

    /**
     * 获取 ajax 同步 token 地址
     *
     * @return string
     **/
    protected function ajaxSyncUrl()
    {
        return $this->ssoHost . self::SSO_AJAX_SYNC_URI;
    }

    /**
     * 判断是否登录
     *
     * @return bool
     **/
    protected function isLogin()
    {
        $host = $this->ssoHost . self::SSO_ISLOGIN_URI;

        $resJson = $this->curl('GET', $host, ['sso_token' => $this->ssoToken]);

        $res = json_decode($resJson, true);
        if ($res['status'] == 200) {
            Yii::$app->session->set('sso_language', $res['data']['language']);
            return true;
        } elseif ($res['status'] == 402) {
            
            Yii::$app->session->remove('sso_token');
            Yii::$app->session->remove('sso_user');
        }

        return false;
    }

    /**
     * 获取用户信息
     *
     * @return array|null
     **/
    protected function user()
    {
        $this->initUser();
        if (! Yii::$app->session->has('sso_user')) {
            return null;
        }

        return json_decode(Yii::$app->session->get('sso_user'));
    }

    /**
     * 获取用户ID
     *
     * @return array|null
     **/
    protected function userId()
    {
        $this->initUser();
        if (! Yii::$app->session->has('sso_user')) {
            return null;
        }

        $user = json_decode(Yii::$app->session->get('sso_user'));

        return $user->id;
    }

    /**
     * 获取用户名称
     *
     * @return array|null
     **/
    protected function userName()
    {
        $this->initUser();
        if (! Yii::$app->session->has('sso_user')) {
            return null;
        }

        $user = json_decode(Yii::$app->session->get('sso_user'));

        return $user->name;
    }

    /**
     * 判断用户是否是超级管理员
     *
     * @return bool
     **/
    protected function isSuperUser()
    {
        $this->initUser();
        if (! Yii::$app->session->has('sso_user')) {
            return false;
        }
        $user = json_decode(Yii::$app->session->get('sso_user'));

        return $user->is_super_user == 1 ? true : false;
    }

    /**
     * 获取当前用户语言
     *
     * @return string
     **/
    protected function language()
    {
        if (! Yii::$app->session->has('sso_language')) {
            return null;
        }

        return Yii::$app->session->get('sso_language');
    }

    /**
     * 获取菜单信息
     *
     * @return array|null
     **/
    protected function menu()
    {
        $this->initUser();
        if (! Yii::$app->session->has('sso_menu')) {
            return null;
        }

        return json_decode(Yii::$app->session->get('sso_menu'), true);
    }

    /**
     * 获取权限信息
     *
     * @return array|null
     **/
    protected function permissions()
    {
        $this->initUser();
        if (! Yii::$app->session->has('sso_permissions')) {
            return [];
        }

        return json_decode(Yii::$app->session->get('sso_permissions'), true);
    }

    /**
     * 获取可访问的站点
     *
     * @return array|null
     **/
    protected function sites()
    {
        $this->initUser();
        if (! Yii::$app->session->has('sso_sites')) {
            return [];
        }

        return json_decode(Yii::$app->session->get('sso_sites'), true);
    }

    /**
     * 获取多个用户信息
     *
     * @param $userIds array 用户id
     * @return array|null
     **/
    protected function users($userIds)
    {
        $host = $this->ssoHost . self::SSO_USERS_URI;

        $resJson = $this->curl('POST', $host, ['sso_token' => $this->ssoToken, 'user_ids' => implode(',', $userIds)]);
        $res = json_decode($resJson, true);
        if ($res['status'] == 200) {

            return $res['data'];
        }

        throw new \Exception($res['message'], $res['status']);
    }

    /**
     * 获取用户信息
     *
     * @return array|null
     **/
    protected function getInfoFromSSO()
    {
        $host = $this->ssoHost . self::SSO_USER_URI;
        $resJson = $this->curl('GET', $host, ['sso_token' => $this->ssoToken]);

        $res = json_decode($resJson, true);

        if ($res['status'] == 200) {
            $info = json_decode($res['data'], true);

            $this->ssoInfo = $info;
            Yii::$app->session->set('sso_user', json_encode($info['user']));
            Yii::$app->session->set('sso_menu', json_encode($info['menu']));
            Yii::$app->session->set('sso_permissions', json_encode($info['route_permissions']));
            Yii::$app->session->set('sso_sites', json_encode($info['site_permissions']));
        }
        if ($res['status'] == 402) {
            Yii::$app->session->remove('sso_token');
            Yii::$app->session->remove('sso_user');
        }

        return;
    }

    /**
     * 退出登录
     *
     * @return bool
     **/
    protected function logout()
    {
        Yii::$app->session->remove('sso_token');
        Yii::$app->session->remove('sso_user');
        $host = $this->ssoHost . self::SSO_LOGOUT_URI;

        $resJson = $this->curl('GET', $host, ['sso_token' => $this->ssoToken]);
        $res = json_decode($resJson, true);
        if ($res['status'] == 200) {
            return true;
        }

        throw new \Exception($res['message'], $res['status']);
    }

    /**
     * 设置语言
     *
     * @param $language string 语言
     * @return bool
     **/
    protected function setLanguage($language)
    {
        $host = $this->ssoHost . self::SSO_SET_LANGUAGE_URI;

        $resJson = $this->curl('POST', $host, ['sso_token' => $this->ssoToken, 'language' => $language]);
        $res = json_decode($resJson, true);
        if ($res['status'] == 200) {
            Yii::$app->session->set('sso_language', $language);

            return true;
        }

        throw new \Exception($res['message'], $res['status']);
    }

    /**
     * 路由权限验证
    **/
    protected function hasAccess($route)
    {
        if ($this->isSuperUser()) {
            return true;
        }

        $permissions = $this->permissions();
        $route = $this->appAlias . ':' . $route;

        if (is_array($permissions)) {
            if (isset($permissions[$route]) && $permissions[$route] == '0') {
                return false;
            }
        }

        return true;
    }

    /**
     * 菜单权限验证
     *
     * @param $route string 菜单标识
     * @return bool
    **/
    protected function menuHasAccess($route)
    {
        if ($this->isSuperUser()) {
            return true;
        }

        $permissions = $this->permissions();
        if (is_array($permissions)) {
            if (isset($permissions[$route]) && $permissions[$route] == '1') {
                return true;
            }
        }

        return false;
    }

    /**
     * 菜单权限验证
     *
     * @param $siteId int 站点ID
     * @return bool
    **/
    protected function siteHasAccess($siteId)
    {
        if ($this->isSuperUser()) {
            return true;
        }

        $sites = $this->sites();
        foreach ($sites as $site) {
            if ($site['id'] == $siteId) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取 sign
     *
     * @return void
     **/
    private function signHeader($params)
    {
        ksort($params);
        $message = http_build_query($params);

        $sign = hash_hmac('sha256', $message, $this->appSecret);
        return [
            'app-key:' . $this->appKey,
            'sign:' . $sign,
        ];
    }

    /**
     * curl request
     *
     * @param $method GET|POST
     * @param $url string
     * @param $params array
     *
     * @return string
     **/
    private function curl($method, $url, $params = [])
    {
        if ($method == 'GET') {
            $url = $url . '?' . http_build_query($params);
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $headers = $this->signHeader($params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $html = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close ($ch);

        if ($info['http_code'] != 200) {
            throw new \Exception('sso service return error, error code is ' . $info['http_code'], 500);
        }

        return $html;
    }
}