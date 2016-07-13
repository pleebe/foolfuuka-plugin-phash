<?php

use Doctrine\DBAL\Schema\Schema;
use Foolz\FoolFrame\Model\Autoloader;
use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFrame\Model\Plugins;
use Foolz\FoolFrame\Model\Uri;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\Plugin\Event;
use Symfony\Component\Routing\Route;

class HHVM_PHASH
{
    public function run()
    {
        Event::forge('Foolz\Plugin\Plugin::execute#foolz/foolfuuka-plugin-phash')
            ->setCall(function ($result) {
                /* @var Context $context */
                $context = $result->getParam('context');
                /** @var Autoloader $autoloader */
                $autoloader = $context->getService('autoloader');

                $autoloader->addClassMap([
                    'Foolz\FoolFrame\Controller\Admin\Plugins\PerceptualHash' => __DIR__ . '/classes/controller/admin.php',
                    'Foolz\FoolFuuka\Controller\Chan\PerceptualHash' => __DIR__ . '/classes/controller/chan.php',
                    'Foolz\FoolFuuka\Plugins\PerceptualHash\Model\PerceptualHash' => __DIR__ . '/classes/model/phash.php',
                    'Foolz\FoolFuuka\Plugins\PerceptualHash\Console\Console' => __DIR__ . '/classes/console/console.php',
                    'Foolz\FoolFuuka\Plugins\BanishCP\Console\Console' => __DIR__ . '/classes/console/banconsole.php'
                ]);

                if (file_exists(__DIR__ . '/vendor/aodto/phasher/src/PHasher.php')) {
                    $autoloader->addClass('PHasher', __DIR__ . '/vendor/aodto/phasher/src/PHasher.php');
                }

                $context->getContainer()
                    ->register('foolfuuka-plugin.phash', 'Foolz\FoolFuuka\Plugins\PerceptualHash\Model\PerceptualHash')
                    ->addArgument($context);

                Event::forge('Foolz\FoolFrame\Model\Context::handleWeb#obj.afterAuth')
                    ->setCall(function ($result) use ($context) {
                        // don't add the admin panels if the user is not an admin
                        if ($context->getService('auth')->hasAccess('maccess.admin')) {
                            Event::forge('Foolz\FoolFrame\Controller\Admin::before#var.sidebar')
                                ->setCall(function ($result) {
                                    $sidebar = $result->getParam('sidebar');
                                    $sidebar[]['plugins'] = [
                                        "content" => ["phash/manage" => ["level" => "admin", "name" => _i("Perceptual Hash"), "icon" => 'icon-file']]
                                    ];
                                    $result->setParam('sidebar', $sidebar);
                                });

                            $context->getRouteCollection()->add(
                                'foolframe.plugin.phash.admin', new Route(
                                    '/admin/plugins/phash/{_suffix}',
                                    [
                                        '_suffix' => 'manage',
                                        '_controller' => '\Foolz\FoolFrame\Controller\Admin\Plugins\PerceptualHash::manage'
                                    ],
                                    [
                                        '_suffix' => '.*'
                                    ]
                                )
                            );
                        }

                    });

                Event::forge('Foolz\FoolFrame\Model\Context::handleConsole#obj.app')
                    ->setCall(function ($result) use ($context) {
                        $result->getParam('application')
                            ->add(new \Foolz\FoolFuuka\Plugins\PerceptualHash\Console\Console($context));
                    });
                Event::forge('Foolz\FoolFrame\Model\Context::handleConsole#obj.app')
                    ->setCall(function ($result) use ($context) {
                        $result->getParam('application')
                            ->add(new \Foolz\FoolFuuka\Plugins\BanishCP\Console\Console($context));
                    });

                Event::forge('Foolz\FoolFrame\Model\Context::handleWeb#obj.routing')
                    ->setCall(function ($result) use ($context) {
                        /** @var RadixCollection $radix_coll */
                        $radix_coll = $context->getService('foolfuuka.radix_collection');
                        $radix_all = $radix_coll->getAll();

                        foreach ($radix_all as $radix) {
                            $obj = $result->getObject();
                            $obj->getRouteCollection()->add(
                                'foolfuuka.plugin.phash.chan.radix.' . $radix->shortname, new Route(
                                '/' . $radix->shortname . '/view_phash/{_suffix}',
                                [
                                    '_suffix' => '',
                                    '_controller' => '\Foolz\FoolFuuka\Controller\Chan\PerceptualHash::view_phash',
                                    'radix_shortname' => $radix->shortname
                                ],
                                [
                                    '_suffix' => '.*'
                                ]
                            ));
                        }
                    });
                Event::forge('Foolz\FoolFuuka\Model\Media::insert#var.media')
                    ->setCall(function ($object) use ($context) {
                        $context->getService('foolfuuka-plugin.phash')->processUpload($object);
                    });
            });

        Event::forge('Foolz\FoolFrame\Model\Plugin::install#foolz/foolfuuka-plugin-phash')
            ->setCall(function ($result) {
                /** @var Context $context */
                $context = $result->getParam('context');
                /** @var DoctrineConnection $dc */
                $dc = $context->getService('doctrine');
                /** @var Schema $schema */
                $schema = $result->getParam('schema');
                $table = $schema->createTable($dc->p('plugin_fu_phash'));
                if ($dc->getConnection()->getDriver()->getName() == 'pdo_mysql') {
                    $table->addOption('charset', 'utf8mb4');
                    $table->addOption('collate', 'utf8mb4_general_ci');
                }
                $table->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true]);
                $table->addColumn('md5', 'string', ['length' => 25]);
                $table->addColumn('phash', 'string', ['length' => 17]);
                $table->setPrimaryKey(['id']);
                $table->addUniqueIndex(['md5'], $dc->p('plugin_fu_phash_md5_index'));
                $table->addIndex(['phash'], $dc->p('plugin_fu_phash_index'));
                $bantable = $schema->createTable($dc->p('plugin_fu_known_cp'));
                if ($dc->getConnection()->getDriver()->getName() == 'pdo_mysql') {
                    $bantable->addOption('charset', 'utf8mb4');
                    $bantable->addOption('collate', 'utf8mb4_general_ci');
                }
                $bantable->addColumn('phash', 'string', ['length' => 17]);
                $bantable->setPrimaryKey(['phash']);
                $limits = $schema->createTable($dc->p('plugin_fu_phash_counters'));
                if ($dc->getConnection()->getDriver()->getName() == 'pdo_mysql') {
                    $limits->addOption('charset', 'utf8mb4');
                    $limits->addOption('collate', 'utf8mb4_general_ci');
                }
                $limits->addColumn('bid', 'integer', ['unsigned' => true]);
                $limits->addColumn('max_hashed', 'integer', ['unsigned' => true]);
                $limits->setPrimaryKey(['bid']);
            });
    }
}

(new HHVM_PHASH())->run();
