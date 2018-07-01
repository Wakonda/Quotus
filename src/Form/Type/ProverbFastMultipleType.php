<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\Proverb;
use App\Entity\Country;
use App\Repository\CountryRepository;

class ProverbFastMultipleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('url', TextType::class, array(
                'constraints' => [new Assert\NotBlank(), new Assert\Url()], 'label' => 'URL', 'mapped' => false
            ))
			->add('ipProxy', TextType::class, array(
                'label' => 'Adresse Proxy', 'required' => false, 'mapped' => false, 'constraints' => [new Assert\Regex("#^[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}:[0-9]{2,4}$#")]
            ))
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
            ->add('save', SubmitType::class, array('label' => 'Ajouter', 'attr' => array('class' => 'btn btn-success')));
    }

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			"data_class" => Proverb::class
		));
	}

    public function getName()
    {
        return 'proverbfastmultiple';
    }
}