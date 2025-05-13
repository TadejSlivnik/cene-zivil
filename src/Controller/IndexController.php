<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"}, defaults={"query"=""})
     */
    public function index(Request $request, EntityManagerInterface $em)
    {
        $query = $request->query->get('q', '');
        $discountedOnly = (bool)$request->query->get('d', '');
        $sources = $request->query->get('sources', []);

        $terms = explode(' ', $query);
        $terms = array_map('trim', $terms);
        $terms = array_map('strtolower', $terms);
        $terms = array_unique($terms);

        if (strlen(implode(' ', $terms)) <= 2) {
            $terms = [];
            $discountedOnly = true;
        }
        
        $pins = $this->getPinsValue($request);

        $products = $em->getRepository(Product::class)->findByTerms($terms, $discountedOnly, $sources, $terms ? $pins : []);
        sort($terms);

        return $this->render('index.html.twig', [
            'title' => 'Cene živil',
            'description' => 'Cene živil - primerjaj cene živil v trgovinah',
            'keywords' => 'cene živil, primerjaj cene, trgovine, akcije',
            'products' => $products,
            'terms' => $terms,
            'query' => $query,
            'discountedOnly' => $discountedOnly,
            'sources' => Product::SOURCES,
            'selectedSources' => $sources,
            'pins' => $pins,
        ]);
    }

    /**
     * @Route("/pravno-obvestilo", name="disclaimer", methods={"GET"})
     */
    public function disclaimer()
    {
        return $this->render('disclaimer.html.twig', [
            'title' => 'Pravno obvestilo',
            'description' => 'Pravno obvestilo spletne strani Cene živil',
            'keywords' => 'pravno obvestilo, cene živil, pogoji',
        ]);
    }

    /**
     * @Route("/pogoji-uporabe", name="terms_of_use", methods={"GET"})
     */
    public function termsOfUse()
    {
        return $this->render('termsOfUse.html.twig', [
            'title' => 'Pogoji uporabe',
            'description' => 'Pogoji uporabe spletne strani Cene živil',
            'keywords' => 'pogoji uporabe, cene živil, pogoji',
        ]);
    }

    /**
     * @Route("/pin/{id}", name="pin", methods={"GET"})
     */
    public function pin($id, Request $request)
    {
        $pins = $this->getPinsValue($request);

        if (in_array($id, $pins)) {
            unset($pins[array_search($id, $pins)]);
        } else {
            $pins[] = $id;
        }

        $response = new Response();
        $response->headers->setCookie(new Cookie('zivila-pin', json_encode($pins), time() + 3600 * 24 * 30));
        return $response;
    }

    private function getPinsValue(Request $request)
    {
        $pins = $request->cookies->get('zivila-pin', '');
        $pins = json_decode($pins, true);
        if (!is_array($pins)) {
            $pins = [];
        }
        return $pins;
    }
}
