<?php namespace Pckg\Charts\Provider;

use Pckg\Framework\Provider;

class Charts extends Provider
{

    public function assets()
    {
        return [
            /**
             * Chart.js
             */
            '/node_modules/chart.js/dist/Chart.min.js',
        ];
    }

}