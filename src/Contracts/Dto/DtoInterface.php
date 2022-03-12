<?php

namespace App\Rabbitmq\Contracts\Dto;

use Illuminate\Support\Collection;

interface DtoInterface
{
    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @return string
     */
    public function toJson(): string;

    /**
     * @return Collection
     */
    public function toCollection(): Collection;

    /**
     * @param array $parameters
     * @return AbstractDataObjectTransfer
     */
    public static function make(array $parameters): AbstractDataObjectTransfer;

    /**
     * @return array
     */
    public function only(): array;

    /**
     * @return bool
     */
    public function isEmpty(): bool;
}
