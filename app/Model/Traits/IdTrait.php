<?php

declare(strict_types=1);

namespace Energycalculator\Model\Traits;

trait IdTrait
{
    /**
     * @var string
     */
    private $id;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }
}
