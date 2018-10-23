<?php
declare(strict_types=1);

namespace Infrastructure\Database\DataMappers;

Interface TableMappers
{
    public function select(string $columns = "*", array $whereColumnsInfo = null);
    public function getDefaultSelectColumnsString();
    public function getTableName(): string;
    public function getOrderByColumnName(): ?string;
    public function getOrderByAsc(): bool;
}