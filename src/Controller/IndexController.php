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

        $terms = array_unique(array_map('trim', explode(' ', $query)));

        if (strlen(implode(' ', $terms)) <= 2) {
            $terms = [];
        }

        if ($terms) {
            $products = $em->getRepository(Product::class)->findByTerms($terms);
            sort($terms);
        } else {
            $products = $em->getRepository(Product::class)->findMostDiscountedProducts();
        }

        return $this->render('index.html.twig', [
            'title' => 'Cene živil',
            'products' => $products,
            'terms' => $terms,
            'query' => $query,
        ]);
    }
}
