<?php


namespace app\web\controller\v1;


class Index extends Base
{
    public function index()
    {
        return $this->fetch();
    }
}