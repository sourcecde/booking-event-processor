<?php

namespace App\Services\Interfaces;

interface ProcessorInterface
{
    public function process(array $message);
}
