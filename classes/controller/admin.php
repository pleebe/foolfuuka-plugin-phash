<?php

namespace Foolz\FoolFrame\Controller\Admin\Plugins;

use Foolz\FoolFuuka\Plugins\PerceptualHash\Model\PerceptualHash as PHash;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Foolz\FoolFrame\Model\Validation\ActiveConstraint\Trim;

class PerceptualHash extends \Foolz\FoolFrame\Controller\Admin
{
    /**
     * @var PHash
     */
    protected $phash;

    public function before()
    {
        parent::before();

        $this->param_manager->setParam('controller_title', _i('Plugins'));
    }

    public function security()
    {
        return $this->getAuth()->hasAccess('maccess.admin');
    }

    protected function structure()
    {
        $form = [
            'open' => [
                'type' => 'open',
            ],
            'paragraph' => [
                'type' => 'paragraph',
                'help' => _i('Before using this plugin you must run composer in the plugin directory.</p><pre>php composer install</pre><p>')
            ],
            'paragraph2' => [
                'type' => 'paragraph',
                'help' => _i('This plugin uses 3 tables <code>ff_plugin_fu_phash</code>, <code>ff_plugin_fu_phash_counters</code> and <code>ff_plugin_fu_known_cp</code>. The main table is used for storing phashes and matching md5b64. Counters table is used for resuming the hashing process. Known CP table is used for banning bad phashes.')
            ],
            'paragraph3' => [
                'type' => 'paragraph',
                'help' => _i('</p><h4>Generating phashes</h4>Included CLI daemon is used to generate and insert phashes into the <code>ff_plugin_fu_phash</code> table. Run it from the FoolFuuka directory.</p><pre>php console generate_phash:run</pre><p>')
            ],
            'paragraph4' => [
                'type' => 'paragraph',
                'help' => _i('Processing large boards might take a few tries. If the app dies because of memory limits you can temporarily increase it like this</p><pre>php -dmemory_limit=512M console generate_phash:run</pre><p>')
            ],
            'paragraph5' => [
                'type' => 'paragraph',
                'help' => _i('if interrupted, the application will continue from where it left off.')
            ],
            'paragraph6' => [
                'type' => 'paragraph',
                'help' => _i('if corrupt hashes were inserted, you can update new hashes with <code>--force=yes</code>.')
            ],
            'paragraph7' => [
                'type' => 'paragraph',
                'help' => _i('Note: Use force only once and then use <code>--continueforce=yes</code> to keep forcing as needed.')
            ],
            'paragraph8' => [
                'type' => 'paragraph',
                'help' => _i('</p><h4>Viewing phashes on the site</h4><p>This plugin includes a route <code>/$board/view_phash/$hash</code> for viewing similar looking files. You can put either the url-safe md5b64 or hex phash string in. It will only show files that exist on that board, but it is aware of all similar hashes because of the shared table.')
            ],
            'paragraph9' => [
                'type' => 'paragraph',
                'help' => _i('</p><h4>Banning known bad files with phash</h4><pre>php console phash_ban:run</pre><p>Ban operation might take a while.')
            ],
            'paragraph10' => [
                'type' => 'paragraph',
                'help' => _i('Banning uses phashes in the <code>ff_plugin_fu_known_cp</code> table. By default it bans only 100% matches just to be safe.')
            ],
            'paragraph11' => [
                'type' => 'paragraph',
                'help' => _i('Use <code>--similar=true</code> to ban reasonably similar looking images as well.')
            ],
            'paragraph12' => [
                'type' => 'paragraph',
                'help' => _i('</p><h4>Add new banned phash</h4><p>You can insert known phashes of files you want to ban here.</p><p>')
            ],
            'foolfuuka.plugin.phash.insertknowncp' => [
                'type' => 'textarea',
                'label' => _i('Insert phash of known illegal file or whatever you want to ban.'),
                'help' => _i('One per line'),
                'class' => 'span8',
                'validation' => [new Trim()]
            ],
            'paragraph13' => [
                'type' => 'paragraph',
                'help' => _i('</p><h4>Hashing Method</h4>')
            ],
            'foolfuuka.plugin.phash.method' => [
                'type' => 'select',
                'label' => _i('Perceptual Hashing method'),
                'help' => _i('Select the method you want to use. PHasher is not compatible with DCT'),
                'options' => [
                    'phasher' => 'PHasher (No extra configuration needed)',
                    'extension' => 'PHP Extension',
                    'bridge' => 'Bridge'
                ],
                'preferences' => true
            ],
            'separator-2' => [
                'type' => 'separator-short'
            ]
        ];

        if ($this->preferences->get('foolfuuka.plugin.phash.method') == 'bridge') {
            $form['foolfuuka.plugin.phash.bridge.library'] = [
                'type' => 'input',
                'label' => _i('Bridge Library'),
                'help' => _i('Add LD_LIBRARY_PATH if needed'),
                'class' => 'span3',
                'preferences' => true
            ];
            $form['foolfuuka.plugin.phash.bridge.path'] = [
                'type' => 'input',
                'label' => _i('Bridge Binary Path'),
                'help' => _i('Path of PHash binary'),
                'class' => 'span3',
                'preferences' => true
            ];
        } else if($this->preferences->get('foolfuuka.plugin.phash.method') == 'extension') {
            $form['paragraph14'] = [
                'type' => 'paragraph',
                'help' => _i('Remember to build PHash PHP extension and add it to your php.ini')
            ];
        } else if($this->preferences->get('foolfuuka.plugin.phash.method') == 'phasher') {
            $form['paragraph14'] = [
                'type' => 'paragraph',
                'help' => _i('PHasher method is not compatible with the others. If you change it you must regenerate all hashes with <code>--force=yes</code>')
            ];
        }

        $form['separator-3'] = [
            'type' => 'separator-short'
        ];
        $form['submit'] = [
            'type' => 'submit',
            'class' => 'btn-primary',
            'value' => _i('Submit')
        ];
        $form['separator'] = [
            'type' => 'separator-short'
        ];
        $form['close'] = [
            'type' => 'close'
            ,
        ];

        return $form;
    }

    public function action_manage()
    {
        $this->param_manager->setParam('method_title', [_i('FoolFuuka'), _i("Perceptual Hashing"),_i('Documentation & Settings')]);

        $data['form'] = $this->structure();

        if ($this->getPost()) {
            $this->phash = $this->getContext()->getService('foolfuuka-plugin.phash');
            $this->phash->insertKnownCP($this->getPost('foolfuuka,plugin,phash,insertknowncp'));
            $this->preferences->submit_auto($this->getRequest(), $data['form'], $this->getPost());
        }

        // create a form
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }
}
