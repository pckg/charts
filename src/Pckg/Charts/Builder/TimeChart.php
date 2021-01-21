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

    protected $minDate = '-3months';

    protected $groupBy;

    protected $stack;

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

    public function setMinDate(string $date)
    {
        $this->minDate = $date;

        return $this;
    }

    public function setGroupBy(string $groupBy)
    {
        $this->groupBy = $groupBy;

        return $this;
    }

    public function setStack(string $stack)
    {
        $this->stack = $stack;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getData()
    {
        $minDate = date('Y-m-d 00:00:00', strtotime($this->minDate));
        $maxDate = date('Y-m-d 00:00:00', strtotime('+1 day'));

        $entity = $this->entity->select(
            [
                'status' => Raw::raw($this->status),
                'step' => Raw::raw($this->step),
            ]
        )
            ->groupBy($this->groupBy ?? 'step, status');

        if (!$this->dimension) {
            $entity->addSelect(['count' => 'COUNT(' . $this->entity->getTable() . '.id)']);
        } else if (is_array($this->dimension)) {
            foreach ($this->dimension as $k => $dim) {
                $entity->addSelect([$k => $dim]);
            }
        }

        if ($this->stack) {
            $entity->addSelect([
                'stack' => $this->stack,
            ]);
        }

        if ($this->timeField) {
            $entity->where($entity->getTable() . '.' . $this->timeField, $minDate, '>');
        }

        $data = $entity->all();

        $date = new Carbon($minDate);
        $times = [];
        /**
         * Prepare times.
         */
        while ($maxDate > $date) {
            // fill times and increase date
            ($this->carbonStep)($date, $times);
        }

        $statuses = [];
        $explodedStatus = explode('.', $this->status);
        $realStatus = count($explodedStatus) > 2 ? 'status' : end($explodedStatus);
        $data->each(
            function ($record) use (&$times, &$statuses, $realStatus) {
                $addition = $this->groupBy ? (':' . $record->stack) : '';
                if (!$this->dimension) {
                    $times[$record->step][$record->{$realStatus} . $addition] = $record->count;
                    $statuses[$record->{$realStatus} . $addition][$record->step] = $record->count;
                } else if (is_array($this->dimension)) {
                    foreach ($this->dimension as $k => $dim) {
                        $times[$record->step][$record->{$realStatus} . $addition . ':' . $k] = $record->{$k};
                        $statuses[$record->{$realStatus} . $addition . ':' . $k][$record->step] = $record->{$k};
                    }
                }
            }
        );

        $chart = [
            'labels' => array_keys($times),
            'datasets' => [],
        ];

        $clrs = [
            'rgba(255, 0, 0, 0.5)',
            'rgba(0, 255, 0, 0.5)',
            'rgba(0, 0, 255, 0.5)',
            'rgba(127, 127, 127, 0.5)',
            'rgba(255, 255, 0, 0.5)',
            'rgba(0, 255, 255, 0.5)',
            'rgba(255, 0, 255, 0.5)',
            'rgba(0, 0, 0, 0.5)',
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

        foreach ($statuses as $status => $statusTimes) {
            $dataset = [
                'label' => $status,
                'data' => [],
                'borderColor' => $colors[$status],
                'backgroundColor' => 'transparent',
                'borderWidth' => 2,
            ];
            foreach ($times as $time => $timeStatuses) {
                $dataset['data'][] = $statusTimes[$time] ?? 0;
            }
            $chart['datasets'][] = $dataset;
        }
        $dataset = [
            'label' => 'total',
            'data' => [],
            'borderColor' => $colors['total'],
            'backgroundColor' => 'transparent',
            'borderWidth' => 1,
        ];
        foreach ($times as $time => $statuses) {
            $total = 0;
            foreach ($statuses as $status) {
                $total += $status;
            }
            $dataset['data'][] = $total;
        }
        $chart['datasets'][] = $dataset;

        return $chart;
    }

}