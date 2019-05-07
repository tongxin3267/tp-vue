<?php

namespace twig;

use think\Container;
use think\exception\TemplateNotFoundException;
use think\Loader;
use think\facade\Env;
use think\facade\Config;
use Twig\Loader\FilesystemLoader;

class Twig
{
    // 模板引擎参数
    protected $config = [
        // 视图基础目录（集中式）
        'view_base'   => '',
        // 模板起始路径
        'view_path'   => '',
        // 模板文件后缀
        'view_suffix' => 'twig',
        // 模板文件名分隔符
        'view_depr'   => DIRECTORY_SEPARATOR,
    ];

    // 模板变量
    private $vars=[];

    // twig实例
    private $template;

    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, (array) $config);
        if (empty($this->config['view_path'])) {
            $this->config['view_path'] = Container::get('app')->getModulePath() . 'view/';
        }
    }

    private function _inits()
    {
        if(is_object($this->template)) return $this->template;
        try{
            $loader = new FilesystemLoader($this->config['view_path']);
            $this->template = new \Twig\Environment($loader, array(
                'cache' => Env::get('RUNTIME_PATH').'compile/',
                'debug'=> Config::get('app.app_debug')
            ));

            $this->template->addGlobal('Request',app('request'));

            $this->template->addFunction(new \Twig\TwigFunction('url',function($uri,$params=[]){
                return url($uri,$params);
            }));
            if(class_exists('twig\Functions')){
                $class=new Functions();
                $l=get_class_methods($class);
                foreach ($l as $m) $this->template->addFunction(new TwigFunction($m,[$class,$m]));
            }
            if(class_exists('twig\Filters')){
                $class=new Filters();
                $l=get_class_methods($class);
                foreach ($l as $m) $this->template->addFilter(new TwigFilter($m,[$class,$m]));
            }

            // 更改标签
            $lexer = new \Twig\Lexer($this->template, array(
//                'tag_comment'  => array('{*', '*}'),
//                'tag_block'    => array('{', '}'),
                'tag_variable' => array('${', '}'),
            ));
            $this->template->setLexer($lexer);
        }catch (\Twig\Error\Error $e){
            throw new TemplateNotFoundException($e->getMessage());
        }
    }

    /**
     * 检测是否存在模板文件
     * @access public
     * @param string $template 模板文件或者模板规则
     * @return bool
     */
    public function exists($template)
    {

        // 分析模板文件规则
        $request = Container::get('request');

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($module, $template) = explode('@', $template);
        }

        if ($this->config['view_base']) {
            // 基础视图目录
            $module = isset($module) ? $module : $request->module();
            $path   = $this->config['view_base'] . ($module ? $module . DIRECTORY_SEPARATOR : '');
        } else {
            $path = isset($module) ? Container::get('app')->getAppPath() . $module . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR : $this->config['view_path'];
        }

        $depr = $this->config['view_depr'];

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = Loader::parseName($request->controller());
            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认规则定位
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $request->action();
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        $template=$path . ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');
        return is_file($template);
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template)
    {
        // 分析模板文件规则
        $request = Container::get('request');

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($module, $template) = explode('@', $template);
        }

        $depr = '/';

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = Loader::parseName($request->controller());
            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认规则定位
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $request->action();
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        return ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');
    }

    public function assign($name,$value=null)
    {
        if(is_array($name)){
            $this->vars=array_merge($this->vars,$name);
        }else{
            $this->vars[$name]=$value;
        }
    }

    /**
     * 渲染模板文件
     * @access public
     * @param string    $template 模板文件
     * @param array     $data 模板变量
     * @return void
     */
    public function fetch($template, $data = [])
    {
        $this->_inits();

        // 记录视图信息
        Container::get('app')
            ->log('[ VIEW ] ' . $template . ' [ ' . var_export(array_keys($data), true) . ' ]');

        try{
            echo $this->template->render($this->parseTemplate($template),array_merge($this->vars,$data));
        }catch (\Twig\Error\Error $e){
            throw new TemplateNotFoundException($e->getMessage());
        }

    }

    /**
     * 渲染模板内容
     * @access public
     * @param string    $content 模板内容
     * @param array     $data 模板变量
     * @return void
     */
    public function display($template, $data = [])
    {
        $this->_inits();

        // 记录视图信息
        Container::get('app')
            ->log('[ VIEW ] ' . $template . ' [ ' . var_export(array_keys($data), true) . ' ]');

        try{
            echo $this->template->render($this->parseTemplate($template),array_merge($this->vars,$data));
        }catch (\Twig\Error\Error $e){
            throw new TemplateNotFoundException($e->getMessage());
        }
    }

    /**
     * 配置模板引擎
     * @access private
     * @param string|array  $name 参数名
     * @param mixed         $value 参数值
     * @return void
     */
    public function config($name, $value = null)
    {
        if (is_array($name)) {
            $this->config = array_merge($this->config, $name);
        } elseif (is_null($value)) {
            return isset($this->config[$name]) ? $this->config[$name] : null;
        } else {
            $this->config[$name] = $value;
        }
    }

}