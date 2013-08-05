<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Versioning;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\ModuleManager\Listener\ConfigListener;
use Zend\ModuleManager\ModuleEvent;

class PrototypeRouteListener extends AbstractListenerAggregate
{
    /**
     * Route prototype to add to router
     * @var array
     */
    protected $versionRoutePrototype = array(
        'zf_ver_version' => array(
            'type' => 'segment',
            'options' => array(
                'route' => '/v:version',
                'constraints' => array(
                    'version' => '\d+',
                ),
                'defaults' => array(
                    'version' => 1,
                ),
            ),
        ),
    );

    /**
     * Attach listener to ModuleEvent::EVENT_MERGE_CONFIG
     *
     * @param  EventManagerInterface $events
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, array($this, 'onMergeConfig'));
    }

    /**
     * Listen to ModuleEvent::EVENT_MERGE_CONFIG
     *
     * Looks for zf-versioning.url and router configuration; if both present,
     * injects the route prototype and adds a chain route to each route listed
     * in the zf-versioning.url array.
     *
     * @param  ModuleEvent $e
     */
    public function onMergeConfig(ModuleEvent $e)
    {
        $configListener = $e->getConfigListener();
        if (!$configListener instanceof ConfigListener) {
            return;
        }

        $config = $configListener->getMergedConfig(false);

        // Check for config keys
        if (!isset($config['zf-versioning'])
            || !isset($config['router'])
        ) {
            return;
        }

        // Do we need to inject a prototype?
        if (!isset($config['zf-versioning']['uri'])
            || !is_array($config['zf-versioning']['uri'])
            || empty($config['zf-versioning']['uri'])
        ) {
            return;
        }

        // Insert route prototype
        if (!isset($config['router']['prototypes'])) {
            $config['router']['prototypes'] = $this->versionRoutePrototype;
        } else {
            $config['router']['prototypes'] = array_merge(
                $config['router']['prototypes'],
                $this->versionRoutePrototype
            );
        }

        // Pre-process route list to strip out duplicates (often a result of
        // specifying nested routes)
        $routes   = $config['zf-versioning']['uri'];
        $filtered = array();
        foreach ($routes as $index => $route) {
            if (strstr($route, '/')) {
                $temp  = explode('/', $route, 2);
                $route = array_shift($temp);
            }
            if (in_array($route, $filtered)) {
                continue;
            }
            $filtered[] = $route;
        }
        $routes = $filtered;

        // Inject chained routes
        foreach ($routes as $routeName) {
            if (!isset($config['router']['routes'][$routeName])) {
                continue;
            }
            if (!isset($config['router']['routes'][$routeName]['chain_routes'])) {
                $config['router']['routes'][$routeName]['chain_routes'] = array('zf_ver_version');
            } else {
                $config['router']['routes'][$routeName]['chain_routes'][] = 'zf_ver_version';
            }
        }

        // Reset merged config
        $configListener->setMergedConfig($config);
    }
}