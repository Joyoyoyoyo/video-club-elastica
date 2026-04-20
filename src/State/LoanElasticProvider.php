<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\TraversablePaginator; // <--- AJOUTÉ
use App\Entity\Loan;   // <--- AJOUTÉ
use App\Entity\User;   // <--- AJOUTÉ
use App\Entity\Video;  // <--- AJOUTÉ
use App\Service\LoanSearchService;

class LoanElasticProvider implements ProviderInterface
{
    public const ITEMS_PER_PAGE = 50;

    public function __construct(
        private LoanSearchService $loanSearchService,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof GetCollection) {
            return null; 
        }

        $currentPage = (int) ($context['filters']['page'] ?? 1);
        // Utilisation de la constante pour éviter l'erreur de variable indéfinie
        $from = ($currentPage - 1) * self::ITEMS_PER_PAGE;

        // On renomme en $resultSet pour être cohérent avec la suite
        $resultSet = $this->loanSearchService->loansApi($from);

        $loans = [];
        // On boucle sur le resultSet
        foreach ($resultSet->getResults() as $result) {
            $data = $result->getData();
            
            $loan = new Loan();
            // Très important pour API Platform : l'ID du document
            $loan->setId($result->getId()); 
            
            if (isset($data['startDate'])) {
                $loan->setStartDate(new \DateTime($data['startDate']));
            }
            if (isset($data['endDate'])) {
                $loan->setEndDate(new \DateTime($data['endDate']));
            }

            if (isset($data['borrower'])) {
                $borrower = new User();
                $borrower->setName($data['borrower']['name'] ?? null);
                $loan->setBorrower($borrower);
            }

            if (isset($data['video'])) {
                $video = new Video();
                $video->setTitle($data['video']['title'] ?? null);
                $loan->setVideo($video);
            }

            $loans[] = $loan;
        }

        return new TraversablePaginator(
            new \ArrayIterator($loans),
            (float) $currentPage,
            (float) self::ITEMS_PER_PAGE,
            (float) $resultSet->getTotalHits() // Récupéré depuis l'objet Elastica
        );
    }
}