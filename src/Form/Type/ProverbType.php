<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\Proverb;
use App\Entity\Country;
use App\Entity\Language;
use App\Repository\CountryRepository;
use App\Repository\LanguageRepository;

class ProverbType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$locale = $options["locale"];

        $builder
			->add('text', TextareaType::class, array(
                'attr' => array('class' => 'redactor'), 'label' => 'admin.proverb.Text'
            ))
			->add('country', EntityType::class, array(
				'label' => 'admin.proverb.Country',
				'class' => Country::class,
				'query_builder' => function (CountryRepository $er) {
					return $er->findAllForChoice();
				},
				'multiple' => false, 
				'expanded' => false,
				'constraints' => array(new Assert\NotBlank()),
				'placeholder' => 'main.field.ChooseAnOption'
			))
			->add('language', EntityType::class, array(
				'label' => 'admin.form.Language',
				'class' => Language::class,
				'query_builder' => function (LanguageRepository $er) use ($locale) {
					return $er->findAllForChoice($locale);
				},
				'multiple' => false,
				'required' => true,
				'expanded' => false,
				'placeholder' => 'main.field.ChooseAnOption',
				'constraints' => new Assert\NotBlank()
			))

            ->add('save', SubmitType::class, array('label' => 'admin.main.Save', 'attr' => array('class' => 'btn btn-success')));
    }

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(array(
			"data_class" => Proverb::class,
			"locale" => null
		));
	}
	
    public function getName()
    {
        return 'proverb';
    }
}