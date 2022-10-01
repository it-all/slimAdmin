<?php
declare(strict_types=1);

namespace Infrastructure\Database\DataMappers;

Interface ListViewMappers
{
    public function getListViewTitle(): string;
    public function getInsertTitle(): string;
    public function getUpdateColumnName(): ?string;
    public function select(?string $columns = null, ?array $whereColumnsInfo = null, ?string $orderBy = null, ?int $limit = null): ?array;
    public function getCountSelectColumns(): int;
    public function getListViewSortColumn(): ?string;
    public function getListViewSortAscending(): ?bool;
}