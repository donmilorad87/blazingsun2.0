<?php

declare(strict_types=1);

namespace BlazingSun\Api\Page;

/**
 * JSON-LD @graph for one language's page. Nodes link by @id, so search engines (and the admin's schema diagram)
 * see one graph: WebPage → isPartOf WebSite → publisher Company (a LocalBusiness) → founder Person ← about WebPage.
 * The Person node is kept exactly as it was before the CMS existed.
 */
final class SchemaGraph
{
    public const string BASE = 'https://blazingsun.space';

    private const array ADDRESS = [
        '@type' => 'PostalAddress',
        'streetAddress' => 'NH Svete Popovica 24',
        'postalCode' => '32300',
        'addressLocality' => 'Gornji Milanovac',
        'addressCountry' => 'RS',
    ];

    private const array PERSON = [
        '@type' => 'Person',
        '@id' => self::BASE . '/#person',
        'name' => 'Milorad Đuković',
        'jobTitle' => 'Senior Full Stack Web Engineer',
        'description' => 'Milorad Đuković is a Senior Full Stack Web Engineer with 15+ years of experience in JavaScript, TypeScript, Node.js, Rust, PHP, React, WordPress, Shopify, APIs, AI engineering and scalable web platforms.',
        'url' => self::BASE . '/',
        'image' => self::BASE . '/images/milorad-djukovic.jpg',
        'telephone' => '+381629616231',
        'email' => 'djmyle@gmail.com',
        'address' => self::ADDRESS,
        'sameAs' => ['https://www.linkedin.com/in/donmilorad', 'https://github.com/donmilorad87'],
        'worksFor' => ['@id' => self::BASE . '/#company'],
    ];

    /** ProfessionalService is the schema.org LocalBusiness subtype for a software/consulting business. */
    private const array COMPANY = [
        '@type' => 'ProfessionalService',
        '@id' => self::BASE . '/#company',
        'name' => 'Blazing Sun',
        'url' => self::BASE . '/',
        'logo' => self::BASE . '/favicon.png',
        'image' => self::BASE . '/favicon.png',
        'telephone' => '+381629616231',
        'email' => 'djmyle@gmail.com',
        'address' => self::ADDRESS,
        'areaServed' => 'Worldwide',
        'identifier' => [
            ['@type' => 'PropertyValue', 'propertyID' => 'MB', 'value' => '67500156'],
            ['@type' => 'PropertyValue', 'propertyID' => 'PIB', 'value' => '114340949'],
        ],
        'founder' => ['@id' => self::BASE . '/#person'],
    ];

    /**
     * @param list<string> $languages iso2 codes of every language
     * @return array<string, mixed>
     */
    public static function build(string $path, string $lang, array $languages, string $title, string $description): array
    {
        assert(str_starts_with($path, '/'), 'page path must be absolute');
        assert($languages !== [], 'at least one language');
        $url = self::BASE . $path;

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::PERSON,
                self::COMPANY,
                [
                    '@type' => 'WebSite',
                    '@id' => self::BASE . '/#website',
                    'url' => self::BASE . '/',
                    'name' => 'Blazing Sun',
                    'inLanguage' => $languages,
                    'publisher' => ['@id' => self::BASE . '/#company'],
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => "$url#webpage",
                    'url' => $url,
                    'name' => $title,
                    'description' => $description,
                    'inLanguage' => $lang,
                    'isPartOf' => ['@id' => self::BASE . '/#website'],
                    'about' => ['@id' => self::BASE . '/#person'],
                ],
            ],
        ];
    }

    /**
     * JSON for a <script> element: "</" can never close the tag early.
     *
     * @param array<string, mixed> $graph
     */
    public static function toScript(array $graph): string
    {
        return json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }
}
