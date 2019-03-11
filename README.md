## Requirement

1. PHP >= 5.6
2. yii >= 2.0
3. **[Composer](https://getcomposer.org/)**

## Installation

```shell
$ composer require "ghost-cat/azoya-sso:^1.0"
```

## Config
配置文件 `params.php` 中添加 sso 参数，各个配置项找 azoya sso 管理员申请
```
// sso 配置
'sso_host' => 'http://www.azoya-sso.com',
'sso_app_alias' => 'promotion',
'sso_app_key' => 'promotion123',
'sso_app_secret' => 'e363aba26c370f3231ef5ac83567e57d',
```
确保项目中通过 `Yii::$app->params['sso_host']` ，`Yii::$app->params['sso_app_alias']` ... 可以访问到配置的值


## Usage

基本使用:

```php
<?php

use AzoyaSso\Client as SSOClient;

// 获取用户ID/用户名称
$userId = SSOClient::userId();
$userName = SSOClient::userName();
```



判断是否有 `sso_token`，返回 `bool` 类型

```php
SSOClient::hasToken();
```



获取同步 `sso_token` 地址

```php
$url = SSOClient::syncUrl();
```



获取登录地址

```php
$loginUrl = SSOClient::loginUrl();
```



获取 sso 后台主页地址（logo链接地址）

```php
$url = SSOClient::homeUrl();
```



判断当前是否登录，返回 `bool` 类型
```php
SSOClient::isLogin();
```



判断当前路由是否有权限，返回 `bool` 类型；参数 `route` 为当前地址去除host和参数部分，比如地址是 `http://www.azoya-sso.com/role/edit?id=16`，则该地址的`route` 为 `/role/edit`
```php
SSOClient::hasAccess($route);
```



获取菜单数据，返回数据为 `array` 类型
```php
$menu = SSOClient::menu();
```



判断当前菜单是否有权限，返回 `bool` 类型；当菜单是一级菜单时，`menuRoute` 为一级菜单的 `alias` 字段的值；当菜单为非一级时，`menuRoute` 为一级菜单的 `alias` 字段与当前菜单的 `url` 拼接（用冒号 `:` 拼接），比如 `promotion:/promotion/lucky-draw/index`
```php
SSOClient::menuHasAccess($menuRoute);
```



判断站点是否有权限，返回 `bool` 类型；$siteId 就是各个站点的 website id
```php
SSOClient::siteHasAccess($siteId);
```



获取授权站点数据，返回数据为 `array` 类型
```php
$sites = SSOClient::sites();
```
返回数据转为 json 如下：
```json
[
    {
        "id":2,
        "name":"po"
    },
    {
        "id":14,
        "name":"CECS-PD"
    },
    {
        "id":33,
        "name":"ba"
    }
]
```



批量查询用户数据， `userIds` 类型为数组；用于列表显示操作人姓名
```php
$sites = SSOClient::users($userIds);
```
返回数据转为 json 如下：
```json
[
    {
        "id":"25",
        "name":"admin"
    },
    {
        "id":"26",
        "name":"huan"
    },
    {
        "id":"33",
        "name":"test"
    }
]
```



退出登录
```php
SSOClient::logout();
```



获取语言
```php
SSOClient::language();
```



设置语言，`language` 的值目前只支持 `zh-CN` 和 `en-US`
```php
SSOClient::setLanguage($language);
```



## Yii Example

自定义过滤器 `SSOFilter`
```php
<?php

namespace app\components;

use Yii;
use yii\web\Response;
use yii\base\ActionFilter;
use AzoyaSso\Client as SSOClient;

class SSOFilter extends ActionFilter
{

    public function beforeAction($action)
    {
        
        // 同步 sso_token
        if (!SSOClient::hasToken()) {
            return Yii::$app->controller->redirect(SSOClient::syncUrl());
        }

        // token 状态判断
        $tokenStatus = SSOClient::tokenStatus();
        if ($tokenStatus == SSOClient::SSO_TOKEN_INVALID) {
            // token 无效，同步token
            return Yii::$app->controller->redirect(SSOClient::syncUrl());
        } elseif ($tokenStatus == SSOClient::SSO_TOKEN_NOTLOGIN) {
            // token 未登录
            return Yii::$app->controller->redirect(SSOClient::loginUrl());
        }

        if (!SSOClient::hasAccess('/' . Yii::$app->request->pathInfo)) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['status' => 403, 'message' => '没有权限'];
            return false;
        }
        
        // 设置语言
        Yii::$app->language = SSOClient::language();
        
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        return parent::afterAction($action, $result);
    }
}
```



`BaseController` 引用 `SSOFilter` 过滤器，其他所有控制器继承 `BaseController` 开发

```php
    /**
     * sso filter
     *
     * @return array
     **/
    public function behaviors()
    {
        return [
            [
                'class' => SSOFilter::className(),
                'except' => ['sso/sync'],
            ],
        ];
    }
```



添加 `SsoController.php`，确保 `/sso/sync` ， `/sso/logout` 可以访问到
```php
<?php

namespace app\controllers;

use Yii;
use AzoyaSso\Client as SSOClient;

class SsoController extends BaseController
{

    /**
     * 同步 sso token
     *
     * @return json
     **/
    public function actionSync()
    {
        try {
            $token = Yii::$app->request->get('sso_token');
            $to = Yii::$app->request->get('to');
            Yii::$app->session->set('sso_token', $token);

            $to = !empty($to) ? base64_decode($to) : '/site/index';
            
            return $this->redirect($to);
        } catch (\Exception $e) {

            return $this->returnJson($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 退出登录
     *
     * @return redirect
     **/
    public function actionLogout()
    {
        SSOClient::logout();

        return $this->redirect(SSOClient::loginUrl());
    }

    /**
     * 选择语言
     *
     * @return json
     **/
    public function actionSetLanguage()
    {
        try {
            $language = Yii::$app->request->post('language');
            SSOClient::setLanguage($language);

            return $this->returnJson(200, '设置成功');
        } catch (\Exception $e) {

            return $this->returnJson($e->getCode(), $e->getMessage());
        }
    }
}
```

按钮可以通过 `SSOClient::hasAccess()` 判断是否有权限，并决定是否展示



## License

MIT


