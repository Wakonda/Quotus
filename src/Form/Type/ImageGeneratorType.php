<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ImageGeneratorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('image', FileType::class, array("label" => "Image", "required" => true, 'constraints' => new Assert\NotBlank()))
			->add('font_size', IntegerType::class, ["label" => "Taille de la police", "required" => true, 'constraints' => new Assert\NotBlank(), "data" => 35])
			->add('invert_colors', CheckboxType::class, ["label" => "Inverser les couleurs", "required" => false])
            ->add('save', SubmitType::class, array('label' => 'Valider', "attr" => array("class" => "btn btn-primary")))
			;
    }

    public function getName()
    {
        return 'image_generator';
    }
}