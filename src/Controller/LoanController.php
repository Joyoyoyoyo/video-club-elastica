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

            $loans = $searchService->findLoansByDateRange($startDate, $endDate);
            return new JsonResponse($loans);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
