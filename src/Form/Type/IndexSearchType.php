<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Repository\CountryRepository;

use App\Entity\Country;

class IndexSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('text', TextType::class, array("label" => "Mots-clés", "required" => false, "attr" => array("class" => "tagit full_width")))
			->add('country', EntityType::class, array(
				'label' => 'Pays',
				'class' => Country::class,
				'query_builder' => function (CountryRepository $er) {
					return $er->findAllForChoice();
				},
				'multiple' => false, 
				'expanded' => false,
				'constraints' => array(new Assert\NotBlank()),
				'placeholder' => 'Sélectionnez un pays'
			))
            ->add('search', SubmitType::class, array('label' => 'Rechercher', "attr" => array("class" => "btn btn-primary")))
			;
    }

    public function getName()
    {
        return 'index_search';
    }
}