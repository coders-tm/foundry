<?php

namespace Foundry\Concerns;

use Foundry\Models\Module;
use Foundry\Models\Permission;
use Foundry\Services\Helpers as ServicesHelpers;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Reader;
use PDO;

trait Helpers
{
    // jsonable column to migration table
    protected function jsonable(): string
    {
        $driverName = DB::connection()->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $dbVersion = DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        $isOldVersion = version_compare($dbVersion, '5.7.8', 'lt');

        return $driverName === 'mysql' && $isOldVersion ? 'text' : 'json';
    }

    protected function getFilters($conditions = [])
    {
        $filters = [];
        foreach ($conditions as $condition) {
            $type = $condition['type'];
            if (Str::contains($type, ':')) {
                $fields = explode(':', $type);
                $filters[$fields[0]][] = $this->getRelation($condition, $fields[1]);
            } else {
                $filters[$type] = $this->getRelation($condition);
            }
        }

        return $filters;
    }

    public function getRelation($condition = [], $field = false)
    {
        $relation = false;
        $type = $field ? $field : $condition['type'];
        $value = $condition['value'];
        switch ($condition['relation']) {
            case 'EQUALS':
                $relation = [$type, '=', $value];
                break;
            case 'NOT_EQUALS':
                $relation = [$type, '<>', $value];
                break;
            case 'GREATER_THAN':
                $relation = [$type, '>', $value];
                break;
            case 'LESS_THAN':
                $relation = [$type, '<', $value];
                break;
            case 'STARTS_WITH':
                $relation = [$type, 'like', "{$value}%"];
                break;
            case 'ENDS_WITH':
                $relation = [$type, 'like', "%{$value}"];
                break;
            case 'CONTAINS':
                $relation = [$type, 'like', "%{$value}%"];
                break;
            case 'NOT_CONTAINS':
                $relation = [$type, 'not like', "%{$value}%"];
                break;
            case 'IS_NULL':
                $relation = [$type, 'is', 'null'];
                break;
            case 'IS_NOT_NULL':
                $relation = [$type, 'is not', 'null'];
                break;
        }

        return $relation;
    }

    protected function csvToArray($filename = '', $no_header = false, $delimiter = ',')
    {
        if (! file_exists($filename) || ! is_readable($filename)) {
            return false;
        }

        try {
            $header = null;
            $data = [];
            if (($handle = fopen($filename, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if ($no_header) {
                        $rowData = [];
                        $empty = 0;
                        foreach ($row as $i => $item) {
                            $key = $i + 1;
                            $rowData["field{$key}"] = $item;
                            if (empty($rowData["field{$key}"])) {
                                $empty++;
                            }
                        }
                        if ($empty != count($rowData)) {
                            $data[] = $rowData;
                        }
                    } else {
                        if (! $header) {
                            $header = $row;
                        } else {
                            $rowData = [];
                            $empty = 0;
                            foreach ($header as $i => $head) {
                                $field = Str::slug($head, '_');
                                $rowData[$field] = isset($row[$i]) && $row[$i] != 'NULL' ? $row[$i] : null;
                                if (empty($rowData[$field])) {
                                    $empty++;
                                }
                            }
                            if ($empty != count($rowData)) {
                                $data[] = $rowData;
                            }
                        }
                    }
                }
                fclose($handle);
            }

            return $data;
        } catch (\Throwable $ex) {
            return $ex;
        }
    }

    protected function csvHeaders($filename = '', $delimiter = ',')
    {
        $csv = Reader::createFromPath($filename, 'r');
        $csv->setHeaderOffset(0);
        $csv->setDelimiter($delimiter);

        // Normalize CSV headers to remove newlines
        return array_map('trim', $csv->getHeader());
    }

    protected function distance($lat1, $lon1, $lat2, $lon2, $unit = 'mi')
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = Str::lower($unit);

        if ($unit == 'km') {
            return round(($miles * 1.609344), 2);
        } else {
            return round($miles, 2);
        }
    }

    protected function weeksBetweenTwoDates($start, $end)
    {
        $weeks = [];

        while ($start->weekOfYear !== $end->weekOfYear) {
            $weeks[] = $start->startOfWeek()->format('Y-m-d');
            $start->addWeek();
        }

        return $weeks;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected function paginate($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, $options);
    }

    protected static function location()
    {
        return ServicesHelpers::location();
    }

    protected function updateOrCreateModule($item): Module
    {
        $module = Module::updateOrCreate([
            'name' => $item['name'],
        ], [
            'url' => $item['url'],
            'show_menu' => isset($item['show_menu']) ? $item['show_menu'] : 1,
            'sort_order' => $item['sort_order'],
        ]);

        // delete removed permissions
        $module->permissions()->whereNotIn('action', $item['sub_items'])->forceDelete();

        foreach ($item['sub_items'] as $action) {
            Permission::updateOrCreate([
                'scope' => "{$module->key}:" . Str::slug($action),
            ], [
                'module_key' => $module->key,
                'action' => $action,
            ]);
        }

        return $module;
    }
}
