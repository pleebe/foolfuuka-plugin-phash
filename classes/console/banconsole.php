<?php

namespace Foolz\FoolFuuka\Plugins\BanishCP\Console;

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
            ->setName('phash_ban:run')
            ->setDescription('Ban known bad files using perceptual hash.')
            ->addOption(
                'similar',
                null,
                InputOption::VALUE_OPTIONAL,
                _i('Ban similar looking images too')
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('similar') == 'true') {
            $this->phash_ban($output, true);
        } else {
            $this->phash_ban($output, false);
        }
    }

    public function phash_ban($output, $similar = false)
    {
        $boards = $this->radix_coll->getAll();
        $output->writeln("\n<comment>This might take a while.</comment>");
        $output->writeln("\n<comment>By default this bans only 100% matches just to be safe.</comment>");
        $output->writeln("<comment>Use <info>--similar=true</info> to ban reasonably similar looking images as well.</comment>");
        while (true) {
            foreach ($boards as $board) {
                $output->writeln("\n* Processing $board->shortname");
                if ($similar) {
                    foreach ($this->phash->getbanned() as $phash) {
                        $sim = $this->phash->getSimilar($phash[0] . $phash[1]);
                        foreach ($sim as $s) {
                            $similarity = $this->phash->I->CompareStrings($phash, $s['phash']);
                            if ($similarity > 80) {
                                $this->phash->banPHash($s['phash']);
                            }
                        }
                    }
                } else {
                    foreach ($this->phash->getbanned() as $phash) {
                        $this->phash->banPHash($phash);
                    }
                }
            }
            $output->writeln("\n* Sleeping for 1 hour.");
            sleep(60 * 60);
        }
    }
}
