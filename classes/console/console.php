<?php

namespace Foolz\FoolFuuka\Plugins\PerceptualHash\Console;

use Foolz\FoolFrame\Model\Context;
use Foolz\FoolFrame\Model\DoctrineConnection;
use Foolz\FoolFuuka\Model\RadixCollection;
use Foolz\FoolFuuka\Plugins\PerceptualHash\Model\PerceptualHash as PHash;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Console extends Command
{
    /**
     * @var \Foolz\FoolFrame\Model\Context
     */
    protected $context;

    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var PHash
     */
    protected $phash;

    /**
     * @var RadixCollection
     */
    protected $radix_coll;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->dc = $context->getService('doctrine');
        $this->radix_coll = $context->getService('foolfuuka.radix_collection');
        $this->phash = $context->getService('foolfuuka-plugin.phash');
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('generate_phash:run')
            ->setDescription('Generates phashes for new images on all boards.')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_OPTIONAL,
                _i('Force hash updates')
            )
            ->addOption(
                'continueforce',
                null,
                InputOption::VALUE_OPTIONAL,
                _i('Keep forcing hash updates')
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('force') == 'yes') {
            $this->generate_phash($output, true, true);
        } else if ($input->getOption('continueforce') == 'yes') {
            $this->generate_phash($output, false, true);
        } else {
            $this->generate_phash($output);
        }
    }

    public function generate_phash($output, $force = false, $continueforce = false)
    {
        $boards = $this->radix_coll->getAll();
        $output->writeln("\n<comment>Processing large board might take a few tries.</comment>");
        $output->writeln("<comment>If the app dies because of memory limits you can temporarily increase it like this</comment>\n");
        $output->writeln("\t<info>php -dmemory_limit=512M console generate_phash:run</info>\n");
        $output->writeln("<comment>if interrupted, the application will continue from where it left off</comment>");
        $output->writeln("\n<comment>if corrupt hashes were inserted, you can update new hashes with </comment><info>--force=yes</info>");
        $output->writeln("<comment>Note: Use force only once and then use <info>--continueforce=yes</info> to keep forcing.</comment>");
        while (true) {
            foreach ($boards as $board) {
                $output->writeln("\n* Processing $board->shortname");
                foreach ($this->phash->loopMedia($board, $force) as $media) {
                    $phash = $this->phash->processPHash($board, $media);
                    if ($phash !== null && $phash !== '') {
                        $this->phash->insertPHash($media['media_hash'], $phash, $continueforce);
                    }
                    $this->phash->updatelimits($board, $media['media_id']);
                }
                $output->writeln("* Files left to process " . $this->phash->getStatus($board));
            }
            $force = false;
            $output->writeln("\n* Sleeping for 5 minutes.");
            sleep(5 * 60);
        }
    }
}
