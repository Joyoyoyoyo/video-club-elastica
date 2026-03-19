<?php

namespace App\Controller;

use App\Service\LoanSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BorrowerController extends AbstractController
{
    #[Route('/borrower/search', name: 'app_borrower_index')]
    public function index(): Response
    {
        return $this->render('borrower/index.html.twig');
    }

    #[Route('/api/borrower/loans', name: 'api_borrower_loans_search', methods: ['GET'])]
    public function searchLoans(Request $request, LoanSearchService $searchService): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $loans = $searchService->findLoansByBorrower($query);

        return new JsonResponse($loans);
    }
}