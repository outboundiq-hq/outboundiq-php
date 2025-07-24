<?php

namespace OutboundIQ\Transports;

use OutboundIQ\Contracts\MetricInterface;

interface TransportInterface
{
    /**
     * Add a metric to the queue
     *
     * @param MetricInterface $metric
     * @return mixed
     */
    public function addMetric(MetricInterface $metric);

    /**
     * Send data to OutboundIQ server
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Reset the queue
     *
     * @return void
     */
    public function resetQueue(): void;
} 