<?php


namespace ObjectivePHP\DevTools\DebugBar\Collector;


use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\Renderable;
use ObjectivePHP\Events\EventInterface;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class EventsCollector implements DataCollectorInterface, Renderable
{
    /**
     * @var array
     */
    protected $events = [];

    protected $varDumper;

    /**
     * EventsCollector constructor.
     */
    public function __construct()
    {
        $this->varDumper = DataCollector::getDefaultVarDumper();
    }


    function collect()
    {
        return [
            'events' => $this->events,
            'count' => count($this->events)
            ];
    }

    public function storeEvent(EventInterface $event)
    {
        $message = (string) $event->getName();

        if(is_object($origin = $event->getOrigin()))
        {
            $message .= ' from <i>' . get_class($origin) . '</i>';
        }
        $this->events[] = [
            'message' => (string) $event->getName() ,
            'message_html' => $message ,
            'is_string' => true,
            'label' => get_class($event),
            'time' => microtime(true)
        ];

    }

    function getName()
    {
        return 'events';
    }

    /**
     * @return array
     */
    public function getWidgets()
    {
        $widget = "PhpDebugBar.Widgets.MessagesWidget";
        return [
            "events" => [
                "widget" => $widget,
                "map" => "events.events",
                "default" => "[]"
            ],
            "events:badge" => [
                "map" => "events.count",
                "default" => "0"
            ]
        ];
    }

}
