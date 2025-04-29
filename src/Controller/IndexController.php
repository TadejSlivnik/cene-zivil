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
        $terms = array_filter($terms, function ($term) {
            return strlen($term) >= 3;
        });

        if ($terms) {
            $qb = $em->createQueryBuilder()
                ->select('p')
                ->from(Product::class, 'p')
                ->orderBy('p.id', 'DESC')
                ->setMaxResults(1000);

            foreach ($terms as $k => $term) {
                if (strlen($term) < 3) {
                    continue;
                }
                $qb->andWhere("p.title LIKE :term$k OR p.productId LIKE :term$k")
                    ->setParameter("term$k", "%$term%");
            }

            $qb->orderBy('p.unitPrice', 'ASC');

            $products = $qb->getQuery()->getResult();
        } else {
            $products = $em->getRepository(Product::class)->findMostDiscountedProducts();
        }

        sort($terms);

        return $this->render('index.html.twig', [
            'title' => 'Cene živil',
            'products' => $products,
            'terms' => $terms,
            'query' => $query,
        ]);
    }
}
