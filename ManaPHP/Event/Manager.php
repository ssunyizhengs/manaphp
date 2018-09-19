<?php

namespace ManaPHP\Event;

/**
 * Class ManaPHP\Event\Manager
 *
 * @package eventsManager
 */
class Manager implements ManagerInterface
{
    /**
     * @var array
     */
    protected $_events = [];

    /**
     * @var array
     */
    protected $_peeks = [];

    /**
     * Attach a listener to the events manager
     *
     * @param string           $event
     * @param callable|\object $handler
     *
     * @return void
     */
    public function attachEvent($event, $handler)
    {
        if (!$handler instanceof \Closure && is_object($handler)) {
            $parts = explode(':', $event);

            $method = 'on' . ucfirst($parts[0]) . ucfirst($parts[1]);
            $handler = [$handler, $method];
        }

        $this->_events[$event][] = $handler;
    }

    /**
     * Fires an event in the events manager causing that active listeners be notified about it
     *
     *<code>
     *    $eventsManager->fire('db', $connection);
     *</code>
     *
     * @param string                         $event
     * @param \ManaPHP\Component|\ManaPHP\Di $source
     * @param array                          $data
     *
     * @return bool|null
     */
    public function fireEvent($event, $source, $data = [])
    {
        $handlers = [];
        if (isset($this->_peeks['*'])) {
            $handlers = $this->_peeks['*'];
        }

        list($p1, $p2) = explode(':', $event);
        if (isset($this->_peeks[$p1]['*'])) {
            $handlers = array_merge($handlers, $this->_peeks[$p1]['*']);
        }

        if (isset($this->_peeks[$p1][$p2])) {
            $handlers = array_merge($handlers, $this->_peeks[$p1][$p2]);
        }

        foreach ((array)$handlers as $handler) {
            if ($handler instanceof \Closure) {
                $handler($event, $source, $data);
            } else {
                $handler[0]->{$handler[1]}($event, $source, $data);
            }
        }

        if (!isset($this->_events[$event])) {
            return null;
        }

        $ret = null;
        /** @noinspection ForeachSourceInspection */
        foreach ($this->_events[$event] as $i => $handler) {
            if ($handler instanceof \Closure) {
                $ret = $handler($source, $data, $event);
            } else {
                $ret = $handler[0]->{$handler[1]}($source, $data, $event);
            }

            if ($ret === false) {
                return false;
            }
        }

        return $ret;
    }

    /**
     * @param string   $event
     * @param callable $handler
     *
     * @return static
     */
    public function peekEvent($event, $handler)
    {
        if ($event === '*') {
            $this->_peeks['*'][] = $handler;
        } else {
            list($p1, $p2) = explode(':', $event);
            $this->_peeks[$p1][$p2][] = $handler;
        }

        return $this;
    }
}