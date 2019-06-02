<?php


namespace ObjectivePHP\DevTools;


use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use ObjectivePHP\Application\AbstractEngine;
use ObjectivePHP\Application\HttpApplicationInterface;
use ObjectivePHP\Application\Package\AbstractPackage;
use ObjectivePHP\Application\Workflow\WorkflowEvent;
use ObjectivePHP\Application\Workflow\WorkflowEventInterface;
use ObjectivePHP\DevTools\DebugBar\Collector\EventsCollector;
use ObjectivePHP\DevTools\DebugBar\Collector\RoutingCollector;
use ObjectivePHP\DevTools\Router\AssetsRouter;
use ObjectivePHP\Events\EventInterface;
use ObjectivePHP\Events\EventsHandler;
use ObjectivePHP\Primitives\String\Camel;
use ObjectivePHP\Router\MetaRouter;
use ObjectivePHP\Router\RouterInterface;
use Zend\Diactoros\Response;

class DevToolsPackage extends AbstractPackage
{


    /**
     * @var DebugBar
     */
    protected $debugBar;

    /**
     * DebuggingToolsPackage constructor.
     */
    public function __construct(AbstractEngine $engine)
    {

        // instantiate the DebugBar
        $debugBar = $this->initDebugBar();
        $this->bindEvents($engine->getEventsHandler());
        $engine->getServicesFactory()->registerRawService(['id' => 'devtools.debugbar', 'instance' => $debugBar]);

        // handle assets from vendor


    }

    public function onPackagesInit(WorkflowEventInterface $event)
    {
        $application = $event->getApplication();

        if ($application instanceof HttpApplicationInterface) {

            /** @var RouterInterface $router */
            $router = $event->getApplication()->getRouter();
            if ($router instanceof MetaRouter) {
                $router->registerRouter(new AssetsRouter());
            }

            // plug debug bar
            $event->getApplication()->getEventsHandler()->bind(WorkflowEvent::RESPONSE_READY, [$this, 'addDebugBar'], EventsHandler::BINDING_MODE_FIRST);

        }
    }

    public function addDebugBar(WorkflowEvent $event)
    {
        /** @var StandardDebugBar $debugbar */
        $debugbar = $event->getApplication()->getServicesFactory()->get('devtools.debugbar');

        /** @var Response $response */
        $response = $event->getContext()['response'];

        $body = $response->getBody();
        $body->rewind();
        $output = $body->getContents();

        $output = str_replace('</head>', $debugbar->getJavascriptRenderer()->setBaseUrl('/assets/debugbar')->renderHead() . '</head>', $output);
        $output = str_replace('</body>', $debugbar->getJavascriptRenderer()->render() . '</body>', $output);

        $body->rewind();
        $body->write($output);

    }

    public function initDebugBar(): DebugBar
    {
        $this->debugBar = new StandardDebugBar();

        $this->debugBar->addCollector(new EventsCollector());

        return $this->debugBar;

    }

    public function bindEvents(EventsHandler $eventsHandler)
    {
        $this->bindEventsCollector($eventsHandler);
        $this->bindTimeDataColector($eventsHandler);
    }

    public function bindTimeDataColector(EventsHandler $eventsHandler)
    {


        $eventsHandler->bind('*.start', [$this, 'collectTimeDataBegin']);
        $eventsHandler->bind('*.done', [$this, 'collectTimeDataEnd']);
    }

    public function collectTimeDataBegin(EventInterface $event)
    {
        /** @var TimeDataCollector $collector */
        $collector = $this->debugBar->getCollector('time');

        $name = explode('.', (string) $event->getName());
        array_pop($name);

        $label = '';
        foreach ($name as $part) {
            $label .= Camel::case($part) . ' ';
        }
        if(is_object($origin = $event->getOrigin())) {
            $label .= 'from ' . get_class($origin);
        }
        $collector->startMeasure(implode('.', $name), $label);


    }

    public function collectTimeDataEnd(WorkflowEvent $event)
    {
        /** @var TimeDataCollector $collector */
        $collector = $this->debugBar->getCollector('time');

        $name = explode('.', (string) $event->getName());
        array_pop($name);

        if($collector->hasStartedMeasure(implode('.', $name)))
        $collector->stopMeasure(implode('.', $name));


    }

    public function bindEventsCollector(EventsHandler $eventsHandler)
    {
        $eventsHandler->bind('*', [$this, 'collectEventsData']);
    }

    public function collectEventsData(EventInterface $event)
    {

        $debugBar = $this->getDebugBar()['events']->storeEvent($event);
    }

    /**
     * @return DebugBar
     */
    public function getDebugBar(): DebugBar
    {
        return $this->debugBar;
    }

    /**
     * @param DebugBar $debugBar
     * @return DebuggingToolsPackage
     */
    public function setDebugBar(DebugBar $debugBar): DebuggingToolsPackage
    {
        $this->debugBar = $debugBar;
        return $this;
    }


}
