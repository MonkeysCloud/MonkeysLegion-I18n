<?php

declare(strict_types=1);

/**
 * Example: Integrating I18n with MonkeysLegion Services
 * 
 * This example shows how to integrate the I18n package with:
 * - MonkeysLegion Router
 * - MonkeysLegion Template Engine
 * - MonkeysLegion Cache
 * - MonkeysLegion Database
 */

namespace App\Bootstrap;

use MonkeysLegion\I18n\TranslatorFactory;
use MonkeysLegion\I18n\Middleware\LocaleMiddleware;
use MonkeysLegion\I18n\Middleware\LocaleUrlMiddleware;
use MonkeysLegion\I18n\Template\I18nTemplateExtension;
use Psr\Container\ContainerInterface;

class I18nServiceProvider
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Register I18n services
     */
    public function register(): void
    {
        // Load configuration
        $config = require __DIR__ . '/../config/i18n.php';
        
        // Get cache from container (MonkeysLegion-Cache)
        $cache = $this->container->has('cache') 
            ? $this->container->get('cache') 
            : null;
        
        // Get PDO from container (MonkeysLegion-Database)
        $pdo = $this->container->has('pdo') 
            ? $this->container->get('pdo') 
            : null;
        
        // Create I18n system
        $system = TranslatorFactory::createSystem(array_merge($config, [
            'cache' => $cache,
            'pdo' => $pdo,
        ]));
        
        // Register in container
        $this->container->set('translator', $system['translator']);
        $this->container->set('locale.manager', $system['manager']);
        
        // Register middleware with router
        $this->registerMiddleware($system);
        
        // Register template directives
        $this->registerTemplateDirectives($system['translator']);
    }

    /**
     * Register middleware with router
     */
    private function registerMiddleware(array $system): void
    {
        if (!$this->container->has('router')) {
            return;
        }
        
        $router = $this->container->get('router');
        
        // Global locale detection middleware
        $router->middleware(new LocaleMiddleware(
            $system['manager'],
            $system['translator']
        ));
        
        // URL-based locale middleware for localized routes
        $router->group(['prefix' => '{locale}', 'middleware' => [
            new LocaleUrlMiddleware($system['manager'], $system['translator'])
        ]], function($router) {
            // All localized routes go here
            require __DIR__ . '/../routes/web.php';
        });
    }

    /**
     * Register template directives
     */
    private function registerTemplateDirectives($translator): void
    {
        if (!$this->container->has('template')) {
            return;
        }
        
        $template = $this->container->get('template');
        
        $extension = new I18nTemplateExtension($translator);
        $extension->register($template);
    }
}

// =============================================================================
// Example: Using in a Controller
// =============================================================================

namespace App\Controllers;

use MonkeysLegion\I18n\Translator;

class ProductController
{
    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function index()
    {
        // Get products from database
        $products = Product::all();
        
        // Translate messages
        $title = $this->translator->trans('products.list.title');
        $description = $this->translator->trans('products.list.description', [
            'count' => count($products)
        ]);
        
        return view('products.index', [
            'products' => $products,
            'title' => $title,
            'description' => $description
        ]);
    }

    public function show(int $id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            throw new NotFoundException(
                $this->translator->trans('products.not_found')
            );
        }
        
        return view('products.show', [
            'product' => $product
        ]);
    }

    public function switchLocale(string $locale)
    {
        // Validate locale
        if (!in_array($locale, ['en', 'es', 'fr'])) {
            return redirect()->back();
        }
        
        // Set locale
        $this->translator->setLocale($locale);
        
        // Store in session
        $_SESSION['locale'] = $locale;
        
        // Redirect back with success message
        return redirect()->back()->with('success', 
            $this->translator->trans('messages.locale_changed')
        );
    }
}

// =============================================================================
// Example: Using in Templates
// =============================================================================

?>

<!-- resources/views/products/index.blade.php -->
<!DOCTYPE html>
<html lang="@locale">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('products.list.title')</title>
</head>
<body>
    <!-- Language Switcher -->
    <nav class="language-switcher">
        <a href="/en{{ $currentPath }}">English</a>
        <a href="/es{{ $currentPath }}">Español</a>
        <a href="/fr{{ $currentPath }}">Français</a>
    </nav>

    <h1>@lang('products.list.title')</h1>
    <p>@lang('products.list.description', ['count' => count($products)])</p>

    <!-- Product List -->
    <div class="products">
        @foreach($products as $product)
            <div class="product-card">
                <h3>{{ $product->name }}</h3>
                <p class="price">@currency($product->price, 'USD')</p>
                <p class="stock">
                    @choice('products.stock', $product->quantity, [
                        'count' => $product->quantity
                    ])
                </p>
                <a href="/{{ lang() }}/products/{{ $product->id }}">
                    @lang('common.view_details')
                </a>
            </div>
        @endforeach
    </div>
</body>
</html>

<?php

// =============================================================================
// Example: API Endpoints with I18n
// =============================================================================

namespace App\Api\Controllers;

class ApiProductController
{
    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function index()
    {
        // Get locale from Accept-Language header
        $locale = $this->getLocaleFromHeader();
        $this->translator->setLocale($locale);
        
        $products = Product::all()->map(function($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'currency' => 'USD',
                'formatted_price' => $this->formatPrice($product->price, $locale),
                'stock_message' => $this->translator->choice(
                    'products.stock', 
                    $product->quantity,
                    ['count' => $product->quantity]
                )
            ];
        });
        
        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('api.products.retrieved'),
            'data' => $products
        ]);
    }

    private function getLocaleFromHeader(): string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
        $locale = substr($header, 0, 2);
        return in_array($locale, ['en', 'es', 'fr']) ? $locale : 'en';
    }

    private function formatPrice(float $price, string $locale): string
    {
        return $this->translator->trans('format.currency', [
            'amount' => $price,
            'currency' => 'USD'
        ]);
    }
}

// =============================================================================
// Example: Email Templates with I18n
// =============================================================================

namespace App\Mail;

use MonkeysLegion\I18n\Translator;

class OrderConfirmationMail
{
    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function send(Order $order)
    {
        // Set locale based on user preference
        $this->translator->setLocale($order->user->locale);
        
        $subject = $this->translator->trans('emails.order.subject', [
            'order_id' => $order->id
        ]);
        
        $body = $this->renderTemplate('emails.order_confirmation', [
            'order' => $order,
            'greeting' => $this->translator->trans('emails.greeting', [
                'name' => $order->user->name
            ]),
            'total' => $this->formatCurrency($order->total),
            'message' => $this->translator->trans('emails.order.message'),
            'footer' => $this->translator->trans('emails.footer')
        ]);
        
        return $this->mailer->send(
            $order->user->email,
            $subject,
            $body
        );
    }

    private function formatCurrency(float $amount): string
    {
        return $this->translator->trans('format.currency', [
            'amount' => $amount
        ]);
    }
}

// =============================================================================
// Example: Validation Messages with I18n
// =============================================================================

namespace App\Validation;

class Validator
{
    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && empty($data[$field])) {
                $errors[$field] = $this->translator->trans('validation.required', [
                    'field' => $this->getFieldName($field)
                ]);
            }
            
            if ($rule === 'email' && !filter_var($data[$field] ?? '', FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = $this->translator->trans('validation.email', [
                    'field' => $this->getFieldName($field)
                ]);
            }
        }
        
        return $errors;
    }

    private function getFieldName(string $field): string
    {
        // Try to get translated field name
        $key = "validation.attributes.{$field}";
        
        if ($this->translator->has($key)) {
            return $this->translator->trans($key);
        }
        
        // Fallback to field name
        return str_replace('_', ' ', $field);
    }
}
