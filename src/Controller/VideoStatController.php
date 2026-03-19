<?php

namespace App\Controller;

use App\Service\LoanSearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VideoStatController extends AbstractController
{
    private $searchService;

    public function __construct(LoanSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Cette route affiche la page HTML principale (le squelette).
     * Elle est appelée une seule fois quand on charge la page.
     */
    #[Route('/video/stats', name: 'app_video_stats', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('video/stats.html.twig');
    }

    /**
     * Cette route est l'API utilisée par le JavaScript.
     * Elle renvoie uniquement du JSON pour plus de rapidité.
     */
    #[Route('/api/video/search-stats', name: 'api_video_stats_data', methods: ['GET'])]
    public function getSearchStats(Request $request): JsonResponse
    {
        // On récupère le paramètre "q" de l'URL (ex: ?q=le+parrain)
        $query = $request->query->get('q', '');

        // Si la recherche est trop courte, on renvoie un tableau vide
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        // On appelle notre service qui interroge Elasticsearch
        $stats = $this->searchService->searchVideoStats($query);

        // On retourne les "buckets" (groupements) au format JSON
        return new JsonResponse($stats);
    }
}