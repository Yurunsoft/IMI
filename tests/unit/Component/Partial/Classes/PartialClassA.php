<?php

namespace Imi\Test\Component\Partial\Classes;

use Imi\Bean\Annotation\Bean;

/**
 * @Bean("PartialClassA")
 */
class PartialClassA
{
    /**
     * @return int
     */
    public function test1()
    {
        return 1;
    }
}
