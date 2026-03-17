<?php

namespace App\Reports\DataProviders;

interface ReportDataProviderInterface
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<int|string, mixed>
     */
    public function resolve(array $params, array $context = []): array;
}
