<?php

namespace App\Rabbitmq\Dto\Products;

use App\Rabbitmq\Contracts\Dto\AbstractDataObjectTransfer;

/**
 * @property int $id
 * @property mixed $name
 */
class ProductCreateObject extends AbstractDataObjectTransfer
{

    /**
     * @inheritDoc
     */
    public function only(): array
    {
        return [
            'id' =>  $this->id,
            'name' =>  $this->name
        ];
    }
}
