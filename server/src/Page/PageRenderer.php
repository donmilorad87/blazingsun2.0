<?php

declare(strict_types=1);

namespace BlazingSun\Api\Page;

use RuntimeException;

/** Renders templates/page.php for one PageView. */
final readonly class PageRenderer
{
    public function __construct(private string $template)
    {
    }

    public function render(PageView $view): string
    {
        if (!is_file($this->template)) {
            throw new RuntimeException("Page template not found: {$this->template}");
        }
        ob_start();
        try {
            (static function (string $template, PageView $view): void {
                require $template;
            })($this->template, $view);
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }

        return (string) ob_get_clean();
    }
}
