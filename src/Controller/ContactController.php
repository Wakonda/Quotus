<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\Type\ContactType;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ContactController extends Controller
{
    public function indexAction(Request $request)
    {
		$form = $this->createForm(ContactType::class, null);

        return $this->render('Index/contact.html.twig', array('form' => $form->createView()));
    }
	
	public function sendAction(Request $request, SessionInterface $session)
	{
		$entity = new Contact();
        $form = $this->createForm(ContactType::class, $entity);
		$form->handleRequest($request);

		if($form->isValid())
		{
			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();
			$session->getFlashBag()->add('message', 'Votre message a été envoyé avec succès !');

			return $this->redirect($this->generateUrl('index'));
		}
		
		return $this->render('Index/contact.html.twig', array('form' => $form->createView()));
	}
}
