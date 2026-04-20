<?php

namespace App\Service;

use FOS\ElasticaBundle\Index\IndexManager;
use Elastica\Aggregation\Terms;
use Elastica\Aggregation\DateHistogram;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\FunctionScore;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\MatchQuery;
use Elastica\Query\Range;
use Psr\Log\LoggerInterface;
use App\State\LoanElasticProvider;
use Elastica\ResultSet;

class LoanSearchService
{
    private Index $index;
    private LoggerInterface $logger;

    public function __construct(IndexManager $indexManager, LoggerInterface $logger)
    {
        $this->index = $indexManager->getIndex('app');
        $this->logger = $logger;
    }

    public function loansApi(int $from): ResultSet
    {
        $query = new Query();
        $query->setQuery(new Query\MatchAll());
        $query->setSize(LoanElasticProvider::ITEMS_PER_PAGE);
        $query->setFrom($from);
        
        $query->setSort(['startDate' => ['order' => 'desc']]);


        return $this->index->search($query);
    }

    public function searchVideoStats(string $searchTerm): array
    {
        $this->logger->info('Searching video stats for term: {searchTerm}', ['searchTerm' => $searchTerm]);
        
        $query = new Query();
        
        // 2. La recherche
        $matchQuery = new MatchQuery();
        $matchQuery->setFieldQuery('video.title', $searchTerm);
        $matchQuery->setFieldParam('video.title', 'fuzziness', 'AUTO');
        $query->setQuery($matchQuery);

        // 3. L'agrégation
        $agg = new Terms('count_by_video');
        $agg->setField('video.title.keyword'); 
        $query->setSize(0);
        $query->addAggregation($agg);

        try {
            $resultSet = $this->index->search($query);
            $this->logger->info('Video stats search completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Error searching video stats: {error}', ['error' => $e->getMessage()]);
            return []; 
        }
        
        return $resultSet->getAggregations()['count_by_video']['buckets'] ?? [];
    }

    public function findLoansByBorrower(string $username): array
    {
        $this->logger->info('Finding loans for borrower: {username}', ['username' => $username]);
        
        $query = new Query();
        
        $matchQuery = new MatchQuery();
        $matchQuery->setFieldQuery('borrower.name', $username);
        $matchQuery->setFieldParam('borrower.name', 'fuzziness', 'AUTO');
        $query->setQuery($matchQuery);

        // On veut les résultats les plus récents en premier
        $query->setSort(['startDate' => ['order' => 'desc']]);
        
        // On demande 50 résultats par défaut
        $query->setSize(50);

        try {
            $resultSet = $this->index->search($query);
            $this->logger->info('Borrower search completed: {count} results found', ['count' => count($resultSet->getResults())]);
        } catch (\Exception $e) {
            $this->logger->error('Error finding loans by borrower: {error}', ['error' => $e->getMessage()]);
            return []; 
        }
                
        // Ici, on transforme les résultats en tableaux exploitables
        return array_map(function($hit) {
            return $hit->getSource();
        }, $resultSet->getResults());
    }

    public function getLoansCountByMonth(): array
    {
        $this->logger->info('Getting loans count by month for last 12 months');
        
        $query = new Query();
        
        // 1. Calculer la date d'il y a 12 mois
        $twelveMonthsAgo = new \DateTime('-12 months');
        
        // 2. Filtre par plage de dates
        $rangeQuery = new Query\Range();
        $rangeQuery->addField('startDate', [
            'gte' => $twelveMonthsAgo->format('Y-m-d')
        ]);
        
        $query->setQuery($rangeQuery);
        
        // 3. Agrégation : 3 arguments requis (Nom, Champ, Intervalle)
        $agg = new DateHistogram('loans_by_month', 'startDate', 'month');
        
        $agg->setParam('min_doc_count', 0);
        
        $query->addAggregation($agg);
        $query->setSize(0); 

        try {
            $resultSet = $this->index->search($query);
            $aggregations = $resultSet->getAggregations();
            $this->logger->info('Monthly loans count aggregation completed');
        } catch (\Exception $e) {
            $this->logger->error('Error getting loans count by month: {error}', ['error' => $e->getMessage()]);
            return [];
        }
        
        // 4. Transformation des résultats
        $result = [];
        $monthNames = [
            'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
        ];
        
        if (isset($aggregations['loans_by_month']['buckets'])) {
            foreach ($aggregations['loans_by_month']['buckets'] as $bucket) {
                // Extraction sécurisée de la date via le timestamp (en ms chez ES)
                $timestamp = $bucket['key'] / 1000; 
                $date = new \DateTime();
                $date->setTimestamp((int)$timestamp);
                
                $monthIndex = (int)$date->format('n') - 1;
                $year = $date->format('Y');
                
                $formatKey = $monthNames[$monthIndex] . ' ' . $year;
                $result[$formatKey] = $bucket['doc_count'] ?? 0;
            }
        }
        
        return $result;
    }

    public function findLoansByDateRange(\DateTime $startDate, ?\DateTime $endDate = null, bool $ongoing = false): array
    {
        $query = new Query();
        
        // 1. Recherche de base (Plage de dates)
        $rangeQuery = $this->createDatesRangeQuery($startDate, $endDate);

        // 2. Préparation du critère "En cours" (endDate n'existe pas)
        $isOngoingCriteria = new BoolQuery();
        $isOngoingCriteria->addMustNot(new Exists('endDate'));

        if ($ongoing) {
            $boolQuery = new BoolQuery();
            $boolQuery->addMust($rangeQuery);
            $boolQuery->addMust($isOngoingCriteria);
            
            $query->setQuery($boolQuery);
            $query->setSort(['startDate' => ['order' => 'desc']]);
        } else {
            $functionScore = new FunctionScore();
            $functionScore->setQuery($rangeQuery); 
            
            $functionScore->addWeightFunction(10.0, $isOngoingCriteria);
            
            // On multiplie le score initial par 10 pour les gagnants
            $functionScore->setBoostMode(FunctionScore::BOOST_MODE_MULTIPLY);
            
            $query->setQuery($functionScore);
            
            $query->setSort([
                '_score'    => ['order' => 'desc'],
                'startDate' => ['order' => 'desc']
            ]);
        }

        $query->setSize(100);

        try {
            $resultSet = $this->index->search($query);
            return array_map(fn($hit) => $hit->getSource(), $resultSet->getResults());
        } catch (\Exception $e) {
            $this->logger->error('Error: ' . $e->getMessage());
            return []; 
        }
    }

    public function getLoansCountByDateRange(\DateTime $startDate, ?\DateTime $endDate = null): int
    {
        $this->logger->info('Getting loans count by date range: {startDate} to {endDate}', [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate ? $endDate->format('Y-m-d') : 'null'
        ]);
        
        $query = new Query();
        
        // Recherche par plage de dates
        $rangeQuery = $this->createDatesRangeQuery($startDate, $endDate);
        
        $query->setQuery($rangeQuery);
        $query->setSize(0);

        try {
            $resultSet = $this->index->search($query);
            $count = $resultSet->getTotalHits();
            $this->logger->info('Date range count query completed: {count} loans found', ['count' => $count]);
            return $count;
        } catch (\Exception $e) {
            $this->logger->error('Error getting loans count by date range: {error}', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function getTop10Videos(): array
    {
        $this->logger->info('Getting top 10 videos by loan count');
        
        $query = new Query();
        
        $agg = new Terms('top_videos');
        $agg->setField('video.title.keyword');
        $agg->setParam('order', ['_count' => 'desc']);
        $agg->setSize(10);
        
        $query->addAggregation($agg);
        $query->setSize(0);
        
        try {
            $resultSet = $this->index->search($query);
            $this->logger->info('Top 10 videos aggregation completed');
            return $resultSet->getAggregations()['top_videos']['buckets'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('Error getting top 10 videos: {error}', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getTopBorrowers(int $limit = 10): array
    {
        $limit = max(1, $limit);
        $this->logger->info('Getting top borrowers by loan count', ['limit' => $limit]);

        $query = new Query();

        $agg = new Terms('top_borrowers');
        $agg->setField('borrower.name.keyword');
        $agg->setParam('order', ['_count' => 'desc']);
        $agg->setSize($limit);

        $query->addAggregation($agg);
        $query->setSize(0);

        try {
            $resultSet = $this->index->search($query);
            $this->logger->info('Top borrowers aggregation completed');

            $buckets = $resultSet->getAggregations()['top_borrowers']['buckets'] ?? [];

            return array_map(static fn (array $bucket): array => [
                'borrower' => $bucket['key'] ?? '',
                'count' => $bucket['doc_count'] ?? 0,
            ], $buckets);
        } catch (\Exception $e) {
            $this->logger->error('Error getting top borrowers: {error}', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function createDatesRangeQuery(\DateTime $startDate, ?\DateTime $endDate = null): Range {
        $rangeQuery = new Range();
        
        if ($endDate) {
            $rangeQuery->addField('startDate', [
                'gte' => $startDate->format('Y-m-d'),
                'lte' => $endDate->format('Y-m-d')
            ]);
        } else {
            $rangeQuery->addField('startDate', [
                'gte' => $startDate->format('Y-m-d')
            ]);
        }

        return $rangeQuery;
    }
}