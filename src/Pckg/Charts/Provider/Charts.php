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
            '/bower_components/chart.js/dist/Chart.min.js',
        ];
    }

}