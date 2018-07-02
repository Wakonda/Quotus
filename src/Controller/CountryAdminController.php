<?php

namespace App\Controller;

use App\Entity\Country;
use App\Form\Type\CountryType;
use App\Service\GenericFunction;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class CountryAdminController extends Controller
{
	public function indexAction(Request $request)
	{
		return $this->render('Country/index.html.twig');
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
		$entities = $entityManager->getRepository(Country::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $entityManager->getRepository(Country::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			$row[] = $entity->getTitle();

			$show = $this->generateUrl('countryadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('countryadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">Lire</a> - <a href="'.$edit.'" alt="Edit">Modifier</a>';

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

    public function newAction(Request $request)
    {
		$entity = new Country();
        $form = $this->createForm(CountryType::class, $entity);

		return $this->render('Country/new.html.twig', array('form' => $form->createView()));
    }
	
	public function createAction(Request $request)
	{
		$entity = new Country();
        $form = $this->createForm(CountryType::class, $entity);
		$form->handleRequest($request);
		$this->checkForDoubloon($entity, $form);

		if($entity->getFlag() == null)
			$form->get("flag")->addError(new FormError('Ce champ ne peut pas être vide'));
		
		if($form->isValid())
		{
			$gf = new GenericFunction();
			$image = $gf->getUniqCleanNameForFile($entity->getFlag());
			$entity->getFlag()->move("photo/country/", $image);
			$entity->setFlag($image);

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('countryadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Country/new.html.twig', array('form' => $form->createView()));
	}
	
	public function showAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Country::class)->find($id);
	
		return $this->render('Country/show.html.twig', array('entity' => $entity));
	}
	
	public function editAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Country::class)->find($id);
		$form = $this->createForm(CountryType::class, $entity);
	
		return $this->render('Country/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	public function updateAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Country::class)->find($id);
		$currentImage = $entity->getFlag();
		$form = $this->createForm(CountryType::class, $entity);
		$form->handleRequest($request);
		$this->checkForDoubloon($entity, $form);
		
		if($form->isValid())
		{
			if(!is_null($entity->getFlag()))
			{
				$gf = new GenericFunction();
				$image = $gf->getUniqCleanNameForFile($entity->getFlag());
				$entity->getFlag()->move("photo/country/", $image);
			}
			else
				$image = $currentImage;

			$entity->setFlag($image);
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('countryadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Country/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	private function checkForDoubloon($entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(Country::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError('Cette entrée existe déjà !'));
		}
	}
}