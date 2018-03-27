<?php namespace Pckg\Charts\Builder;

use Carbon\Carbon;
use Pckg\Database\Entity;
use Pckg\Database\Query\Raw;

class TimeChart
{

    /**
     * @var Entity
     */
    protected $entity;

    protected $timeField;

    protected $step;

    protected $status;

    protected $carbonStep;

    protected $dataStep;

    protected $dimension = null;

    public function setDimension($dimension)
    {
        $this->dimension = $dimension;

        return $this;
    }

    public function setEntity(Entity $entity)
    {
        $this->entity = $entity;

        return $this;
    }

    public function setTimeField($field)
    {
        $this->timeField = $field;

        return $this;
    }

    public function setStep($step)
    {
        $this->step = $step;

        return $this;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function setCarbonStep(callable $carbonStep)
    {
        $this->carbonStep = $carbonStep;

        return $this;
    }

    public function prepareSelect(Entity $entity = null)
    {
        if (!$entity) {
            $entity = $this->entity;
        }

        //$minDate = date('Y-m-d', strtotime('-6 months'));

        $entity->select(
            [
                'status' => is_object($this->status) ? $this->status : Raw::raw($this->status),
                'step'   => Raw::raw($this->step),
            ]
        )
               ->groupBy('step, status');

        $entity->addSelect($this->getDimensions());

        if ($this->timeField) {
            //$entity->where($this->timeField, $minDate, '>');
        }

        return $entity;
    }

    private function getDimensions()
    {
        $dimensions = [];
        if (!$this->dimension) {
            $dimensions['count'] = 'COUNT(' . $this->entity->getTable() . '.id)';
        } elseif (!is_array($this->dimension)) {
            $dimensions = ['count' => $this->dimension];
        } else {
            $dimensions = $this->dimension;
        }

        return $dimensions;
    }

    public function getData()
    {
        $this->prepareSelect();
        $data = $this->entity->all();

        $minDate = date('Y-m-d', strtotime($data->min($this->timeField)));
        $maxDate = date('Y-m-d', strtotime($data->max($this->timeField)));
        $date = new Carbon($minDate);
        $times = [];
        /**
         * Prepare times.
         */
        while ($maxDate > $date) {
            // fill times and increase date
            $callback = $this->carbonStep;
            $callback($date, $times);
        }

        $statuses = [];
        $dimensions = $this->getDimensions();
        $data->each(
            function($record) use (&$times, &$statuses, $dimensions) {
                foreach ($dimensions as $key => $val) {
                    $times[$record->step][$record->status][$key] = $record->{$key};
                    $statuses[$record->status][$record->step][$key] = $record->{$key};
                }
            }
        );

        $chart = [
            'labels'   => array_keys($times),
            'datasets' => [],
        ];

        $clrs = [
            'rgba(0, 255, 0, 0.5)',
            'rgba(255, 0, 0, 0.5)',
            'rgba(0, 0, 255, 0.5)',
            'rgba(100, 100, 100, 0.5)',
            'rgba(50, 50, 50, 0.5)',
        ];
        $colors = [
            'total' => 'rgba(0, 0, 0, 0.5)',
        ];
        foreach (array_keys($statuses) as $status) {
            if ($clrs) {
                $colors[$status] = array_pop($clrs);
            } else {
                $colors[$status] = 'rgba(' . rand(0, 255) . ', ' . rand(0, 255) . ', ' . rand(0, 255) . ', 0.5)';
            }
        }

        foreach ($dimensions as $dimensionKey => $dimVal) {
            foreach ($statuses as $status => $statusTimes) {
                $dataset = [
                    'label'           => $status . '-' . $dimensionKey,
                    'data'            => [],
                    'borderColor'     => $colors[$status],
                    'backgroundColor' => 'transparent',
                    'borderWidth'     => 2,
                ];
                foreach ($times as $time => $timeStatuses) {
                    $dataset['data'][] = $statusTimes[$time][$dimensionKey] ?? 0;
                }
                $chart['datasets'][] = $dataset;
            }
        }
        $dataset = [
            'label'           => 'total',
            'data'            => [],
            'borderColor'     => $colors['total'],
            'backgroundColor' => 'transparent',
            'borderWidth'     => 1,
        ];
        foreach ($dimensions as $dimensionKey => $dimVal) {
            foreach ($times as $time => $statuses) {
                $total = 0;
                foreach ($statuses as $status) {
                    $total += $status[$dimensionKey];
                }
                $dataset['data'][] = $total;
            }
        }
        $chart['datasets'][] = $dataset;

        return $chart;
    }

}