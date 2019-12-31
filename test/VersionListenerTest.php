<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-versioning for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-versioning/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-versioning/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\Versioning;

use Laminas\ApiTools\Versioning\VersionListener;
use Laminas\EventManager\EventManager;
use Laminas\Http\Request;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router\RouteMatch;
use PHPUnit_Framework_TestCase as TestCase;

class VersionListenerTest extends TestCase
{
    public function setUp()
    {
        $this->event = new MvcEvent();
        $this->event->setRouteMatch(new RouteMatch(array()));

        $this->listener = new VersionListener();
    }

    public function testAttachesToRouteEventAtNegativePriority()
    {
        $events = new EventManager();
        $events->attach($this->listener);
        $listeners = $events->getListeners('route');
        $this->assertEquals(1, count($listeners));
        $this->assertTrue($listeners->hasPriority(-41));
        $callback = $listeners->getIterator()->current()->getCallback();
        $test     = array_shift($callback);
        $this->assertSame($this->listener, $test);
    }

    public function testDoesNothingIfNoRouteMatchPresentInEvent()
    {
        $event = new MvcEvent();
        $this->assertNull($this->listener->onRoute($event));
    }

    public function testDoesNothingIfNoVersionAndNoLaminasVerVersionParameterInRouteMatch()
    {
        $this->assertNull($this->listener->onRoute($this->event));
    }

    public function testDoesNothingIfNoControllerParameterInRouteMatch()
    {
        $matches = $this->event->getRouteMatch();
        $matches->setParam('version', 2);
        $this->assertNull($this->listener->onRoute($this->event));
    }

    public function testDoesNothingIfControllerHasNoVersionNamespace()
    {
        $matches = $this->event->getRouteMatch();
        $matches->setParam('version', 2);
        $matches->setParam('controller', 'Foo\Bar\Controller');
        $this->assertNull($this->listener->onRoute($this->event));
    }

    public function testDoesNothingIfVersionAndControllerVersionNamespaceAreSame()
    {
        $matches = $this->event->getRouteMatch();
        $matches->setParam('version', 2);
        $matches->setParam('controller', 'Foo\V2\Rest\Bar\Controller');
        $this->assertNull($this->listener->onRoute($this->event));
    }

    public function testAltersControllerVersionNamespaceToReflectVersion()
    {
        $matches = $this->event->getRouteMatch();
        $matches->setParam('version', 2);
        $matches->setParam('controller', 'Foo\V1\Rest\Bar\Controller');
        $result = $this->listener->onRoute($this->event);
        $this->assertInstanceOf('Laminas\Mvc\Router\RouteMatch', $result);
        $this->assertEquals('Foo\V2\Rest\Bar\Controller', $result->getParam('controller'));
    }
}
