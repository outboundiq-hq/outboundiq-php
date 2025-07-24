<?php

namespace OutboundIQ\Contracts;

interface MetricInterface
{
    /**
     * Convert the metric to an array format for transmission
     *
     * @return array
     */
    public function toArray(): array;
} 