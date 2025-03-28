<?php

namespace App\Controller;

use App\Repository\JobRepository;
use App\Service\FranceTravailAdapter;
use App\Service\JobService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class JobController extends AbstractController
{
    public function __construct(private JobService $jobService)
    {}

    #[Route('/', name: 'index')]
    public function index(JobRepository $jobRepository): Response
    {
        return $this->render('job/index.html.twig', [
            'jobs' => $jobRepository->findAll()
        ]);
    }

    #[Route('/import-jobs', name: 'import_jobs')]
    public function importJobs(Request $request): Response
    {
        $city = $request->get('city');
        $date = $request->get('date');

        $result = $this->jobService->importFranceTravailJobs($city, $date);
        
        if ($result->getStatusCode() == 200) {
            $url = json_decode($result->getContent(), true)['url'];
            return $this->redirect($url);
        } else {
            $this->addFlash('warning', 'Une erreur s\'est produite lors de l\'import.');
            return $this->redirectToRoute('index');
        }
    }
}
