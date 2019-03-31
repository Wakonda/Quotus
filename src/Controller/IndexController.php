<?php

namespace App\Controller;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Translation\TranslatorInterface;

use App\Form\Type\IndexSearchType;
use App\Service\Captcha;
use App\Service\Gravatar;
use App\Service\Pagination;

use App\Entity\Proverb;
use App\Entity\ProverbImage;
use App\Entity\Country;
use App\Entity\Page;
use App\Entity\Store;
use App\Entity\Language;
use App\Entity\Biography;

use Spipu\Html2Pdf\Html2Pdf;
use MatthiasMullie\Minify;

class IndexController extends Controller
{
    public function indexAction(Request $request)
    {
		$form = $this->createFormIndexSearch($request->getLocale(), null);
		$entityManager = $this->getDoctrine()->getManager();
		$random = $entityManager->getRepository(Proverb::class)->getRandomProverb($request->getLocale());

        return $this->render('Index/index.html.twig', array('form' => $form->createView(), 'random' => $random));
    }
	
	public function getToken($redirectURL)
	{
			if(!isset($_GET['code']))
			{
				header("Location: ".$loginUrl);
				die;
			}

			return $_GET['code'];
	}

	public function changeLanguageAction(Request $request, $locale)
	{
		$request->getSession()->set('_locale', $locale);
		return $this->redirect($this->generateUrl('index'));
	}
	
	public function indexSearchAction(Request $request, TranslatorInterface $translator)
	{
		$search = $request->request->get("index_search");
		$entityManager = $this->getDoctrine()->getManager();
		$search['country'] = (empty($search['country'])) ? null : $search['country'];
		
		unset($search["_token"]);
		
		$criteria = $search;
		$criteria['country'] = (empty($search['country'])) ? null : $entityManager->getRepository(Country::class)->find($search['country'])->getTitle();
		$criteria = array_filter(array_values($criteria));
		$criteria = empty($criteria) ? $translator->trans("search.result.None") : $criteria;

		return $this->render('Index/resultIndexSearch.html.twig', array('search' => base64_encode(json_encode($search)), 'criteria' => $criteria));
	}

	public function indexSearchDatatablesAction(Request $request, $search)
	{
		$iDisplayStart = $request->query->get('iDisplayStart');
		$iDisplayLength = $request->query->get('iDisplayLength');

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
		$sSearch = json_decode(base64_decode($search));

		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Proverb::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Proverb::class)->findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();

			$show = $this->generateUrl('read', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
			$country = $entity->getCountry();
			
			$row[] = '<a href="'.$show.'" alt="Show">'.$entity->getText().'</a>';
			$row[] = '<img src="'.$request->getBaseUrl().'/photo/country/'.$country->getFlag().'" class="flag">';

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}
	
	public function readAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		
		$browsingProverbs = $entityManager->getRepository(Proverb::class)->browsingProverbShow($id);

		return $this->render('Index/read.html.twig', array('entity' => $entity, 'browsingProverbs' => $browsingProverbs));
	}
	
	public function byImagesAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$query = $entityManager->getRepository(ProverbImage::class)->getPaginator();
		
		$paginator  = $this->get('knp_paginator');
		$pagination = $paginator->paginate(
			$query, /* query NOT result */
			$request->query->getInt('page', 1), /*page number*/
			10 /*limit per page*/
		);
		
		$pagination->setCustomParameters(['align' => 'center']);
		
		return $this->render('Index/byimage.html.twig', ['pagination' => $pagination]);
	}
	
	public function downloadImageProverbAction($fileName)
	{
		$response = new BinaryFileResponse('photo/proverb/'.$fileName);
		$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
		return $response;
	}

	public function readPDFAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Proverb::class)->find($id);
		$content = $this->renderView('Index/pdf.html.twig', array('entity' => $entity));

		$html2pdf = new Html2Pdf('P','A4','fr');
		$html2pdf->WriteHTML($content);
;
		$file = $html2pdf->Output('proverb.pdf');
		$response = new Response($file);
		$response->headers->set('Content-Type', 'application/pdf');

		return $response;
	}

	public function lastAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$entities = $entityManager->getRepository(Proverb::class)->getLastEntries($request->getLocale());

		return $this->render('Index/last.html.twig', array('entities' => $entities));
    }
	
	public function statAction(Request $request)
    {
		$entityManager = $this->getDoctrine()->getManager();
		$statistics = $entityManager->getRepository(Proverb::class)->getStat($request->getLocale());

		return $this->render('Index/stat.html.twig', array('statistics' => $statistics));
    }

	// COUNTRY
	public function countryAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Country::class)->find($id);

		return $this->render('Index/country.html.twig', array('entity' => $entity));
	}

	public function countryDatatablesAction(Request $request, $countryId)
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
		$entities = $entityManager->getRepository(Proverb::class)->getProverbByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId);
		$iTotal = $entityManager->getRepository(Proverb::class)->getProverbByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity["proverb_text"];
			$show = $this->generateUrl('read', array('id' => $entity["proverb_id"], 'slug' => $entity["proverb_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">Lire</a>';

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	// BY COUNTRIES
	public function byCountriesAction(Request $request)
    {
        return $this->render('Index/bycountry.html.twig');
    }
	
	public function byCountriesDatatablesAction(Request $request)
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
		$entities = $entityManager->getRepository(Proverb::class)->findProverbByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale());
		$iTotal = $entityManager->getRepository(Proverb::class)->findProverbByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $request->getLocale(), true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);

		foreach($entities as $entity)
		{
			if(!empty($entity['country_id']))
			{
				$row = array();

				$show = $this->generateUrl('country', array('id' => $entity['country_id'], 'slug' => $entity['country_slug']));
				$row[] = '<a href="'.$show.'" alt="Show"><img src="'.$request->getBaseUrl().'/photo/country/'.$entity['flag'].'" class="flag" /> '.$entity['country_title'].'</a>';

				$row[] = '<span class="badge badge-secondary">'.$entity['number_proverbs_by_country'].'</span>';

				$output['aaData'][] = $row;
			}
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}
	
	// BY LETTER
	public function letterAction(Request $request, $letter)
	{
		return $this->render('Index/letter.html.twig', array('letter' => $letter));
	}

	public function letterDatatablesAction(Request $request, $letter)
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
		$entities = $entityManager->getRepository(Proverb::class)->getProverbByLetterDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $letter);
		$iTotal = $entityManager->getRepository(Proverb::class)->getProverbByLetterDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $letter, true);

		$output = array(
			"sEcho" => $request->query->get('sEcho'),
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iTotal,
			"aaData" => array()
		);
		
		foreach($entities as $entity)
		{
			$row = array();
			$row[] = $entity["proverb_text"];
			$show = $this->generateUrl('read', array('id' => $entity["proverb_id"], 'slug' => $entity["proverb_slug"]));
			$row[] = '<a href="'.$show.'" alt="Show">Lire</a>';

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	public function byLettersAction(Request $request)
    {
        return $this->render('Index/byletter.html.twig');
    }

	public function byLettersDatatablesAction(Request $request)
	{
		$results = [];
		$entityManager = $this->getDoctrine()->getManager();
		
		foreach(range('A', 'Z') as $letter)
		{
			$subArray = [];
			
			$subArray["letter"] = $letter;
			
			$resQuery = $entityManager->getRepository(Proverb::class)->findProverbByLetter($letter, $request->getLocale());
			$subArray["link"] = $resQuery["number_letter"];
			$results[] = $subArray;
		}
		
		return $this->render('Index/byletterDatatable.html.twig', array('results' => $results));
	}

	public function reloadCaptchaAction(Request $request)
	{
		$captcha = new Captcha($request->getSession());

		$wordOrNumberRand = rand(1, 2);
		$length = rand(3, 7);

		if($wordOrNumberRand == 1)
			$word = $captcha->wordRandom($length);
		else
			$word = $captcha->numberRandom($length);

		$response = new Response(json_encode(array("new_captcha" => $captcha->generate($word))));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}

	public function reloadGravatarAction(Request $request)
	{
		$gr = new Gravatar();

		$response = new Response(json_encode(array("new_gravatar" => $gr->getURLGravatar())));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}

	public function pageAction(Request $request, $name)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$language = $entityManager->getRepository(Language::class)->findOneBy(['abbreviation' => $request->getLocale()]);
		$entity = $entityManager->getRepository(Page::class)->findOneBy(["internationalName" => $name, "language" => $language]);
		
		return $this->render('Index/page.html.twig', array("entity" => $entity));
	}
	
    public function storeAction(Request $request, Pagination $pagination, $page)
    {
		$em = $this->getDoctrine()->getManager();

		$query = $request->request->get("query", null);
		$page = (empty(intval($page))) ? 1 : $page;
		$nbMessageByPage = 12;
		
		$entities = $em->getRepository(Store::class)->getProducts($nbMessageByPage, $page, $query, $request->getLocale());
		$totalEntities = $em->getRepository(Store::class)->getProducts(0, 0, $query, $request->getLocale(), true);
		
		$links = $pagination->setPagination(['url' => 'store'], $page, $totalEntities, $nbMessageByPage);

		return $this->render('Index/store.html.twig', array(
			'entities' => $entities,
			'page' => $page,
			'query' => $query,
			'links' => $links
		));
    }

	public function readStoreAction($id)
	{
		$em = $this->getDoctrine()->getManager();
		$entity = $em->getRepository(Store::class)->find($id);
		
		return $this->render('Index/readStore.html.twig', [
			'entity' => $entity
		]);
	}
	
	public function generateWidgetAction()
	{
		return $this->render('Index/generate_widget.html.twig');
	}
	
	// AUTHOR
	public function authorAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Biography::class)->find($id);
		$stores = $entityManager->getRepository(Store::class)->findBy(["biography" => $entity]);

		return $this->render('Index/author.html.twig', array('entity' => $entity, "stores" => $stores));
	}
	
	public function widgetAction(Request $request)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$proverb = $entityManager->getRepository(Proverb::class)->getRandomProverb($request->getLocale());

		return $this->render('Index/Widget/randomProverbWidget.html.twig', ['proverb' => $proverb]);
	}

	private function createFormIndexSearch($locale, $entity)
	{
		return $this->createForm(IndexSearchType::class, null, ["locale" => $locale]);
	}
}