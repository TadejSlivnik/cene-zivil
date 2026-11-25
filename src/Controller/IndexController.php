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
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    private const MAX_PINS = 100;
    private const MAX_TERMS = 10;
    private const MAX_TERM_LENGTH = 100;
    private const MAX_SESSION_TERMS = 200;

    #[Route('/', name: 'index', methods: ['GET'], defaults: ['query' => ''])]
    public function index(Request $request, EntityManagerInterface $em, RequestStack $requestStack)
    {
        $query = $request->query->get('q', '');
        $discountedOnly = (bool) $request->query->get('d', '');
        // all() throws a 400 if "sources" is not an array; drop nested values
        $sources = array_values(array_filter($request->query->all('sources'), 'is_string'));

        $terms = explode(' ', $query);
        $terms = array_map('trim', $terms);
        $terms = array_map('strtolower', $terms);
        $terms = array_unique($terms);
        // Each term adds two LIKE clauses and may be stored in SearchTermCount (varchar 255).
        $terms = array_slice(array_values(array_filter($terms, function ($term) {
            return '' !== $term && mb_strlen($term) <= self::MAX_TERM_LENGTH;
        })), 0, self::MAX_TERMS);

        if (strlen(implode(' ', $terms)) <= 2) {
            $terms = [];
            $discountedOnly = true;
        }

        if ($terms) {
            if ($session = $requestStack->getSession()) {
                $previousTerms = $session->get('search_terms', []);
                // Keep only the most recent terms so a long-lived session can't grow without bound.
                $allTerms = array_slice(array_values(array_unique(array_merge($previousTerms, $terms))), -self::MAX_SESSION_TERMS);
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

        $showSources = Product::SOURCES;

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
            'sources' => $showSources,
            'selectedSources' => $sources,
            'pins' => $pins,
        ]);
    }

    #[Route('/pravno-obvestilo', name: 'disclaimer', methods: ['GET'])]
    public function disclaimer()
    {
        return $this->render('disclaimer.html.twig', [
            'title' => 'Pravno obvestilo',
            'canonical' => 'https://www.cene-zivil.si/pravno-obvestilo',
        ]);
    }

    #[Route('/pogoji-uporabe', name: 'terms_of_use', methods: ['GET'])]
    public function termsOfUse()
    {
        return $this->render('termsOfUse.html.twig', [
            'title' => 'Pogoji uporabe',
            'canonical' => 'https://www.cene-zivil.si/pogoji-uporabe',
        ]);
    }

    #[Route('/pin/{id}', name: 'pin', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pin($id, Request $request)
    {
        $id = (int) $id;
        $pins = $this->getPinsValue($request);

        if (in_array($id, $pins, true)) {
            $pins = array_values(array_diff($pins, [$id]));
        } elseif (count($pins) < self::MAX_PINS) {
            $pins[] = $id;
        }

        $response = new Response();
        $response->headers->setCookie(new Cookie('zivila-pin', json_encode($pins), time() + 3600 * 24 * 30));

        return $response;
    }

    /**
     * The cookie is client-controlled: only keep positive integer IDs, as a plain list.
     *
     * @return int[]
     */
    private function getPinsValue(Request $request): array
    {
        $pins = json_decode((string) $request->cookies->get('zivila-pin', ''), true);
        if (!is_array($pins)) {
            return [];
        }

        $ids = [];
        foreach ($pins as $pin) {
            if ((is_int($pin) || (is_string($pin) && ctype_digit($pin))) && (int) $pin > 0) {
                $ids[] = (int) $pin;
            }
        }

        return array_slice(array_values(array_unique($ids)), 0, self::MAX_PINS);
    }

    #[Route('/chart/{productId}', name: 'chart', methods: ['GET'])]
    public function chart($productId, EntityManagerInterface $em)
    {
        $product = $em->getRepository(Product::class)->find($productId);
        if (!$product instanceof Product) {
            return $this->json(['error' => 'Product not found.'], 404);
        }

        $productPrices = $em->getRepository(ProductPriceHistory::class)->findBy(['product' => $productId], ['createdAt' => 'ASC']);
        if (!$productPrices) {
            return $this->json(['error' => 'No price history found for this product.'], 404);
        }

        $data = [];
        $dataFormatted = [];
        $formatter = new \NumberFormatter('sl_SI', \NumberFormatter::CURRENCY);

        foreach ($productPrices as $priceHistory) {
            // Store date-price pairs for time-based chart
            $data[] = [
                'x' => $priceHistory->getCreatedAt()->format('c'), // ISO 8601 format
                'y' => $priceHistory->getPrice(),
                'date' => $priceHistory->getCreatedAt()->format('d.m.Y'),
            ];

            // Store formatted values for tooltips
            $dataFormatted[] = $formatter->formatCurrency($priceHistory->getPrice(), 'EUR');
        }

        // Fetch chart data based on ID
        return $this->json([
            'title' => $product->getTitle(),
            'trgovina' => $product->getTrgovina(),
            'datasets' => [
                [
                    'label' => 'Cena',
                    'data' => $data,
                    'stepped' => 'before',
                    'formattedData' => $dataFormatted,
                    'pointRadius' => 6,
                    'pointHoverRadius' => 8,
                ],
            ],
        ]);
    }
}
