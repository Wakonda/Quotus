<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vote;
use App\Entity\Comment;
use App\Form\Type\UserType;
use App\Form\Type\UpdatePasswordType;
use App\Form\Type\ForgottenPasswordType;
use App\Form\Type\LoginType;

use App\Service\MailerProverbius;
use App\Service\PasswordHash;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class UserController extends Controller
{
    public function loginAction(Request $request, AuthenticationUtils $authenticationUtils, SessionInterface $session)
    {
		if($request->query->get("t") != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$entity = $entityManager->getRepository(User::class)->findOneByToken($request->query->get("t"));
			
			$now = new \Datetime();

			if($entity->getExpiredAt() > $now)
			{
				$session->getFlashBag()->add('confirm_login', 'Félicitation '.$entity->getUsername(). ', votre compte a été activé. Veuillez entrer vos identifiants pour vous connecter.');
				$entity->setEnabled(true);
				$entityManager->persist($entity);
				$entityManager->flush();
			}
			else
				$session->getFlashBag()->add('expired_login', 'Désolé '.$entity->getUsername(). ', votre compte ne peut pas être activé, puisque le lien est expiré.');
		}

		return $this->render('User/login.html.twig', array(
				'error'         => $authenticationUtils->getLastAuthenticationError(),
				'last_username' => $authenticationUtils->getLastUsername()
		));
    }

	public function listAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(User::class)->findAll();

		return $this->render('User/list.html.twig', array('entities' => $entities));
	}

	public function showAction(TokenStorageInterface $tokenStorage, $username)
	{
		$entityManager = $this->getDoctrine()->getManager();
		if(!empty($username))
			$entity = $entityManager->getRepository(User::class)->findOneByName(["username" => $username]);
		else
			$entity = $tokenStorage->getToken()->getUser();

		return $this->render('User/show.html.twig', array('entity' => $entity));
	}

	public function newAction(Request $request)
	{
		$entity = new User();
        $form = $this->createFormUser($entity, false);

		return $this->render('User/new.html.twig', array('form' => $form->createView()));
	}

	public function createAction(Request $request, SessionInterface $session, \Swift_Mailer $mailer)
	{
		$entity = new User();
        $form = $this->createFormUser($entity, false);
		$form->handleRequest($request);
		
		$params = $request->request->get("user");

		if($params["captcha"] != "" and $session->get("captcha_word") != $params["captcha"])
			$form->get("captcha")->addError(new FormError('Le mot doit correspondre à l\'image'));

		$this->checkForDoubloon($entity, $form);

		if($form->isValid())
		{
			if(!is_null($entity->getAvatar()))
			{
				$image = uniqid()."_avatar.png";
				$entity->getAvatar()->move("photo/user/", $image);
				$entity->setAvatar($image);
			}

			$ph = new PasswordHash();
			$salt = $ph->create_hash($entity->getPassword());
			
			$encoder = new MessageDigestPasswordEncoder();
			$entity->setPassword($encoder->encodePassword($entity->getPassword(), $salt));
			
			$expiredAt = new \Datetime();
			$entity->setExpiredAt($expiredAt->modify("+1 day"));
			$entity->setToken(md5(uniqid(mt_rand(), true).$entity->getUsername()));
			$entity->setEnabled(false);
			$entity->setSalt($salt);

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();

			// Send email
			$body = $this->renderView('User/confirmationInscription_mail.html.twig', array("entity" => $entity));

			$mailer->getTransport()->setStreamOptions(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]);
			$message = (new \Swift_Message('Proverbius - Inscription'))
				->setFrom('amatukami66@gmail.com', "Proverbius")
				->setTo($entity->getEmail())
				->setBody($body, 'text/html');
		
			$mailer->send($message);

			return $this->render('User/confirmationInscription.html.twig', array('entity' => $entity));
		}

		return $this->render('User/new.html.twig', array('form' => $form->createView()));
	}

	public function editAction(Request $request, TokenStorageInterface $tokenStorage, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		if(!empty($id))
			$entity = $entityManager->getRepository(User::class)->find($id);
		else
		{
			$this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY', null, 'Unable to access this page!');
			$entity = $tokenStorage->getToken()->getUser();
		}

		$form = $this->createFormUser($entity, true);
	
		return $this->render('User/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	public function updateAction(Request $request, TokenStorageInterface $tokenStorage, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		if(empty($id))
			$entity = $tokenStorage->getToken()->getUser();
		else
			$entity = $entityManager->getRepository(User::class)->find($id);
		
		$current_avatar = $entity->getAvatar();

		$form = $this->createFormUser($entity, true);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($entity, $form);

		if($form->isValid())
		{
			if(!is_null($entity->getAvatar()))
			{
				unlink("photo/user/".$current_avatar);
				$image = uniqid()."_avatar.png";
				$entity->getAvatar()->move("photo/user/", $image);
				$entity->setAvatar($image);
			}
			else
				$entity->setAvatar($current_avatar);

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('user_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('User/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}
	
	public function updatePasswordAction(Request $request, TokenStorageInterface $tokenStorage)
	{
		$entity = $tokenStorage->getToken()->getUser();
		$form = $this->createForm(UpdatePasswordType::class, $entity);
		
		return $this->render('User/updatepassword.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}
	
	public function updatePasswordSaveAction(Request $request, SessionInterface $session, TokenStorageInterface $tokenStorage)
	{
		$entity = $tokenStorage->getToken()->getUser();
        $form = $this->createForm(UpdatePasswordType::class, $entity);
		$form->handleRequest($request);

		if($form->isValid())
		{
			$ph = new PasswordHash();
			$salt = $ph->create_hash($entity->getPassword());
			
			$encoder = new MessageDigestPasswordEncoder();
			$entity->setSalt($salt);
			$entity->setPassword($encoder->encodePassword($entity->getPassword(), $salt));
			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();

			$session->getFlashBag()->add('new_password', 'Votre mot de passe a bien été modifié.');

			return $this->redirect($this->generateUrl('user_show', array('id' => $id)));
		}
		
		return $this->render('User/updatepassword.html.twig', array('form' => $form->createView()));
	}
	
	public function forgottenPasswordAction(Request $request)
	{
		$form = $this->createForm(ForgottenPasswordType::class, null);
	
		return $this->render('User/forgottenpassword.html.twig', array('form' => $form->createView()));
	}
	
	public function forgottenPasswordSendAction(Request $request, \Swift_Mailer $mailer)
	{
        $form = $this->createForm(ForgottenPasswordType::class, null);
		$form->handleRequest($request);
	
		$params = $request->request->get("forgotten_password");

		if($params["captcha"] != "" and $request->getSession()->get("captcha_word") != $params["captcha"])
			$form->get("captcha")->addError(new FormError('Le mot doit correspondre à l\'image'));

		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(User::class)->findByUsernameOrEmail($params["emailUsername"]);

		if(empty($entity))
			$form->get("emailUsername")->addError(new FormError("Le nom d'utilisateur ou l'adresse email n'existe pas"));

		if(!$form->isValid())
		{
			return $this->render('User/forgottenpassword.html.twig', array('form' => $form->createView()));
		}
		
		$temporaryPassword = $this->randomPassword();
		$ph = new PasswordHash();
		$salt = $ph->create_hash($temporaryPassword);

		$encoder = new MessageDigestPasswordEncoder();
		$entity->setSalt($salt);
		$entity->setPassword($encoder->encodePassword($temporaryPassword, $salt));
		$entityManager->persist($entity);
        $entityManager->flush();
		
		// Send email
		$body = $this->renderView('User/forgottenpassword_mail.html.twig', array("entity" => $entity, "temporaryPassword" => $temporaryPassword));
		
		$mailer->getTransport()->setStreamOptions(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]);
		$message = (new \Swift_Message("Proverbius - Mot de passe oublié"))
			->setFrom('amatukami66@gmail.com', "Proverbius")
			->setTo($entity->getEmail())
			->setBody($body, 'text/html');
	
		$mailer->send($message);
		
		return $this->render('User/forgottenpasswordsend.html.twig');
	}

	private function randomPassword($length = 8)
	{
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789&+-$";
		
		if($length >= strlen($chars))
			$length = 8;
		
		$password = substr(str_shuffle($chars), 0, $length);
		
		return $password;
	}

	private function createFormUser($entity, $ifEdit)
	{
		return $this->createForm(UserType::class, $entity, array('edit' => $ifEdit));
	}

	private function checkForDoubloon($entity, $form)
	{
		if($entity->getUsername() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(User::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("username")->addError(new FormError('Un utilisateur ayant le même nom d\'utilisateur / email existe déjà !'));
		}
	}
	
	private function createTemporaryPassword($email)
	{
		$key = strlen(uniqid());
		
		if(strlen($key) < strlen($email))
			$key = str_pad($key, strlen($email), $key, STR_PAD_RIGHT);
		elseif(strlen($key) > strlen($email))
		{
			$diff = strlen($key) - strlen($email);
			$key = substr($key, 0, -$diff);
		}
		
		return $email ^ $key;
	}

	private function testStrongestPassword($form, $password)
	{
		$min_length = 5;
		
		$letter = array();
		$number = array();
		
		for($i = 0; $i < strlen($password); $i++)
		{
			$current = $password[$i];
			
			if(($current >= 'a' and $current <= 'z') or ($current >= 'A' and $current <= 'Z'))
				$letter[] = $current;
			if($current >= '0' and $current <= '9')
				$number[] = $current;
		}
		
		if(strlen($password) > 0)
		{
			if(strlen($password) < $min_length)
				$form->get("password")->addError(new FormError('Votre mot de passe doit contenir au moins '.$min_length.' caractères.'));
			else
			{
				if(count($letter) == 0)
					$form->get('password')->addError(new FormError('Votre mot de passe doit comporter au moins une lettre.'));
				if(count($number) == 0)
					$form->get('password')->addError(new FormError('Votre mot de passe doit comporter au moins un chiffre.'));
			}
		}
	}
	
	// Profil show
	//** Mes Votes
	public function votesUserDatatablesAction(Request $request, $username)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i<intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Vote::class)->findVoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username);
		$iTotal = $entityManager->getRepository(Vote::class)->findVoteByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();

			$show = $this->generateUrl('read', array('id' => $entity['id'], 'slug' => $entity["slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity['text'].'</a>';
			
			list($icon, $color) = (($entity['vote'] == -1) ? array("fa-arrow-down", "red") : array("fa-arrow-up", "green"));
			$row[] = "<i class='fa ".$icon."' aria-hidden='true' style='color: ".$color.";'></i>";

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	//** Mes Commentaires
	public function commentsUserDatatablesAction(Request $request, $username)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');
		$sSearch = $request->query->get('sSearch');

		$sortByColumn = array();
		$sortDirColumn = array();
			
		for($i=0 ; $i<intval($request->query->get('iSortingCols')); $i++)
		{
			if ($request->query->get('bSortable_'.intval($request->query->get('iSortCol_'.$i))) == "true" )
			{
				$sortByColumn[] = $request->query->get('iSortCol_'.$i);
				$sortDirColumn[] = $request->query->get('sSortDir_'.$i);
			}
		}

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Comment::class)->findCommentByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username);
		$iTotal = $entityManager->getRepository(Comment::class)->findCommentByUser($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $username, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();

			$show = $this->generateUrl('read', array('id' => $entity['id'], 'slug' => $entity["slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity['text'].'</a>';
			$row[] = "le ".date_format(new \Datetime($entity['created_at']), "d/m/Y à H:i:s");

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}
}