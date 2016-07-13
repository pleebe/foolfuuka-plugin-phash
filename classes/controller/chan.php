<?php

namespace Foolz\FoolFuuka\Controller\Chan;

use Foolz\FoolFrame\Model\Plugins;
use Foolz\FoolFrame\Model\Uri;
use Foolz\FoolFuuka\Plugins\PerceptualHash\Model\PerceptualHash as PHash;
use Foolz\Plugin\Plugin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Foolz\FoolFrame\Model\Context;

class PerceptualHash extends \Foolz\FoolFuuka\Controller\Chan
{
    /**
     * @var Plugin
     */
    protected $plugin;

    /**
     * @var PHash
     */
    protected $phash;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var Context
     */
    protected $context;

    public function before()
    {
        $this->context = $this->getContext();
        /** @var Plugins $plugins */
        $plugins = $this->context->getService('plugins');
        $this->phash = $this->context->getService('foolfuuka-plugin.phash');
        $this->uri = $this->context->getService('uri');
        $this->plugin = $plugins->getPlugin('foolz/foolfuuka-plugin-phash');

        parent::before();
    }

    public function handlemd5($md5)
    {
        $md5 = str_replace(['-', '_'], ['+', '/'], $md5);
        if (substr($md5, -2) !== '==') {
            $md5 = $md5 . '==';
        }
        return $md5;
    }

    public function radix_view_phash($hash = null)
    {
        if ($hash === null) {
            return $this->error(_i('No hash specified.'));
        }

        $is_md5 = false;

        if (strlen($hash) <= 16) {
            $phash['phash'] = $hash;
        } else {
            $md5 = $this->handlemd5($hash);
            $phash = $this->phash->getPHashfrommd5($md5);
            if ($phash == '' || $phash == null) {
                return $this->error(_i('Hash not found.'));
            }
            $is_md5 = true;

        }
        // add limits at the end
        $similar = $this->phash->getSimilar($phash['phash'][0] . $phash['phash'][1] . $phash['phash'][2] . $phash['phash'][3], 0);
        if (empty($similar)) {
            return $this->error(_i('Hash not found.'));
        }
        $h_medias = [];
        $m_medias = [];
        $l_medias = [];

        //$counter = 0;

        foreach ($similar as $sim) {
            /*if($counter>=200)
                continue;*/
            $similarity = $this->phash->I->CompareStrings($phash['phash'], $sim['phash']);
            if ($similarity > 80) {
                $h_medias[] = $sim['md5'];
                //$counter++;
            } else if ($similarity > 50) {
                $m_medias[] = $sim['md5'];
                //$counter++;
            } else if ($similarity > 30) {
                $l_medias[] = $sim['md5'];
                //$counter++;
            }
        }

        $this->param_manager->setParam('section_title', _i('Viewing similar images of “' . htmlentities(($is_md5 ? $md5 : $phash['phash'])) . '”'));
        $this->builder->getProps()->addTitle(_i('Viewing similar images of “' . htmlentities(($is_md5 ? $md5 : $phash['phash'])) . '”'));
        ob_start();
        ?>
        <link href="<?= $this->plugin->getAssetManager()->getAssetLink('style.css') ?>" rel="stylesheet"
              type="text/css"/>
        <?php
        include __DIR__ . '/../../views/phash.php';
        $string = ob_get_clean();
        $partial = $this->builder->createPartial('body', 'plugin');
        $partial->getParamManager()->setParam('content', $string);

        return new Response($this->builder->build());
    }
}