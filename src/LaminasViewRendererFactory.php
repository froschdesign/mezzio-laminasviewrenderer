<?php

/**
 * @see       https://github.com/mezzio/mezzio-laminasviewrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-laminasviewrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-laminasviewrenderer/blob/master/LICENSE.md New BSD License
 */

namespace Mezzio\LaminasView;

use Interop\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver;
use Mezzio\Helper\ServerUrlHelper as BaseServerUrlHelper;
use Mezzio\Helper\UrlHelper as BaseUrlHelper;
use Mezzio\Router\RouterInterface;

/**
 * Create and return a LaminasView template instance.
 *
 * Requires the Mezzio\Router\RouterInterface service (for creating
 * the UrlHelper instance).
 *
 * Optionally requires the Laminas\View\HelperPluginManager service; if present,
 * will use the service to inject the PhpRenderer instance.
 *
 * Optionally uses the service 'config', which should return an array. This
 * factory consumes the following structure:
 *
 * <code>
 * 'templates' => [
 *     'layout' => 'name of layout view to use, if any',
 *     'map'    => [
 *         // template => filename pairs
 *     ],
 *     'paths'  => [
 *         // namespace / path pairs
 *         //
 *         // Numeric namespaces imply the default/main namespace. Paths may be
 *         // strings or arrays of string paths to associate with the namespace.
 *     ],
 * ]
 * </code>
 *
 * Injects the HelperPluginManager used by the PhpRenderer with mezzio
 * overrides of the url and serverurl helpers.
 */
class LaminasViewRendererFactory
{
    /**
     * @param ContainerInterface $container
     * @returns LaminasViewRenderer
     */
    public function __invoke(ContainerInterface $container)
    {
        $config   = $container->has('config') ? $container->get('config') : [];
        $config   = isset($config['templates']) ? $config['templates'] : [];

        // Configuration
        $resolver = new Resolver\AggregateResolver();
        $resolver->attach(
            new Resolver\TemplateMapResolver(isset($config['map']) ? $config['map'] : []),
            100
        );

        // Create the renderer
        $renderer = new PhpRenderer();
        $renderer->setResolver($resolver);

        // Inject helpers
        $this->injectHelpers($renderer, $container);

        // Inject renderer
        $view = new LaminasViewRenderer($renderer, isset($config['layout']) ? $config['layout'] : null);

        // Add template paths
        $allPaths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
        foreach ($allPaths as $namespace => $paths) {
            $namespace = is_numeric($namespace) ? null : $namespace;
            foreach ((array) $paths as $path) {
                $view->addPath($path, $namespace);
            }
        }

        return $view;
    }

    /**
     * Inject helpers into the PhpRenderer instance.
     *
     * If a HelperPluginManager instance is present in the container, uses that;
     * otherwise, instantiates one.
     *
     * In each case, injects with the custom url/serverurl implementations.
     *
     * @param PhpRenderer $renderer
     * @param ContainerInterface $container
     */
    private function injectHelpers(PhpRenderer $renderer, ContainerInterface $container)
    {
        $helpers = $container->has(HelperPluginManager::class)
            ? $container->get(HelperPluginManager::class)
            : ($container->has(\Zend\View\HelperPluginManager::class)
                ? $container->get(\Zend\View\HelperPluginManager::class)
                : new HelperPluginManager($container));

        $helpers->setAlias('url', BaseUrlHelper::class);
        $helpers->setAlias('Url', BaseUrlHelper::class);
        $helpers->setFactory(BaseUrlHelper::class, function () use ($container) {
            if (! $container->has(BaseUrlHelper::class)
                && ! $container->has(\Zend\Expressive\Helper\UrlHelper::class)
            ) {
                throw new Exception\MissingHelperException(sprintf(
                    'An instance of %s is required in order to create the "url" view helper; not found',
                    BaseUrlHelper::class
                ));
            }
            return new UrlHelper($container->has(BaseUrlHelper::class) ? $container->get(BaseUrlHelper::class) : $container->get(\Zend\Expressive\Helper\UrlHelper::class));
        });

        $helpers->setAlias('serverurl', BaseServerUrlHelper::class);
        $helpers->setAlias('serverUrl', BaseServerUrlHelper::class);
        $helpers->setAlias('ServerUrl', BaseServerUrlHelper::class);
        $helpers->setFactory(BaseServerUrlHelper::class, function () use ($container) {
            if (! $container->has(BaseServerUrlHelper::class)
                && ! $container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)
            ) {
                throw new Exception\MissingHelperException(sprintf(
                    'An instance of %s is required in order to create the "url" view helper; not found',
                    BaseServerUrlHelper::class
                ));
            }
            return new ServerUrlHelper($container->has(BaseServerUrlHelper::class) ? $container->get(BaseServerUrlHelper::class) : $container->get(\Zend\Expressive\Helper\ServerUrlHelper::class));
        });

        $renderer->setHelperPluginManager($helpers);
    }
}