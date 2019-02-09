<?php

namespace App\Controller;

use App\Entity\Proverb;
use App\Entity\Country;
use App\Entity\Language;
use App\Service\GenericFunction;
use App\Service\ImageGenerator;
use App\Form\Type\ProverbType;
use App\Form\Type\ProverbFastMultipleType;
use App\Form\Type\ImageGeneratorType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;

use Abraham\TwitterOAuth\TwitterOAuth;
use seregazhuk\PinterestBot\Factories\PinterestBot;

require __DIR__.'/../../vendor/simple_html_dom.php';

class ProverbAdminController extends Controller
{
	private $formName = "proverb";
	
	public function indexAction(Request $request)
	{
		return $this->render('Proverb/index.html.twig');
	}

	public function indexDatatablesAction(Request $request)
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
		$entities = $entityManager->getRepository(Proverb::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $entityManager->getRepository(Proverb::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity->getId();
			$row[] = $entity->getText();
			$row[] = $entity->getLanguage()->getTitle();
			
			$show = $this->generateUrl('proverbadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('proverbadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">Lire</a> - <a href="'.$edit.'" alt="Edit">Modifier</a>';

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}

    public function newAction(Request $request, $countryId)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Proverb();
		
		if(!empty($countryId))
			$entity->setCountry($entityManager->getRepository(Country::class)->find($countryId));
		
		$form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Proverb/new.html.twig', array('form' => $form->createView()));
    }
	
	public function createAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Proverb();
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($entity, $form);

		if($form->isValid())
		{
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Proverb/new.html.twig', array('form' => $form->createView()));
	}
	
	public function showAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
		$imageGeneratorForm = $this->createForm(ImageGeneratorType::class);
	
		return $this->render('Proverb/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}
	
	public function editAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$form = $this->genericCreateForm($entity->getLanguage()->getAbbreviation(), $entity);
	
		return $this->render('Proverb/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	public function updateAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);
		
		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($entity, $form);
		
		if($form->isValid())
		{
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Proverb/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}
	
	public function deleteAction(Request $request, SessionInterface $session, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$entityManager->remove($entity);
		$entityManager->flush();
		
		$session->getFlashBag()->add('message', 'Le proverbe a été supprimé avec succès !');

		return $this->redirect($this->generateUrl('proverbadmin_index'));
	}

	public function newFastMultipleAction(Request $request)
	{
		$form = $this->createForm(ProverbFastMultipleType::class, new Proverb());

		return $this->render('Proverb/fastMultiple.html.twig', array('form' => $form->createView()));
	}
	
	public function addFastMultipleAction(Request $request, SessionInterface $session)
	{
		$entity = new Proverb();
		
		$form = $this->createForm(ProverbFastMultipleType::class, $entity);
		
		$form->handleRequest($request);
		$req = $request->request->get($form->getName());

		if(!empty($req["url"]) and filter_var($req["url"], FILTER_VALIDATE_URL))
		{
			$url = $req["url"];
			$url_array = parse_url($url);

			$authorizedURLs = ['d3d3LmxpbnRlcm5hdXRlLmNvbQ==', 'Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy', 'ZGljb2NpdGF0aW9ucy5sZW1vbmRlLmZy', 'd3d3LnByb3ZlcmJlcy1mcmFuY2Fpcy5mcg=='];

			if(!in_array(base64_encode($url_array['host']), $authorizedURLs))
				$form->get("url")->addError(new FormError('URL inconnue'));
		}

		if($form->isValid())
		{
			$gf = new GenericFunction();

			if(!empty($ipProxy = $form->get('ipProxy')->getData()))
				$html = $gf->getContentURL($url, $ipProxy);
			else
				$html = $gf->getContentURL($url);

			$proverbsArray = [];

			$dom = new \simple_html_dom();
			$dom->load($html);

			switch(base64_encode($url_array['host']))
			{
				case 'd3d3LmxpbnRlcm5hdXRlLmNvbQ==':
					foreach($dom->find('td.libelle_proverbe strong') as $pb)
					{					
						$entityProverb = clone $entity;
						$text = str_replace("\r\n", "", trim($pb->plaintext));
						
						$entityProverb->setText($text);

						$proverbsArray[] = $entityProverb;
					}
					break;
				case 'Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy':
					foreach($dom->find('div#citation_citationSearchList q') as $pb)
					{					
						$entityProverb = clone $entity;
						$text = str_replace("\r\n", "", trim($pb->plaintext));
						
						$entityProverb->setText($text);
						
						$proverbsArray[] = $entityProverb;
					}
					break;
				case 'ZGljb2NpdGF0aW9ucy5sZW1vbmRlLmZy':
					foreach($dom->find('div#content blockquote') as $pb)
					{
						$entityProverb = clone $entity;
						$text = str_replace("\r\n", "", trim(utf8_encode($pb->plaintext)));

						$entityProverb->setText($text);

						$proverbsArray[] = $entityProverb;
					}
					break;
				case 'd3d3LnByb3ZlcmJlcy1mcmFuY2Fpcy5mcg==':
					foreach($dom->find("div.post q") as $pb)
					{
						$entityProverb = clone $entity;
						$entityProverb->setText($pb->plaintext);

						$proverbsArray[] = $entityProverb;
					}
					break;
			}

			$numberAdded = 0;
			$numberDoubloons = 0;

			$entityManager = $this->getDoctrine()->getManager();

			foreach($proverbsArray as $proverb)
			{
				if($entityManager->getRepository(Proverb::class)->checkForDoubloon($proverb) > 0)
					$numberDoubloons++;
				else
				{
					$entityManager->persist($proverb);
					$entityManager->flush();
					$numberAdded++;
				}
			}

			$session->getFlashBag()->add('message', $numberAdded.' proverbe(s) ajouté(s), '.$numberDoubloons.' doublon(s)');
	
			return $this->redirect($this->generateUrl('proverbadmin_index'));
		}
		
		return $this->render('Proverb/fastMultiple.html.twig', array('form' => $form->createView()));
	}

	public function twitterAction(Request $request, SessionInterface $session, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);

		$locale = strtoupper($entity->getLanguage()->getAbbreviation());
		
		$consumer_key = getenv("TWITTER_CONSUMER_KEY_".$locale);
		$consumer_secret = getenv("TWITTER_CONSUMER_SECRET_".$locale);
		$access_token = getenv("TWITTER_ACCESS_TOKEN_".$locale);
		$access_token_secret = getenv("TWITTER_ACCESS_TOKEN_SECRET_".$locale);

		$connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);

		$parameters = [];
		$parameters["status"] = $request->request->get("twitter_area")." ".$this->generateUrl("read", array("id" => $id, 'slug' => $entity->getSlug()), UrlGeneratorInterface::ABSOLUTE_URL);
		$image = $request->request->get('image_tweet');

		if(!empty($image)) {
			$media = $connection->upload('media/upload', array('media' => $image));
			$parameters['media_ids'] = implode(',', array($media->media_id_string));
		}

		$statues = $connection->post("statuses/update", $parameters);
	
		$session->getFlashBag()->add('message', 'Twitter envoyé avec succès');
	
		return $this->redirect($this->generateUrl("proverbadmin_show", array("id" => $id)));
	}

	public function pinterestAction(Request $request, SessionInterface $session, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
		$mail = getenv("PINTEREST_MAIL");
		$pwd = getenv("PINTEREST_PASSWORD");
		$username = getenv("PINTEREST_USERNAME");

		$bot = PinterestBot::create();
		$bot->auth->login($mail, $pwd);
		
		$boards = $bot->boards->forUser($username);
		
		$image = $request->request->get('image_pinterest');
		
		$bot->pins->create($image, $boards[0]['id'], $request->request->get("pinterest_area"), $this->generateUrl("read", ["id" => $entity->getId(), "slug" => $entity->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));
		
		if(empty($bot->getLastError()))
			$session->getFlashBag()->add('message', 'Envoyé avec succès sur Pinterest');
		else
			$session->getFlashBag()->add('message', $bot->getLastError());
	
		return $this->redirect($this->generateUrl("proverbadmin_show", array("id" => $id)));
	}
	
	public function facebookAction(Request $request, $id)
	{
		if(getenv("FACEBOOK_APP_ENV") == "dev")
		{
			// TEST FACEBOOK
			$fb = new \Facebook\Facebook([
			  'app_id' => getenv("FACEBOOK_DEV_APP_ID"),
			  'app_secret' => getenv("FACEBOOK_DEV_APP_SECRET"),
			  'default_graph_version' => 'v2.10'
			]);
			
			$userId = getenv("FACEBOOK_DEV_USER_ID");
			$token = getenv("FACEBOOK_DEV_TOKEN");
			$pageId = getenv("FACEBOOK_DEV_PAGE_ID");
			
			$response = $fb->get("/".$pageId."?fields=access_token", $token);
			
			$accessTokenPage = $response->getDecodedBody()['access_token'];
			
			$data = [
				'caption' => $request->request->get("facebook_area"),
				'url' => $request->request->get("image_facebook")
			];

			$response = $fb->post('/'.$pageId.'/photos', $data, $accessTokenPage);
		}

		$session->getFlashBag()->add('message', 'Envoyé avec succès sur Facebook');
		
		return $this->redirect($this->generateUrl("proverbadmin_show", ["id" => $id]));
	}
	
	public function saveImageAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
        $imageGeneratorForm = $this->createForm(ImageGeneratorType::class);
        $imageGeneratorForm->handleRequest($request);
		
		if ($imageGeneratorForm->isSubmitted() && $imageGeneratorForm->isValid())
		{
			$file = $imageGeneratorForm->get('image')->getData();
			
            $fileName = md5(uniqid()).'_'.$file->getClientOriginalName();

			$data = file_get_contents($file->getPathname());
			$image = imagecreatefromstring($data);
			
			ob_start();
			imagepng($image);
			$png = ob_get_clean();
				
			$image_size = getimagesizefromstring($png);
			
			$font = realpath(__DIR__."/../../public").DIRECTORY_SEPARATOR.'font'.DIRECTORY_SEPARATOR.'source-serif-pro'.DIRECTORY_SEPARATOR.'SourceSerifPro-Regular.otf';

			$widthText = $image_size[0] * 0.9;
			$start_x = $image_size[0] * 0.1;
			$start_y = $image_size[1] * 0.35;

			$copyright_x = $image_size[0] * 0.03;
			$copyright_y = $image_size[1] - $image_size[1] * 0.03;

			if($imageGeneratorForm->get('invert_colors')->getData())
			{
				$white = imagecolorallocate($image, 0, 0, 0);
				$black = imagecolorallocate($image, 255, 255, 255);
			}
			else
			{
				$black = imagecolorallocate($image, 0, 0, 0);
				$white = imagecolorallocate($image, 255, 255, 255);
			}

			$imageGenerator = new ImageGenerator();
			$imageGenerator->setFontColor($black);
			$imageGenerator->setStrokeColor($white);
			$imageGenerator->setStroke(true);
			$imageGenerator->setBlur(true);
			$imageGenerator->setFont($font);
			$imageGenerator->setFontSize($imageGeneratorForm->get('font_size')->getData());
			$imageGenerator->setImage($image);
			
			$text = html_entity_decode($entity->getText(), ENT_QUOTES);
			
			$imageGenerator->setText($text);
			$imageGenerator->setCopyright(["x" => $copyright_x, "y" => $copyright_y, "text" => "proverbius.wakonda.guru"]);

			$imageGenerator->generate($start_x, $start_y, $widthText);

			imagepng($image, "photo/proverb/".$fileName);
			imagedestroy($image);
			
			$entity->addImage($fileName);
			
			$entityManager->persist($entity);
			$entityManager->flush();
			
			$redirect = $this->generateUrl('proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}

        return $this->render('Proverb/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}
	
	public function removeImageAction(Request $request, $id, $fileName)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
		$entity->removeImage($fileName);
		
		$entityManager->persist($entity);
		$entityManager->flush();
		
		$filesystem = new Filesystem();
		$filesystem->remove("photo/proverb/".$fileName);
		
		$redirect = $this->generateUrl('proverbadmin_show', array('id' => $entity->getId()));

		return $this->redirect($redirect);
	}
	
	private function genericCreateForm($locale, $entity)
	{
		return $this->createForm(ProverbType::class, $entity, array('locale' => $locale));
	}
	
	private function checkForDoubloon($entity, $form)
	{
		if($entity->getText() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(Proverb::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("text")->addError(new FormError('Cette entrée existe déjà !'));
		}
	}
}