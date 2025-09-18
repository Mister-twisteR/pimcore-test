<?php

namespace App\Model\DataObject;

class Product extends \Pimcore\Model\DataObject\Product
{
    public function setName(?string $name): static
    {
        if ($name) {
            $name = mb_strtoupper($name);
        }

        return parent::setName($name);
    }
}
