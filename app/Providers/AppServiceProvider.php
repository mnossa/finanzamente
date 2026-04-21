<?php

namespace App\Providers;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Util\UrlEncoder;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use App\Models\Household;
use App\Observers\HouseholdObserver;
use Carbon\Carbon;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        AliasLoader::getInstance()->alias('JsonLdMulti', \Artesaos\SEOTools\Facades\JsonLdMulti::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        // Customizza il rendering dei link Markdown per aggiungere rel="nofollow" ai link esterni
        Str::macro('markdownWithNofollow', function ($string, $options = []) {
            $options = array_merge([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ], $options);

            $environment = new Environment($options);
            $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());

            // Custom LinkRenderer (delegazione perché LinkRenderer è final)
            $environment->addRenderer(Link::class, new class(new LinkRenderer()) implements \League\CommonMark\Renderer\NodeRendererInterface, ConfigurationAwareInterface {
                public function __construct(private readonly LinkRenderer $delegate) {}

                public function setConfiguration(ConfigurationInterface $configuration): void
                {
                    $this->delegate->setConfiguration($configuration);
                }

                public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
                {
                    $element = $this->delegate->render($node, $childRenderer);
                    if ($element instanceof \League\CommonMark\Util\HtmlElement) {
                        $href = $element->getAttribute('href');
                        if ($href && preg_match('/^(https?:)?\/\//i', $href)) {
                            $rel = $element->getAttribute('rel');
                            $rel = $rel ? $rel . ' nofollow' : 'nofollow';
                            $element->setAttribute('rel', $rel);
                        }
                    }
                    return $element;
                }
            });

            $converter = new \League\CommonMark\MarkdownConverter($environment);
            return $converter->convert($string)->getContent();
        });

        // Imposta la lingua italiana per Carbon (date)
        Carbon::setLocale('it');

        // Registra observer per la creazione automatica delle categorie
        Household::observe(HouseholdObserver::class);

        Gate::policy(\App\Models\BankImportLayout::class, \App\Policies\BankImportLayoutPolicy::class);
    }
}
