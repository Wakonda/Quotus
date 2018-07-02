<?php

namespace App\Repository;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use App\Entity\Proverb;

/**
 * Proverb repository
 */
class ProverbRepository extends ServiceEntityRepository implements iRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Proverb::class);
    }

	public function findIndexSearch($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $datasObject, $count = false)
	{
		$aColumns = array('pa.text', 'co.title');
		$qb = $this->createQueryBuilder("pa");

		$qb->leftjoin("pa.country", "co");

		if(!empty($datasObject->text))
		{
			$keywords = explode(",", $datasObject->text);
			$i = 0;
			foreach($keywords as $keyword)
			{
				$keyword = "%".$keyword."%";
				$qb->andWhere("(pa.text LIKE :keyword".$i)
				   ->orWhere("pa.text LIKE :keywordEntities".$i.")")
			       ->setParameter("keyword".$i, $keyword)
			       ->setParameter("keywordEntities".$i, htmlentities($keyword));
				$i++;
			}
		}

		if(!empty($datasObject->country))
		{
			$qb->andWhere("co.id = :country")
			   ->setParameter("country", $datasObject->country);
		}

		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if($count)
		{
			$qb->select("COUNT(pa) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}

    public function findProverbByCountry($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $count = false)
    {
		$qb = $this->createQueryBuilder("pa");

		$aColumns = array( 'co.title', 'COUNT(pa.id)');
		
		$qb->select("co.id AS country_id, co.title AS country_title, COUNT(pa.id) AS number_proverbs_by_country, co.flag AS flag, co.slug AS country_slug")
		   ->leftjoin("pa.country", "co")
		   ->groupBy("co.id, co.title, co.flag")
		   ;
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('co.title LIKE "'.$search.'"');
		}
		if($count)
		{
			$params = [];
			
			foreach($qb->getParameters()->getIterator() as $i => $item)
				$params[] = $item->getValue();

			$res = $this->_em->getConnection()->executeQuery("SELECT COUNT(*) AS count FROM (".$qb->getQuery()->getSql().") AS SQ", $params);

			return $res->fetch()["count"];
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
    }

    public function findProverbByLetter($letter, $count = false)
    {
		$qb = $this->createQueryBuilder("pa");
		
		$qb->select("COUNT(pa.id) AS number_letter")
		   ->where("SUBSTRING(pa.text, 1, 1) = :letter")
		   ->setParameter("letter", $letter);

		return $qb->getQuery()->getOneOrNullResult();
    }

	public function getRandomProverb()
	{
		$qb = $this->createQueryBuilder("pt");

		$qb->select("COUNT(pt) AS countRow");
		
		$max = $qb->getQuery()->getSingleScalarResult() - 1;
		$offset = rand(0, $max);

		$qb = $this->createQueryBuilder("pt");

		$qb->setFirstResult($offset)
		   ->setMaxResults(1);

		return $qb->getQuery()->getOneOrNullResult();
	}

	public function getLastEntries()
	{
		$qb = $this->createQueryBuilder("pt");

		$qb->setMaxResults(7)
		   ->orderBy("pt.id", "DESC");
		   
		return $qb->getQuery()->getResult();
	}
	
	public function getStat()
	{
		$qbProverb = $this->createQueryBuilder("pt");

		$qbProverb->select("COUNT(pt)");
		
		return $qbProverb->getQuery()->getSingleScalarResult();
	}

	public function getDatatablesForIndex($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array( 'pf.id', 'pf.text', 'pf.id');

		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);
		
		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->where('pf.text LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}

	public function checkForDoubloon($entity)
	{
		$qb = $this->createQueryBuilder("pf");

		$qb->select("COUNT(pf) AS count")
		   ->where("pf.slug = :slug")
		   ->setParameter('slug', $entity->getSlug());

		if($entity->getId() != null)
		{
			$qb->andWhere("pf.id != :id")
			   ->setParameter("id", $entity->getId());
		}

		return $qb->getQuery()->getSingleScalarResult();
	}

	public function browsingProverbShow($ProverbId)
	{
		// Previous
		$subqueryPrevious = 'p.id = (SELECT MAX(p2.id) FROM App\Entity\Proverb p2 WHERE p2.id < '.$ProverbId.')';
		$qb_previous = $this->createQueryBuilder('p');
		
		$qb_previous->select("p.id, p.text, p.slug AS slug")
		   ->andWhere($subqueryPrevious);
		   
		// Next
		$subqueryNext = 'p.id = (SELECT MIN(p2.id) FROM App\Entity\Proverb p2 WHERE p2.id > '.$ProverbId.')';
		$qb_next = $this->createQueryBuilder('p');
		
		$qb_next->select("p.id, p.text, p.slug AS slug")
		   ->andWhere($subqueryNext);
		
		$res = array(
			"previous" => $qb_previous->getQuery()->getOneOrNullResult(),
			"next" => $qb_next->getQuery()->getOneOrNullResult()
		);

		return $res;
	}

	public function getProverbByCountryDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $countryId, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array('pf.text', 'pf.id');
		
		$qb->select("pf.text AS proverb_text, pf.id AS proverb_id, pf.slug AS proverb_slug")
		   ->innerjoin("pf.country", "co")
		   ->where("co.id = :id")
		   ->setParameter("id", $countryId);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.text LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}

	public function getProverbByLetterDatatables($iDisplayStart, $iDisplayLength, $sortByColumn, $sortDirColumn, $sSearch, $letter, $count = false)
	{
		$qb = $this->createQueryBuilder("pf");

		$aColumns = array('pf.text', 'pf.id');
		
		$qb->select("pf.text AS proverb_text, pf.id AS proverb_id, pf.slug AS proverb_slug")
		   ->where("SUBSTRING(pf.text, 1, 1) = :letter")
		   ->setParameter("letter", $letter);
		
		if(!empty($sortDirColumn))
		   $qb->orderBy($aColumns[$sortByColumn[0]], $sortDirColumn[0]);

		if(!empty($sSearch))
		{
			$search = "%".$sSearch."%";
			$qb->andWhere('pf.text LIKE :search')
			   ->setParameter('search', $search);
		}
		if($count)
		{
			$qb->select("COUNT(pf) AS count");
			return $qb->getQuery()->getSingleScalarResult();
		}
		else
			$qb->setFirstResult($iDisplayStart)->setMaxResults($iDisplayLength);

		return $qb->getQuery()->getResult();
	}
}