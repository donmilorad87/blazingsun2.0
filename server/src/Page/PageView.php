<?php

declare(strict_types=1);

namespace BlazingSun\Api\Page;

use BlazingSun\Api\Content\Content;
use BlazingSun\Api\Content\Definitions;
use BlazingSun\Api\Content\RichText;

/**
 * Everything templates/page.php needs for one language. Every method returns HTML-safe output:
 * plain text is escaped, rich text keeps only <em>/<b> (RichText), URLs were validated on save and are escaped too.
 *
 * @phpstan-import-type Language from Content
 */
final readonly class PageView
{
    public string $schemaJson;

    public function __construct(
        public Content $content,
        public string $lang,
        public Assets $assets,
        public string $siteUrl,
        public int $year,
    ) {
        assert($content->language($lang) !== null, "unknown language $lang");
        assert($siteUrl !== '' && !str_ends_with($siteUrl, '/'), 'site URL without trailing slash');
        $languages = array_column($content->languages, 'iso2');
        $this->schemaJson = SchemaGraph::toScript(SchemaGraph::build(
            $this->path($lang),
            $lang,
            $languages,
            RichText::toPlain($this->text('labels', 'meta_title')),
            RichText::toPlain($this->text('labels', 'meta_description')),
        ));
    }

    /** URL path of a language's page: the main language lives at /, the others at /{iso2}/. */
    public function path(string $iso2): string
    {
        return $iso2 === $this->content->mainLanguage() ? '/' : "/$iso2/";
    }

    public function url(string $iso2): string
    {
        return $this->siteUrl . $this->path($iso2);
    }

    /** Translated text, escaped. */
    public function text(string $scope, string $field): string
    {
        return self::escape($this->content->text($scope, $field, $this->lang));
    }

    /** Translated text that may contain <em>/<b>. */
    public function rich(string $scope, string $field): string
    {
        return RichText::toHtml($this->content->text($scope, $field, $this->lang));
    }

    /** Untranslated value, escaped. */
    public function value(string $scope, string $field): string
    {
        return self::escape($this->content->value($scope, $field));
    }

    /** @return list<string> item scopes of a section group, in order */
    public function items(string $section, string $group = 'items'): array
    {
        return array_map(Content::itemScope(...), $this->content->itemIds($section, $group));
    }

    /** "01 / Profile": the number is the section's position (the home banner doesn't count). */
    public function beforeTitle(string $slug): string
    {
        $number = array_search($slug, array_keys(Definitions::sections()), true);

        return sprintf('%02d / %s', (int) $number, $this->text(Content::sectionScope($slug), 'before_title'));
    }

    /** @return array<string, string> anchor => menu label, for the sections shown in the main menu */
    public function menu(): array
    {
        $menu = [];
        foreach (Definitions::sections() as $slug => $section) {
            if ($section['nav'] && ($this->content->nav[$slug] ?? false)) {
                $menu[$section['anchor']] = $this->text(Content::sectionScope($slug), 'nav_label');
            }
        }

        return $menu;
    }

    /** @return Language */
    public function currentLanguage(): array
    {
        return $this->content->language($this->lang) ?? $this->content->languages[0];
    }

    /** @param array<string, string> $attributes */
    public function image(string $scope, string $field, string $sizes, array $attributes): string
    {
        return Picture::html($this->content->image($scope, $field), $sizes, $attributes);
    }

    /**
     * @param Language $language
     * @param array<string, string> $attributes
     */
    public function flag(array $language, array $attributes): string
    {
        $media = $language['flag_media_id'] === null ? null : $this->content->mediaById($language['flag_media_id']);

        return Picture::html($media, '24px', ['class' => 'flag', 'alt' => '', ...$attributes]);
    }

    /** Link attributes and text of a contact item (phone, e-mail or link). */
    public function contactLink(string $scope): string
    {
        $type = $this->content->value($scope, 'type');
        $value = $this->content->value($scope, 'value');
        $label = $this->content->text($scope, 'label', $this->lang);
        $icon = $this->content->value($scope, 'icon');
        $shown = $label !== '' ? $label : $value;

        [$href, $external] = match ($type) {
            'phone' => ['tel:' . preg_replace('/[^0-9+]/', '', $value), false],
            'email' => ['mailto:' . $value, false],
            default => [$value, true],
        };
        // A phone number never wraps in the middle.
        $text = self::escape($type === 'phone' ? str_replace(' ', "\u{00A0}", $shown) : $shown);
        $prefix = $icon === '' ? '' : self::escape($icon) . ($type === 'phone' ? '&nbsp;' : ' ');

        return sprintf('<a class="contact__link" href="%s"%s>%s%s</a>', self::escape($href), $external ? ' target="_blank" rel="noopener"' : '', $prefix, $text);
    }

    /** Extra attributes for a link that leaves the page. */
    public function linkTarget(string $href): string
    {
        return str_starts_with($href, 'http') ? ' target="_blank" rel="noopener"' : '';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
