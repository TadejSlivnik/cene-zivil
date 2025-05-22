<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SitemapCommand extends AbstractCommand
{
    protected static $defaultName = 'app:sitemap';

    protected function executeCommand(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Sitemap Command');
        $this->io->section('Updating sitemap...');

        $today = new \DateTime();
        $today->setTimezone(new \DateTimeZone('Europe/Ljubljana'));

        $lastChangePogoji = new \DateTime();
        $lastChangePogoji->setTimezone(new \DateTimeZone('Europe/Ljubljana'));
        $lastChangePogoji->setTimestamp(1746261513);

        // Here you would typically generate or update the sitemap.
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://www.cene-zivil.si</loc>
        <lastmod>{$today->format(\DateTime::ATOM)}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>https://www.cene-zivil.si/pravno-obvestilo</loc>
        <lastmod>{$lastChangePogoji->format(\DateTime::ATOM)}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    <url>
        <loc>https://www.cene-zivil.si/pogoji-uporabe</loc>
        <lastmod>{$lastChangePogoji->format(\DateTime::ATOM)}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
</urlset>
XML;

        $sitemapPath = $this->parameterBag->get('kernel.project_dir') . '/public/sitemap.xml';
        file_put_contents($sitemapPath, $xml);
        if (!file_exists($sitemapPath)) {
            $this->io->error('Failed to write sitemap file.');
            return Command::FAILURE;
        }
        
        $this->io->success('Sitemap updated successfully!');
        $this->io->note('You can find the updated sitemap at: /sitemap.xml');

        return Command::SUCCESS;
    }
}