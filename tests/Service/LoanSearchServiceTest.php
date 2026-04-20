<?php

namespace App\Tests\Service;

use App\Service\LoanSearchService;
use Elastica\Index;
use Elastica\Query;
use Elastica\ResultSet;
use FOS\ElasticaBundle\Index\IndexManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoanSearchServiceTest extends TestCase
{
    public function testGetTopBorrowersReturnsFormattedAggregationAndClampsLimit(): void
    {
        $indexManager = $this->createMock(IndexManager::class);
        $index = $this->createMock(Index::class);
        $logger = $this->createMock(LoggerInterface::class);
        $resultSet = $this->createMock(ResultSet::class);

        $indexManager
            ->method('getIndex')
            ->with('app')
            ->willReturn($index);

        $index
            ->expects($this->once())
            ->method('search')
            ->with($this->callback(function (Query $query): bool {
                $queryArray = $query->toArray();

                return ($queryArray['size'] ?? null) === 0
                    && ($queryArray['aggs']['top_borrowers']['terms']['field'] ?? null) === 'borrower.name.keyword'
                    && ($queryArray['aggs']['top_borrowers']['terms']['size'] ?? null) === 1;
            }))
            ->willReturn($resultSet);

        $resultSet
            ->method('getAggregations')
            ->willReturn([
                'top_borrowers' => [
                    'buckets' => [
                        ['key' => 'Alice', 'doc_count' => 4],
                        ['key' => 'Bob', 'doc_count' => 2],
                    ],
                ],
            ]);

        $service = new LoanSearchService($indexManager, $logger);
        $result = $service->getTopBorrowers(-3);

        $this->assertSame([
            ['borrower' => 'Alice', 'count' => 4],
            ['borrower' => 'Bob', 'count' => 2],
        ], $result);
    }

    public function testGetTopBorrowersReturnsEmptyArrayOnSearchException(): void
    {
        $indexManager = $this->createMock(IndexManager::class);
        $index = $this->createMock(Index::class);
        $logger = $this->createMock(LoggerInterface::class);

        $indexManager
            ->method('getIndex')
            ->with('app')
            ->willReturn($index);

        $index
            ->method('search')
            ->willThrowException(new \RuntimeException('Elasticsearch unavailable'));

        $service = new LoanSearchService($indexManager, $logger);

        $this->assertSame([], $service->getTopBorrowers());
    }
}
