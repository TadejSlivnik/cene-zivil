<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        
        $products = $em->getRepository(Product::class)->findByTerms($terms, $discountedOnly, $sources);
        sort($terms);

        return $this->render('index.html.twig', [
            'title' => 'Cene živil',
            'products' => $products,
            'terms' => $terms,
            'query' => $query,
            'discountedOnly' => $discountedOnly,
            'sources' => Product::SOURCES,
            'selectedSources' => $sources,
        ]);
    }
}
