<?php
namespace twig;


use JShrink\Minifier;
use think\facade\Config;
use tiny\TinyMinify;

class Functions
{
    /*
    取随机数
    模板里使用 {{ random(0,99) }}
    public function random($min,$max)
    {
        return mt_rand($min,$max);
    }
    */

    public function config($name = '', $value = null)
    {
        return config($name, $value);
    }

    public function session($name, $value = '', $prefix = null)
    {
        return session($name, $value, $prefix);
    }

    public function date($format)
    {
        return date($format);
    }

    public function tiny($template)
    {
        echo TinyMinify::html($template);
    }

    public function tinyj($script)
    {
        echo Minifier::minify($script);
    }

    public function static_url($path)
    {
        return '/static/'.$path;
    }

    public function library($path)
    {
        return Config::get('app.app_version').'/'.$path.'.twig';
    }
}