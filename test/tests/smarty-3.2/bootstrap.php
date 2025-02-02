<?php

require_once DISTRIBUTION_DIR . 'libs/Smarty.class.php';

abstract class BenchmarkBase extends Benchmarker
{
    protected $smarty = null;
    
    public function __construct()
    {
        $this->smarty = new Smarty();
        $this->smarty
            ->setTemplateDir(TEST_DIR . 'templates/')
            ->setCompileDir(TMP_DIR . 'compiled/')
            ->setCacheDir(TMP_DIR . 'cached/');
        $this->smarty->caching = 0;
    }
}