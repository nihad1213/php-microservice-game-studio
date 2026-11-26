<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\SaleFilter;
use App\Domain\SalesReport;

final class ReportService
{
    public function __construct(private SalesReport $report) {}

    public function byGame(SaleFilter $filter): array
    {
        return $this->report->byGame($filter);
    }

    public function byRegion(SaleFilter $filter): array
    {
        return $this->report->byRegion($filter);
    }

    public function monthly(SaleFilter $filter): array
    {
        return $this->report->monthly($filter);
    }
}