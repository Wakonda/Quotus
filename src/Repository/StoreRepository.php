<?php

namespace App\Repository;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\Store;

/**
 * Store repository
 */
class StoreRepository extends ServiceEntityRepository implements iRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Store::class);
    }

	public function getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $count = false)
	{
		$qb = $this->createQueryBuilder("pa");

		$aColumns = array( 'pa.id', 'pa.title', 'pa.id');

		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->where('pa.title LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pa) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}

	public function checkForDoubloon($entity)
	{
		$qb = $this->createQueryBuilder("pa");

		$qb->select("COUNT(pa) AS count")
		   ->where("pa.title = :title")
		   ->setParameter('title', $entity->getTitle());

		if($entity->getId() != null)
		{
			$qb->andWhere("pa.id != :id")
			   ->setParameter("id", $entity->getId());
		}
		
		return $qb->getQuery()->getSingleScalarResult();
	}

	public function countEntities($query)
	{
		$qb = $this->createQueryBuilder('o');
		
		$qb	->select('COUNT(o)');
		
		if(!empty($query))
			$qb->where("o.tag IN (:tags)")
		       ->setParameter("tags", explode(",", $query));

		return $qb->getQuery()->getSingleScalarResult();
	}

	public function getProducts($nbrMessageParPage, $page, $query)
	{	
		$firstMessageDisplaying = ($page - 1) * $nbrMessageParPage;
		$qb = $this->createQueryBuilder('o');

		if(!empty($query))
			$qb->where("o.tag IN (:tags)")
		       ->setParameter("tags", explode(",", $query));

		$qb
			->orderBy('o.id', 'DESC')
			->setFirstResult($firstMessageDisplaying)
			->setMaxResults($nbrMessageParPage);

		return $qb->getQuery()->getResult();
	}
}