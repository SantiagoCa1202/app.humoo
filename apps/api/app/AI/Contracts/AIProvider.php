<?php

namespace App\AI\Contracts;

interface AIProvider
{
    public function generate(array $context): array;
}
