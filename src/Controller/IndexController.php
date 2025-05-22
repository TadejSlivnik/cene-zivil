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
            'metaTitle' => 'Cene živil v slovenskih trgovinah – primerjaj Mercator, Hofer, Lidl, Spar, Tuš, DM',
            'metaDescription' => 'Primerjaj aktualne cene živil v trgovinah Mercator, Hofer, Lidl, Spar, Tuš in DM. Preglej akcije, popuste, cene na enoto in hitro poišči želene izdelke.',
            'metaKeywords' => 'cene živil, primerjava cen, trgovine Slovenija, Mercator, Hofer, Lidl, Spar, Tuš, DM, akcije, popusti, prehrambeni izdelki, živila',
            'canonical' => 'https://www.cene-zivil.si',
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
        ]);
    }

    /**
     * @Route("/pogoji-uporabe", name="terms_of_use", methods={"GET"})
     */
    public function termsOfUse()
    {
        return $this->render('termsOfUse.html.twig', [
            'title' => 'Pogoji uporabe',
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
