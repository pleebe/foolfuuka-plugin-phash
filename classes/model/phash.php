<?php

namespace Foolz\FoolFuuka\Plugins\PerceptualHash\Model;

use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFrame\Model\Model;
use Foolz\FoolFrame\Model\Preferences;
use Foolz\FoolFuuka\Model\RadixCollection;
use PHasher;

class PerceptualHash extends Model
{
    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var Preferences
     */
    protected $preferences;

    protected $basedir;

    public function __construct(Context $context)
    {
        parent::__construct($context);

        $this->dc = $context->getService('doctrine');
        $this->preferences = $context->getService('preferences');

        $this->I = PHasher::Instance();
        $this->basedir = $this->preferences->get('foolfuuka.boards.directory');
    }

    public function getpath($shortname, $media, $thumbnail)
    {
        return $this->basedir . '/' . $shortname . '/'. ($thumbnail ? 'thumb' : 'image') .'/' . substr($media, 0, 4) . '/' . substr($media, 4, 2) . '/'. $media;
    }

    public function getPHash($path)
    {
        switch($this->preferences->get('foolfuuka.plugin.phash.method')) {
            case 'phasher':
                return $this->I->HashAsString($this->I->HashImage($path));
                break;
            case 'extension':
                if (extension_loaded("pHash")) {
                    return $this->I->HashAsString(ph_dct_imagehash($path));
                }
                break;
            case 'bridge':
                $command = '';
                $lib = $this->preferences->get('foolfuuka.plugin.phash.bridge.library');
                if ($lib !== '') {
                    $command = 'LD_LIBRARY_PATH=' . $lib . ' ';
                }
                $exec = $this->preferences->get('foolfuuka.plugin.phash.bridge.path');
                if ($exec !== '') {
                    $command .= $exec;
                } else {
                    $command .= 'phashbridge';
                }
                return exec($command . ' "' . $path . '"');
                break;
            default:
                // PHasher as fallback
                return $this->I->HashAsString($this->I->HashImage($path));
                break;
        }
        return '';
    }

    public function processPHash($radix, $result)
    {
        $skip_media = false;
        // prefer media first, then op thumb, then reply thumb
        if($result['media'] !== null && $result['media'] !== '') {
            $media = $result['media'];
            $path = $this->getpath($radix->shortname, $media, false);
            // skip non image files
            $res = substr($media, -4);
            if ($res == '.swf' || $res == '.pdf' || $res == 'webm') {
                $skip_media = true;
            }
            if (file_exists($path) && !$skip_media) {
                $phash = $this->getPHash($path);
                if (strlen($phash) <= 16 && $phash !== '0' && $phash !== null && $phash !== '' && $phash !== '0000000000000000') {
                    return $phash;
                }
            }
        }
        if ($result['preview_op'] !== null && $result['preview_op'] !== '') {
            $media = $result['preview_op'];
            $path = $this->getpath($radix->shortname, $media, true);
            if (file_exists($path)) {
                $phash = $this->getPHash($path);
                if (strlen($phash) <= 16 && $phash !== '0' && $phash !== null && $phash !== '' && $phash !== '0000000000000000') {
                    return $phash;
                }
            }
        }
        if ($result['preview_reply'] !== null && $result['preview_reply'] !== '') {
            $media = $result['preview_reply'];
            $path = $this->getpath($radix->shortname, $media, true);
            if (file_exists($path)) {
                $phash = $this->getPHash($path);
                if (strlen($phash) <= 16 && $phash !== '0' && $phash !== null && $phash !== '' && $phash !== '0000000000000000') {
                    return $phash;
                }
            }
        }

        // if we got here then no media files exist and we can just return ''
        return '';
    }

    public function loopMedia($radix, $force = false)
    {
        try {
            $limits = $this->dc->qb()
                ->select('max_hashed')
                ->from($this->dc->p('plugin_fu_phash_counters'))
                ->where('bid = :bid')
                ->setParameter(':bid', $radix->id)
                ->execute()
                ->fetch();

            if (!$limits['max_hashed'] || $force) {
                $limit = 0;
            } else {
                $limit = $limits['max_hashed'];
            }
        } catch (\Exception $e) {
            $limit = 0;
        }

        return $this->dc->qb()
            ->select('media_id, media_hash, media, preview_op, preview_reply')
            ->from($radix->getTable('_images'), 'ri')
            ->where('media_id > :limit')
            ->setParameter(':limit', $limit)
            ->orderBy('media_id', 'asc')
            ->setMaxResults(1000)
            ->execute()
            ->fetchAll();
    }

    public function insertPHash($md5, $phash, $force = false)
    {
        // md5 field is unique so check if it exists first
        $re = $this->dc->qb()
            ->select('count(md5) as count')
            ->from($this->dc->p('plugin_fu_phash'))
            ->where('md5 = :md5')
            ->setParameter(':md5', $md5)
            ->execute()
            ->fetch();

        if (!$re['count']) {
            // insert. nothing to do if it exists, because md5 can only have one phash but phash can have multiple md5
            $this->dc->getConnection()
                ->insert($this->dc->p('plugin_fu_phash'), ['md5' => $md5, 'phash' => $phash]);
        } else {
            // We can force update if the plugin inserted corrupt hashes before
            if($force) {
                $this->dc->qb()
                    ->update($this->dc->p('plugin_fu_phash'))
                    ->set('phash', ':phash')
                    ->where('md5 = :md5')
                    ->setParameter(':md5', $md5)
                    ->setParameter(':phash', $phash)
                    ->execute();
            }
        }
    }

    public function processUpload($object)
    {
        try {
            $phash = $this->getPHash($object->getParam('path'));
            if (strlen($phash) <= 16 && $phash !== '0' && $phash !== null && $phash !== '' && $phash !== '0000000000000000') {
                $this->insertPHash($object->getParam('hash'), $phash);
            }
        } catch (\Exception $e) {}
    }

    public function getPHashfrommd5($md5)
    {
        return $this->dc->qb()
            ->select('phash')
            ->from($this->dc->p('plugin_fu_phash'))
            ->where('md5 = :md5')
            ->setParameter(':md5', $md5)
            ->execute()
            ->fetch();
    }

    public function getsimilar($phash, $limit = 0)
    {
        $arr = [];
        $results = $this->dc->qb()
            ->select('md5, phash')
            ->from($this->dc->p('plugin_fu_phash'))
            ->where('phash like :phash')
            ->setParameter(':phash', $phash.'%');
        if($limit !== 0) {
            // we might get a lot of results so let's limit
            $results = $results->setMaxResults($limit);
        }
        $results = $results->execute()
            ->fetchAll();
        foreach($results as $result) {
            $arr[] = ['phash' => $result['phash'], 'md5' => $result['md5']];
        }
        return $arr;
    }

    public function getmd5fromPHash($phash)
    {
        $arr = [];
        $results = $this->dc->qb()
            ->select('md5')
            ->from($this->dc->p('plugin_fu_phash'))
            ->where('phash = :phash')
            ->setParameter(':phash', $phash)
            ->execute()
            ->fetchAll();
        foreach($results as $result) {
            $arr[] = $result['md5'];
        }
        return $arr;
    }

    public function getStatus($radix)
    {
        $re = $this->dc->qb()
            ->select('max_hashed')
            ->from($this->dc->p('plugin_fu_phash_counters'))
            ->where('bid = :bid')
            ->setParameter(':bid', $radix->id)
            ->execute()
            ->fetch();
        $r = $this->dc->qb()
            ->select('max(media_id) as max')
            ->from($radix->getTable('_images'), 'ri')
            ->execute()
            ->fetch();
        return (int)$r['max'] - (int)$re['max_hashed'];
    }

    public function updatelimits($radix, $limit)
    {
        $re = $this->dc->qb()
            ->select('count(bid) as count')
            ->from($this->dc->p('plugin_fu_phash_counters'))
            ->where('bid = :bid')
            ->setParameter(':bid', $radix->id)
            ->execute()
            ->fetch();

        if (!$re['count']) {
            $this->dc->getConnection()
                ->insert($this->dc->p('plugin_fu_phash_counters'), ['bid' => $radix->id, 'max_hashed' => $limit]);
        } else {
            $this->dc->qb()
                ->update($this->dc->p('plugin_fu_phash_counters'))
                ->set('max_hashed', $limit)
                ->where('bid = :bid')
                ->setParameter(':bid', $radix->id)
                ->execute();
        }
    }

    public function getbanned()
    {
        $arr = [];
        $results = $this->dc->qb()
            ->select('phash')
            ->from($this->dc->p('plugin_fu_known_cp'))
            ->execute()
            ->fetchAll();
        foreach($results as $result) {
            $arr[] = $result['phash'];
        }
        return $arr;
    }

    public function delete($radix, $md5)
    {
        $data = $this->dc->qb()
            ->select('media, preview_op, preview_reply')
            ->from($radix->getTable('_images'), 'ri')
            ->where('media_hash = :md5')
            ->setParameter(':md5', $md5)
            ->execute()
            ->fetch();
        if ($data['media'] !== null && $data['media'] !== '')
            unlink($this->getpath($radix->shortname, $data['media'], false));
        if ($data['preview_op'] !== null && $data['preview_op'] !== '')
            unlink($this->getpath($radix->shortname, $data['preview_op'], true));
        if ($data['preview_reply'] !== null && $data['preview_reply'] !== '')
            unlink($this->getpath($radix->shortname, $data['preview_reply'], true));
    }

    public function insertKnownCP($input)
    {
        $list = preg_split('/\r\n|\r|\n/', $input);
        foreach($list as $item) {
            $re = $this->dc->qb()
                ->select('count(phash) as count')
                ->from($this->dc->p('plugin_fu_known_cp'))
                ->where('phash = :phash')
                ->setParameter(':phash', $item)
                ->execute()
                ->fetch();

            if (!$re['count']) {
                $this->dc->getConnection()
                    ->insert($this->dc->p('plugin_fu_known_cp'), ['phash' => $item]);
            }
        }
    }

    public function banPHash($phash)
    {
        $md5 = $this->getmd5fromPHash($phash);
        $radix_coll = $this->context->getService('foolfuuka.radix_collection');
        foreach($md5 as $hash) {
            $re = $this->dc->qb()
                ->select('count(md5) as count')
                ->from($this->dc->p('banned_md5'))
                ->where('md5 = :md5')
                ->setParameter(':md5', $hash)
                ->execute()
                ->fetch();

            if (!$re['count']) {
                $this->dc->getConnection()
                    ->insert($this->dc->p('banned_md5'), ['md5' => $hash]);

                foreach ($radix_coll->getAll() as $radix) {
                    try {
                        $this->delete($radix, $hash);

                        $i = $this->dc->qb()
                            ->select('COUNT(*) as count')
                            ->from($radix->getTable('_images'), 'ri')
                            ->where('media_hash = :md5')
                            ->setParameter(':md5', $hash)
                            ->execute()
                            ->fetch();

                        if (!$i['count']) {
                            $this->dc->getConnection()
                                ->insert($radix->getTable('_images'), ['media_hash' => $hash, 'banned' => 1]);
                        } else {
                            $this->dc->qb()
                                ->update($radix->getTable('_images'))
                                ->set('banned', 1)
                                ->where('media_hash = :media_hash')
                                ->setParameter(':media_hash', $hash)
                                ->execute();
                        }
                    } catch (\Exception $e) {
                    }
                }
            }
        }
    }
}
