<?php

namespace App\Providers;

use App\Actions\Passkeys\GeneratePlatformRegistrationOptions;
use App\Http\Responses\PasskeyLoginResponse as AppPasskeyLoginResponse;
use App\Models\BankImportLayout;
use App\Models\Household;
use App\Models\Investment;
use App\Models\User;
use App\Observers\HouseholdObserver;
use App\Observers\InvestmentObserver;
use App\Policies\BankImportLayoutPolicy;
use Artesaos\SEOTools\Facades\JsonLdMulti;
use Carbon\Carbon;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Passkeys;
use Laravel\Pulse\Facades\Pulse;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        AliasLoader::getInstance()->alias('JsonLdMulti', JsonLdMulti::class);

        $this->app->bind(GenerateRegistrationOptions::class, GeneratePlatformRegistrationOptions::class);
        $this->app->singleton(PasskeyLoginResponse::class, AppPasskeyLoginResponse::class);

        // Telescope: require-dev, solo local/staging e TELESCOPE_ENABLED=true. Mai in produzione pubblica.
        // class_exists con stringa: evita autoload fallito se package assente (composer --no-dev).
        if (
            class_exists('Laravel\\Telescope\\TelescopeApplicationServiceProvider')
            && $this->app->environment(['local', 'staging'])
            && filter_var(env('TELESCOPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passkeys::useUserModel(User::class);

        Vite::prefetch(concurrency: 1);
        // Customizza il rendering dei link Markdown per aggiungere rel="nofollow" ai link esterni
        Str::macro('markdownWithNofollow', function ($string, $options = []) {
            $options = array_merge([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ], $options);

            $environment = new Environment($options);
            $environment->addExtension(new CommonMarkCoreExtension);

            // Custom LinkRenderer (delegazione perché LinkRenderer è final)
            $environment->addRenderer(Link::class, new class(new LinkRenderer) implements ConfigurationAwareInterface, NodeRendererInterface
            {
                public function __construct(private readonly LinkRenderer $delegate) {}

                public function setConfiguration(ConfigurationInterface $configuration): void
                {
                    $this->delegate->setConfiguration($configuration);
                }

                public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
                {
                    $element = $this->delegate->render($node, $childRenderer);
                    if ($element instanceof HtmlElement) {
                        $href = $element->getAttribute('href');
                        if ($href && preg_match('/^(https?:)?\/\//i', $href)) {
                            $rel = $element->getAttribute('rel');
                            $rel = $rel ? $rel.' nofollow' : 'nofollow';
                            $element->setAttribute('rel', $rel);
                        }
                    }

                    return $element;
                }
            });

            $converter = new MarkdownConverter($environment);

            return $converter->convert($string)->getContent();
        });

        // Imposta la lingua italiana per Carbon (date)
        Carbon::setLocale('it');

        // Registra observer per la creazione automatica delle categorie
        Household::observe(HouseholdObserver::class);
        Investment::observe(InvestmentObserver::class);

        Gate::policy(BankImportLayout::class, BankImportLayoutPolicy::class);

        Gate::define('viewPulse', function (?User $user) {
            if (! $user) {
                return false;
            }

            $ownerEmail = config('prelaunch.magazine_admin_email', '');

            return $ownerEmail !== ''
                && strtolower($user->email) === strtolower($ownerEmail);
        });

        // Pulse UI: nessun nome/email/Gravatar — solo ID anonimo.
        Pulse::user(fn ($user) => [
            'name' => 'Utente #'.$user->id,
            'extra' => '',
            'avatar' => '',
        ]);
    }
}
