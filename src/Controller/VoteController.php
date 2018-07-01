<?php

namespace App\Controller;

use App\Entity\Vote;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class VoteController extends Controller
{
	public function voteAction(Request $request, TokenStorageInterface $tokenStorage, $id)
	{
		$vote = $request->query->get('vote');
		$entityManager = $this->getDoctrine()->getManager();
		
		$state = "";
		
		if(!empty($vote))
		{
			$user = $tokenStorage->getToken()->getUser();
			
			if(is_object($user))
			{
				$vote = ($vote == "up") ? 1 : -1;

				$entity = new Vote();
				
				$entity->setVote($vote);
				$entity->setProverb($entityManager->getRepository(Proverb::class)->find($id));
				
				
				$userDb = $entityManager->getRepository(User::class)->findByUsernameOrEmail($user->getUsername());
				$entity->setUser($userDb);
			
				$numberOfDoubloons = $entityManager->getRepository(Vote::class)->checkIfUserAlreadyVote($id, $userDb->getId());
				
				if($numberOfDoubloons >= 1)
					$state = "Vous avez déjà voté pour ce proverbe";
				else
				{
					$entityManager->persist($entity);
					$entityManager->flush();
				}
			}
			else
				$state = "Vous devez être connecté pour pouvoir voter !";
		}

		$up_values = $entityManager->getRepository(Vote::class)->countVoteByProverb($id, 1);
		$down_values = $entityManager->getRepository(Vote::class)->countVoteByProverb($id, -1);
		$total = $up_values + $down_values;
		$value = ($total == 0) ? 50 : round(((100 * $up_values) / $total), 1);

		$response = new Response(json_encode(array("up" => $up_values, "down" => $down_values, "value" => $value, "alreadyVoted" => $state)));
		$response->headers->set('Content-Type', 'application/json');

		return $response;
	}
}