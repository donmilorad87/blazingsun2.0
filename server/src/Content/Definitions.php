<?php

declare(strict_types=1);

namespace BlazingSun\Api\Content;

/**
 * What can be edited: the fixed page sections with their fields and item groups, plus header, footer and UI labels.
 * One source of truth: the API validates against it and the admin portal builds its forms from it.
 *
 * Field types:
 *   text  — translated plain text           rich  — translated text, only <em> and <b> allowed
 *   plain — one value for every language    url   — link: https://…, http://…, mailto:…, tel:… or #anchor
 *   select — one of `options`               image — id of an uploaded media file
 *
 * @phpstan-type Field array{name: string, label: string, type: string, required: bool, options?: list<string>}
 * @phpstan-type Group array{label: string, max: int, fields: list<Field>}
 * @phpstan-type Section array{label: string, anchor: string, nav: bool, fields: list<Field>, groups: array<string, Group>}
 */
final class Definitions
{
    /** Upper bound for items in one group (bounded loops, bounded page size). */
    public const int MAX_ITEMS = 30;

    public const int MAX_LANGUAGES = 20;

    public const array SETTING_SCOPES = ['header', 'footer', 'labels'];

    /** @return array<string, Section> slug => section, in page order */
    public static function sections(): array
    {
        return [
            'hero' => self::section('Home banner', '', false, [
                self::field('before_title', 'Before title', 'text', true),
                self::field('title', 'Title', 'rich', true),
                self::field('description', 'Description', 'text', true),
                self::field('info', 'Small info (below the buttons)', 'text'),
            ], [
                'items' => self::group('Buttons', [
                    self::field('label', 'Label', 'text', true),
                    self::field('href', 'Link (#section or URL)', 'url', true),
                    self::field('style', 'Style', 'select', true, ['primary', 'ghost']),
                ], 4),
            ]),
            'about' => self::section('Profile', 'about', true, [
                ...self::head(),
                self::field('quote', 'Profile description (quote)', 'text'),
                self::field('company_description', 'Company description', 'text'),
            ], [
                'items' => self::group('Stats', [
                    self::field('value', 'Value (e.g. 15+)', 'plain', true),
                    self::field('label', 'Label', 'text', true),
                ]),
                'company_items' => self::group('Company details', [
                    self::field('text', 'Text', 'text', true),
                ]),
            ]),
            'stack' => self::section('Capabilities', 'stack', true, self::head(), [
                'items' => self::group('Capabilities', [
                    self::field('title', 'Title', 'text', true),
                    self::field('text', 'Text', 'rich', true),
                ]),
            ]),
            'experience' => self::section('Experience', 'experience', true, self::head(), [
                'items' => self::group('Jobs', [
                    self::field('period', 'Period (e.g. 2020 — 2023)', 'text', true),
                    self::field('title', 'Role', 'text', true),
                    self::field('company', 'Company', 'plain', true),
                    self::field('text', 'Description', 'text'),
                ]),
            ]),
            'projects' => self::section('Selected work', 'projects', true, self::head(), [
                'items' => self::group('Projects', [
                    self::field('category', 'Category', 'text'),
                    self::field('name', 'Name', 'text', true),
                    self::field('text', 'Description', 'text'),
                    self::field('tech', 'Technologies (e.g. PHP · SQL)', 'plain'),
                    self::field('url', 'Link (optional)', 'url'),
                ]),
            ]),
            'cv' => self::section('Expanded CV', 'cv-highlights', true, self::head(), [
                'items' => self::group('Cards', [
                    self::field('title', 'Title', 'text', true),
                    self::field('text', 'Text', 'text', true),
                ]),
            ]),
            'contact' => self::section('Contact', 'contact', true, self::head(), [
                'items' => self::group('Contact links', [
                    self::field('type', 'Type', 'select', true, ['phone', 'email', 'link']),
                    self::field('value', 'Phone, e-mail or URL', 'plain', true),
                    self::field('label', 'Shown text (empty: the value)', 'text'),
                    self::field('icon', 'Icon (a character, e.g. ☎)', 'plain'),
                ], 10),
            ]),
        ];
    }

    /** @return array<string, list<Field>> scope => fields, for header, footer and labels */
    public static function settings(): array
    {
        return [
            'header' => [
                self::field('name_dark', 'Site name (dark theme)', 'text', true),
                self::field('name_light', 'Site name (light theme)', 'text', true),
                self::field('logo_dark', 'Logo (dark theme)', 'image', true),
                self::field('logo_light', 'Logo (light theme)', 'image', true),
                self::field('icon_dark', 'Icon (dark theme): theme switch and hero banner', 'image', true),
                self::field('icon_light', 'Icon (light theme): theme switch and hero banner', 'image', true),
            ],
            'footer' => [
                self::field('name', 'Your name', 'plain', true),
                self::field('company', 'Company', 'plain', true),
                self::field('position', 'Position', 'text', true),
                self::field('country', 'Base country', 'text', true),
            ],
            'labels' => [
                self::field('meta_title', 'Page title (browser tab, search results)', 'text', true),
                self::field('meta_description', 'Meta description', 'text', true),
                self::field('nav_label', 'Main menu (screen readers)', 'text', true),
                self::field('theme_toggle', 'Theme switch button (screen readers)', 'text', true),
                self::field('back_to_top', 'Back-to-top button (screen readers)', 'text', true),
            ],
        ];
    }

    public static function isTranslated(string $type): bool
    {
        return $type === 'text' || $type === 'rich';
    }

    /**
     * Everything the admin portal needs to build its forms.
     *
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return ['sections' => self::sections(), 'settings' => self::settings()];
    }

    /** @return list<Field> the fields every section except the home banner has */
    private static function head(): array
    {
        return [
            self::field('before_title', 'Before title (the number is added automatically)', 'text', true),
            self::field('title', 'Title', 'text', true),
            self::field('description', 'Description', 'text'),
            self::field('nav_label', 'Main menu label', 'text', true),
        ];
    }

    /**
     * @param list<Field> $fields
     * @return Group
     */
    private static function group(string $label, array $fields, int $max = self::MAX_ITEMS): array
    {
        return ['label' => $label, 'max' => min($max, self::MAX_ITEMS), 'fields' => $fields];
    }

    /**
     * @param list<Field> $fields
     * @param array<string, Group> $groups
     * @return Section
     */
    private static function section(string $label, string $anchor, bool $nav, array $fields, array $groups): array
    {
        return ['label' => $label, 'anchor' => $anchor, 'nav' => $nav, 'fields' => $fields, 'groups' => $groups];
    }

    /**
     * @param list<string> $options
     * @return Field
     */
    private static function field(string $name, string $label, string $type, bool $required = false, array $options = []): array
    {
        $field = ['name' => $name, 'label' => $label, 'type' => $type, 'required' => $required];

        return $options === [] ? $field : [...$field, 'options' => $options];
    }
}
