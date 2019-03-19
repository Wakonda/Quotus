<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Proverb;
use App\Entity\ProverbImage;

class JsonToEntityImagesCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:json-to-entity';

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
		parent::__construct();
        $this->em = $em;
    }
	
    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$proverbs = $this->em->getRepository(Proverb::class)->findAll();
		
		foreach($proverbs as $proverb) {
			$images = $proverb->getImages();
			
			if(!empty($images)) {
				foreach($images as $image) {
					$proverbImage = new ProverbImage();
					
					$proverbImage->setImage($image);
					$proverbImage->setProverb($proverb);
					$proverb->addProverbImage($proverbImage);
					
					$this->em->persist($proverb);
					$this->em->persist($proverbImage);
				}
				$this->em->flush();
			}
		}
    }
}