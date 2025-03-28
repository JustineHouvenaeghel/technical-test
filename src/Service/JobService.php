<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JobService
{
    public function __construct(private ContainerBagInterface $params, private HttpClientInterface $client, private EntityManagerInterface $em) {}

    public function importFranceTravailJobs(?string $city = '', ?string $date = '')
    {
        switch ($city) { //TODO change this to use a db table of French cities with city names, zip codes, departments and INSEE codes
            case 'bordeaux':
                $cityArray['bordeaux'] = 'commune=33063';
                break;

            case 'rennes':
                $cityArray['rennes'] = 'commune=35238';
                break;

            case 'paris':
                $cityArray['paris'] = 'departement=75';
                break;

            default:
                $cityArray = [
                    'bordeaux' => 'commune=33063',
                    'rennes' => 'commune=35238',
                    'paris' => 'departement=75',
                ];
                break;
        }

        if($date) {
            $from = new \DateTime($date . ' 00:00:00');
            $to = new \DateTime($date . ' 23:59:59');
        } else {
            $from = new \DateTime('today midnight');
            $to = new \DateTime();
        }

        $urlAuth = $this->params->get('france_travail_url_auth');
        $urlSearch = $this->params->get('france_travail_url_search');
        $clientId = $this->params->get('france_travail_client_id');
        $clientSecret = $this->params->get('france_travail_client_secret');

        $importedData = [];
        
        try {
            $responseAuth = $this->client->request(
                'POST',
                $urlAuth,
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'scope' => 'api_offresdemploiv2 o2dsoffre'
                    ]
                ]
            );
    
            if ($responseAuth->getStatusCode() === 200) {
                $responseArray  = $responseAuth->toArray();
                $token = $responseArray['access_token'];

                foreach ($cityArray as $ville => $param) {
                    $i = 0;
                    $importedData[$ville] = ['types de contrat' => [], 'entreprises' => []];
                    $minRange = 0;
                    $maxRange = $totalJobs = 149;
                    do {
                        $response = $this->client->request(
                            "GET",
                            $urlSearch . '?minCreationDate=' . $from->format('Y-m-d\TH:i:s\Z'). '&maxCreationDate=' . $to->format('Y-m-d\TH:i:s\Z') . '&' . $param . '&range=' . $minRange . '-' . $maxRange,
                            [
                                "headers" => [
                                    "Accept" => "application/json",
                                    "Authorization" => "Bearer {$token}"
                                ]
                            ]
                        );
            
                        if (in_array($response->getStatusCode(), [200, 206])) {
                            $results = $response->toArray()['resultats'];
                            
                           if($response->getStatusCode() == 206) { // Partial result : check total nb of job offers and loop to the end of the list
                               preg_match('/offres ([0-9]+)-([0-9]+)\/([0-9]+)/', $response->getHeaders()['content-range'][0], $rangeArray);
                               if (count($rangeArray) == 4) {
                                   $minRange = $rangeArray[2] + 1;
                                   $maxRange = $rangeArray[2] + 150 <= $rangeArray[3] ? $rangeArray[2] + 150 : $rangeArray[3];
                                   $totalJobs = $rangeArray[3];
                               }
                           }

                            foreach ($results as $result) {
                                $job = $this->em->getRepository(Job::class)->findOneBy(['franceTravailId' => $result['id']]); //Check if job is already in db
        
                                if (is_null($job)) {
                                    $job = new Job();
                                    $job->setCreatedAt(new \DateTime($result['dateCreation']));
                                }

                                if (($pos = strpos($result['lieuTravail']['libelle'], " - ")) !== FALSE)
                                {
                                    $location  = array_key_exists('libelle', $result['lieuTravail']) ? $result['lieuTravail']['libelle'] : '';
                                    // Retrieve the city name from the location formatted [DPT] - [city_name]
                                    $cityName = ($location !== '') ? substr($location, $pos + 3) : '';
                                    $zipCode = array_key_exists('codePostal', $result['lieuTravail']) ? $result['lieuTravail']['codePostal'] : '';
                                }
        
                                $job->setName($result['intitule'])
                                    ->setDescription($result['description'])
                                    ->setZipCode($cityName ?? '')
                                    ->setCity($zipCode ?? '')
                                    ->setCountry($result['intitule'])
                                    ->setContractType($result['typeContrat'])
                                    ->setUpdatedAt(new \DateTime($result['dateActualisation']))
                                    ->setFranceTravailId($result['id'])
                                ;
        
                                $this->em->persist($job);

                                if(array_key_exists($result['typeContrat'], $importedData[$ville]['types de contrat'])) {
                                    $importedData[$ville]['types de contrat'][$result['typeContrat']] = $importedData[$ville]['types de contrat'][$result['typeContrat']] + 1;
                                } else {
                                    $importedData[$ville]['types de contrat'][$result['typeContrat']] = 1;
                                }
        
                                if (array_key_exists('nom', $result['entreprise'])) {
                                    $company = $this->em->getRepository(Company::class)->findOneBy(['name' => $result['entreprise']['nom']]) ?? new Company(); //Check if company is already in db

                                    $company->setName($result['entreprise']['nom'])
                                        ->setDescription(array_key_exists('description', $result['entreprise']) ? $result['entreprise']['description'] : '')
                                        ->setUrl(array_key_exists('url', $result['entreprise']) ? $result['entreprise']['url'] : '')
                                    ;
        
                                    $company->addJob($job);
                                    $this->em->persist($company);
                                    $this->em->flush(); //Flush to avoid duplicate companies during import

                                    if(array_key_exists($result['entreprise']['nom'], $importedData[$ville]['entreprises'])) {
                                        $importedData[$ville]['entreprises'][$result['entreprise']['nom']] = $importedData[$ville]['entreprises'][$result['entreprise']['nom']] + 1;
                                    } else {
                                        $importedData[$ville]['entreprises'][$result['entreprise']['nom']] = 1;
                                    }
                                }

                                $i++;
        
                                if (($i % 100) === 0) {
                                    $this->em->flush();
                                    $this->em->clear(); // Clear to prevent memory issues
                                }
                            }
                            
                        } else {
                            dump($response->getStatusCode()); //TODO Handle other response codes
                        }
                    } while ($minRange < $maxRange && $maxRange <= $totalJobs);
                    $this->em->flush();
                    $importedData[$ville]['offres'] = $i;
                }
            }
            
            $importedAt = new \DateTime();
            $filesystem = new Filesystem();
            $filesystem->mkdir($this->params->get('kernel.project_dir').'/public/logs', 0644);
            $filesystem->dumpFile($this->params->get('kernel.project_dir').'/public/logs/import-france-travail-'. $importedAt->format('YmdHis') .'.json', json_encode($importedData, JSON_PRETTY_PRINT));

            return new JsonResponse(['url' => '/logs/import-france-travail-'. $importedAt->format('YmdHis') .'.json'], 200);

        } catch (\Error $e) {
            return new JsonResponse('An error occured : ' . $e->getMessage(),  400);
        }
    }
}