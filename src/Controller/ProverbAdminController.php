<?php

namespace App\Controller;

use App\Entity\Proverb;
use App\Entity\ProverbImage;
use App\Entity\Country;
use App\Entity\Language;
use App\Service\GenericFunction;
use App\Service\ImageGenerator;
use App\Form\Type\ProverbType;
use App\Form\Type\ProverbFastMultipleType;
use App\Form\Type\ImageGeneratorType;
use App\Service\PHPImage;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\TranslatorInterface;

use Abraham\TwitterOAuth\TwitterOAuth;
use seregazhuk\PinterestBot\Factories\PinterestBot;

require __DIR__.'/../../vendor/simple_html_dom.php';

class ProverbAdminController extends Controller
{
	private $formName = "proverb";
	private $authorizedURLs = ['d3d3LmxpbnRlcm5hdXRlLmNvbQ==', 'Y2l0YXRpb24tY2VsZWJyZS5sZXBhcmlzaWVuLmZy', 'ZGljb2NpdGF0aW9ucy5sZW1vbmRlLmZy', 'd3d3LnByb3ZlcmJlcy1mcmFuY2Fpcy5mcg==', 'Y3JlYXRpdmVwcm92ZXJicy5jb20=', 'd3d3LnNwZWNpYWwtZGljdGlvbmFyeS5jb20='];

	public function indexAction(Request $request)
	{
		return $this->render('Proverb/index.html.twig');
	}

	public function indexDatatablesAction(Request $request, TranslatorInterface $translator)
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
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$translator->trans('admin.index.Read').'</a> - <a href="'.$edit.'" alt="Edit">'.$translator->trans('admin.index.Update').'</a>';

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
		
		$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));
		
		if(!empty($countryId))
			$entity->setCountry($entityManager->getRepository(Country::class)->find($countryId));
		
		$form = $this->genericCreateForm($request->getLocale(), $entity);

		return $this->render('Proverb/new.html.twig', array('form' => $form->createView()));
    }
	
	public function createAction(Request $request, TranslatorInterface $translator)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Proverb();
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);

        $form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($translator, $entity, $form);

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

	public function updateAction(Request $request, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$locale = $request->request->get($this->formName)["language"];
		$language = $entityManager->getRepository(Language::class)->find($locale);
		
		$form = $this->genericCreateForm($language->getAbbreviation(), $entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($translator, $entity, $form);
		
		if($form->isValid())
		{
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Proverb/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	public function newFastMultipleAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = new Proverb();
		$entity->setLanguage($entityManager->getRepository(Language::class)->findOneBy(["abbreviation" => $request->getLocale()]));
		
		$form = $this->createForm(ProverbFastMultipleType::class, $entity, ["locale" => $request->getLocale()]);

		return $this->render('Proverb/fastMultiple.html.twig', array('form' => $form->createView(), "authorizedURLs" => $this->authorizedURLs));
	}
	
	public function addFastMultipleAction(Request $request, SessionInterface $session, TranslatorInterface $translator)
	{
		$entity = new Proverb();
		
		$form = $this->createForm(ProverbFastMultipleType::class, $entity, ["locale" => $request->getLocale()]);
		
		$form->handleRequest($request);
		$req = $request->request->get($form->getName());

		if(!empty($req["url"]) and filter_var($req["url"], FILTER_VALIDATE_URL))
		{
			$url = $req["url"];
			$url_array = parse_url($url);

			if(!in_array(base64_encode($url_array['host']), $this->authorizedURLs))
				$form->get("url")->addError(new FormError($translator->trans("admin.error.UnknownURL")));
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
				case 'Y3JlYXRpdmVwcm92ZXJicy5jb20=':
					foreach($dom->find('center table tr td center table[CELLPADDING=10] tr td') as $pb) {
						if(!empty($pb->plaintext)) {
							$entityProverb = clone $entity;
							$entityProverb->setText($pb->plaintext);

							$proverbsArray[] = $entityProverb;
						}
					}
				break;
				case 'd3d3LnNwZWNpYWwtZGljdGlvbmFyeS5jb20=':
					foreach($dom->find('.quotes li') as $quote)
					{
						$entityProverb = clone $entity;
						$content = $quote->innertext;
						$content = preg_replace('/<span[^>]*>.*?<\/span>/i', '', $content);
						
						$entityProverb->setText(strip_tags($content));
						
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

			$session->getFlashBag()->add('message', $translator->trans("admin.index.AddedSuccessfully", ["%numberAdded%" => $numberAdded, "%numberDoubloons%" => $numberDoubloons]));
	
			return $this->redirect($this->generateUrl('proverbadmin_index'));
		}
		
		return $this->render('Proverb/fastMultiple.html.twig', array('form' => $form->createView(), "authorizedURLs" => $this->authorizedURLs));
	}

	public function twitterAction(Request $request, SessionInterface $session, TranslatorInterface $translator, $id)
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
		$imageId = $request->request->get('image_id_tweet');

		if(!empty($imageId)) {
			$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($imageId);
			
			$media = $connection->upload('media/upload', array('media' => $request->getUriForPath('/photo/proverb/'.$proverbImage->getImage())));
			$parameters['media_ids'] = implode(',', array($media->media_id_string));
		}

		$statues = $connection->post("statuses/update", $parameters);
		
		if(isset($statues->errors) and !empty($statues->errors))
			$session->getFlashBag()->add('message', $translator->trans("admin.index.SentError"));
		else {
			$proverbImage->addSocialNetwork("Twitter");
			$entityManager->persist($proverbImage);
			$entityManager->flush();
		
			$session->getFlashBag()->add('message', $translator->trans("admin.index.SentSuccessfully"));
			
		}
	
		return $this->redirect($this->generateUrl("proverbadmin_show", array("id" => $id)));
	}

	public function pinterestAction(Request $request, SessionInterface $session, TranslatorInterface $translator, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
		$mail = getenv("PINTEREST_MAIL");
		$pwd = getenv("PINTEREST_PASSWORD");
		$username = getenv("PINTEREST_USERNAME");

		$pinterestBoards = [
			"Proverbes" => "fr",
			"Proverbs" => "en"
		];

		$bot = PinterestBot::create();
		$bot->auth->login($mail, $pwd);
		
		$boards = $bot->boards->forUser($username);
		$i = 0;

		foreach($boards as $board) {
			if($pinterestBoards[$board["name"]] == $entity->getLanguage()->getAbbreviation()) {
				break;
			}
			$i++;
		}

		$imageId = $request->request->get('image_id_pinterest');
		$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($imageId);
		
		if(empty($proverbImage)) {
			$session->getFlashBag()->add('message', $translator->trans("admin.index.YouMustSelectAnImage"));
			return $this->redirect($this->generateUrl("proverbadmin_show", array("id" => $id)));
		}
			
		$bot->pins->create($request->getUriForPath('/photo/proverb/'.$proverbImage->getImage()), $boards[$i]['id'], $request->request->get("pinterest_area"), $this->generateUrl("read", ["id" => $entity->getId(), "slug" => $entity->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));
		
		if(empty($bot->getLastError())) {
			$session->getFlashBag()->add('message', $translator->trans("admin.index.SentSuccessfully"));

			$proverbImage->addSocialNetwork("Pinterest");
			$entityManager->persist($proverbImage);
			$entityManager->flush();
		}
		else
			$session->getFlashBag()->add('message', $bot->getLastError());
	
		return $this->redirect($this->generateUrl("proverbadmin_show", array("id" => $id)));
	}
	
	public function facebookAction(Request $request, TranslatorInterface $translator, $id)
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
			
			$entityManager = $this->getDoctrine()->getManager();
			
			$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($request->request->get("image_id_facebook"));
			
			$data = [
				'caption' => $request->request->get("facebook_area"),
				'url' => $request->getUriForPath('/photo/proverb/'.$proverbImage->getImage())
			];
			
			try {
				$response = $fb->post('/'.$pageId.'/photos', $data, $accessTokenPage);

				$proverbImage->addSocialNetwork("Facebook");
				$entityManager->persist($proverbImage);
				$entityManager->flush();
				
				$session->getFlashBag()->add('message', $translator->trans("admin.index.SentSuccessfully"));
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				$session->getFlashBag()->add('message', $translator->trans("admin.index.SentError"));
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				$session->getFlashBag()->add('message', $translator->trans("admin.index.SentError"));
			}
		}

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
			$data = $imageGeneratorForm->getData();
			$file = $data['image'];
            $fileName = md5(uniqid()).'_'.$file->getClientOriginalName();
			$text = $entity->getText();
			
			$font = realpath(__DIR__."/../../public").DIRECTORY_SEPARATOR.'font'.DIRECTORY_SEPARATOR.'source-serif-pro'.DIRECTORY_SEPARATOR.'SourceSerifPro-Regular.otf';

			if($data["version"] == "v1")
			{
				$image = imagecreatefromstring(file_get_contents($file->getPathname()));
				
				ob_start();
				imagepng($image);
				$png = ob_get_clean();
					
				$image_size = getimagesizefromstring($png);
				

				$widthText = $image_size[0] * 0.9;
				$start_x = $image_size[0] * 0.1;
				$start_y = $image_size[1] * 0.35;

				$copyright_x = $image_size[0] * 0.03;
				$copyright_y = $image_size[1] - $image_size[1] * 0.03;

				if($data['invert_colors'])
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
				$imageGenerator->setFontSize($data['font_size']);
				$imageGenerator->setImage($image);
				
				$text = html_entity_decode($entity->getText(), ENT_QUOTES);
				
				$imageGenerator->setText($text);
				$imageGenerator->setCopyright(["x" => $copyright_x, "y" => $copyright_y, "text" => "proverbius.wakonda.guru"]);

				$imageGenerator->generate($start_x, $start_y, $widthText);

				imagepng($image, "photo/proverb/".$fileName);
				imagedestroy($image);
			}
			else
			{
				$textColor = [0, 0, 0];
				$strokeColor = [255, 255, 255];
				$rectangleColor = [255, 255, 255];
				
				if($data["invert_colors"]) {
					$textColor = [255, 255, 255];
					$strokeColor = [0, 0, 0];
					$rectangleColor = [0, 0, 0];
				}

				$bg = $data['image']->getPathName();
				$image = new PHPImage();
				$image->setDimensionsFromImage($bg);
				$image->draw($bg);
				$image->setAlignHorizontal('center');
				$image->setAlignVertical('center');
				$image->setFont($font);
				$image->setTextColor($textColor);
				$image->setStrokeWidth(1);
				$image->setStrokeColor($strokeColor);
				$gutter = 50;
				$image->rectangle($gutter, $gutter, $image->getWidth() - $gutter * 2, $image->getHeight() - $gutter * 2, $rectangleColor, 0.5);
				$image->textBox("“".html_entity_decode($text)."”", array(
					'width' => $image->getWidth() - $gutter * 2,
					'height' => $image->getHeight() - $gutter * 2,
					'fontSize' => $data["font_size"],
					'x' => $gutter,
					'y' => $gutter
				));

				imagepng($image->getResource(), "photo/proverb/".$fileName);
				imagedestroy($image->getResource());
			}

			$entity->addProverbImage(new ProverbImage($fileName));
			
			$entityManager->persist($entity);
			$entityManager->flush();
			
			$redirect = $this->generateUrl('proverbadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}

        return $this->render('Proverb/show.html.twig', array('entity' => $entity, 'imageGeneratorForm' => $imageGeneratorForm->createView()));
	}
	
	public function removeImageAction(Request $request, $id, $proverbImageId)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$proverbImage = $entityManager->getRepository(ProverbImage::class)->find($proverbImageId);
		
		$fileName = $proverbImage->getImage();
		
		$entity->removeProverbImage($proverbImage);
		
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
	
	private function checkForDoubloon(TranslatorInterface $translator, $entity, $form)
	{
		if($entity->getText() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(Proverb::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("text")->addError(new FormError($translator->trans("admin.index.ThisEntryAlreadyExists")));
		}
	}
}