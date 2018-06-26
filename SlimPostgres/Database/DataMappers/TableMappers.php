<?php
declare(strict_types=1);

namespace SlimPostgres\Database\DataMappers;

Interface TableMappers
{
    public function select(string $columns = "*", array $whereColumnsInfo = null);
    public function getSelectColumnsString();
    public function getTableName(bool $plural = true): string;
    public function getOrderByColumnName(): ?string;
    public function getOrderByAsc(): bool;
}