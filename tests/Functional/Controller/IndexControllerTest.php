<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Product;
use App\Entity\SearchTermCount;
use App\Tests\Support\ProductFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class IndexControllerTest extends WebTestCase
{
    use ProductFactory;

    private KernelBrowser $client;
    private Product $milkDiscounted;
    private Product $milkSpar;
    private Product $bread;
    private Product $deletedMilk;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->milkDiscounted = $this->createProduct(['title' => 'Mleko polnomastno 1 l', 'source' => Product::SOURCE_MERCATOR, 'price' => 0.99, 'regularPrice' => 1.29, 'discount' => 23, 'ean' => ',3830000000011,']);
        $this->milkSpar = $this->createProduct(['title' => 'Alpsko mleko 1 l', 'source' => Product::SOURCE_SPAR, 'price' => 1.19, 'regularPrice' => 1.19, 'ean' => ',3830000000028,']);
        $this->bread = $this->createProduct(['title' => 'Kruh beli 500 g', 'source' => Product::SOURCE_HOFER, 'price' => 1.49, 'regularPrice' => 1.99, 'discount' => 25]);
        $this->deletedMilk = $this->createProduct(['title' => 'Mleko staro', 'source' => Product::SOURCE_TUS, 'price' => 0.50, 'regularPrice' => 1.00, 'discount' => 50, 'deletedAt' => new \DateTime()]);
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @return list<int> product IDs in the order they are listed
     */
    private function listedProductIds(): array
    {
        return $this->client->getCrawler()->filter('button.fetch-button')->each(fn ($button) => (int) $button->attr('data-id'));
    }

    public function testHomepageListsOnlyDiscountedProducts(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Cene živil');
        $ids = $this->listedProductIds();
        self::assertContains($this->milkDiscounted->getId(), $ids);
        self::assertContains($this->bread->getId(), $ids);
        self::assertNotContains($this->milkSpar->getId(), $ids, 'no search term forces "discounted only"');
        self::assertNotContains($this->deletedMilk->getId(), $ids, 'soft-deleted products are hidden');
    }

    public function testSearchByTerm(): void
    {
        $this->client->request('GET', '/', ['q' => 'MLEKO']);

        self::assertResponseIsSuccessful();
        $ids = $this->listedProductIds();
        self::assertEqualsCanonicalizing([$this->milkDiscounted->getId(), $this->milkSpar->getId()], $ids);
    }

    public function testAllTermsMustMatch(): void
    {
        $this->client->request('GET', '/', ['q' => 'mleko alpsko']);

        self::assertSame([$this->milkSpar->getId()], $this->listedProductIds());
    }

    public function testSearchByEanMatchesWholeCode(): void
    {
        $this->client->request('GET', '/', ['q' => '3830000000028']);
        self::assertSame([$this->milkSpar->getId()], $this->listedProductIds());

        $this->client->request('GET', '/', ['q' => '383000000002']);
        self::assertSame([], $this->listedProductIds(), 'a partial EAN does not match the ",ean," list');
        self::assertSelectorExists('td.no-results');
    }

    public function testFilterBySource(): void
    {
        $this->client->request('GET', '/', ['q' => 'mleko', 'sources' => [Product::SOURCE_SPAR]]);

        self::assertSame([$this->milkSpar->getId()], $this->listedProductIds());
    }

    public function testDiscountedOnlyFilter(): void
    {
        $this->client->request('GET', '/', ['q' => 'mleko', 'd' => '1']);

        self::assertSame([$this->milkDiscounted->getId()], $this->listedProductIds());
    }

    public function testPinnedProductsAreListedFirstEvenIfTheyDoNotMatch(): void
    {
        $this->client->getCookieJar()->set(new Cookie('zivila-pin', json_encode([$this->bread->getId()])));
        $this->client->request('GET', '/', ['q' => 'mleko']);

        $ids = $this->listedProductIds();
        self::assertSame($this->bread->getId(), $ids[0]);
        self::assertCount(3, $ids);
    }

    public function testPinsFromTheOldStringCookieFormatStillWork(): void
    {
        $this->client->getCookieJar()->set(new Cookie('zivila-pin', json_encode([(string) $this->bread->getId()])));
        $this->client->request('GET', '/', ['q' => 'mleko']);

        self::assertSame($this->bread->getId(), $this->listedProductIds()[0]);
    }

    public function testMaliciousPinCookieIsIgnored(): void
    {
        // Regression: the keys of a JSON-object cookie used to be interpolated into DQL
        $this->client->getCookieJar()->set(new Cookie('zivila-pin', '{"1 THEN 0 WHEN 1=1":5,"x":[1]}'));
        $this->client->request('GET', '/', ['q' => 'mleko']);

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing([$this->milkDiscounted->getId(), $this->milkSpar->getId()], $this->listedProductIds());
    }

    public function testSearchTermsAreCappedAndCounted(): void
    {
        $terms = array_map(fn ($i) => "izraz$i", range(1, 50));
        $terms[] = str_repeat('x', 300);
        $this->client->request('GET', '/', ['q' => implode(' ', $terms)]);

        self::assertResponseIsSuccessful();
        $counts = $this->getEntityManager()->getRepository(SearchTermCount::class)->findAll();
        self::assertCount(10, $counts, 'only the first 10 terms are used');
        foreach ($counts as $count) {
            self::assertLessThanOrEqual(100, mb_strlen($count->getSearchTerm()));
        }
    }

    public function testRepeatedSearchInTheSameSessionIsCountedOnce(): void
    {
        $this->client->request('GET', '/', ['q' => 'mleko']);
        $this->client->request('GET', '/', ['q' => 'mleko']);

        $count = $this->getEntityManager()->getRepository(SearchTermCount::class)->find('mleko');
        self::assertSame(1, $count->getCount());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedQueryProvider(): iterable
    {
        yield 'sources as a string' => [['q' => 'mleko', 'sources' => 'spar']];
        yield 'q as an array' => [['q' => ['mleko']]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedQueryProvider')]
    public function testMalformedQueryIsABadRequest(array $query): void
    {
        $this->client->request('GET', '/', $query);

        self::assertResponseStatusCodeSame(400);
    }

    public function testPinTogglesTheCookie(): void
    {
        $id = $this->milkSpar->getId();

        $this->client->request('GET', "/pin/$id");
        self::assertResponseIsSuccessful();
        self::assertSame([$id], json_decode($this->client->getCookieJar()->get('zivila-pin')->getValue(), true));

        $this->client->request('GET', "/pin/$id");
        self::assertSame([], json_decode($this->client->getCookieJar()->get('zivila-pin')->getValue(), true));
    }

    public function testPinCookieIsHttpOnly(): void
    {
        $this->client->request('GET', '/pin/1');

        self::assertResponseHasCookie('zivila-pin');
        $cookie = $this->client->getResponse()->headers->getCookies()[0];
        self::assertTrue($cookie->isHttpOnly());
    }

    public function testPinRequiresNumericId(): void
    {
        $this->client->request('GET', '/pin/abc');

        self::assertResponseStatusCodeSame(404);
    }

    public function testChartReturnsPriceHistory(): void
    {
        $this->createPriceHistory($this->milkDiscounted, ['price' => 1.29, 'regularPrice' => 1.29, 'discount' => null]);
        $this->createPriceHistory($this->milkDiscounted);

        $this->client->request('GET', '/chart/'.$this->milkDiscounted->getId());

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Mleko polnomastno 1 l', $data['title']);
        self::assertSame('Mercator', $data['trgovina']);
        self::assertCount(2, $data['datasets'][0]['data']);
        self::assertSame([1.29, 0.99], array_column($data['datasets'][0]['data'], 'y'));
        self::assertCount(2, $data['datasets'][0]['formattedData']);
    }

    public function testChartForUnknownProduct(): void
    {
        $this->client->request('GET', '/chart/999999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Product not found.'], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testChartWithoutHistory(): void
    {
        $this->client->request('GET', '/chart/'.$this->milkSpar->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testLegalPages(): void
    {
        foreach (['/pravno-obvestilo' => 'Pravno obvestilo', '/pogoji-uporabe' => 'Pogoji uporabe'] as $path => $title) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('title', $title);
        }
    }

    public function testPostIsNotAllowed(): void
    {
        $this->client->request('POST', '/');

        self::assertResponseStatusCodeSame(405);
    }
}
