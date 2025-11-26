<?php
namespace App\Events;

class OrderCreateRequestedEvent
{
    public array $productList;

    public function __construct(array $productList)
    {
        $this->productList = array_values($productList);
    }
}

