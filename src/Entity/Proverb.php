<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use App\Service\GenericFunction;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProverbRepository")
 */
class Proverb
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="text")
     */
    protected $text;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $slug;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     */
    protected $country;

    /**
     * @ORM\Column(type="json", length=255)
     */
    protected $images;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setText($text)
    {
        $this->text = $text;
		$this->setSlug();
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($country)
    {
        $this->country = $country;
    }

    public function getImages()
    {
        return $this->images;
    }

    public function setImages($images)
    {
        $this->images = $images;
    }
	
	public function addImage($image)
	{
		if(!is_array($this->images))
			$this->images = [];
		
		$this->images[] = $image;
	}
	
	public function removeImage($image)
	{
		$this->images = array_diff($this->images, [$image]);
	}

    public function getSlug()
    {
        return $this->slug;
    }

    public function setSlug()
    {
		if(empty($this->slug))
			$this->slug = GenericFunction::slugify($this->text, 30);
    }
}