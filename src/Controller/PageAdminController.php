<?php

namespace App\Controller;

use App\Entity\Page;
use App\Form\Type\PageType;
use App\Service\GenericFunction;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class PageAdminController extends Controller
{
	public function indexAction(Request $request)
	{
		return $this->render('Page/index.html.twig');
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
		$entities = $entityManager->getRepository(Page::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch);
		$iTotal = $entityManager->getRepository(Page::class)->getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, true);

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
			
			$show = $this->generateUrl('pageadmin_show', array('id' => $entity->getId()));
			$edit = $this->generateUrl('pageadmin_edit', array('id' => $entity->getId()));
			
			$row[] = '<a href="'.$show.'" alt="Show">Lire</a> - <a href="'.$edit.'" alt="Edit">Modifier</a>';

			$output['aaData'][] = $row;
		}

		$response = new Response(json_encode($output));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}

    public function newAction(Request $request)
    {
		$entity = new Page();
        $form = $this->genericCreateForm($entity);

		return $this->render('Page/new.html.twig', array('form' => $form->createView()));
    }
	
	public function createAction(Request $request)
	{
		$entity = new Page();
        $form = $this->genericCreateForm($entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($entity, $form);
		if($entity->getPhoto() == null)
			$form->get("photo")->addError(new FormError('Ce champ ne peut pas être vide'));

		if($form->isValid())
		{
			$entityManager = $this->getDoctrine()->getManager();
			$gf = new GenericFunction();
			$image = $gf->getUniqCleanNameForFile($entity->getPhoto());
			$entity->getPhoto()->move("photo/page/", $image);
			$entity->setPhoto($image);
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('pageadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
		
		return $this->render('Page/new.html.twig', array('form' => $form->createView()));
	}
	
	public function showAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Page::class)->find($id);
	
		return $this->render('Page/show.html.twig', array('entity' => $entity));
	}
	
	public function editAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Page::class)->find($id);
		$form = $this->genericCreateForm($entity);
	
		return $this->render('Page/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	public function updateAction(Request $request, $id)
	{
		$entityManager = $this->getDoctrine()->getManager();
		$entity = $entityManager->getRepository(Page::class)->find($id);
		$currentImage = $entity->getPhoto();
		$form = $this->genericCreateForm($entity);
		$form->handleRequest($request);
		
		$this->checkForDoubloon($entity, $form);
		
		if($form->isValid())
		{
			if(!is_null($entity->getPhoto()))
			{
				$gf = new GenericFunction();
				$image = $gf->getUniqCleanNameForFile($entity->getPhoto());
				$entity->getPhoto()->move("photo/page/", $image);
			}
			else
				$image = $currentImage;

			$entityManager = $this->getDoctrine()->getManager();
			$entity->setPhoto($image);
			$entityManager->persist($entity);
			$entityManager->flush();

			$redirect = $this->generateUrl('pageadmin_show', array('id' => $entity->getId()));

			return $this->redirect($redirect);
		}
	
		return $this->render('Page/edit.html.twig', array('form' => $form->createView(), 'entity' => $entity));
	}

	public function uploadImageMCEAction(Request $request)
	{
		$file = $request->files->get('image');
		$file->move('photo/page', $file->getClientOriginalName());
		
		$path = $request->getBaseUrl()."/photo/page/".$file->getClientOriginalName();
		
		return new Response(sprintf("<script>top.$('.mce-btn.mce-open').parent().find('.mce-textbox').val('%s');</script>", $path));
	}
	
	private function genericCreateForm($entity)
	{
		return $this->createForm(PageType::class, $entity);
	}
	
	private function checkForDoubloon($entity, $form)
	{
		if($entity->getTitle() != null)
		{
			$entityManager = $this->getDoctrine()->getManager();
			$checkForDoubloon = $entityManager->getRepository(Page::class)->checkForDoubloon($entity);

			if($checkForDoubloon > 0)
				$form->get("title")->addError(new FormError('Cette entrée existe déjà !'));
		}
	}
}