<?php use Foolz\FoolFuuka\Model\CommentBulk;
use Foolz\FoolFuuka\Model\Media;
use Foolz\FoolFuuka\Model\MediaFactory;
?>
<div style="margin: 20px auto; width:960px;">
    <?php
    $bulk = new CommentBulk();
    $mediaf = new MediaFactory($this->context);
    $vars = ['h_medias' => 'High','m_medias' => 'Medium','l_medias' => 'Low'];
    foreach($vars as $medias => $text) :
        if(!empty($$medias)) :
            ?>
            <div class="hashes">
            <h4><?= $text ?> Similarity Matches</h4>
            <?php
            foreach($$medias as $hash) :
                try {
                    $media = $mediaf->getByMediaHash($this->radix, $hash, true);
                    $bulk->import((array) $media, $this->radix);
                    $media = new Media($this->getContext(), $bulk);
                    $media->op = true;
                    if($media->getMediaStatus($this->getRequest()) !== 'banned') : ?>
                        <div class="phash_image">
                            <a target="_blank" href="<?= $media->getMediaLink($this->getRequest()) ?>">
                                <img src="<?= $media->getThumbLink($this->getRequest())  ?>" />
                            </a>
                            <div class="phash_tools">
                                <a href="<?= $this->uri->create($this->radix->shortname . '/search/image/' . $media->getSafeMediaHash()) ?>" class="btnr parent"><?= _i('View Same') ?></a>
                                <a href="<?= $this->uri->create($this->radix->shortname . '/view_phash/' . $media->getSafeMediaHash()) ?>" class="btnr parent"><?= _i('View Similar') ?></a>
                            </div>
                        </div>
                    <?php endif;
                } catch (\Exception $e) {}
            endforeach;
            ?></div><?php
        endif;
    endforeach; ?>
<hr></div><div class="clearfix"></div>