<?php

namespace App\Service;

use FOS\ElasticaBundle\Index\IndexManager; // <-- Nouvel import
use Elastica\Aggregation\Terms;
use Elastica\Query;
use Elastica\Query\MatchPhrasePrefix;

class LoanSearchService
{
    private $indexManager;

    public function __construct(IndexManager $indexManager)
    {
        $this->indexManager = $indexManager;
    }

    public function searchVideoStats(string $searchTerm): array
    {
        // 1. On récupère l'index "app" (défini dans ton yaml)
        $index = $this->indexManager->getIndex('app');

        $query = new Query();
        
        // 2. La recherche
        $match = new MatchPhrasePrefix();
        $match->setFieldQuery('video.title', $searchTerm);
        $query->setQuery($match);

        // 3. L'agrégation
        $agg = new Terms('count_by_video');
        $agg->setField('video.title.keyword'); 
        $agg->setSize(10); 
        $query->addAggregation($agg);

        // 4. On lance la recherche directement sur l'index
        $resultSet = $index->search($query);
        
        return $resultSet->getAggregations()['count_by_video']['buckets'] ?? [];
    }

    public function findLoansByBorrower(string $username): array
    {
        $index = $this->indexManager->getIndex('app');

        $query = new Query();
        
        // Recherche exacte ou partielle sur le nom du client
        // On utilise .keyword si on veut le nom EXACT, ou borrower.name pour du flou
        $match = new Query\MatchPhrasePrefix();
        $match->setFieldQuery('borrower.name', $username);
        $query->setQuery($match);

        // On veut les résultats les plus récents en premier
        $query->setSort(['startDate' => ['order' => 'desc']]);
        
        // On demande 50 résultats par défaut
        $query->setSize(50);

        $resultSet = $index->search($query);
        
        // Ici, on transforme les résultats en tableaux exploitables
        return array_map(function($hit) {
            return $hit->getSource();
        }, $resultSet->getResults());
    }
}