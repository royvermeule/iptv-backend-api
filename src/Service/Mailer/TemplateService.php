<?php

declare(strict_types=1);

namespace App\Service\Mailer;

class TemplateService
{
    private string $templateDir;

    public function __construct(?string $templateDir = null)
    {
        $this->templateDir = $templateDir ?? dirname(__DIR__, 3) . '/templates/emails';
    }

    public function render(string $template, array $variables): string
    {
        $path = $this->templateDir . '/' . $template;

        if (!file_exists($path)) {
            throw new \RuntimeException("Email template not found: {$template}");
        }

        $html = file_get_contents($path);

        foreach ($variables as $key => $value) {
            $html = str_replace('{{' . $key . '}}', htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $html);
        }

        return $html;
    }
}
