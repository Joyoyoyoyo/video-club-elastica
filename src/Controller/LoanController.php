<?php

namespace App\Controller;

use App\Service\LoanSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LoanController extends AbstractController
{
    /**
     * Display loans search by date page
     * GET /loans/by-date
     */
    #[Route('/loans/by-date', name: 'app_loans_by_date')]
    public function byDate(): Response
    {
        return $this->render('loan/by-date.html.twig');
    }

    /**
     * Search loans by date range
     * GET /api/loans/by-date?start_date=2026-01-01&end_date=2026-03-31
     */
    #[Route('/api/loans/by-date', name: 'api_loans_by_date', methods: ['GET'])]
    public function searchByDate(Request $request, LoanSearchService $searchService): JsonResponse
    {
        $startDateStr = $request->query->get('start_date');
        $endDateStr = $request->query->get('end_date');
        
        
        if (!$startDateStr) {
            return new JsonResponse(
                ['error' => 'start_date parameter is required (format: Y-m-d)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $startDate = \DateTime::createFromFormat('Y-m-d', $startDateStr);
            if (!$startDate) {
                throw new \InvalidArgumentException('Invalid start_date format');
            }
            
            $endDate = null;
            if ($endDateStr) {
                $endDate = \DateTime::createFromFormat('Y-m-d', $endDateStr);
                if (!$endDate) {
                    throw new \InvalidArgumentException('Invalid end_date format');
                }
            }

            $currentLoans = false; // Default to false (show all loans)
            if ($request->query->has('current')) {
                $current = $request->query->get('current');
                if (in_array(strtolower((string) $current), ['1', 'true', 'yes', 'on'], true)) {
                    $currentLoans = true;
                } elseif (in_array(strtolower((string) $current), ['0', 'false', 'no', 'off'], true)) {
                    $currentLoans = false;
                } else {
                    throw new \InvalidArgumentException('Invalid current parameter, expected boolean');
                }
            }

            $loans = $searchService->findLoansByDateRange($startDate, $endDate, $currentLoans);
            return new JsonResponse($loans);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Display loans monthly statistics page
     * GET /loans/monthly
     */
    #[Route('/loans/monthly', name: 'app_loans_monthly')]
    public function monthly(): Response
    {
        return $this->render('loan/monthly.html.twig');
    }

    /**
     * Get loans count by month for the last 12 months
     * GET /api/loans/monthly
     */
    #[Route('/api/loans/monthly', name: 'api_loans_monthly', methods: ['GET'])]
    public function getMonthlyStats(LoanSearchService $searchService): JsonResponse
    {
        $stats = $searchService->getLoansCountByMonth();
        return new JsonResponse($stats);
    }

    /**
     * Display loans count by date range page
     * GET /loans/count-by-date
     */
    #[Route('/loans/count-by-date', name: 'app_loans_count_by_date')]
    public function countByDate(): Response
    {
        return $this->render('loan/count-by-date.html.twig');
    }

    /**
     * Get loans count by date range
     * GET /api/loans/count-by-date?start_date=2026-01-01&end_date=2026-03-31
     */
    #[Route('/api/loans/count-by-date', name: 'api_loans_count_by_date', methods: ['GET'])]
    public function getLoansCount(Request $request, LoanSearchService $searchService): JsonResponse
    {
        $startDateStr = $request->query->get('start_date');
        $endDateStr = $request->query->get('end_date');
        
        if (!$startDateStr) {
            return new JsonResponse(
                ['error' => 'start_date parameter is required (format: Y-m-d)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $startDate = \DateTime::createFromFormat('Y-m-d', $startDateStr);
            if (!$startDate) {
                throw new \InvalidArgumentException('Invalid start_date format');
            }
            
            $endDate = null;
            if ($endDateStr) {
                $endDate = \DateTime::createFromFormat('Y-m-d', $endDateStr);
                if (!$endDate) {
                    throw new \InvalidArgumentException('Invalid end_date format');
                }
            }

            $count = $searchService->getLoansCountByDateRange($startDate, $endDate);
            return new JsonResponse(['count' => $count]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Display top 10 videos page
     * GET /loans/top-videos
     */
    #[Route('/loans/top-videos', name: 'app_loans_top_videos')]
    public function topVideos(LoanSearchService $searchService): Response
    {
        $topVideos = $searchService->getTop10Videos();
        return $this->render('loan/top-videos.html.twig', [
            'topVideos' => $topVideos,
        ]);
    }

    /**
     * Display top borrowers page
     * GET /loans/top-borrowers
     */
    #[Route('/loans/top-borrowers', name: 'app_loans_top_borrowers')]
    public function topBorrowers(Request $request, LoanSearchService $searchService): Response
    {
        $limit = max(1, $request->query->getInt('limit', 10));
        $topBorrowers = $searchService->getTopBorrowers($limit);

        return $this->render('loan/top-borrowers.html.twig', [
            'topBorrowers' => $topBorrowers,
            'limit' => $limit,
        ]);
    }
}
