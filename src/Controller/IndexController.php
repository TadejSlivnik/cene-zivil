<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductPriceHistory;
use App\Entity\SearchTermCount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    /**
     * @Route("/", name="index", methods={"GET"}, defaults={"query"=""})
     */
    public function index(Request $request, EntityManagerInterface $em, RequestStack $requestStack)
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

        if ($terms) {
            if ($session = $requestStack->getSession()) {
                $previousTerms = $session->get('search_terms', []);
                $allTerms = array_unique(array_merge($previousTerms, $terms));
                $session->set('search_terms', $allTerms);

                $searchTerms = array_diff($terms, $previousTerms);
                $searchTerms = array_filter($searchTerms, function ($term) {
                    return strlen($term) > 2; // Only track terms longer than 2 characters
                });
                if ($searchTerms) {
                    $em->getRepository(SearchTermCount::class)->addSearchTerms($searchTerms);
                    $em->clear();
                }
            }
        }

        $pins = $this->getPinsValue($request);

        $products = $em->getRepository(Product::class)->findByTerms($terms, $discountedOnly, $sources, $terms ? $pins : []);
        sort($terms);

        $sources = Product::SOURCES;
        unset($sources[Product::SOURCE_HOFER]);
        unset($sources[Product::SOURCE_LIDL]);

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
            'canonical' => 'https://www.cene-zivil.si/pravno-obvestilo',
        ]);
    }

    /**
     * @Route("/pogoji-uporabe", name="terms_of_use", methods={"GET"})
     */
    public function termsOfUse()
    {
        return $this->render('termsOfUse.html.twig', [
            'title' => 'Pogoji uporabe',
            'canonical' => 'https://www.cene-zivil.si/pogoji-uporabe',
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

    /**
     * @Route("/chart/{productId}", name="chart", methods={"GET"})
     */
    public function chart($productId)
    {
        $product = $this->getDoctrine()->getRepository(Product::class)->find($productId);
        if (!$product instanceof Product) {
            return $this->json(['error' => 'Product not found.'], 404);
        }

        $productPrices = $this->getDoctrine()->getRepository(ProductPriceHistory::class)->findBy(['product' => $productId], ['createdAt' => 'ASC']);
        if (!$productPrices) {
            return $this->json(['error' => 'No price history found for this product.'], 404);
        }

        $labels = [];
        $data = [];
        $data2 = [];
        $dataFormatted = [];
        $data2Formatted = [];
        $formatter = new \NumberFormatter('sl_SI', \NumberFormatter::CURRENCY);

        foreach ($productPrices as $priceHistory) {
            $labels[] = $priceHistory->getCreatedAt()->format('d.m.Y');
            // Store raw values for chart calculations
            $data[] = $priceHistory->getPrice();
            $data2[] = $priceHistory->getRegularPrice();

            // Store formatted values for tooltips
            $dataFormatted[] = $formatter->formatCurrency($priceHistory->getPrice(), 'EUR');
            $data2Formatted[] = $formatter->formatCurrency($priceHistory->getRegularPrice(), 'EUR');
        }

        // Fetch chart data based on ID
        return $this->json([
            'title' => $product->getTitle(),
            'trgovina' => $product->getTrgovina(),
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Redna cena',
                'data' => $data2,
                'stepped' => 'before',
                'formattedData' => $data2Formatted,
            ], [
                'label' => 'Trenutna cena',
                'data' => $data,
                'stepped' => 'before',
                'formattedData' => $dataFormatted,
            ],]
        ]);
    }
}
