<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class UpdatePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
			->add('password', RepeatedType::class, array(
				'label' => 'Nouveau mot de passe',
				'type' => PasswordType::class,
				'invalid_message' => 'Les mots de passe doivent correspondre',
				'options' => array('required' => true),
				'first_options'  => array('label' => 'Mot de passe'),
				'second_options' => array('label' => 'Mot de passe (validation)'),
			))
			
            ->add('save', SubmitType::class, array('label' => 'Sauvegarder', 'attr' => array('class' => 'btn btn-success')));
    }

    public function getName()
    {
        return 'updatepassword';
    }
}
